<?php

namespace Tests\Feature\ImportV3;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use App\Models\User;
use App\Services\ImportV3\ProjectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectImporterTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function importer(): ProjectImporter
    {
        return new ProjectImporter;
    }

    private function minimal(): array
    {
        return [
            'schema_version' => '3.0',
            'project' => ['name' => 'Test Project'],
            'epics' => [],
        ];
    }

    private function fullPayload(string $projectName = 'Full Project'): array
    {
        return [
            'schema_version' => '3.0',
            'project' => [
                'external_key' => 'proj-ext-1',
                'code' => 'FULL',
                'name' => $projectName,
                'description' => 'A full project',
                'visibility' => 'private',
                'status' => 'active',
                'tags' => ['web', 'api'],
            ],
            'epics' => [
                [
                    'external_key' => 'epic-ext-1',
                    'title' => 'Epic One',
                    'description' => 'First epic',
                    'milestone_tag' => 'v1.0',
                    'status' => 'active',
                    'priority' => 2,
                    'tags' => ['backend'],
                    'goals' => ['ship MVP'],
                    'tasks' => [
                        [
                            'external_key' => 'task-ext-1',
                            'title' => 'Task One',
                            'description' => 'First task',
                            'status' => 'TODO',
                            'stage' => 'ready',
                            'priority' => 'high',
                            'execution_mode' => 'automated',
                            'estimate' => 'M',
                            'tags' => ['api'],
                            'context' => 'ctx',
                            'instructions' => 'do the thing',
                            'acceptance_criteria' => 'it works',
                            'custom_fields' => ['team' => 'backend'],
                            'prompts' => [
                                [
                                    'external_key' => 'prompt-ext-1',
                                    'agent_type' => 'claude_code',
                                    'format_type' => 'structured',
                                    'title' => 'Impl prompt',
                                    'purpose' => 'implementation',
                                    'content' => 'Implement this feature.',
                                    'version' => 1,
                                ],
                            ],
                        ],
                        [
                            'external_key' => 'task-ext-2',
                            'title' => 'Task Two',
                            'dependencies' => ['task-ext-1'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Minimal import
    // -------------------------------------------------------------------------

    public function test_minimal_valid_import_creates_project(): void
    {
        $result = $this->importer()->import($this->minimal());

        $this->assertTrue($result->projectCreated);
        $this->assertFalse($result->projectUpdated);
        $this->assertEquals(0, $result->epicsCreated);
        $this->assertEquals(0, $result->tasksCreated);
        $this->assertEquals(1, Project::count());
    }

    // -------------------------------------------------------------------------
    // Create without IDs
    // -------------------------------------------------------------------------

    public function test_create_new_project_without_ids(): void
    {
        $payload = $this->fullPayload();
        $result = $this->importer()->import($payload);

        $this->assertTrue($result->projectCreated);
        $this->assertEquals(1, $result->epicsCreated);
        $this->assertEquals(2, $result->tasksCreated);
        $this->assertEquals(1, $result->promptsCreated);

        $project = Project::where('code', 'FULL')->first();
        $this->assertNotNull($project);
        $this->assertEquals('proj-ext-1', $project->external_key);
        $this->assertEquals('active', $project->status);

        $epic = Epic::where('external_key', 'epic-ext-1')->first();
        $this->assertNotNull($epic);
        $this->assertEquals('active', $epic->status);
        $this->assertEquals(2, $epic->priority);

        $task = Task::where('external_key', 'task-ext-1')->first();
        $this->assertNotNull($task);
        $this->assertEquals('ready', $task->stage);
        $this->assertEquals('automated', $task->execution_mode);
        $this->assertEquals('M', $task->estimate_size);
        $this->assertEquals(Task::PRIORITY_HIGH, $task->priority);

        $prompt = TaskPrompt::where('external_key', 'prompt-ext-1')->first();
        $this->assertNotNull($prompt);
        $this->assertEquals('claude_code', $prompt->agent_type);
        $this->assertEquals('implementation', $prompt->purpose);
    }

    // -------------------------------------------------------------------------
    // Re-import idempotency (upsert, not duplicate)
    // -------------------------------------------------------------------------

    public function test_reimport_same_json_updates_not_duplicates(): void
    {
        $payload = $this->fullPayload();
        $this->importer()->import($payload);

        // Mutate payload
        $payload['project']['name'] = 'Updated Project';
        $payload['epics'][0]['title'] = 'Epic One Updated';
        $payload['epics'][0]['tasks'][0]['title'] = 'Task One Updated';
        $payload['epics'][0]['tasks'][0]['prompts'][0]['content'] = 'Updated content.';

        $result = $this->importer()->import($payload);

        $this->assertFalse($result->projectCreated);
        $this->assertTrue($result->projectUpdated);
        $this->assertEquals(0, $result->epicsCreated);
        $this->assertEquals(1, $result->epicsUpdated);
        $this->assertEquals(0, $result->tasksCreated);
        $this->assertEquals(2, $result->tasksUpdated);
        $this->assertEquals(0, $result->promptsCreated);
        $this->assertEquals(1, $result->promptsUpdated);

        // Only one of each in DB
        $this->assertEquals(1, Project::count());
        $this->assertEquals(1, Epic::count());
        $this->assertEquals(2, Task::count());
        $this->assertEquals(1, TaskPrompt::count());

        // Values updated
        $this->assertEquals('Updated Project', Project::first()->name);
        $this->assertEquals('Epic One Updated', Epic::first()->title);
        $this->assertEquals('Task One Updated', Task::where('external_key', 'task-ext-1')->first()->title);
        $this->assertEquals('Updated content.', TaskPrompt::first()->content);
    }

    // -------------------------------------------------------------------------
    // Match by external_key
    // -------------------------------------------------------------------------

    public function test_match_and_update_by_external_key(): void
    {
        // First import creates the project
        $payload = $this->fullPayload('Original Name');
        $this->importer()->import($payload);

        // Second import omits id but includes same external_key — should update
        $payload2 = [
            'schema_version' => '3.0',
            'project' => [
                'external_key' => 'proj-ext-1',
                'name' => 'Updated via external_key',
            ],
            'epics' => [],
        ];
        $result = $this->importer()->import($payload2);

        $this->assertFalse($result->projectCreated);
        $this->assertTrue($result->projectUpdated);
        $this->assertEquals('Updated via external_key', Project::where('external_key', 'proj-ext-1')->first()->name);
        $this->assertEquals(1, Project::count());
    }

    public function test_match_epic_by_external_key(): void
    {
        $this->importer()->import($this->fullPayload());

        $payload2 = [
            'schema_version' => '3.0',
            'project' => ['external_key' => 'proj-ext-1', 'name' => 'Full Project'],
            'epics' => [
                [
                    'external_key' => 'epic-ext-1',
                    'title' => 'Epic Renamed',
                ],
            ],
        ];
        $this->importer()->import($payload2);

        $this->assertEquals(1, Epic::count());
        $this->assertEquals('Epic Renamed', Epic::where('external_key', 'epic-ext-1')->first()->title);
    }

    // -------------------------------------------------------------------------
    // Dependency resolution
    // -------------------------------------------------------------------------

    public function test_dependency_warnings_for_unknown_external_keys(): void
    {
        $payload = $this->minimal();
        $payload['epics'] = [
            [
                'title' => 'Epic',
                'tasks' => [
                    [
                        'external_key' => 'task-a',
                        'title' => 'Task A',
                        'dependencies' => ['task-nonexistent'],
                    ],
                ],
            ],
        ];

        $result = $this->importer()->import($payload);

        $this->assertNotEmpty($result->dependencyWarnings);
        $this->assertStringContainsString('task-nonexistent', $result->dependencyWarnings[0]);
    }

    public function test_dependency_resolution_succeeds_for_known_keys(): void
    {
        $result = $this->importer()->import($this->fullPayload());

        // task-ext-2 depends on task-ext-1 — both imported — no warnings
        $this->assertEmpty($result->dependencyWarnings);

        $task2 = Task::where('external_key', 'task-ext-2')->first();
        $this->assertNotNull($task2);
        $this->assertJsonStringEqualsJsonString('["task-ext-1"]', $task2->dependencies);
    }

    // -------------------------------------------------------------------------
    // Transaction rollback
    // -------------------------------------------------------------------------

    public function test_transaction_rolls_back_on_failure(): void
    {
        // Subclass that throws on second epic
        $importer = new class extends ProjectImporter
        {
            protected function upsertEpic(array $d, int $projectId): array
            {
                static $count = 0;
                if (++$count > 1) {
                    throw new \RuntimeException('Simulated failure on second epic');
                }

                return parent::upsertEpic($d, $projectId);
            }
        };

        $payload = $this->minimal();
        $payload['epics'] = [
            ['title' => 'Epic One'],
            ['title' => 'Epic Two'],  // triggers the throw
        ];

        $this->expectException(\RuntimeException::class);

        try {
            $importer->import($payload);
        } finally {
            // Project and Epic One must not persist — full rollback
            $this->assertEquals(0, Project::count());
            $this->assertEquals(0, Epic::count());
        }
    }

    // -------------------------------------------------------------------------
    // Field mapping details
    // -------------------------------------------------------------------------

    public function test_priority_string_normalised(): void
    {
        $payload = $this->minimal();
        $payload['epics'] = [
            ['title' => 'E', 'tasks' => [
                ['title' => 'low',  'priority' => 'low'],
                ['title' => 'mid',  'priority' => 'medium'],
                ['title' => 'high', 'priority' => 'high'],
            ]],
        ];

        $this->importer()->import($payload);

        $tasks = Task::orderBy('id')->get();
        $this->assertEquals(Task::PRIORITY_LOW, $tasks[0]->priority);
        $this->assertEquals(Task::PRIORITY_MEDIUM, $tasks[1]->priority);
        $this->assertEquals(Task::PRIORITY_HIGH, $tasks[2]->priority);
    }

    public function test_object_agent_is_flattened_to_type_string(): void
    {
        $payload = $this->minimal();
        $payload['epics'] = [
            ['title' => 'E', 'tasks' => [
                ['title' => 'obj',    'agent' => ['type' => 'claude_code', 'lease' => null]],
                ['title' => 'scalar', 'agent' => 'cursor2'],
                ['title' => 'empty',  'agent' => []],
            ]],
        ];

        $this->importer()->import($payload);

        $tasks = Task::orderBy('id')->get();
        $this->assertSame('claude_code', $tasks[0]->agent);
        $this->assertSame('cursor2', $tasks[1]->agent);
        // Typeless object → column default ('human'), never a NOT NULL violation.
        $this->assertSame('human', $tasks[2]->agent);
    }

    public function test_prompt_default_version_is_one(): void
    {
        $payload = $this->minimal();
        $payload['epics'] = [
            ['title' => 'E', 'tasks' => [
                ['title' => 'T', 'prompts' => [
                    ['agent_type' => 'claude_code', 'format_type' => 'structured', 'content' => 'x'],
                ]],
            ]],
        ];

        $this->importer()->import($payload);

        $this->assertEquals(1, TaskPrompt::first()->version);
    }

    public function test_owner_email_not_found_adds_warning_not_failure(): void
    {
        $payload = $this->minimal();
        $payload['project']['owner_email'] = 'nobody@example.com';

        $result = $this->importer()->import($payload);

        $this->assertTrue($result->projectCreated);
        $this->assertNotEmpty($result->dependencyWarnings);
        $this->assertStringContainsString('nobody@example.com', $result->dependencyWarnings[0]);
    }

    public function test_owner_email_resolved_when_user_exists(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $payload = $this->minimal();
        $payload['project']['owner_email'] = 'owner@example.com';

        $this->importer()->import($payload);

        $this->assertEquals($user->id, Project::first()->owner_id);
    }

    public function test_import_summary_counts_are_accurate(): void
    {
        $payload = $this->fullPayload();
        $result = $this->importer()->import($payload);

        $this->assertEquals(1, $result->epicsCreated);
        $this->assertEquals(0, $result->epicsUpdated);
        $this->assertEquals(2, $result->tasksCreated);
        $this->assertEquals(0, $result->tasksUpdated);
        $this->assertEquals(1, $result->promptsCreated);
        $this->assertEquals(0, $result->promptsUpdated);
        $this->assertIsFloat($result->elapsedSeconds);
    }
}
