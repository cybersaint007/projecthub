<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WebhookEndpointController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $webhooks = $project->webhookEndpoints()->latest()->get();

        return view('projects.webhooks', compact('project', 'webhooks'));
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'url' => 'required|url|max:500',
            'secret' => 'nullable|string|max:255',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:task.ready',
        ]);

        $project->webhookEndpoints()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', __('ui.flash_webhook_added'));
    }

    public function destroy(Request $request, Project $project, WebhookEndpoint $webhook)
    {
        $this->authorize('update', $project);

        if ($webhook->project_id !== $project->id) {
            abort(403);
        }

        $webhook->delete();

        return back()->with('status', __('ui.flash_webhook_removed'));
    }
}
