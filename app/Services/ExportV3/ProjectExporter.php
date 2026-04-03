<?php

namespace App\Services\ExportV3;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use App\Models\User;

class ProjectExporter
{
    // Resolved user map: id → email, built once per export call
    private array $userMap = [];

    /**
     * Export a Project into canonical v3 format.
     */
    public function export(Project $project): array
    {
        $project->load([
            'owner',
            'epics'                  => fn ($q) => $q->withTrashed()->orderBy('position')->orderBy('id'),
            'epics.tasks'            => fn ($q) => $q->withTrashed()->orderBy('position')->orderBy('id'),
            'epics.tasks.taskPrompts' => fn ($q) => $q->orderBy('id'),
        ]);

        $this->buildUserMap($project);

        return [
            'schema_version' => '3.0',
            'project'        => $this->exportProject($project),
            'epics'          => $project->epics
                ->map(fn (Epic $epic) => $this->exportEpic($epic))
                ->values()
                ->toArray(),
            'meta' => [
                'exported_at' => now()->toIso8601String(),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Project
    // -------------------------------------------------------------------------

    private function exportProject(Project $project): array
    {
        return $this->strip([
            'id'           => $project->id,
            'external_key' => $project->external_key,
            'code'         => $project->code,
            'name'         => $project->name,
            'description'  => $project->description,
            'visibility'   => $project->visibility,
            'status'       => $project->status,
            'owner_email'  => $project->owner?->email,
            'tags'         => $this->decodeJson($project->getRawOriginal('tags')),
        ]);
    }

    // -------------------------------------------------------------------------
    // Epics
    // -------------------------------------------------------------------------

    private function exportEpic(Epic $epic): array
    {
        $data = $this->strip([
            'id'           => $epic->id,
            'external_key' => $epic->external_key,
            'title'        => $epic->title,
            'description'  => $epic->description,
            'milestone_tag' => $epic->milestone_tag,
            'status'       => $epic->status,
            'priority'     => $epic->priority,
            'position'     => $epic->position,
            'owner_email'  => $this->userMap[$epic->owner_id] ?? null,
            'tags'         => $this->decodeJson($epic->getRawOriginal('tags')),
            'goals'        => $this->decodeJson($epic->getRawOriginal('goals')),
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

    // -------------------------------------------------------------------------
    // Tasks
    // -------------------------------------------------------------------------

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
            'estimate'            => $task->estimate_size,       // DB: estimate_size → export: estimate
            'execution_mode'      => $task->execution_mode,
            'assignee'            => $this->decodeJson($task->assignee_value), // string or array
            'assignee_type'       => $task->assignee_type,
            'tags'                => $task->tags,                // already decoded (model cast)
            'context'             => $task->context,
            'instructions'        => $task->instructions,
            'acceptance_criteria' => $task->acceptance_criteria,
            'dependencies'        => $this->decodeJson($task->getRawOriginal('dependencies')),
            'blocking'            => $this->decodeJson($task->getRawOriginal('blocking')),
            'review'              => $this->decodeJson($task->getRawOriginal('review_metadata')), // DB: review_metadata → export: review
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

    // -------------------------------------------------------------------------
    // Prompts
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Pre-load all user emails referenced by epic owner_ids to avoid N+1 queries.
     */
    private function buildUserMap(Project $project): void
    {
        $ownerIds = $project->epics
            ->pluck('owner_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (!empty($ownerIds)) {
            $this->userMap = User::whereIn('id', $ownerIds)
                ->pluck('email', 'id')
                ->toArray();
        }
    }

    /**
     * Normalize task priority to valid constants (1/3/5) for clean export.
     * Any value ≤1 → 1 (low), ≥5 → 5 (high), anything else → 3 (medium).
     */
    private function normalizeTaskPriority(?int $priority): ?int
    {
        if ($priority === null) return null;
        if ($priority <= Task::PRIORITY_LOW)  return Task::PRIORITY_LOW;
        if ($priority >= Task::PRIORITY_HIGH) return Task::PRIORITY_HIGH;
        if ($priority === Task::PRIORITY_MEDIUM) return Task::PRIORITY_MEDIUM;
        // 2 → low, 4 → high (nearest valid constant)
        return $priority < 3 ? Task::PRIORITY_LOW : Task::PRIORITY_HIGH;
    }

    /**
     * Decode a raw DB value that may be a JSON string or already decoded.
     */
    private function decodeJson(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }
        return $value; // already array/object
    }

    /**
     * Remove null values so the export only includes fields that have data.
     */
    private function strip(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null);
    }
}
