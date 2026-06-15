<?php

namespace App\Http\Controllers;

use App\Models\AgentToken;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AgentTokenController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $tokens = $project->agentTokens()->latest()->get();

        return view('projects.agent-tokens', compact('project', 'tokens'));
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $rawToken = AgentToken::generateToken();

        $project->agentTokens()->create([
            'name'       => $data['name'],
            'token'      => $rawToken,
            'created_by' => $request->user()->id,
        ]);

        return back()
            ->with('status', __('ui.flash_agent_token_created'))
            ->with('new_token', $rawToken);
    }

    public function destroy(Request $request, Project $project, AgentToken $agentToken)
    {
        $this->authorize('update', $project);

        if ($agentToken->project_id !== $project->id) {
            abort(403);
        }

        $agentToken->delete();

        return back()->with('status', __('ui.flash_agent_token_revoked'));
    }
}
