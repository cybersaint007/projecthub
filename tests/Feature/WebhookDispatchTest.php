<?php

namespace Tests\Feature;

use App\Jobs\DispatchWebhook;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Project $project;
    private Epic $epic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $this->epic = Epic::factory()->create(['project_id' => $this->project->id]);
    }

    public function test_task_status_transition_to_ready_dispatches_webhook_job(): void
    {
        Bus::fake();

        $task = Task::factory()->create([
            'epic_id' => $this->epic->id,
            'status' => 'Backlog',
            'agent' => 'claude_code',
        ]);

        $endpoint = WebhookEndpoint::create([
            'project_id' => $this->project->id,
            'url' => 'https://example.com/hook',
            'secret' => null,
            'events' => ['task.ready'],
            'active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('tasks.status', $task), ['status' => 'Ready'])
            ->assertRedirect();

        Bus::assertDispatched(DispatchWebhook::class, function ($job) use ($task, $endpoint) {
            return $job->endpoint->id === $endpoint->id
                && $job->payload['event'] === 'task.ready'
                && $job->payload['task_id'] === $task->id
                && $job->payload['task_title'] === $task->title
                && $job->payload['agent_type'] === 'claude_code';
        });
    }

    public function test_inactive_endpoint_is_skipped(): void
    {
        Bus::fake();

        $task = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Backlog']);

        WebhookEndpoint::create([
            'project_id' => $this->project->id,
            'url' => 'https://example.com/hook',
            'events' => ['task.ready'],
            'active' => false,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('tasks.status', $task), ['status' => 'Ready'])
            ->assertRedirect();

        Bus::assertNotDispatched(DispatchWebhook::class);
    }

    public function test_endpoint_subscribed_to_different_event_is_skipped(): void
    {
        Bus::fake();

        $task = Task::factory()->create(['epic_id' => $this->epic->id, 'status' => 'Backlog']);

        WebhookEndpoint::create([
            'project_id' => $this->project->id,
            'url' => 'https://example.com/hook',
            'events' => ['task.done'],
            'active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('tasks.status', $task), ['status' => 'Ready'])
            ->assertRedirect();

        Bus::assertNotDispatched(DispatchWebhook::class);
    }

    public function test_dispatch_webhook_job_sends_correct_payload_and_signature(): void
    {
        Http::fake(['https://example.com/hook' => Http::response('', 200)]);

        $endpoint = WebhookEndpoint::create([
            'project_id' => $this->project->id,
            'url' => 'https://example.com/hook',
            'secret' => 'my-secret',
            'events' => ['task.ready'],
            'active' => true,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'event' => 'task.ready',
            'project_id' => $this->project->id,
            'task_id' => 42,
            'task_title' => 'Test Task',
            'agent_type' => 'claude_code',
            'timestamp' => '2026-03-31T12:00:00+00:00',
        ];

        $job = new DispatchWebhook($endpoint, $payload);
        $job->handle();

        $expectedSig = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'my-secret');

        Http::assertSent(function ($request) use ($payload, $expectedSig) {
            return $request->url() === 'https://example.com/hook'
                && $request->method() === 'POST'
                && $request->header('X-ProjectHub-Event')[0] === 'task.ready'
                && $request->header('X-ProjectHub-Signature')[0] === $expectedSig
                && $request->data() === $payload;
        });
    }

    public function test_dispatch_webhook_job_omits_signature_when_no_secret(): void
    {
        Http::fake(['https://example.com/hook' => Http::response('', 200)]);

        $endpoint = WebhookEndpoint::create([
            'project_id' => $this->project->id,
            'url' => 'https://example.com/hook',
            'secret' => null,
            'events' => ['task.ready'],
            'active' => true,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'event' => 'task.ready',
            'project_id' => $this->project->id,
            'task_id' => 42,
            'task_title' => 'Test Task',
            'agent_type' => 'claude_code',
            'timestamp' => '2026-03-31T12:00:00+00:00',
        ];

        $job = new DispatchWebhook($endpoint, $payload);
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/hook'
                && empty($request->header('X-ProjectHub-Signature'));
        });
    }
}
