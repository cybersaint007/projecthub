<?php

namespace Tests\Feature;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
    }

    public function test_reorder_epics_updates_positions(): void
    {
        $e1 = Epic::factory()->create(['project_id' => $this->project->id, 'position' => 10]);
        $e2 = Epic::factory()->create(['project_id' => $this->project->id, 'position' => 20]);
        $e3 = Epic::factory()->create(['project_id' => $this->project->id, 'position' => 30]);

        $response = $this->actingAs($this->owner)->postJson(route('projects.epics.reorder', $this->project), [
            'epic_ids' => [$e3->id, $e1->id, $e2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $this->assertSame(10, $e3->fresh()->position);
        $this->assertSame(20, $e1->fresh()->position);
        $this->assertSame(30, $e2->fresh()->position);

        $ordered = $this->project->epics()->orderBy('position')->pluck('id')->all();
        $this->assertSame([$e3->id, $e1->id, $e2->id], $ordered);
    }

    public function test_reorder_tasks_within_one_epic(): void
    {
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);
        $t1 = Task::factory()->create(['epic_id' => $epic->id, 'position' => 10]);
        $t2 = Task::factory()->create(['epic_id' => $epic->id, 'position' => 20]);
        $t3 = Task::factory()->create(['epic_id' => $epic->id, 'position' => 30]);

        $response = $this->actingAs($this->owner)->postJson(route('projects.tasks.reorder', $this->project), [
            'columns' => [
                ['epic_id' => $epic->id, 'task_ids' => [$t3->id, $t1->id, $t2->id]],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $this->assertSame(10, $t3->fresh()->position);
        $this->assertSame(20, $t1->fresh()->position);
        $this->assertSame(30, $t2->fresh()->position);
    }

    public function test_reorder_tasks_moves_task_across_epics(): void
    {
        $epicA = Epic::factory()->create(['project_id' => $this->project->id]);
        $epicB = Epic::factory()->create(['project_id' => $this->project->id]);
        $t1 = Task::factory()->create(['epic_id' => $epicA->id, 'position' => 10]);
        $t2 = Task::factory()->create(['epic_id' => $epicA->id, 'position' => 20]);
        $t3 = Task::factory()->create(['epic_id' => $epicB->id, 'position' => 10]);

        $response = $this->actingAs($this->owner)->postJson(route('projects.tasks.reorder', $this->project), [
            'columns' => [
                ['epic_id' => $epicA->id, 'task_ids' => [$t2->id]],
                ['epic_id' => $epicB->id, 'task_ids' => [$t3->id, $t1->id]],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $t1->refresh();
        $this->assertSame($epicB->id, $t1->epic_id);
        $this->assertSame(20, $t1->position);
        $this->assertSame($epicA->id, $t2->fresh()->epic_id);
        $this->assertSame(10, $t2->fresh()->position);
        $this->assertSame($epicB->id, $t3->fresh()->epic_id);
        $this->assertSame(10, $t3->fresh()->position);
    }

    public function test_unauthorized_user_cannot_reorder_epics(): void
    {
        $viewer = User::factory()->create();
        $this->project->accessUsers()->attach($viewer->id, ['role' => 'viewer']);

        $epic = Epic::factory()->create(['project_id' => $this->project->id]);

        $response = $this->actingAs($viewer)->postJson(route('projects.epics.reorder', $this->project), [
            'epic_ids' => [$epic->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_reorder_tasks(): void
    {
        $viewer = User::factory()->create();
        $this->project->accessUsers()->attach($viewer->id, ['role' => 'viewer']);
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);
        $task = Task::factory()->create(['epic_id' => $epic->id]);

        $response = $this->actingAs($viewer)->postJson(route('projects.tasks.reorder', $this->project), [
            'columns' => [['epic_id' => $epic->id, 'task_ids' => [$task->id]]],
        ]);

        $response->assertStatus(403);
    }

    public function test_reject_foreign_epic_ids(): void
    {
        $otherProject = Project::factory()->create(['owner_id' => $this->owner->id]);
        $foreignEpic = Epic::factory()->create(['project_id' => $otherProject->id]);
        $ownEpic = Epic::factory()->create(['project_id' => $this->project->id]);

        $response = $this->actingAs($this->owner)->postJson(route('projects.epics.reorder', $this->project), [
            'epic_ids' => [$ownEpic->id, $foreignEpic->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('epic_ids');
    }

    public function test_reject_foreign_task_ids(): void
    {
        $otherProject = Project::factory()->create(['owner_id' => $this->owner->id]);
        $otherEpic = Epic::factory()->create(['project_id' => $otherProject->id]);
        $foreignTask = Task::factory()->create(['epic_id' => $otherEpic->id]);
        $ownEpic = Epic::factory()->create(['project_id' => $this->project->id]);
        $ownTask = Task::factory()->create(['epic_id' => $ownEpic->id]);

        $response = $this->actingAs($this->owner)->postJson(route('projects.tasks.reorder', $this->project), [
            'columns' => [
                ['epic_id' => $ownEpic->id, 'task_ids' => [$ownTask->id, $foreignTask->id]],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('columns');
    }
}
