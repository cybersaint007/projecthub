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

After completing the task, you MUST output a work log in exactly this format:

### Summary

#### What was already complete
Describe any work that was pre-existing before you started — files already created, logic already implemented, tests already passing. If nothing was pre-existing, write "N/A".

#### What was done
A concise narrative of what you implemented or changed. Cover:
- New files created (controllers, services, migrations, views, routes, etc.) and what each does
- Existing files modified and what changed and why
- Tests written or run, the commands used, and the results (e.g. `php artisan test --filter FooTest` — 12 passed)

### Manual Verification
Numbered steps a human reviewer can follow to confirm the work is correct:
1. Step one (e.g. run a specific command, visit a URL, check a UI element or API response)
2. Step two
3. ...

### Notes
Any caveats, known issues, follow-up tasks, or things the reviewer should pay attention to. If none, write "None."

### Completion Status
You MUST end your work log with exactly one of these lines:
- `COMPLETION: COMPLETE` — you finished all requirements and the task is ready for review
- `COMPLETION: INCOMPLETE` — you were unable to finish (blocked, ran out of context, partial work, etc.)

Only mark COMPLETE if you are confident the acceptance criteria are met and tests pass (if applicable).
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
