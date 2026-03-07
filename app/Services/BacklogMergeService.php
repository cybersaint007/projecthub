<?php

namespace App\Services;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use Illuminate\Support\Facades\DB;

class BacklogMergeService
{
    /**
     * Preview what a merge would do without touching the database.
     */
    public function preview(Project $project, array $data): array
    {
        $project->load([
            'epics' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'epics.tasks' => fn ($q) => $q->orderBy('position')->orderBy('id'),
        ]);

        $epicsAdded = 0;
        $epicsUpdated = 0;
        $tasksAdded = 0;
        $tasksUpdated = 0;

        foreach (($data['epics'] ?? []) as $epicData) {
            $existingEpic = $this->findEpic($project, $epicData);

            if ($existingEpic) {
                $epicsUpdated++;
                $epicTasks = $existingEpic->tasks;
            } else {
                $epicsAdded++;
                $epicTasks = collect();
            }

            foreach (($epicData['tasks'] ?? []) as $taskData) {
                $existingTask = $this->findTask($epicTasks, $taskData);
                $existingTask ? $tasksUpdated++ : $tasksAdded++;
            }
        }

        return [
            'epics_added'   => $epicsAdded,
            'epics_updated' => $epicsUpdated,
            'tasks_added'   => $tasksAdded,
            'tasks_updated' => $tasksUpdated,
            'note'          => 'Existing tasks with logs, artifacts, or prompts are updated in-place — nothing is deleted.',
        ];
    }

    /**
     * Merge the JSON into the project. Never deletes anything.
     */
    public function merge(Project $project, array $data, ?int $userId = null): array
    {
        return DB::transaction(function () use ($project, $data, $userId) {
            $project->load([
                'epics' => fn ($q) => $q->orderBy('position')->orderBy('id'),
                'epics.tasks' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            ]);

            $epicsAdded = 0;
            $epicsUpdated = 0;
            $tasksAdded = 0;
            $tasksUpdated = 0;
            $promptsAdded = 0;

            foreach (($data['epics'] ?? []) as $epicIndex => $epicData) {
                $existingEpic = $this->findEpic($project, $epicData);

                if ($existingEpic) {
                    $existingEpic->update([
                        'title'        => $epicData['title'],
                        'description'  => $epicData['description'] ?? $existingEpic->description,
                        'milestone_tag' => $epicData['milestone_tag'] ?? $existingEpic->milestone_tag,
                        'position'     => $epicData['position'] ?? $existingEpic->position,
                    ]);
                    $epic = $existingEpic;
                    $epicsUpdated++;
                } else {
                    $epic = Epic::create([
                        'project_id'   => $project->id,
                        'title'        => $epicData['title'],
                        'description'  => $epicData['description'] ?? null,
                        'milestone_tag' => $epicData['milestone_tag'] ?? null,
                        'position'     => $epicData['position'] ?? (($epicIndex + 1) * 10),
                    ]);
                    $epicsAdded++;
                }

                // Reload tasks for this epic (needed after potential create)
                $epic->load('tasks');

                foreach (($epicData['tasks'] ?? []) as $taskIndex => $taskData) {
                    $existingTask = $this->findTask($epic->tasks, $taskData);

                    if ($existingTask) {
                        $this->updateTaskSafeFields($existingTask, $taskData);
                        $tasksUpdated++;
                        $task = $existingTask;
                    } else {
                        $task = $this->createTask($epic, $taskData, $taskIndex);
                        $tasksAdded++;
                    }

                    // Merge prompts: add only new ones (matched by agent_type+format_type+title)
                    if (!empty($taskData['prompts']) && is_array($taskData['prompts'])) {
                        $task->load('taskPrompts');
                        foreach ($taskData['prompts'] as $promptData) {
                            $exists = $task->taskPrompts->contains(function ($p) use ($promptData) {
                                return $p->agent_type === $promptData['agent_type']
                                    && $p->format_type === ($promptData['format_type'] ?? 'structured')
                                    && $p->title === ($promptData['title'] ?? null);
                            });

                            if (!$exists) {
                                TaskPrompt::create([
                                    'task_id'     => $task->id,
                                    'agent_type'  => $promptData['agent_type'],
                                    'format_type' => $promptData['format_type'] ?? 'structured',
                                    'title'       => $promptData['title'] ?? null,
                                    'version'     => $promptData['version'] ?? 1,
                                    'content'     => $promptData['content'],
                                    'created_by'  => $userId,
                                ]);
                                $promptsAdded++;
                            }
                        }
                    }
                }
            }

            return [
                'epics_added'   => $epicsAdded,
                'epics_updated' => $epicsUpdated,
                'tasks_added'   => $tasksAdded,
                'tasks_updated' => $tasksUpdated,
                'prompts_added' => $promptsAdded,
            ];
        });
    }

    /**
     * Find an existing epic by id (preferred) or title.
     */
    private function findEpic($project, array $epicData): ?Epic
    {
        if (!empty($epicData['id'])) {
            return $project->epics->firstWhere('id', $epicData['id']);
        }
        return $project->epics->firstWhere('title', $epicData['title']);
    }

    /**
     * Find an existing task by id (preferred) or title within the epic's task collection.
     */
    private function findTask($tasks, array $taskData): ?Task
    {
        if (!empty($taskData['id'])) {
            return $tasks->firstWhere('id', $taskData['id']);
        }
        return $tasks->firstWhere('title', $taskData['title']);
    }

    /**
     * Update only the safe editable fields — leaves logs, artifacts, prompts intact.
     */
    private function updateTaskSafeFields(Task $task, array $taskData): void
    {
        $safeFields = ['description', 'status', 'agent', 'priority', 'tags',
                       'context', 'instructions', 'acceptance_criteria', 'position'];

        $updates = [];
        foreach ($safeFields as $field) {
            if (array_key_exists($field, $taskData)) {
                $updates[$field] = $taskData[$field];
            }
        }

        if (!empty($updates)) {
            $task->update($updates);
        }
    }

    /**
     * Create a brand-new task under the given epic.
     */
    private function createTask(Epic $epic, array $taskData, int $index): Task
    {
        $attrs = [
            'epic_id'  => $epic->id,
            'title'    => $taskData['title'],
            'position' => $taskData['position'] ?? (($index + 1) * 10),
        ];

        foreach (Task::exportableFields() as $field) {
            if ($field === 'title' || $field === 'position') {
                continue;
            }
            if (array_key_exists($field, $taskData)) {
                $attrs[$field] = $taskData[$field];
            }
        }

        return Task::create($attrs);
    }
}
