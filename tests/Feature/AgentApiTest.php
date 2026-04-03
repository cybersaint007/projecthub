<?php

namespace Tests\Feature;

use App\Models\AgentToken;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;
    private Epic $epic;
    private AgentToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->epic = Epic::factory()->create(['project_id' => $this->project->id]);
        $this->token = AgentToken::create([
            'name' => 'test-token',
            'token' => 'test-agent-token-64chars-' . str_repeat('x', 39),
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
        ]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token->token];
    }

    public function test_next_task_returns_earliest_eligible(): void
    {
        $t1 = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Ready', 'position' => 20]);
        $t2 = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Ready', 'position' => 10]);
        Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'InProgress', 'position' => 5]);

        $response = $this->getJson('/api/agent/projects/' . $this->project->id . '/tasks/next', $this->headers());

        $response->assertOk();
        $response->assertJsonPath('task.id', $t2->id);
    }

    public function test_next_task_picks_up_backlog(): void
    {
        $t1 = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Backlog', 'position' => 10]);
        Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'TODO', 'position' => 5]);

        $response = $this->getJson('/api/agent/projects/' . $this->project->id . '/tasks/next', $this->headers());

        $response->assertOk();
        $response->assertJsonPath('task.id', $t1->id);
    }

    public function test_next_task_filters_by_agent_type(): void
    {
        Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Ready', 'agent' => 'human', 'position' => 10]);
        $t2 = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Ready', 'agent' => 'claude_code', 'position' => 20]);

        $response = $this->getJson('/api/agent/projects/' . $this->project->id . '/tasks/next?agent_type=claude_code', $this->headers());

        $response->assertOk();
        $response->assertJsonPath('task.id', $t2->id);
    }

    public function test_next_task_skips_claimed(): void
    {
        Task::factory()->create([
            'epic_id' => $this->epic->id,
            'status' => 'Ready',
            'position' => 10,
            'leased_until' => now()->addHour(),
        ]);
        $t2 = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Ready', 'position' => 20]);

        $response = $this->getJson('/api/agent/projects/' . $this->project->id . '/tasks/next', $this->headers());

        $response->assertOk();
        $response->assertJsonPath('task.id', $t2->id);
    }

    public function test_claim_is_atomic(): void
    {
        $task = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'TODO']);

        $r1 = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'worker-1',
        ], $this->headers());
        $r1->assertOk();
        $r1->assertJsonStructure(['lease_token', 'leased_until']);

        // Second claim should fail (conflict)
        $r2 = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'worker-2',
        ], $this->headers());
        $r2->assertStatus(409);
    }

    public function test_claim_succeeds_after_expired_lease(): void
    {
        $task = Task::factory()->create([
            'epic_id' => $this->epic->id,
            'status' => 'TODO',
            'leased_by' => 'old-worker',
            'lease_token' => 'old-token',
            'leased_until' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'new-worker',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonStructure(['lease_token', 'leased_until']);
    }

    public function test_bundle_returns_prompt_from_task_prompt(): void
    {
        $task = Task::factory()->create([
            'epic_id' => $this->epic->id,
            'status' => 'TODO',
            'agent' => 'claude_code',
            'context' => 'test context',
            'instructions' => 'test instructions',
        ]);

        TaskPrompt::create([
            'task_id' => $task->id,
            'agent_type' => 'claude_code',
            'format_type' => 'structured',
            'title' => 'Test Prompt',
            'version' => 1,
            'content' => 'Do the thing.',
            'created_by' => $this->owner->id,
        ]);

        // Claim first
        $claimResponse = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'w1',
        ], $this->headers());
        $leaseToken = $claimResponse->json('lease_token');

        $response = $this->getJson('/api/agent/tasks/' . $task->id . '/bundle?lease_token=' . $leaseToken, $this->headers());

        $response->assertOk();
        $response->assertJsonPath('prompt.generated', false);
        $this->assertStringStartsWith('Do the thing.', $response->json('prompt.content'));
        $response->assertJsonPath('prompt.version', 1);
        $response->assertJsonPath('task.context', 'test context');
    }

    public function test_bundle_generates_prompt_when_no_task_prompt(): void
    {
        $task = Task::factory()->create([
            'epic_id' => $this->epic->id,
            'status' => 'TODO',
            'agent' => 'claude_code',
            'context' => 'Some context',
            'instructions' => 'Build it',
        ]);

        $claimResponse = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'w1',
        ], $this->headers());
        $leaseToken = $claimResponse->json('lease_token');

        $response = $this->getJson('/api/agent/tasks/' . $task->id . '/bundle?lease_token=' . $leaseToken, $this->headers());

        $response->assertOk();
        $response->assertJsonPath('prompt.generated', true);
        $this->assertStringContainsString('Some context', $response->json('prompt.content'));
        $this->assertStringContainsString('Build it', $response->json('prompt.content'));
    }

    public function test_logs_require_lease_token(): void
    {
        $task = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'TODO']);

        $response = $this->postJson('/api/agent/tasks/' . $task->id . '/logs', [
            'level' => 'info',
            'message' => 'test log',
        ], $this->headers());

        $response->assertStatus(403);
    }

    public function test_logs_with_valid_lease(): void
    {
        $task = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'TODO']);

        $claimResponse = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'w1',
        ], $this->headers());
        $leaseToken = $claimResponse->json('lease_token');

        $response = $this->postJson('/api/agent/tasks/' . $task->id . '/logs', [
            'lease_token' => $leaseToken,
            'level' => 'info',
            'message' => 'Step completed successfully',
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertDatabaseHas('task_logs', [
            'task_id' => $task->id,
            'log_type' => 'ai',
        ]);
    }

    public function test_status_update_validates_transitions(): void
    {
        $task = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Ready']);

        $claimResponse = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'w1',
        ], $this->headers());
        $leaseToken = $claimResponse->json('lease_token');

        // Invalid: Ready -> Done
        $response = $this->patchJson('/api/agent/tasks/' . $task->id . '/status', [
            'lease_token' => $leaseToken,
            'status' => 'Done',
        ], $this->headers());
        $response->assertStatus(422);

        // Valid: Ready -> InProgress
        $response = $this->patchJson('/api/agent/tasks/' . $task->id . '/status', [
            'lease_token' => $leaseToken,
            'status' => 'InProgress',
        ], $this->headers());
        $response->assertOk();
        $this->assertSame('InProgress', $task->fresh()->status);
    }

    public function test_agent_can_release_task_to_backlog(): void
    {
        $task = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Ready']);

        $claimResponse = $this->postJson('/api/agent/tasks/' . $task->id . '/claim', [
            'worker_id' => 'w1',
        ], $this->headers());
        $leaseToken = $claimResponse->json('lease_token');

        // Move to InProgress first
        $this->patchJson('/api/agent/tasks/' . $task->id . '/status', [
            'lease_token' => $leaseToken,
            'status' => 'InProgress',
        ], $this->headers())->assertOk();

        // Release to Backlog (task could not be completed)
        $response = $this->patchJson('/api/agent/tasks/' . $task->id . '/status', [
            'lease_token' => $leaseToken,
            'status' => 'Backlog',
        ], $this->headers());

        $response->assertOk();
        $fresh = $task->fresh();
        $this->assertSame('Backlog', $fresh->status);
        $this->assertNull($fresh->lease_token);
        $this->assertNull($fresh->leased_by);
        $this->assertNull($fresh->leased_until);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/api/agent/projects/' . $this->project->id . '/tasks/next', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/agent/projects/' . $this->project->id . '/tasks/next');

        $response->assertStatus(401);
    }

    public function test_project_scoped_token_cannot_access_other_project(): void
    {
        $otherProject = Project::factory()->create(['owner_id' => $this->owner->id]);

        $response = $this->getJson('/api/agent/projects/' . $otherProject->id . '/tasks/next', $this->headers());

        $response->assertStatus(403);
    }
}
