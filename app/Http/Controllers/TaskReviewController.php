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
            'result'         => 'required|in:' . implode(',', TaskReview::RESULTS),
            'note'           => 'required|string',
            'route_exists'   => 'boolean',
            'ui_exists'      => 'boolean',
            'service_exists' => 'boolean',
            'test_exists'    => 'boolean',
        ]);

        $data['route_exists']   = (bool) ($data['route_exists'] ?? false);
        $data['ui_exists']      = (bool) ($data['ui_exists'] ?? false);
        $data['service_exists'] = (bool) ($data['service_exists'] ?? false);
        $data['test_exists']    = (bool) ($data['test_exists'] ?? false);

        if ($data['result'] === 'pass') {
            $allChecked = $data['route_exists'] && $data['ui_exists']
                && $data['service_exists'] && $data['test_exists'];

            if (!$allChecked) {
                return back()
                    ->withInput()
                    ->withErrors(['truth_audit' => __('ui.truth_audit_required')]);
            }
        }

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
