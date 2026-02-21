<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            // For admins, load all projects (including trashed) with counts
            $projects = Project::withTrashed()->withCount(['epics', 'users'])->orderBy('deleted_at', 'asc')->orderBy('name', 'asc')->get();
        } else {
            // For non-admins, only show active projects they're assigned to
            $projects = $user->projects()->withCount('epics')->get();
        }

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Project::create($data);

        return redirect()->route('projects.index')->with('status', 'Project created.');
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        // Allow viewing soft-deleted projects for admins
        if ($user->isAdmin()) {
            $project = Project::withTrashed()->findOrFail($id);
        } else {
            $project = Project::findOrFail($id);
        }

        $this->authorizeProject($user, $project);

        // Load epics and tasks (including trashed for admins)
        if ($user->isAdmin()) {
            $project->load(['epics' => function ($query) {
                $query->withTrashed();
            }, 'epics.tasks' => function ($query) {
                $query->withTrashed();
            }, 'users']);
        } else {
            $project->load(['epics.tasks', 'users']);
        }

        return view('projects.show', compact('project'));
    }

    public function edit(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project->update($data);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    public function destroy(Request $request, Project $project)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($project) {
            // Soft delete all related epics
            $project->epics()->each(function ($epic) {
                // Soft delete all tasks under each epic
                $epic->tasks()->delete();
                // Soft delete the epic
                $epic->delete();
            });

            // Detach users from project (optional - keeping it simple, we don't reattach on restore)
            $project->users()->detach();

            // Soft delete the project
            $project->delete();

            return redirect()->route('projects.index')->with('status', 'Project deleted.');
        });
    }

    /**
     * Restore a soft-deleted project
     */
    public function restore(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            // Load the soft-deleted project by ID
            $project = Project::onlyTrashed()->findOrFail($id);

            // Restore all related epics
            $project->epics()->onlyTrashed()->each(function ($epic) {
                // Restore all tasks under each epic
                $epic->tasks()->onlyTrashed()->restore();
                // Restore the epic
                $epic->restore();
            });

            // Restore the project
            $project->restore();

            return redirect()->route('projects.show', $project)->with('status', 'Project restored.');
        });
    }

    private function authorizeProject($user, Project $project): void
    {
        if ($user->isAdmin()) {
            return;
        }

        // Non-admins cannot view deleted projects
        if ($project->trashed()) {
            abort(403, 'You cannot view deleted projects.');
        }

        if (!$user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403, 'You are not assigned to this project.');
        }
    }
}
