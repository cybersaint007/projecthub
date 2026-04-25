<?php

namespace App\Services\ImportV3;

class EpicSchemaValidator
{
    private array $errors = [];

    /**
     * Validate a decoded epic-level JSON payload.
     * Accepts a single epic:   { schema_version: "3.0", epic: { title, tasks?: [] } }
     * Or multiple epics:       { schema_version: "3.0", epics: [ { title, tasks?: [] }, ... ] }
     */
    public function validate(array $data): bool
    {
        $this->errors = [];

        if (!array_key_exists('schema_version', $data)) {
            $this->errors[] = 'schema_version is required';
        } elseif ($data['schema_version'] !== '3.0') {
            $this->errors[] = 'schema_version must be "3.0", got "' . $data['schema_version'] . '"';
        }

        $hasEpic  = array_key_exists('epic', $data);
        $hasEpics = array_key_exists('epics', $data);

        if (!$hasEpic && !$hasEpics) {
            $this->errors[] = 'epic or epics is required';
        } elseif ($hasEpic) {
            if (!is_array($data['epic'])) {
                $this->errors[] = 'epic must be an object';
            } else {
                $this->validateEpic($data['epic'], 'epic');
            }
        } else {
            if (!is_array($data['epics']) || !array_is_list($data['epics'])) {
                $this->errors[] = 'epics must be an array';
            } else {
                foreach ($data['epics'] as $i => $epic) {
                    if (!is_array($epic)) {
                        $this->errors[] = "epics[$i] must be an object";
                    } else {
                        $this->validateEpic($epic, "epics[$i]");
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function validateEpic(array $epic, string $path = 'epic'): void
    {
        if (!array_key_exists('title', $epic) || !is_string($epic['title']) || trim($epic['title']) === '') {
            $this->errors[] = "$path.title is required";
        }

        if (!array_key_exists('tasks', $epic)) {
            return;
        }

        if (!is_array($epic['tasks']) || !array_is_list($epic['tasks'])) {
            $this->errors[] = "$path.tasks must be an array";
            return;
        }

        foreach ($epic['tasks'] as $i => $task) {
            if (!is_array($task)) {
                $this->errors[] = "$path.tasks[$i] must be an object";
                continue;
            }
            if (!array_key_exists('title', $task) || !is_string($task['title']) || trim($task['title']) === '') {
                $this->errors[] = "$path.tasks[$i].title is required";
            }
        }
    }
}
