<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskReview;
use Illuminate\Http\Request;

class TaskReviewController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorizeTask($request->user(), $task);

        $data = $request->validate([
            'result' => 'required|in:' . implode(',', TaskReview::RESULTS),
            'note' => 'required|string',
        ]);

        $task->reviews()->create($data);

        if ($data['result'] === 'pass') {
            $task->update(['status' => 'Done']);
        } elseif ($data['result'] === 'changes_requested') {
            $task->update(['status' => 'InProgress']);
        }

        return back()->with('status', 'Review submitted.');
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
