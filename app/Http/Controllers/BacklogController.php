<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BacklogExportService;
use App\Services\BacklogReplaceService;
use Illuminate\Http\Request;

class BacklogController extends Controller
{
    public function exportJson(Request $request, Project $project, BacklogExportService $exportService)
    {
        $this->authorizeProject($request->user(), $project);

        $data = $exportService->export($project);
        $filename = ($project->code ?: 'project') . '_backlog_' . now()->format('Ymd_Hi') . '.json';

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    public function importReplacePreview(Request $request, Project $project, BacklogReplaceService $replaceService)
    {
        $this->authorizeProject($request->user(), $project);

        $json = $this->extractJson($request);
        if ($json === null) {
            return back()->withErrors(['import' => 'Provide valid JSON via paste or file upload.']);
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['import' => 'Invalid JSON: ' . json_last_error_msg()]);
        }

        if (!isset($data['epics']) || !is_array($data['epics'])) {
            return back()->withErrors(['import' => 'JSON must contain an "epics" array.']);
        }

        $validation = $this->validateImportData($data);
        if ($validation !== null) {
            return back()->withErrors(['import' => $validation]);
        }

        $preview = $replaceService->preview($project, $data);

        return back()
            ->with('preview', $preview)
            ->with('import_json', $json)
            ->withInput();
    }

    public function importReplaceApply(Request $request, Project $project, BacklogReplaceService $replaceService)
    {
        $this->authorizeProject($request->user(), $project);

        $json = $request->input('confirmed_json');
        if (!$json) {
            return back()->withErrors(['import' => 'No JSON data to apply.']);
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['import' => 'Invalid JSON.']);
        }

        $validation = $this->validateImportData($data);
        if ($validation !== null) {
            return back()->withErrors(['import' => $validation]);
        }

        $backup = $request->boolean('backup', true);

        $result = $replaceService->replace($project, $data, $request->user()->id, $backup);

        return redirect()->route('projects.show', $project)
            ->with('status', "Backlog replaced: {$result['epics_created']} epics, {$result['tasks_created']} tasks created."
                . ($result['prompts_created'] ? " {$result['prompts_created']} prompts restored." : '')
                . ($result['backup_created'] ? ' Backup saved.' : ''));
    }

    public function importReplaceForm(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        return view('backlog.import-replace', compact('project'));
    }

    private function extractJson(Request $request): ?string
    {
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'file|max:2048|mimes:json,txt',
            ]);
            return $request->file('file')->get();
        }

        if ($request->filled('json_payload')) {
            return $request->input('json_payload');
        }

        return null;
    }

    private function validateImportData(array $data): ?string
    {
        if (!isset($data['epics']) || !is_array($data['epics'])) {
            return 'JSON must contain an "epics" array.';
        }

        foreach ($data['epics'] as $i => $epic) {
            if (empty($epic['title'])) {
                return "Epic at index {$i} is missing a title.";
            }
            if (isset($epic['tasks']) && is_array($epic['tasks'])) {
                foreach ($epic['tasks'] as $j => $task) {
                    if (empty($task['title'])) {
                        return "Task at epic[{$i}].tasks[{$j}] is missing a title.";
                    }
                    if (isset($task['status']) && !in_array($task['status'], \App\Models\Task::STATUSES, true)) {
                        return "Task '{$task['title']}' has invalid status '{$task['status']}'.";
                    }
                    if (isset($task['agent']) && !in_array($task['agent'], \App\Models\Task::AGENTS, true)) {
                        return "Task '{$task['title']}' has invalid agent '{$task['agent']}'.";
                    }
                    if (isset($task['priority']) && !in_array((int) $task['priority'], [1, 3, 5], true)) {
                        return "Task '{$task['title']}' has invalid priority '{$task['priority']}'.";
                    }
                }
            }
        }

        return null;
    }

    private function authorizeProject($user, Project $project): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $role = $project->roleFor($user);
        if (!in_array($role, ['owner', 'editor'], true)) {
            abort(403, 'You do not have permission to manage this project backlog.');
        }
    }
}
