<?php

namespace App\Services\ImportV3;

class ImportResult
{
    public function __construct(
        public readonly bool $projectCreated,
        public readonly bool $projectUpdated,
        public readonly int $epicsCreated,
        public readonly int $epicsUpdated,
        public readonly int $tasksCreated,
        public readonly int $tasksUpdated,
        public readonly int $promptsCreated,
        public readonly int $promptsUpdated,
        public readonly array $dependencyWarnings,
        public readonly float $elapsedSeconds,
    ) {}

    public function toArray(): array
    {
        return [
            'project'   => $this->projectCreated ? 'created' : ($this->projectUpdated ? 'updated' : 'unchanged'),
            'epics'     => ['created' => $this->epicsCreated, 'updated' => $this->epicsUpdated],
            'tasks'     => ['created' => $this->tasksCreated, 'updated' => $this->tasksUpdated],
            'prompts'   => ['created' => $this->promptsCreated, 'updated' => $this->promptsUpdated],
            'warnings'  => $this->dependencyWarnings,
            'elapsed_s' => $this->elapsedSeconds,
        ];
    }
}
