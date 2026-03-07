<?php

namespace Tests\Feature\ExportV3;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use App\Models\User;
use App\Services\ExportV3\ProjectExporter;
use App\Services\ImportV3\ProjectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectExporterTest extends TestCase
{
    use RefreshDatabase;

    private function exporter(): ProjectExporter
    {
        return new ProjectExporter();
    }

    private function makeProject(array $overrides = []): Project
    {
        $owner   = User::factory()->create();
        $project = Project::factory()->create(array_merge(['owner_id' => $owner->id], $overrides));
        return $project->fresh();
    }

    private function makeEpic(Project $project, array $overrides = []): Epic
    {
        $epic = Epic::factory()->create(array_merge(['project_id' => $project->id], $overrides));
        return $epic->fresh();
    }

    private function makeTask(Epic $epic, array $overrides = []): Task
    {
        $task = Task::factory()->create(array_merge(['epic_id' => $epic->id], $overrides));
        return $task->fresh();
    }

    private function makePrompt(Task $task, array $overrides = []): TaskPrompt
    {
        return TaskPrompt::create(array_merge([
            'task_id'     => $task->id,
            'agent_type'  => 'claude_code',
            'format_type' => 'structured',
            'content'     => 'Do the thing.',
            'version'     => 1,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Envelope structure
    // -------------------------------------------------------------------------

    public function test_minimal_export_has_correct_envelope(): void
    {
        $project = $this->makeProject();
        $output  = $this->exporter()->export($project);

        $this->assertEquals('3.0', $output['schema_version']);
        $this->assertArrayHasKey('project', $output);
        $this->assertArrayHasKey('epics', $output);
        $this->assertArrayHasKey('meta', $output);
        $this->assertArrayHasKey('exported_at', $output['meta']);
        $this->assertIsArray($output['epics']);
    }

    public function test_exported_at_is_iso8601(): void
    {
        $project = $this->makeProject();
        $output  = $this->exporter()->export($project);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $output['meta']['exported_at']
        );
    }

    // -------------------------------------------------------------------------
    // Project fields
    // -------------------------------------------------------------------------

    public function test_project_core_fields_exported(): void
    {
        $owner   = User::factory()->create(['email' => 'owner@test.com']);
        $project = Project::factory()->create([
            'owner_id'   => $owner->id,
            'name'       => 'My Project',
            'description' => 'A description',
            'code'       => 'MP',
            'visibility' => 'shared',
        ]);

        $output = $this->exporter()->export($project);
        $p      = $output['project'];

        $this->assertEquals($project->id,  $p['id']);
        $this->assertEquals('My Project',  $p['name']);
        $this->assertEquals('A description', $p['description']);
        $this->assertEquals('MP',          $p['code']);
        $this->assertEquals('shared',      $p['visibility']);
        $this->assertEquals('owner@test.com', $p['owner_email']);
    }

    public function test_project_v3_fields_exported(): void
    {
        $project = $this->makeProject();
        $project->external_key = 'proj-ext-1';
        $project->status       = 'active';
        $project->tags         = json_encode(['web', 'api']);
        $project->save();

        $output = $this->exporter()->export($project->fresh());
        $p      = $output['project'];

        $this->assertEquals('proj-ext-1',   $p['external_key']);
        $this->assertEquals('active',        $p['status']);
        $this->assertEquals(['web', 'api'],  $p['tags']);
        $this->assertIsArray($p['tags']);  // not a raw JSON string
    }

    public function test_null_project_fields_are_omitted(): void
    {
        $project = $this->makeProject(['description' => null]);
        $output  = $this->exporter()->export($project);

        $this->assertArrayNotHasKey('description',  $output['project']);
        $this->assertArrayNotHasKey('external_key', $output['project']);
        $this->assertArrayNotHasKey('status',       $output['project']);
        $this->assertArrayNotHasKey('tags',         $output['project']);
    }

    // -------------------------------------------------------------------------
    // Epic fields
    // -------------------------------------------------------------------------

    public function test_epic_fields_exported(): void
    {
        $owner   = User::factory()->create(['email' => 'epic.owner@test.com']);
        $project = $this->makeProject();
        $epic    = $this->makeEpic($project, ['owner_id' => $owner->id]);
        $epic->external_key  = 'epic-ext-1';
        $epic->status        = 'active';
        $epic->priority      = 2;
        $epic->tags          = json_encode(['frontend']);
        $epic->goals         = json_encode(['Ship MVP']);
        $epic->milestone_tag = 'v1.0';
        $epic->position      = 5;
        $epic->save();

        $output = $this->exporter()->export($project->fresh());
        $e      = $output['epics'][0];

        $this->assertEquals($epic->id,    $e['id']);
        $this->assertEquals('epic-ext-1', $e['external_key']);
        $this->assertEquals('active',     $e['status']);
        $this->assertEquals(2,            $e['priority']);
        $this->assertEquals(['frontend'], $e['tags']);
        $this->assertEquals(['Ship MVP'], $e['goals']);
        $this->assertEquals('v1.0',       $e['milestone_tag']);
        $this->assertEquals(5,            $e['position']);
        $this->assertEquals('epic.owner@test.com', $e['owner_email']);
        $this->assertIsArray($e['tags']);
        $this->assertIsArray($e['goals']);
    }

    public function test_epic_without_tasks_omits_tasks_key(): void
    {
        $project = $this->makeProject();
        $this->makeEpic($project);

        $output = $this->exporter()->export($project->fresh());
        $this->assertArrayNotHasKey('tasks', $output['epics'][0]);
    }

    // -------------------------------------------------------------------------
    // Task fields
    // -------------------------------------------------------------------------

    public function test_rich_task_fields_exported(): void
    {
        $project = $this->makeProject();
        $epic    = $this->makeEpic($project);
        $task    = $this->makeTask($epic, ['status' => 'TODO', 'priority' => 5]);

        $task->external_key        = 'task-ext-1';
        $task->stage               = 'ready';
        $task->execution_mode      = 'automated';
        $task->estimate_size       = 'L';
        $task->assignee_value      = 'agent-claude';
        $task->assignee_type       = 'agent';
        $task->tags                = ['api', 'backend']; // cast to array on save
        $task->context             = 'Some context';
        $task->instructions        = 'Do this';
        $task->acceptance_criteria = 'It works';
        $task->dependencies        = json_encode(['dep-ext-1']);
        $task->blocking            = json_encode(['block-ext-1']);
        $task->artifacts           = json_encode([['name' => 'output.txt', 'url' => '/files/1']]);
        $task->review_metadata     = json_encode(['required' => true]);
        $task->custom_fields       = json_encode(['team' => 'backend']);
        $task->save();

        $output = $this->exporter()->export($project->fresh());
        $t      = $output['epics'][0]['tasks'][0];

        $this->assertEquals('task-ext-1',   $t['external_key']);
        $this->assertEquals('ready',         $t['stage']);
        $this->assertEquals('automated',     $t['execution_mode']);
        $this->assertEquals('L',             $t['estimate']);       // mapped from estimate_size
        $this->assertEquals('agent-claude',  $t['assignee']);       // mapped from assignee_value
        $this->assertEquals('agent',         $t['assignee_type']);
        $this->assertEquals(['api','backend'], $t['tags']);
        $this->assertEquals('Some context',  $t['context']);
        $this->assertEquals(['dep-ext-1'],   $t['dependencies']);
        $this->assertEquals(['block-ext-1'], $t['blocking']);
        $this->assertEquals(['required' => true], $t['review']);   // mapped from review_metadata
        $this->assertEquals([['name' => 'output.txt', 'url' => '/files/1']], $t['artifacts']);
        $this->assertEquals(['team' => 'backend'], $t['custom_fields']);

        // All JSON-backed fields must be arrays, not raw strings
        $this->assertIsArray($t['dependencies']);
        $this->assertIsArray($t['blocking']);
        $this->assertIsArray($t['review']);
        $this->assertIsArray($t['artifacts']);
        $this->assertIsArray($t['custom_fields']);
    }

    public function test_task_without_prompts_omits_prompts_key(): void
    {
        $project = $this->makeProject();
        $epic    = $this->makeEpic($project);
        $this->makeTask($epic);

        $output = $this->exporter()->export($project->fresh());
        $this->assertArrayNotHasKey('prompts', $output['epics'][0]['tasks'][0]);
    }

    // -------------------------------------------------------------------------
    // Prompt fields
    // -------------------------------------------------------------------------

    public function test_prompt_fields_exported(): void
    {
        $project = $this->makeProject();
        $epic    = $this->makeEpic($project);
        $task    = $this->makeTask($epic);
        $prompt  = $this->makePrompt($task, [
            'title'   => 'Impl prompt',
            'version' => 2,
        ]);
        $prompt->external_key = 'prompt-ext-1';
        $prompt->purpose      = 'implementation';
        $prompt->save();

        $output = $this->exporter()->export($project->fresh());
        $p      = $output['epics'][0]['tasks'][0]['prompts'][0];

        $this->assertEquals($prompt->id,       $p['id']);
        $this->assertEquals('prompt-ext-1',    $p['external_key']);
        $this->assertEquals('claude_code',     $p['agent_type']);
        $this->assertEquals('structured',      $p['format_type']);
        $this->assertEquals('Impl prompt',     $p['title']);
        $this->assertEquals(2,                 $p['version']);
        $this->assertEquals('implementation',  $p['purpose']);
        $this->assertEquals('Do the thing.',   $p['content']);
    }

    // -------------------------------------------------------------------------
    // Ordering
    // -------------------------------------------------------------------------

    public function test_epics_ordered_by_position_then_id(): void
    {
        $project = $this->makeProject();
        $b = $this->makeEpic($project, ['title' => 'B', 'position' => 2]);
        $c = $this->makeEpic($project, ['title' => 'C', 'position' => 3]);
        $a = $this->makeEpic($project, ['title' => 'A', 'position' => 1]);

        $output = $this->exporter()->export($project->fresh());
        $titles  = array_column($output['epics'], 'title');

        $this->assertEquals(['A', 'B', 'C'], $titles);
    }

    public function test_tasks_ordered_by_position_then_id(): void
    {
        $project = $this->makeProject();
        $epic    = $this->makeEpic($project);
        $this->makeTask($epic, ['title' => 'T2', 'position' => 20]);
        $this->makeTask($epic, ['title' => 'T3', 'position' => 30]);
        $this->makeTask($epic, ['title' => 'T1', 'position' => 10]);

        $output = $this->exporter()->export($project->fresh());
        $titles  = array_column($output['epics'][0]['tasks'], 'title');

        $this->assertEquals(['T1', 'T2', 'T3'], $titles);
    }

    public function test_prompts_ordered_by_id(): void
    {
        $project = $this->makeProject();
        $epic    = $this->makeEpic($project);
        $task    = $this->makeTask($epic);

        $p1 = $this->makePrompt($task, ['agent_type' => 'claude_code', 'content' => 'first']);
        $p2 = $this->makePrompt($task, ['agent_type' => 'cursor2',    'content' => 'second']);
        $p3 = $this->makePrompt($task, ['agent_type' => 'human',      'content' => 'third']);

        $output  = $this->exporter()->export($project->fresh());
        $prompts = $output['epics'][0]['tasks'][0]['prompts'];

        $this->assertEquals($p1->id, $prompts[0]['id']);
        $this->assertEquals($p2->id, $prompts[1]['id']);
        $this->assertEquals($p3->id, $prompts[2]['id']);
    }

    // -------------------------------------------------------------------------
    // Round-trip: export → import
    // -------------------------------------------------------------------------

    public function test_export_import_round_trip(): void
    {
        // 1. Create a rich project in the DB
        $owner   = User::factory()->create(['email' => 'roundtrip@test.com']);
        $project = Project::factory()->create([
            'owner_id'   => $owner->id,
            'name'       => 'Round Trip Project',
            'code'       => 'RTP',
            'visibility' => 'private',
        ]);
        $project->external_key = 'rtp-proj';
        $project->status       = 'active';
        $project->save();

        $epic = $this->makeEpic($project, ['title' => 'Epic RT']);
        $epic->external_key = 'rtp-epic';
        $epic->save();

        $task = $this->makeTask($epic, ['title' => 'Task RT', 'status' => 'TODO']);
        $task->external_key   = 'rtp-task';
        $task->stage          = 'ready';
        $task->dependencies   = json_encode([]);
        $task->save();

        $this->makePrompt($task, [
            'agent_type'  => 'claude_code',
            'format_type' => 'structured',
            'content'     => 'Build it.',
            'version'     => 1,
        ]);
        // Assign external_key via direct update
        TaskPrompt::where('task_id', $task->id)->update(['external_key' => 'rtp-prompt']);

        // 2. Export
        $exported = $this->exporter()->export($project->fresh());

        $this->assertEquals('3.0', $exported['schema_version']);
        $this->assertArrayHasKey('meta', $exported);

        // 3. Re-import (same project matched by external_key — should update, not duplicate)
        $result = (new ProjectImporter())->import($exported);

        $this->assertFalse($result->projectCreated);
        $this->assertTrue($result->projectUpdated);

        // No DB row explosion
        $this->assertEquals(1, Project::count());
        $this->assertEquals(1, Epic::count());
        $this->assertEquals(1, Task::count());
        $this->assertEquals(1, TaskPrompt::count());

        // Values preserved
        $this->assertEquals('Round Trip Project', Project::first()->name);
        $this->assertEquals('Epic RT', Epic::first()->title);
        $this->assertEquals('Task RT', Task::first()->title);
        $this->assertEquals('rtp-task', Task::first()->external_key);
    }

    public function test_schema_version_in_output_is_string_3_0(): void
    {
        $project = $this->makeProject();
        $output  = $this->exporter()->export($project);

        $this->assertSame('3.0', $output['schema_version']);
        $this->assertIsString($output['schema_version']);
    }
}
