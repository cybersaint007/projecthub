<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskArtifact;
use Illuminate\Http\Request;

class TaskArtifactController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorizeTask($request->user(), $task);

        $data = $request->validate([
            'type' => 'required|in:' . implode(',', TaskArtifact::TYPES),
            'value' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $task->artifacts()->create($data);

        return back()->with('status', 'Artifact added.');
    }

    public function destroy(Request $request, TaskArtifact $artifact)
    {
        $this->authorizeTask($request->user(), $artifact->task);

        $artifact->delete();

        return back()->with('status', 'Artifact removed.');
    }

    private function authorizeTask($user, Task $task): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $project = $task->epic->project;
        if (!$user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403);
        }
    }
}
