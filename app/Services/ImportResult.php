<?php

namespace App\Services;

/**
 * Import result data class
 */
class ImportResult
{
    public function __construct(
        public bool $projectCreated,
        public int $epicsCreated,
        public int $tasksCreated,
        public ?string $projectCode,
        public array $messages = [],
        public float $elapsedTime = 0
    ) {
    }
}
