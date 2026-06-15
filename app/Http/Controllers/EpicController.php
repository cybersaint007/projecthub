<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EpicController extends Controller
{
    public function create(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        return view('epics.create', compact('project'));
    }

    public function store(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'milestone_tag' => 'nullable|string|max:255',
        ]);

        $project->epics()->create($data);

        return redirect()->route('projects.show', $project)->with('status', __('ui.flash_epic_created'));
    }

    public function show(Request $request, Epic $epic)
    {
        $this->authorizeProject($request->user(), $epic->project);

        $epic->load([
            'tasks' => fn ($q) => $request->user()->isAdmin() ? $q->withTrashed() : $q,
            'project',
        ]);

        return view('epics.show', compact('epic'));
    }

    public function edit(Request $request, Epic $epic)
    {
        $this->authorizeProject($request->user(), $epic->project);

        return view('epics.edit', compact('epic'));
    }

    public function update(Request $request, Epic $epic)
    {
        $this->authorizeProject($request->user(), $epic->project);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'milestone_tag' => 'nullable|string|max:255',
        ]);

        $epic->update($data);

        return redirect()->route('epics.show', $epic)->with('status', __('ui.flash_epic_updated'));
    }

    public function destroy(Request $request, Epic $epic)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $project = $epic->project;
        $epic->delete();

        return redirect()->route('projects.show', $project)->with('status', __('ui.flash_epic_deleted'));
    }

    public function restore(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        return DB::transaction(function () use ($id) {
            $epic = Epic::onlyTrashed()->findOrFail($id);
            $epic->tasks()->onlyTrashed()->restore();
            $epic->restore();

            return redirect()->route('projects.show', $epic->project)->with('status', __('ui.flash_epic_restored'));
        });
    }

    public function bulkUpdateAgent(Request $request, Epic $epic)
    {
        $this->authorizeProject($request->user(), $epic->project);

        $data = $request->validate([
            'agent' => 'required|string|in:' . implode(',', \App\Models\Task::AGENTS),
        ]);

        $updated = $epic->tasks()->update(['agent' => $data['agent']]);

        return redirect()->route('epics.show', $epic)
            ->with('status', __('ui.flash_bulk_agent_updated', ['count' => $updated, 'agent' => $data['agent']]));
    }

    public function kanban(Request $request, Epic $epic)
    {
        $this->authorizeProject($request->user(), $epic->project);

        $epic->load('project');
        $tasks = $epic->tasks()->get()->groupBy('status');

        return view('epics.kanban', compact('epic', 'tasks'));
    }

    private function authorizeProject($user, Project $project): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (!$user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403, __('ui.error_not_assigned_project'));
        }
    }
}
