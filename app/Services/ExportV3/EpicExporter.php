<?php

namespace App\Services\ExportV3;

use App\Models\Epic;
use App\Models\Task;
use App\Models\TaskPrompt;

class EpicExporter
{
    public function export(Epic $epic): array
    {
        $epic->load([
            'tasks'             => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'tasks.taskPrompts' => fn ($q) => $q->orderBy('id'),
        ]);

        return [
            'schema_version' => '3.0',
            'epic'           => $this->exportEpic($epic),
            'meta'           => ['exported_at' => now()->toIso8601String()],
        ];
    }

    private function exportEpic(Epic $epic): array
    {
        $data = $this->strip([
            'id'            => $epic->id,
            'external_key'  => $epic->external_key,
            'title'         => $epic->title,
            'description'   => $epic->description,
            'milestone_tag' => $epic->milestone_tag,
            'status'        => $epic->status,
            'priority'      => $epic->priority,
            'position'      => $epic->position,
            'tags'          => $this->decodeJson($epic->getRawOriginal('tags')),
            'goals'         => $this->decodeJson($epic->getRawOriginal('goals')),
        ]);

        $tasks = $epic->tasks
            ->map(fn (Task $task) => $this->exportTask($task))
            ->values()
            ->toArray();

        if (!empty($tasks)) {
            $data['tasks'] = $tasks;
        }

        return $data;
    }

    private function exportTask(Task $task): array
    {
        $data = $this->strip([
            'id'                  => $task->id,
            'external_key'        => $task->external_key,
            'title'               => $task->title,
            'description'         => $task->description,
            'status'              => $task->status,
            'stage'               => $task->stage,
            'priority'            => $this->normalizeTaskPriority($task->priority),
            'position'            => $task->position,
            'estimate'            => $task->estimate_size,
            'execution_mode'      => $task->execution_mode,
            'assignee'            => $this->decodeJson($task->assignee_value),
            'assignee_type'       => $task->assignee_type,
            'tags'                => $task->tags,
            'context'             => $task->context,
            'instructions'        => $task->instructions,
            'acceptance_criteria' => $task->acceptance_criteria,
            'dependencies'        => $this->decodeJson($task->getRawOriginal('dependencies')),
            'blocking'            => $this->decodeJson($task->getRawOriginal('blocking')),
            'review'              => $this->decodeJson($task->getRawOriginal('review_metadata')),
            'artifacts'           => $this->decodeJson($task->getRawOriginal('artifact_refs')),
            'custom_fields'       => $this->decodeJson($task->getRawOriginal('custom_fields')),
        ]);

        $prompts = $task->taskPrompts
            ->map(fn (TaskPrompt $p) => $this->exportPrompt($p))
            ->values()
            ->toArray();

        if (!empty($prompts)) {
            $data['prompts'] = $prompts;
        }

        return $data;
    }

    private function exportPrompt(TaskPrompt $prompt): array
    {
        return $this->strip([
            'id'           => $prompt->id,
            'external_key' => $prompt->external_key,
            'agent_type'   => $prompt->agent_type,
            'format_type'  => $prompt->format_type,
            'title'        => $prompt->title,
            'version'      => $prompt->version,
            'purpose'      => $prompt->purpose,
            'content'      => $prompt->content,
        ]);
    }

    private function normalizeTaskPriority(?int $priority): ?int
    {
        if ($priority === null) return null;
        if ($priority <= Task::PRIORITY_LOW)    return Task::PRIORITY_LOW;
        if ($priority >= Task::PRIORITY_HIGH)   return Task::PRIORITY_HIGH;
        if ($priority === Task::PRIORITY_MEDIUM) return Task::PRIORITY_MEDIUM;
        return $priority < 3 ? Task::PRIORITY_LOW : Task::PRIORITY_HIGH;
    }

    private function decodeJson(mixed $value): mixed
    {
        if ($value === null) return null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }
        return $value;
    }

    private function strip(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null);
    }
}
