<?php

namespace Tests\Feature;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoverExpiredLeasesTest extends TestCase
{
    use RefreshDatabase;

    private Epic $epic;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->epic = Epic::factory()->create(['project_id' => $project->id]);
    }

    private function makeTask(array $attributes = []): Task
    {
        return Task::factory()->create(array_merge([
            'epic_id' => $this->epic->id,
        ], $attributes));
    }

    public function test_expired_lease_inprogress_task_is_recovered(): void
    {
        $task = $this->makeTask([
            'status'      => 'InProgress',
            'leased_by'   => 'worker-abc',
            'lease_token' => 'tok123',
            'leased_until' => now()->subMinutes(10),
        ]);

        $this->artisan('agent:recover-leases')->assertSuccessful()->expectsOutput('Recovered 1 tasks');

        $task->refresh();

        $this->assertEquals('Ready', $task->status);
        $this->assertNull($task->leased_by);
        $this->assertNull($task->lease_token);
        $this->assertNull($task->leased_until);

        $log = TaskLog::where('task_id', $task->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('system', $log->log_type);
        $this->assertStringContainsString('worker-abc', $log->content);
    }

    public function test_active_lease_task_is_not_touched(): void
    {
        $task = $this->makeTask([
            'status'       => 'InProgress',
            'leased_by'    => 'worker-abc',
            'lease_token'  => 'tok123',
            'leased_until' => now()->addMinutes(10),
        ]);

        $this->artisan('agent:recover-leases')->assertSuccessful()->expectsOutput('Recovered 0 tasks');

        $task->refresh();

        $this->assertEquals('InProgress', $task->status);
        $this->assertEquals('worker-abc', $task->leased_by);
        $this->assertEquals(0, TaskLog::where('task_id', $task->id)->count());
    }

    public function test_task_with_null_leased_by_is_not_touched(): void
    {
        $task = $this->makeTask([
            'status'       => 'Ready',
            'leased_by'    => null,
            'lease_token'  => null,
            'leased_until' => null,
        ]);

        $this->artisan('agent:recover-leases')->assertSuccessful()->expectsOutput('Recovered 0 tasks');

        $task->refresh();
        $this->assertEquals('Ready', $task->status);
        $this->assertEquals(0, TaskLog::where('task_id', $task->id)->count());
    }

    public function test_command_outputs_correct_count_for_multiple_tasks(): void
    {
        foreach (range(1, 3) as $_) {
            $this->makeTask([
                'status'       => 'InProgress',
                'leased_by'    => 'worker-xyz',
                'lease_token'  => 'tok',
                'leased_until' => now()->subMinutes(5),
            ]);
        }

        $this->artisan('agent:recover-leases')->assertSuccessful()->expectsOutput('Recovered 3 tasks');

        $this->assertEquals(3, TaskLog::where('log_type', 'system')->count());
    }
}
