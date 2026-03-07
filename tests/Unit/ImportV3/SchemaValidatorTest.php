<?php

namespace Tests\Unit\ImportV3;

use App\Services\ImportV3\SchemaValidator;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    private function validator(): SchemaValidator
    {
        return new SchemaValidator();
    }

    private function validMinimal(): array
    {
        return [
            'schema_version' => '3.0',
            'project' => ['name' => 'My Project'],
            'epics' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Valid cases
    // -------------------------------------------------------------------------

    public function test_valid_minimal_json(): void
    {
        $v = $this->validator();
        $this->assertTrue($v->validate($this->validMinimal()));
        $this->assertEmpty($v->errors());
    }

    public function test_valid_full_structure(): void
    {
        $data = [
            'schema_version' => '3.0',
            'project' => ['name' => 'Full Project', 'code' => 'FP'],
            'epics' => [
                [
                    'title' => 'Epic One',
                    'tasks' => [
                        [
                            'title' => 'Task One',
                            'prompts' => [
                                [
                                    'agent_type' => 'claude_code',
                                    'format_type' => 'markdown',
                                    'content' => 'Do the thing.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $v = $this->validator();
        $this->assertTrue($v->validate($data));
        $this->assertEmpty($v->errors());
    }

    public function test_tasks_and_prompts_are_optional(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [['title' => 'Epic without tasks']];

        $v = $this->validator();
        $this->assertTrue($v->validate($data));
    }

    public function test_prompts_are_optional_on_tasks(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'Epic', 'tasks' => [['title' => 'Task without prompts']]],
        ];

        $v = $this->validator();
        $this->assertTrue($v->validate($data));
    }

    // -------------------------------------------------------------------------
    // schema_version
    // -------------------------------------------------------------------------

    public function test_missing_schema_version(): void
    {
        $data = $this->validMinimal();
        unset($data['schema_version']);

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('schema_version is required', $v->errors());
    }

    public function test_wrong_schema_version(): void
    {
        $data = $this->validMinimal();
        $data['schema_version'] = '2.0';

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('schema_version must be "3.0", got "2.0"', $v->errors());
    }

    // -------------------------------------------------------------------------
    // project
    // -------------------------------------------------------------------------

    public function test_missing_project(): void
    {
        $data = $this->validMinimal();
        unset($data['project']);

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('project is required', $v->errors());
    }

    public function test_missing_project_name(): void
    {
        $data = $this->validMinimal();
        $data['project'] = ['code' => 'X'];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('project.name is required', $v->errors());
    }

    public function test_empty_project_name(): void
    {
        $data = $this->validMinimal();
        $data['project'] = ['name' => '   '];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('project.name is required', $v->errors());
    }

    public function test_project_must_be_object(): void
    {
        $data = $this->validMinimal();
        $data['project'] = 'not-an-object';

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('project must be an object', $v->errors());
    }

    // -------------------------------------------------------------------------
    // epics
    // -------------------------------------------------------------------------

    public function test_missing_epics(): void
    {
        $data = $this->validMinimal();
        unset($data['epics']);

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics is required', $v->errors());
    }

    public function test_epics_must_be_array(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = 'not-an-array';

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics must be an array', $v->errors());
    }

    public function test_missing_epic_title(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [['status' => 'active']];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].title is required', $v->errors());
    }

    public function test_empty_epic_title(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [['title' => '']];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].title is required', $v->errors());
    }

    public function test_epic_tasks_must_be_array_not_object(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [['title' => 'Epic', 'tasks' => ['key' => 'value']]];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks must be an array', $v->errors());
    }

    // -------------------------------------------------------------------------
    // tasks
    // -------------------------------------------------------------------------

    public function test_missing_task_title(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'Epic', 'tasks' => [['priority' => 'high']]],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks[0].title is required', $v->errors());
    }

    public function test_task_title_path_includes_indexes(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'Epic A', 'tasks' => [['title' => 'OK']]],
            ['title' => 'Epic B', 'tasks' => [['title' => 'OK'], ['priority' => 'low']]],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[1].tasks[1].title is required', $v->errors());
    }

    public function test_task_prompts_must_be_array(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'Epic', 'tasks' => [['title' => 'Task', 'prompts' => 'bad']]],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks[0].prompts must be an array', $v->errors());
    }

    // -------------------------------------------------------------------------
    // prompts
    // -------------------------------------------------------------------------

    public function test_missing_prompt_agent_type(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'E', 'tasks' => [
                ['title' => 'T', 'prompts' => [
                    ['format_type' => 'markdown', 'content' => 'Do it.'],
                ]],
            ]],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks[0].prompts[0].agent_type is required', $v->errors());
    }

    public function test_missing_prompt_format_type(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'E', 'tasks' => [
                ['title' => 'T', 'prompts' => [
                    ['agent_type' => 'claude_code', 'content' => 'Do it.'],
                ]],
            ]],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks[0].prompts[0].format_type is required', $v->errors());
    }

    public function test_missing_prompt_content(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'E', 'tasks' => [
                ['title' => 'T', 'prompts' => [
                    ['agent_type' => 'claude_code', 'format_type' => 'markdown'],
                ]],
            ]],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks[0].prompts[0].content is required', $v->errors());
    }

    public function test_prompt_path_includes_all_indexes(): void
    {
        $data = $this->validMinimal();
        $data['epics'] = [
            ['title' => 'E', 'tasks' => [
                ['title' => 'T', 'prompts' => [
                    ['agent_type' => 'claude_code', 'format_type' => 'markdown', 'content' => 'OK'],
                    ['agent_type' => 'claude_code', 'format_type' => 'markdown'],
                ]],
            ]],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks[0].prompts[1].content is required', $v->errors());
    }

    // -------------------------------------------------------------------------
    // Multiple errors
    // -------------------------------------------------------------------------

    public function test_collects_multiple_errors(): void
    {
        $data = [
            'schema_version' => '3.0',
            'project' => ['name' => 'P'],
            'epics' => [
                ['title' => 'E', 'tasks' => [
                    ['title' => 'T1'],
                    [],
                ]],
                [],
            ],
        ];

        $v = $this->validator();
        $this->assertFalse($v->validate($data));
        $this->assertContains('epics[0].tasks[1].title is required', $v->errors());
        $this->assertContains('epics[1].title is required', $v->errors());
    }
}
