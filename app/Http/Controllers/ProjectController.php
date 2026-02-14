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
            $projects = Project::withCount(['epics', 'users'])->get();
        } else {
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

    public function show(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        $project->load(['epics.tasks', 'users']);

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

        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }

    private function authorizeProject($user, Project $project): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (!$user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403, 'You are not assigned to this project.');
        }
    }
}
