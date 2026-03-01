<?php

namespace Tests\Feature;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacklogExportImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->project = Project::factory()->create(['owner_id' => $this->admin->id, 'code' => 'TEST']);
    }

    public function test_export_includes_all_task_fillable_keys(): void
    {
        $epic = Epic::factory()->create(['project_id' => $this->project->id, 'position' => 10]);
        Task::factory()->create([
            'epic_id' => $epic->id,
            'title' => 'Test Task',
            'description' => 'desc',
            'status' => 'Ready',
            'agent' => 'claude_code',
            'priority' => 5,
            'tags' => ['backend', 'api'],
            'context' => 'ctx',
            'instructions' => 'instr',
            'acceptance_criteria' => 'ac',
            'position' => 10,
        ]);

        $response = $this->actingAs($this->admin)->get(route('backlog.export-json', $this->project));

        $response->assertOk();
        $json = $response->json();

        $taskData = $json['epics'][0]['tasks'][0];

        foreach (Task::exportableFields() as $field) {
            $this->assertArrayHasKey($field, $taskData, "Missing field: {$field}");
        }

        $this->assertSame('Test Task', $taskData['title']);
        $this->assertSame('Ready', $taskData['status']);
        $this->assertSame('claude_code', $taskData['agent']);
        $this->assertSame(5, $taskData['priority']);
        $this->assertSame(['backend', 'api'], $taskData['tags']);
        $this->assertSame('ctx', $taskData['context']);
        $this->assertSame('instr', $taskData['instructions']);
        $this->assertSame('ac', $taskData['acceptance_criteria']);
    }

    public function test_export_includes_prompts(): void
    {
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);
        $task = Task::factory()->create(['epic_id' => $epic->id]);
        TaskPrompt::create([
            'task_id' => $task->id,
            'agent_type' => 'claude_code',
            'format_type' => 'structured',
            'title' => 'My Prompt',
            'version' => 1,
            'content' => 'Prompt content here',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('backlog.export-json', $this->project));

        $response->assertOk();
        $taskData = $response->json('epics.0.tasks.0');
        $this->assertArrayHasKey('prompts', $taskData);
        $this->assertCount(1, $taskData['prompts']);
        $this->assertSame('claude_code', $taskData['prompts'][0]['agent_type']);
        $this->assertSame('Prompt content here', $taskData['prompts'][0]['content']);
    }

    public function test_export_preserves_ordering(): void
    {
        $e1 = Epic::factory()->create(['project_id' => $this->project->id, 'position' => 20]);
        $e2 = Epic::factory()->create(['project_id' => $this->project->id, 'position' => 10]);
        Task::factory()->create(['epic_id' => $e1->id, 'position' => 30, 'title' => 'T1-3']);
        Task::factory()->create(['epic_id' => $e1->id, 'position' => 10, 'title' => 'T1-1']);

        $response = $this->actingAs($this->admin)->get(route('backlog.export-json', $this->project));

        $epics = $response->json('epics');
        $this->assertSame($e2->id, $this->project->epics()->orderBy('position')->first()->id);
        $this->assertSame(10, $epics[0]['position']); // e2 first by position
        $this->assertSame('T1-1', $epics[1]['tasks'][0]['title']); // sorted by position
    }

    public function test_import_replace_creates_correct_structure(): void
    {
        // Pre-existing data
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);
        $task = Task::factory()->create(['epic_id' => $epic->id, 'title' => 'Old Task']);

        $json = json_encode([
            'epics' => [
                [
                    'title' => 'New Epic',
                    'position' => 10,
                    'tasks' => [
                        ['title' => 'New Task 1', 'status' => 'TODO', 'agent' => 'claude_code', 'priority' => 5, 'position' => 10],
                        ['title' => 'New Task 2', 'status' => 'Ready', 'position' => 20],
                    ],
                ],
            ],
        ]);

        // Preview
        $previewResponse = $this->actingAs($this->admin)->post(route('backlog.import-replace.preview', $this->project), [
            'json_payload' => $json,
        ]);
        $previewResponse->assertRedirect();

        // Apply
        $applyResponse = $this->actingAs($this->admin)->post(route('backlog.import-replace.apply', $this->project), [
            'confirmed_json' => $json,
            'backup' => '1',
        ]);

        $applyResponse->assertRedirect(route('projects.show', $this->project));

        // Old task should be soft-deleted
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSoftDeleted('epics', ['id' => $epic->id]);

        // New structure should exist
        $newEpics = $this->project->epics()->get();
        $this->assertCount(1, $newEpics);
        $this->assertSame('New Epic', $newEpics[0]->title);
        $this->assertCount(2, $newEpics[0]->tasks);

        // Backup should exist
        $this->assertDatabaseHas('backlog_backups', ['project_id' => $this->project->id]);
    }

    public function test_import_replace_deterministic_ordering(): void
    {
        $json = json_encode([
            'epics' => [
                ['title' => 'Epic A', 'position' => 20, 'tasks' => [
                    ['title' => 'Task A2', 'position' => 20],
                    ['title' => 'Task A1', 'position' => 10],
                ]],
                ['title' => 'Epic B', 'position' => 10, 'tasks' => []],
            ],
        ]);

        $this->actingAs($this->admin)->post(route('backlog.import-replace.apply', $this->project), [
            'confirmed_json' => $json,
            'backup' => '0',
        ]);

        $epics = $this->project->epics()->orderBy('position')->get();
        $this->assertSame('Epic B', $epics[0]->title);
        $this->assertSame('Epic A', $epics[1]->title);

        $tasks = $epics[1]->tasks()->orderBy('position')->get();
        $this->assertSame('Task A1', $tasks[0]->title);
        $this->assertSame(10, $tasks[0]->position);
        $this->assertSame('Task A2', $tasks[1]->title);
        $this->assertSame(20, $tasks[1]->position);
    }

    public function test_import_replace_round_trip(): void
    {
        $epic = Epic::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'RT Epic',
            'position' => 10,
        ]);
        Task::factory()->create([
            'epic_id' => $epic->id,
            'title' => 'RT Task',
            'status' => 'Ready',
            'agent' => 'cursor2',
            'priority' => 5,
            'position' => 10,
            'context' => 'ctx',
            'instructions' => 'instr',
        ]);

        // Export
        $exportResponse = $this->actingAs($this->admin)->get(route('backlog.export-json', $this->project));
        $exportedJson = $exportResponse->getContent();

        // Replace with same data
        $this->actingAs($this->admin)->post(route('backlog.import-replace.apply', $this->project), [
            'confirmed_json' => $exportedJson,
            'backup' => '1',
        ]);

        // Export again
        $exportResponse2 = $this->actingAs($this->admin)->get(route('backlog.export-json', $this->project));
        $data2 = $exportResponse2->json();

        $this->assertCount(1, $data2['epics']);
        $this->assertSame('RT Epic', $data2['epics'][0]['title']);
        $this->assertSame(10, $data2['epics'][0]['position']);
        $this->assertSame('RT Task', $data2['epics'][0]['tasks'][0]['title']);
        $this->assertSame('Ready', $data2['epics'][0]['tasks'][0]['status']);
        $this->assertSame('cursor2', $data2['epics'][0]['tasks'][0]['agent']);
        $this->assertSame(5, $data2['epics'][0]['tasks'][0]['priority']);
    }

    public function test_viewer_cannot_import_replace(): void
    {
        $viewer = User::factory()->create();
        $this->project->accessUsers()->attach($viewer->id, ['role' => 'viewer']);

        $response = $this->actingAs($viewer)->get(route('backlog.import-replace', $this->project));

        $response->assertStatus(403);
    }
}
