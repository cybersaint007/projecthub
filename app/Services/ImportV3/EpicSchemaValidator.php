<?php

namespace App\Services\ImportV3;

class EpicSchemaValidator
{
    private array $errors = [];

    /**
     * Validate a decoded epic-level JSON payload.
     * Expected shape: { schema_version: "3.0", epic: { title, tasks?: [] } }
     */
    public function validate(array $data): bool
    {
        $this->errors = [];

        if (!array_key_exists('schema_version', $data)) {
            $this->errors[] = 'schema_version is required';
        } elseif ($data['schema_version'] !== '3.0') {
            $this->errors[] = 'schema_version must be "3.0", got "' . $data['schema_version'] . '"';
        }

        if (!array_key_exists('epic', $data)) {
            $this->errors[] = 'epic is required';
        } elseif (!is_array($data['epic'])) {
            $this->errors[] = 'epic must be an object';
        } else {
            $this->validateEpic($data['epic']);
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function validateEpic(array $epic): void
    {
        if (!array_key_exists('title', $epic) || !is_string($epic['title']) || trim($epic['title']) === '') {
            $this->errors[] = 'epic.title is required';
        }

        if (!array_key_exists('tasks', $epic)) {
            return;
        }

        if (!is_array($epic['tasks']) || !array_is_list($epic['tasks'])) {
            $this->errors[] = 'epic.tasks must be an array';
            return;
        }

        foreach ($epic['tasks'] as $i => $task) {
            if (!is_array($task)) {
                $this->errors[] = "epic.tasks[$i] must be an object";
                continue;
            }
            if (!array_key_exists('title', $task) || !is_string($task['title']) || trim($task['title']) === '') {
                $this->errors[] = "epic.tasks[$i].title is required";
            }
        }
    }
}
