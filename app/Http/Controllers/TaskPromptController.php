<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskPromptRequest;
use App\Http\Requests\UpdateTaskPromptRequest;
use App\Models\Task;
use App\Models\TaskPrompt;
use Illuminate\Http\Request;

class TaskPromptController extends Controller
{
    public function store(StoreTaskPromptRequest $request, Task $task)
    {
        $this->authorizeTask($request->user(), $task);

        $version = TaskPrompt::nextVersionFor($task->id, $request->validated('agent_type'));

        $task->taskPrompts()->create([
            ...$request->validated(),
            'version' => $version,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', __('ui.flash_prompt_added'));
    }

    public function show(Request $request, Task $task, TaskPrompt $task_prompt)
    {
        $this->authorizeTask($request->user(), $task);
        $this->ensurePromptBelongsToTask($task_prompt, $task);

        return view('task_prompts.show', ['task' => $task, 'prompt' => $task_prompt]);
    }

    public function edit(Request $request, Task $task, TaskPrompt $task_prompt)
    {
        $this->authorizeTask($request->user(), $task);
        $this->ensurePromptBelongsToTask($task_prompt, $task);

        return view('task_prompts.edit', ['task' => $task, 'prompt' => $task_prompt]);
    }

    public function update(UpdateTaskPromptRequest $request, Task $task, TaskPrompt $task_prompt)
    {
        $this->authorizeTask($request->user(), $task);
        $this->ensurePromptBelongsToTask($task_prompt, $task);

        $task_prompt->update($request->validated());

        return redirect()->route('tasks.show', $task)->with('status', __('ui.flash_prompt_updated'));
    }

    public function duplicate(Request $request, Task $task, TaskPrompt $task_prompt)
    {
        $this->authorizeTask($request->user(), $task);
        $this->ensurePromptBelongsToTask($task_prompt, $task);

        $version = TaskPrompt::nextVersionFor($task->id, $task_prompt->agent_type);

        $task->taskPrompts()->create([
            'agent_type' => $task_prompt->agent_type,
            'format_type' => $task_prompt->format_type,
            'title' => $task_prompt->title ? $task_prompt->title . ' (copy)' : null,
            'content' => $task_prompt->content,
            'version' => $version,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', __('ui.flash_prompt_duplicated'));
    }

    public function destroy(Request $request, Task $task, TaskPrompt $task_prompt)
    {
        $this->authorizeTask($request->user(), $task);
        $this->ensurePromptBelongsToTask($task_prompt, $task);

        $task_prompt->delete();

        return back()->with('status', __('ui.flash_prompt_deleted'));
    }

    private function authorizeTask($user, Task $task): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $project = $task->epic->project;
        if (! $user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403);
        }
    }

    private function ensurePromptBelongsToTask(TaskPrompt $prompt, Task $task): void
    {
        if ($prompt->task_id !== $task->id) {
            abort(404);
        }
    }
}
