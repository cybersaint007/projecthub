<?php

namespace App\Services;

use App\Models\BacklogBackup;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use Illuminate\Support\Facades\DB;

class BacklogReplaceService
{
    public function __construct(
        private BacklogExportService $exportService,
    ) {}

    public function preview(Project $project, array $data): array
    {
        $project->load([
            'epics' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'epics.tasks' => fn ($q) => $q->orderBy('position')->orderBy('id'),
        ]);

        $existingEpicCount = $project->epics->count();
        $existingTaskCount = $project->epics->sum(fn ($e) => $e->tasks->count());

        $newEpics = $data['epics'] ?? [];
        $newEpicCount = count($newEpics);
        $newTaskCount = 0;
        foreach ($newEpics as $epic) {
            $newTaskCount += count($epic['tasks'] ?? []);
        }

        return [
            'current' => [
                'epics' => $existingEpicCount,
                'tasks' => $existingTaskCount,
            ],
            'incoming' => [
                'epics' => $newEpicCount,
                'tasks' => $newTaskCount,
            ],
            'changes' => [
                'epics_delta' => $newEpicCount - $existingEpicCount,
                'tasks_delta' => $newTaskCount - $existingTaskCount,
            ],
        ];
    }

    public function replace(Project $project, array $data, ?int $userId = null, bool $backup = true): array
    {
        return DB::transaction(function () use ($project, $data, $userId, $backup) {
            if ($backup) {
                $snapshot = $this->exportService->export($project);
                BacklogBackup::create([
                    'project_id' => $project->id,
                    'created_by' => $userId,
                    'payload_json' => $snapshot,
                    'note' => 'Auto-backup before replace',
                ]);
            }

            // Soft-delete all existing tasks (preserves history/logs/artifacts)
            $project->load([
                'epics' => fn ($q) => $q->orderBy('position')->orderBy('id'),
                'epics.tasks',
            ]);

            foreach ($project->epics as $epic) {
                foreach ($epic->tasks as $task) {
                    $task->delete(); // soft delete
                }
                $epic->delete(); // soft delete
            }

            // Create new structure from JSON
            $epicsCreated = 0;
            $tasksCreated = 0;
            $promptsCreated = 0;

            foreach (($data['epics'] ?? []) as $epicIndex => $epicData) {
                $epic = Epic::create([
                    'project_id' => $project->id,
                    'title' => $epicData['title'],
                    'description' => $epicData['description'] ?? null,
                    'milestone_tag' => $epicData['milestone_tag'] ?? null,
                    'position' => $epicData['position'] ?? (($epicIndex + 1) * 10),
                ]);
                $epicsCreated++;

                foreach (($epicData['tasks'] ?? []) as $taskIndex => $taskData) {
                    $taskAttrs = [
                        'epic_id' => $epic->id,
                        'title' => $taskData['title'],
                        'position' => $taskData['position'] ?? (($taskIndex + 1) * 10),
                    ];

                    foreach (Task::exportableFields() as $field) {
                        if ($field === 'position' || $field === 'title') {
                            continue;
                        }
                        if (array_key_exists($field, $taskData)) {
                            $taskAttrs[$field] = $taskData[$field];
                        }
                    }

                    $task = Task::create($taskAttrs);
                    $tasksCreated++;

                    // Create prompts if provided
                    if (!empty($taskData['prompts']) && is_array($taskData['prompts'])) {
                        foreach ($taskData['prompts'] as $promptData) {
                            TaskPrompt::create([
                                'task_id' => $task->id,
                                'agent_type' => $promptData['agent_type'],
                                'format_type' => $promptData['format_type'] ?? 'structured',
                                'title' => $promptData['title'] ?? null,
                                'version' => $promptData['version'] ?? 1,
                                'content' => $promptData['content'],
                                'created_by' => $userId,
                            ]);
                            $promptsCreated++;
                        }
                    }
                }
            }

            return [
                'epics_created' => $epicsCreated,
                'tasks_created' => $tasksCreated,
                'prompts_created' => $promptsCreated,
                'backup_created' => $backup,
            ];
        });
    }
}
