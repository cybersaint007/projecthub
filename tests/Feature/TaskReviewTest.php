<?php

namespace Tests\Feature;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $epic = Epic::factory()->create(['project_id' => $project->id]);
        $this->task = Task::factory()->create(['epic_id' => $epic->id, 'status' => 'Review']);
    }

    public function test_pass_with_all_checks_marks_task_done(): void
    {
        $response = $this->actingAs($this->admin)->post(route('reviews.store', $this->task), [
            'result'         => 'pass',
            'note'           => 'All good',
            'route_exists'   => '1',
            'ui_exists'      => '1',
            'service_exists' => '1',
            'test_exists'    => '1',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Done', $this->task->fresh()->status);
        $this->assertDatabaseHas('task_reviews', [
            'task_id'        => $this->task->id,
            'result'         => 'pass',
            'route_exists'   => true,
            'ui_exists'      => true,
            'service_exists' => true,
            'test_exists'    => true,
        ]);
    }

    public function test_pass_without_all_checks_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('reviews.store', $this->task), [
            'result'         => 'pass',
            'note'           => 'Missing test',
            'route_exists'   => '1',
            'ui_exists'      => '1',
            'service_exists' => '1',
            // test_exists omitted
        ]);

        $response->assertSessionHasErrors('truth_audit');
        $this->assertEquals('Review', $this->task->fresh()->status);
        $this->assertDatabaseMissing('task_reviews', ['task_id' => $this->task->id]);
    }

    public function test_pass_with_no_checks_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('reviews.store', $this->task), [
            'result' => 'pass',
            'note'   => 'Nothing verified',
        ]);

        $response->assertSessionHasErrors('truth_audit');
        $this->assertEquals('Review', $this->task->fresh()->status);
    }

    public function test_changes_requested_does_not_require_truth_audit(): void
    {
        $response = $this->actingAs($this->admin)->post(route('reviews.store', $this->task), [
            'result' => 'changes_requested',
            'note'   => 'Needs more work',
        ]);

        $response->assertRedirect();
        $this->assertEquals('InProgress', $this->task->fresh()->status);
        $this->assertDatabaseHas('task_reviews', [
            'task_id' => $this->task->id,
            'result'  => 'changes_requested',
        ]);
    }

    public function test_truth_audit_passed_helper_returns_correct_value(): void
    {
        $review = $this->task->reviews()->create([
            'result'         => 'pass',
            'note'           => 'test',
            'route_exists'   => true,
            'ui_exists'      => true,
            'service_exists' => true,
            'test_exists'    => true,
        ]);

        $this->assertTrue($review->truthAuditPassed());

        $partial = $this->task->reviews()->create([
            'result'         => 'pass',
            'note'           => 'test',
            'route_exists'   => true,
            'ui_exists'      => false,
            'service_exists' => true,
            'test_exists'    => true,
        ]);

        $this->assertFalse($partial->truthAuditPassed());
    }
}
