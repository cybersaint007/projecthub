<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskLog;
use Illuminate\Http\Request;

class TaskLogController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorizeEpic($request->user(), $task->epic);
        $data = $request->validate([
            'content' => 'required|string|max:65535',
            'log_type' => 'required|in:' . implode(',', TaskLog::LOG_TYPES),
        ]);

        $task->taskLogs()->create([
            'content' => $data['content'],
            'log_type' => $data['log_type'],
            'user_id' => $request->user()?->id,
        ]);

        return redirect(route('tasks.show', $task) . '#logs')->with('status', 'Work log added.');
    }

    public function update(Request $request, TaskLog $taskLog)
    {
        $this->authorizeEpic($request->user(), $taskLog->task->epic);

        $data = $request->validate([
            'content' => 'required|string|max:65535',
        ]);

        $taskLog->update($data);

        return redirect(route('tasks.show', $taskLog->task) . '#logs')->with('status', 'Work log updated.');
    }

    private function authorizeEpic($user, $epic): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $project = $epic->project;
        if (! $user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403, 'You are not assigned to this project.');
        }
    }
}
