<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use App\Services\ExportV3\EpicExporter;
use App\Services\ImportV3\EpicSchemaValidator;
use App\Services\ImportV3\ProjectImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EpicBacklogController extends Controller
{
    // -------------------------------------------------------------------------
    // Export — single epic with its tasks
    // -------------------------------------------------------------------------

    public function exportV3Json(Request $request, Epic $epic, EpicExporter $exporter)
    {
        $this->authorizeProject($request->user(), $epic->project);

        $data     = $exporter->export($epic);
        $slug     = Str::slug($epic->title) ?: 'epic';
        $filename = 'epic_' . $slug . '_v3_' . now()->format('Ymd_Hi') . '.json';

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    // -------------------------------------------------------------------------
    // Import — add/update an epic (with tasks) inside a project
    // -------------------------------------------------------------------------

    public function importEpicForm(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        return view('backlog.import-v3-epic', compact('project'));
    }

    public function importEpicApply(Request $request, Project $project, ProjectImporter $importer)
    {
        $this->authorizeProject($request->user(), $project);

        $json = $this->extractJson($request);
        if ($json === null) {
            return back()->withErrors(['import' => __('ui.error_provide_valid_json')]);
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['import' => __('ui.error_invalid_json', ['error' => json_last_error_msg()])]);
        }

        $validator = new EpicSchemaValidator();
        if (!$validator->validate($data)) {
            return back()->withErrors(['import' => implode(' | ', $validator->errors())]);
        }

        // Wrap in project structure so ProjectImporter can handle it
        $epics = isset($data['epics']) ? $data['epics'] : [$data['epic']];

        $wrapped = [
            'schema_version' => '3.0',
            'project'        => ['id' => $project->id, 'name' => $project->name],
            'epics'          => $epics,
        ];

        $result = $importer->import($wrapped);

        return redirect()->route('projects.show', $project)
            ->with('status', sprintf(
                'Epic import complete — %d epic(s) created / %d updated, %d task(s) created / %d updated.',
                $result->epicsCreated, $result->epicsUpdated,
                $result->tasksCreated, $result->tasksUpdated,
            ));
    }

    // -------------------------------------------------------------------------
    // Import — upsert tasks into a specific epic
    // -------------------------------------------------------------------------

    public function importTasksForm(Request $request, Epic $epic)
    {
        $this->authorizeProject($request->user(), $epic->project);

        return view('backlog.import-v3-tasks', compact('epic'));
    }

    public function importTasksApply(Request $request, Epic $epic, ProjectImporter $importer)
    {
        $this->authorizeProject($request->user(), $epic->project);

        $json = $this->extractJson($request);
        if ($json === null) {
            return back()->withErrors(['import' => __('ui.error_provide_valid_json')]);
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['import' => __('ui.error_invalid_json', ['error' => json_last_error_msg()])]);
        }

        $validator = new EpicSchemaValidator();
        if (!$validator->validate($data)) {
            return back()->withErrors(['import' => implode(' | ', $validator->errors())]);
        }

        // Force the target epic by ID so tasks always land in the right place
        $epicData = array_merge($data['epic'], ['id' => $epic->id]);

        $wrapped = [
            'schema_version' => '3.0',
            'project'        => ['id' => $epic->project_id, 'name' => $epic->project->name],
            'epics'          => [$epicData],
        ];

        $result = $importer->import($wrapped);

        return redirect()->route('epics.show', $epic)
            ->with('status', sprintf(
                'Tasks import complete — %d task(s) created / %d updated.',
                $result->tasksCreated, $result->tasksUpdated,
            ));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function extractJson(Request $request): ?string
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'file|max:2048|mimes:json,txt']);
            return $request->file('file')->get();
        }

        if ($request->filled('json_payload')) {
            return $request->input('json_payload');
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
            abort(403, __('ui.error_no_backlog_permission'));
        }
    }
}
