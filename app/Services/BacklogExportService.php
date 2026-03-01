<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;

class BacklogExportService
{
    public function export(Project $project): array
    {
        $project->load([
            'epics' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'epics.tasks' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'epics.tasks.taskPrompts',
        ]);

        return [
            'version' => '2.0',
            'exported_at' => now()->toIso8601String(),
            'project' => [
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
            ],
            'epics' => $project->epics->map(fn ($epic) => $this->exportEpic($epic))->values()->toArray(),
        ];
    }

    private function exportEpic($epic): array
    {
        $epicFields = ['title', 'description', 'milestone_tag', 'position'];

        $data = [];
        foreach ($epicFields as $field) {
            $data[$field] = $epic->{$field};
        }

        $data['tasks'] = $epic->tasks->map(fn ($task) => $this->exportTask($task))->values()->toArray();

        return $data;
    }

    private function exportTask($task): array
    {
        $data = [];

        foreach (Task::exportableFields() as $field) {
            $data[$field] = $task->{$field};
        }

        if ($task->taskPrompts->isNotEmpty()) {
            $data['prompts'] = $task->taskPrompts->map(fn ($p) => [
                'agent_type' => $p->agent_type,
                'format_type' => $p->format_type,
                'title' => $p->title,
                'version' => $p->version,
                'content' => $p->content,
            ])->values()->toArray();
        }

        return $data;
    }
}
