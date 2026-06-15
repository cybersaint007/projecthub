<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ExportV3\ProjectExporter;
use App\Services\ImportV3\ProjectImporter;
use App\Services\ImportV3\SchemaValidator;
use Illuminate\Http\Request;

class BacklogController extends Controller
{
    // -------------------------------------------------------------------------
    // V3 Export
    // -------------------------------------------------------------------------

    public function exportV3Json(Request $request, Project $project, ProjectExporter $exporter)
    {
        $this->authorizeProject($request->user(), $project);

        $data     = $exporter->export($project);
        $filename = ($project->code ?: 'project') . '_v3_' . now()->format('Ymd_Hi') . '.json';

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    // -------------------------------------------------------------------------
    // V3 Import — project-scoped (updates an existing project)
    // -------------------------------------------------------------------------

    public function importV3Form(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        return view('backlog.import-v3', compact('project'));
    }

    public function importV3Apply(Request $request, Project $project, ProjectImporter $importer)
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

        $validator = new SchemaValidator();
        if (!$validator->validate($data)) {
            return back()->withErrors(['import' => implode(' | ', $validator->errors())]);
        }

        $result = $importer->import($data);

        return redirect()->route('projects.show', $project)
            ->with('status', $this->buildSummary($result));
    }

    // -------------------------------------------------------------------------
    // V3 Import — global (creates or updates any project)
    // -------------------------------------------------------------------------

    public function importV3GlobalForm()
    {
        return view('backlog.import-v3-global');
    }

    public function importV3GlobalApply(Request $request, ProjectImporter $importer)
    {
        $json = $this->extractJson($request);
        if ($json === null) {
            return back()->withErrors(['import' => __('ui.error_provide_valid_json')]);
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['import' => __('ui.error_invalid_json', ['error' => json_last_error_msg()])]);
        }

        $validator = new SchemaValidator();
        if (!$validator->validate($data)) {
            return back()->withErrors(['import' => implode(' | ', $validator->errors())]);
        }

        $result = $importer->import($data);

        // Locate the project that was created/updated to redirect to it
        $projectData = $data['project'];
        $project = null;
        if (!empty($projectData['external_key'])) {
            $project = Project::where('external_key', $projectData['external_key'])->first();
        }
        if (!$project && !empty($projectData['code'])) {
            $project = Project::where('code', $projectData['code'])->first();
        }
        if (!$project && !empty($projectData['name'])) {
            $project = Project::where('name', $projectData['name'])->first();
        }

        if ($project) {
            return redirect()->route('projects.show', $project)
                ->with('status', $this->buildSummary($result));
        }

        return redirect()->route('projects.index')
            ->with('status', $this->buildSummary($result));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildSummary(\App\Services\ImportV3\ImportResult $result): string
    {
        $summary = sprintf(
            'V3 import complete — project %s, %d epic(s) created / %d updated, %d task(s) created / %d updated, %d prompt(s) created / %d updated.',
            $result->projectCreated ? 'created' : 'updated',
            $result->epicsCreated, $result->epicsUpdated,
            $result->tasksCreated, $result->tasksUpdated,
            $result->promptsCreated, $result->promptsUpdated,
        );

        if (!empty($result->dependencyWarnings)) {
            $summary .= ' Warnings: ' . implode('; ', $result->dependencyWarnings);
        }

        return $summary;
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
