<?php

namespace App\Services\ImportV3;

class SchemaValidator
{
    private array $errors = [];

    /**
     * Validate a decoded JSON payload against the ProjectHub v3 schema.
     *
     * @param  array  $data  Decoded JSON as a PHP array
     * @return bool   True if valid, false if errors were found
     */
    public function validate(array $data): bool
    {
        $this->errors = [];

        $this->validateTopLevel($data);

        // Stop early if top-level structure is broken — nested checks would be misleading
        if (!empty($this->errors)) {
            return false;
        }

        $this->validateProject($data['project']);
        $this->validateEpics($data['epics']);

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    // -------------------------------------------------------------------------
    // Top-level
    // -------------------------------------------------------------------------

    private function validateTopLevel(array $data): void
    {
        if (!array_key_exists('schema_version', $data)) {
            $this->errors[] = 'schema_version is required';
        } elseif ($data['schema_version'] !== '3.0') {
            $this->errors[] = 'schema_version must be "3.0", got "' . $data['schema_version'] . '"';
        }

        if (!array_key_exists('project', $data)) {
            $this->errors[] = 'project is required';
        } elseif (!is_array($data['project'])) {
            $this->errors[] = 'project must be an object';
        }

        if (!array_key_exists('epics', $data)) {
            $this->errors[] = 'epics is required';
        } elseif (!is_array($data['epics'])) {
            $this->errors[] = 'epics must be an array';
        } elseif (!array_is_list($data['epics'])) {
            $this->errors[] = 'epics must be an array, not an object';
        }
    }

    // -------------------------------------------------------------------------
    // Project
    // -------------------------------------------------------------------------

    private function validateProject(array $project): void
    {
        if (!array_key_exists('name', $project) || !is_string($project['name']) || trim($project['name']) === '') {
            $this->errors[] = 'project.name is required';
        }
    }

    // -------------------------------------------------------------------------
    // Epics
    // -------------------------------------------------------------------------

    private function validateEpics(array $epics): void
    {
        foreach ($epics as $i => $epic) {
            $path = "epics[$i]";

            if (!is_array($epic)) {
                $this->errors[] = "$path must be an object";
                continue;
            }

            if (!array_key_exists('title', $epic) || !is_string($epic['title']) || trim($epic['title']) === '') {
                $this->errors[] = "$path.title is required";
            }

            if (array_key_exists('tasks', $epic)) {
                if (!is_array($epic['tasks']) || !array_is_list($epic['tasks'])) {
                    $this->errors[] = "$path.tasks must be an array";
                } else {
                    $this->validateTasks($epic['tasks'], $path);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Tasks
    // -------------------------------------------------------------------------

    private function validateTasks(array $tasks, string $epicPath): void
    {
        foreach ($tasks as $i => $task) {
            $path = "$epicPath.tasks[$i]";

            if (!is_array($task)) {
                $this->errors[] = "$path must be an object";
                continue;
            }

            if (!array_key_exists('title', $task) || !is_string($task['title']) || trim($task['title']) === '') {
                $this->errors[] = "$path.title is required";
            }

            if (array_key_exists('prompts', $task)) {
                if (!is_array($task['prompts']) || !array_is_list($task['prompts'])) {
                    $this->errors[] = "$path.prompts must be an array";
                } else {
                    $this->validatePrompts($task['prompts'], $path);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Prompts
    // -------------------------------------------------------------------------

    private function validatePrompts(array $prompts, string $taskPath): void
    {
        foreach ($prompts as $i => $prompt) {
            $path = "$taskPath.prompts[$i]";

            if (!is_array($prompt)) {
                $this->errors[] = "$path must be an object";
                continue;
            }

            if (!array_key_exists('agent_type', $prompt) || !is_string($prompt['agent_type']) || trim($prompt['agent_type']) === '') {
                $this->errors[] = "$path.agent_type is required";
            }

            if (!array_key_exists('format_type', $prompt) || !is_string($prompt['format_type']) || trim($prompt['format_type']) === '') {
                $this->errors[] = "$path.format_type is required";
            }

            if (!array_key_exists('content', $prompt) || !is_string($prompt['content']) || trim($prompt['content']) === '') {
                $this->errors[] = "$path.content is required";
            }
        }
    }
}
