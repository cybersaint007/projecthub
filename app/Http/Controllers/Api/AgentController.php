<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentToken;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Services\AgentBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    /** Statuses eligible for agent pickup. */
    private const CLAIMABLE_STATUSES = ['TODO', 'Ready'];

    /** Allowed status transitions from agent. */
    private const ALLOWED_STATUS_TRANSITIONS = [
        'TODO' => ['InProgress'],
        'Ready' => ['InProgress'],
        'InProgress' => ['Review', 'Done'],
        'Review' => ['InProgress', 'Done'],
    ];

    /** Default lease duration in minutes. */
    private const LEASE_DURATION_MINUTES = 60;

    public function nextTask(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            'worker_id' => 'sometimes|string|max:255',
            'agent_type' => 'sometimes|string|in:' . implode(',', Task::AGENTS),
        ]);

        $query = Task::whereHas('epic', fn ($q) => $q->where('project_id', $project->id))
            ->whereIn('status', self::CLAIMABLE_STATUSES)
            ->where(function ($q) {
                $q->whereNull('leased_until')
                    ->orWhere('leased_until', '<', now());
            })
            ->orderBy('position')
            ->orderBy('id');

        if ($request->filled('agent_type')) {
            $query->where('agent', $request->input('agent_type'));
        }

        $task = $query->first();

        if (!$task) {
            return response()->json(['task' => null, 'message' => 'No eligible tasks found.'], 200);
        }

        return response()->json([
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'agent' => $task->agent,
                'priority' => $task->priority,
                'position' => $task->position,
            ],
        ]);
    }

    public function claim(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);

        $request->validate([
            'worker_id' => 'required|string|max:255',
        ]);

        $leaseToken = Str::random(48);
        $leasedUntil = now()->addMinutes(self::LEASE_DURATION_MINUTES);

        $updated = DB::table('tasks')
            ->where('id', $task->id)
            ->where(function ($q) {
                $q->whereNull('leased_until')
                    ->orWhere('leased_until', '<', now());
            })
            ->whereNull('deleted_at')
            ->update([
                'leased_by' => $request->input('worker_id'),
                'lease_token' => $leaseToken,
                'leased_until' => $leasedUntil,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'error' => 'Task is already claimed by another worker.',
            ], 409);
        }

        return response()->json([
            'lease_token' => $leaseToken,
            'leased_until' => $leasedUntil->toIso8601String(),
            'task_id' => $task->id,
        ]);
    }

    public function bundle(Request $request, Task $task, AgentBundleService $bundleService): JsonResponse
    {
        $this->authorizeTask($request, $task);
        $this->validateLeaseToken($request, $task);

        return response()->json($bundleService->buildBundle($task));
    }

    public function storeLog(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);
        $this->validateLeaseToken($request, $task);

        $data = $request->validate([
            'level' => 'required|string|in:info,error,warning,debug',
            'message' => 'required|string|max:65535',
        ]);

        $log = $task->taskLogs()->create([
            'user_id' => null,
            'log_type' => 'ai',
            'content' => "[{$data['level']}] {$data['message']}",
        ]);

        return response()->json(['id' => $log->id], 201);
    }

    public function storeArtifact(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);
        $this->validateLeaseToken($request, $task);

        $request->validate([
            'file' => [
                'required', 'file',
                'max:' . (ProjectFile::MAX_SIZE / 1024),
                'extensions:' . implode(',', ProjectFile::ALLOWED_EXTENSIONS),
            ],
            'kind' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $project = $task->epic->project;
        $now = now();
        $path = sprintf('projects/%d/%s/%s', $project->id, $now->format('Y'), $now->format('m'));
        $storedPath = $file->store($path, 'projecthub_private');

        $project->files()->create([
            'uploader_user_id' => null,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'note' => $request->input('note'),
        ]);

        $artifact = $task->artifacts()->create([
            'type' => 'file_path',
            'value' => $storedPath,
            'note' => $request->input('note') ?: $file->getClientOriginalName(),
        ]);

        return response()->json(['id' => $artifact->id, 'stored_path' => $storedPath], 201);
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);
        $this->validateLeaseToken($request, $task);

        $data = $request->validate([
            'status' => 'required|string|in:' . implode(',', Task::STATUSES),
        ]);

        $allowed = self::ALLOWED_STATUS_TRANSITIONS[$task->status] ?? [];
        if (!in_array($data['status'], $allowed, true)) {
            return response()->json([
                'error' => "Cannot transition from '{$task->status}' to '{$data['status']}'.",
                'allowed' => $allowed,
            ], 422);
        }

        $task->update(['status' => $data['status']]);

        if (in_array($data['status'], ['Done', 'Review'], true)) {
            $task->clearLease();
        }

        return response()->json([
            'task_id' => $task->id,
            'status' => $task->fresh()->status,
        ]);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        $agentToken = $request->attributes->get('agent_token');

        if ($agentToken->project_id !== null && $agentToken->project_id !== $project->id) {
            abort(403, 'Token is not authorized for this project.');
        }
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        $agentToken = $request->attributes->get('agent_token');
        $project = $task->epic->project;

        if ($agentToken->project_id !== null && $agentToken->project_id !== $project->id) {
            abort(403, 'Token is not authorized for this project.');
        }
    }

    private function validateLeaseToken(Request $request, Task $task): void
    {
        $leaseToken = $request->input('lease_token') ?? $request->query('lease_token');

        if (!$leaseToken) {
            abort(403, 'Missing lease_token.');
        }

        $task->refresh();

        if ($task->lease_token !== $leaseToken) {
            abort(403, 'Invalid lease_token.');
        }

        if (!$task->hasActiveLease()) {
            abort(403, 'Lease has expired. Re-claim the task.');
        }
    }
}
