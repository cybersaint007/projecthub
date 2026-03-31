<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskPrompt;

class AgentBundleService
{
    public function buildBundle(Task $task): array
    {
        $task->load(['epic.project', 'taskPrompts']);

        $epic = $task->epic;
        $project = $epic->project;

        $prompt = $this->resolvePrompt($task);
        $prompt['content'] .= $this->reportingFooter();

        return [
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
            ],
            'epic' => [
                'id' => $epic->id,
                'title' => $epic->title,
                'description' => $epic->description,
                'milestone_tag' => $epic->milestone_tag,
                'position' => $epic->position,
            ],
            'task' => $this->taskPayload($task),
            'prompt' => $prompt,
        ];
    }

    private function taskPayload(Task $task): array
    {
        $data = ['id' => $task->id];

        foreach (Task::exportableFields() as $field) {
            $data[$field] = $task->{$field};
        }

        $data['assignee_id'] = $task->assignee_id;
        $data['created_at'] = $task->created_at?->toIso8601String();
        $data['updated_at'] = $task->updated_at?->toIso8601String();

        return $data;
    }

    private function resolvePrompt(Task $task): array
    {
        $agentType = $task->agent;

        $prompt = TaskPrompt::where('task_id', $task->id)
            ->where('agent_type', $agentType)
            ->orderByDesc('version')
            ->first();

        if ($prompt) {
            return [
                'id' => $prompt->id,
                'agent_type' => $prompt->agent_type,
                'format_type' => $prompt->format_type,
                'title' => $prompt->title,
                'version' => $prompt->version,
                'content' => $prompt->content,
                'generated' => false,
            ];
        }

        return [
            'id' => null,
            'agent_type' => $agentType,
            'format_type' => 'structured',
            'title' => null,
            'version' => null,
            'content' => $this->generateDefaultPrompt($task),
            'generated' => true,
        ];
    }

    private function reportingFooter(): string
    {
        return <<<'MD'


---

## Work Log Report (Required)

After completing the task, you MUST output a structured work log using exactly this format:

### Summary
A concise paragraph describing what was implemented and any key decisions made.

### Files Created
List every new file created, one per line with a brief note:
- `path/to/file.php` — what it does

### Files Modified
List every existing file changed, one per line with a brief note:
- `path/to/file.php` — what was changed and why

### Tests Conducted
List every test run, the command used, and the result:
- `php artisan test --filter FooTest` — X passed, Y failed (describe any failures)

### Acceptance Criteria Check
Go through each acceptance criterion from the task and confirm whether it was met:
- [ ] Criterion one — met / not met (reason)
- [ ] Criterion two — met / not met (reason)

### Manual Verification Steps
Provide step-by-step instructions a human reviewer can follow to verify the work is correct:
1. Step one (e.g. run a specific command, visit a URL, check a UI element)
2. Step two
3. ...

### Notes
Any caveats, follow-up tasks, or things the reviewer should pay attention to.
MD;
    }

    private function generateDefaultPrompt(Task $task): string
    {
        $parts = [];

        $parts[] = "# Task: {$task->title}";
        $parts[] = '';

        if ($task->context) {
            $parts[] = '## Context';
            $parts[] = $task->context;
            $parts[] = '';
        }

        if ($task->instructions) {
            $parts[] = '## Instructions';
            $parts[] = $task->instructions;
            $parts[] = '';
        }

        if ($task->acceptance_criteria) {
            $parts[] = '## Acceptance Criteria';
            $parts[] = $task->acceptance_criteria;
            $parts[] = '';
        }

        if ($task->description) {
            $parts[] = '## Description';
            $parts[] = $task->description;
            $parts[] = '';
        }

        return implode("\n", $parts);
    }
}
