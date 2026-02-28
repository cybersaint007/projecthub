<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function create(Request $request, Epic $epic)
    {
        $this->authorizeEpic($request->user(), $epic);

        return view('tasks.create', compact('epic'));
    }

    public function store(Request $request, Epic $epic)
    {
        $this->authorizeEpic($request->user(), $epic);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:' . implode(',', Task::STATUSES),
            'agent' => 'required|in:' . implode(',', Task::AGENTS),
            'priority' => 'required|integer|in:1,3,5',
            'tags' => 'nullable|string',
            'context' => 'nullable|string',
            'instructions' => 'nullable|string',
            'acceptance_criteria' => 'nullable|string',
        ]);

        if (! empty($data['tags'])) {
            $data['tags'] = array_map('trim', explode(',', $data['tags']));
        }

        $epic->tasks()->create($data);

        return redirect()->route('epics.show', $epic)->with('status', 'Task created.');
    }

    public function show(Request $request, Task $task)
    {
        $this->authorizeEpic($request->user(), $task->epic);

        $task->load(['epic.project', 'artifacts.projectFile', 'reviews', 'taskPrompts', 'taskLogs' => fn ($q) => $q->with('user')]);

        return view('tasks.show', compact('task'));
    }

    public function edit(Request $request, Task $task)
    {
        $this->authorizeEpic($request->user(), $task->epic);

        $task->load('epic.project');

        return view('tasks.edit', compact('task'));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeEpic($request->user(), $task->epic);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:' . implode(',', Task::STATUSES),
            'agent' => 'required|in:' . implode(',', Task::AGENTS),
            'priority' => 'required|integer|in:1,3,5',
            'tags' => 'nullable|string',
            'context' => 'nullable|string',
            'instructions' => 'nullable|string',
            'acceptance_criteria' => 'nullable|string',
        ]);

        if (! empty($data['tags'])) {
            $data['tags'] = array_map('trim', explode(',', $data['tags']));
        } else {
            $data['tags'] = null;
        }

        $task->update($data);

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorizeEpic($request->user(), $task->epic);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', Task::STATUSES),
        ]);

        $task->update($data);

        return back()->with('status', 'Task status updated.');
    }

    public function updateDescription(Request $request, Task $task)
    {
        $this->authorizeEpic($request->user(), $task->epic);

        $data = $request->validate([
            'description' => 'nullable|string|max:65535',
        ]);

        $task->update($data);

        return back()->with('status', 'Description updated.');
    }

    public function destroy(Request $request, Task $task)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        $epic = $task->epic;
        $task->delete();

        return redirect()->route('epics.show', $epic)->with('status', 'Task deleted.');
    }

    private function authorizeEpic($user, Epic $epic): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $project = $epic->project;
        if (!$user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403, 'You are not assigned to this project.');
        }
    }
}
