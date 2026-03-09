<?php

namespace App\Services\ImportV3;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPrompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectImporter
{
    // Counters reset each import run
    private int $epicsCreated   = 0;
    private int $epicsUpdated   = 0;
    private int $tasksCreated   = 0;
    private int $tasksUpdated   = 0;
    private int $promptsCreated = 0;
    private int $promptsUpdated = 0;
    private array $depWarnings  = [];

    // external_key => task DB id, built during task pass
    private array $taskKeyMap = [];

    // external_key => [dependency keys], built during task pass for second-pass validation
    private array $taskDepsMap = [];

    /**
     * Import a validated v3 payload inside a DB transaction.
     */
    public function import(array $data): ImportResult
    {
        $this->reset();
        $start = microtime(true);

        [$project, $projectCreated, $projectUpdated] = DB::transaction(function () use ($data) {
            [$project, $created, $updated] = $this->upsertProject($data['project']);

            foreach ($data['epics'] as $epicData) {
                [$epic] = $this->upsertEpic($epicData, $project->id);

                foreach ($epicData['tasks'] ?? [] as $taskData) {
                    [$task] = $this->upsertTask($taskData, $epic->id);

                    if ($task->external_key) {
                        $this->taskKeyMap[$task->external_key] = $task->id;
                    }

                    foreach ($taskData['prompts'] ?? [] as $promptData) {
                        $this->upsertPrompt($promptData, $task->id);
                    }
                }
            }

            $this->resolveDependencies();

            return [$project, $created, $updated];
        });

        return new ImportResult(
            projectCreated:     $projectCreated,
            projectUpdated:     $projectUpdated,
            epicsCreated:       $this->epicsCreated,
            epicsUpdated:       $this->epicsUpdated,
            tasksCreated:       $this->tasksCreated,
            tasksUpdated:       $this->tasksUpdated,
            promptsCreated:     $this->promptsCreated,
            promptsUpdated:     $this->promptsUpdated,
            dependencyWarnings: $this->depWarnings,
            elapsedSeconds:     round(microtime(true) - $start, 4),
        );
    }

    // -------------------------------------------------------------------------
    // Upsert — Project
    // -------------------------------------------------------------------------

    /**
     * @return array{Project, bool, bool} [model, created, updated]
     */
    protected function upsertProject(array $d): array
    {
        $project = $this->findProject($d);
        $created = $project === null;
        $project ??= new Project();

        // Core fillable fields
        if (isset($d['name']))        $project->name        = $d['name'];
        if (isset($d['description'])) $project->description = $d['description'];
        if (isset($d['code']))        $project->code        = $d['code'];
        if (isset($d['visibility']))  $project->visibility  = $d['visibility'];

        // V3 fields (not in $fillable — direct assignment)
        if (isset($d['external_key'])) $project->external_key = $d['external_key'];
        if (isset($d['status']))       $project->status       = $d['status'];
        if (isset($d['tags']))         $project->tags         = $this->encodeJson($d['tags']);

        // Resolve owner
        if (isset($d['owner_email'])) {
            $user = $this->resolveUser($d['owner_email']);
            if ($user) {
                $project->owner_id = $user->id;
            } else {
                $this->depWarnings[] = "project: owner_email '{$d['owner_email']}' not found — owner_id not set";
            }
        }

        $project->save();

        $updated = !$created;
        return [$project, $created, $updated];
    }

    private function findProject(array $d): ?Project
    {
        if (!empty($d['id'])) {
            $p = Project::withTrashed()->find((int) $d['id']);
            if ($p) return $p;
        }
        if (!empty($d['external_key'])) {
            $p = Project::withTrashed()->where('external_key', $d['external_key'])->first();
            if ($p) return $p;
        }
        if (!empty($d['code'])) {
            $p = Project::withTrashed()->where('code', $d['code'])->first();
            if ($p) return $p;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Upsert — Epic
    // -------------------------------------------------------------------------

    /**
     * @return array{Epic, bool} [model, created]
     */
    protected function upsertEpic(array $d, int $projectId): array
    {
        $epic    = $this->findEpic($d, $projectId);
        $created = $epic === null;
        $epic  ??= new Epic();

        $epic->project_id = $projectId;

        // Core fillable fields
        if (isset($d['title']))        $epic->title        = $d['title'];
        if (isset($d['description']))  $epic->description  = $d['description'];
        if (isset($d['milestone_tag'])) $epic->milestone_tag = $d['milestone_tag'];
        if (isset($d['position']))     $epic->position     = (int) $d['position'];

        // V3 fields
        if (isset($d['external_key'])) $epic->external_key = $d['external_key'];
        if (isset($d['status']))       $epic->status       = $d['status'];
        if (isset($d['priority']))     $epic->priority     = (int) $d['priority'];
        if (isset($d['tags']))         $epic->tags         = $this->encodeJson($d['tags']);
        if (isset($d['goals']))        $epic->goals        = $this->encodeJson($d['goals']);

        if (isset($d['owner_email'])) {
            $user = $this->resolveUser($d['owner_email']);
            if ($user) {
                $epic->owner_id = $user->id;
            } else {
                $this->depWarnings[] = "epic '{$d['title']}': owner_email '{$d['owner_email']}' not found";
            }
        }

        $epic->save();

        $created ? $this->epicsCreated++ : $this->epicsUpdated++;
        return [$epic, $created];
    }

    private function findEpic(array $d, int $projectId): ?Epic
    {
        if (!empty($d['id'])) {
            $e = Epic::withTrashed()->where('id', (int) $d['id'])->where('project_id', $projectId)->first();
            if ($e) return $e;
        }
        if (!empty($d['external_key'])) {
            $e = Epic::withTrashed()->where('external_key', $d['external_key'])->first();
            if ($e) return $e;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Upsert — Task
    // -------------------------------------------------------------------------

    /**
     * @return array{Task, bool} [model, created]
     */
    protected function upsertTask(array $d, int $epicId): array
    {
        $task    = $this->findTask($d, $epicId);
        $created = $task === null;
        $task  ??= new Task();

        $task->epic_id = $epicId;

        // Core fillable fields
        if (isset($d['title']))               $task->title               = $d['title'];
        if (isset($d['description']))         $task->description         = $d['description'];
        if (isset($d['status']))              $task->status              = $d['status'];
        if (isset($d['position']))            $task->position            = (int) $d['position'];
        if (isset($d['context']))             $task->context             = $d['context'];
        if (isset($d['instructions']))        $task->instructions        = $d['instructions'];
        if (isset($d['acceptance_criteria'])) $task->acceptance_criteria = is_array($d['acceptance_criteria'])
            ? implode("\n", $d['acceptance_criteria'])
            : $d['acceptance_criteria'];
        if (isset($d['tags']))                $task->tags                = $d['tags']; // cast: array
        if (isset($d['priority']))            $task->priority            = $this->normalizePriority($d['priority']);

        // V3 fields
        if (isset($d['external_key']))   $task->external_key   = $d['external_key'];
        if (isset($d['stage']))          $task->stage          = $d['stage'];
        if (isset($d['execution_mode'])) $task->execution_mode = $d['execution_mode'];
        if (isset($d['estimate'])) {
            $estimate = $d['estimate'];
            if (is_array($estimate)) {
                $task->estimate_size  = $estimate['size'] ?? null;
                $task->estimate_hours = isset($estimate['hours']) ? (float) $estimate['hours'] : null;
            } else {
                $task->estimate_size = $estimate;
            }
        }
        if (isset($d['estimate_hours'])) $task->estimate_hours = (float) $d['estimate_hours'];
        if (isset($d['custom_fields']))  $task->custom_fields  = $this->encodeJson($d['custom_fields']);
        if (isset($d['artifacts']))      $task->artifact_refs  = $this->encodeJson($d['artifacts']);
        if (isset($d['review']))         $task->review_metadata = $this->encodeJson($d['review']);

        // Assignee — string or array (future multi-agent support).
        // Arrays are JSON-encoded into the assignee_value string column.
        if (isset($d['assignee'])) {
            $assignee     = $d['assignee'];
            $assigneeType = $d['assignee_type'] ?? null;
            $task->assignee_value = is_array($assignee) ? json_encode($assignee) : $assignee;
            $task->assignee_type  = $assigneeType;

            // Only attempt user resolution for a single string value
            if (is_string($assignee) && (!$assigneeType || $assigneeType === 'human')) {
                $user = $this->resolveUser($assignee);
                if ($user) {
                    $task->assignee_id   = $user->id;
                    $task->assignee_type = 'human';
                }
            }
        } elseif (isset($d['assignee_type'])) {
            $task->assignee_type = $d['assignee_type'];
        }

        // Dependencies/blocking stored as JSON; second pass validates refs
        if (isset($d['dependencies'])) {
            $task->dependencies = $this->encodeJson($d['dependencies']);
        }
        if (isset($d['blocking'])) {
            $task->blocking = $this->encodeJson($d['blocking']);
        }

        // Track for second-pass dependency validation
        if (!empty($d['external_key'])) {
            if (!empty($d['dependencies'])) {
                $this->taskDepsMap[$d['external_key']] = ['deps' => (array) $d['dependencies'], 'blocking' => []];
            }
            if (!empty($d['blocking'])) {
                $this->taskDepsMap[$d['external_key']]['blocking'] = (array) $d['blocking'];
            }
        }

        $task->save();

        $created ? $this->tasksCreated++ : $this->tasksUpdated++;
        return [$task, $created];
    }

    private function findTask(array $d, int $epicId): ?Task
    {
        if (!empty($d['id'])) {
            $t = Task::withTrashed()->where('id', (int) $d['id'])->where('epic_id', $epicId)->first();
            if ($t) return $t;
        }
        if (!empty($d['external_key'])) {
            $t = Task::withTrashed()->where('external_key', $d['external_key'])->first();
            if ($t) return $t;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Upsert — Prompt
    // -------------------------------------------------------------------------

    protected function upsertPrompt(array $d, int $taskId): array
    {
        $prompt  = $this->findPrompt($d, $taskId);
        $created = $prompt === null;
        $prompt ??= new TaskPrompt();

        $prompt->task_id = $taskId;

        if (isset($d['agent_type']))  $prompt->agent_type  = $d['agent_type'];
        if (isset($d['format_type'])) $prompt->format_type = $d['format_type'];
        if (isset($d['title']))       $prompt->title       = $d['title'];
        if (isset($d['content']))     $prompt->content     = $d['content'];
        if (isset($d['version']))     $prompt->version     = $this->normalizeVersion($d['version'], $taskId, $d['agent_type'] ?? $prompt->agent_type);
        if (isset($d['purpose']))     $prompt->purpose     = $d['purpose'];

        // V3 field not in $fillable
        if (isset($d['external_key'])) $prompt->external_key = $d['external_key'];

        // Default version to 1 for new prompts if not given
        if ($created && !isset($d['version'])) {
            $prompt->version = 1;
        }

        $prompt->save();

        $created ? $this->promptsCreated++ : $this->promptsUpdated++;
        return [$prompt, $created];
    }

    private function findPrompt(array $d, int $taskId): ?TaskPrompt
    {
        if (!empty($d['id'])) {
            $p = TaskPrompt::where('id', (int) $d['id'])->where('task_id', $taskId)->first();
            if ($p) return $p;
        }
        if (!empty($d['external_key'])) {
            $p = TaskPrompt::where('external_key', $d['external_key'])->first();
            if ($p) return $p;
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Dependency resolution (second pass)
    // -------------------------------------------------------------------------

    private function resolveDependencies(): void
    {
        foreach ($this->taskDepsMap as $ownerKey => $refs) {
            foreach ($refs['deps'] as $depKey) {
                if (!isset($this->taskKeyMap[$depKey])) {
                    $this->depWarnings[] = "task '{$ownerKey}': dependency external_key '{$depKey}' not found in import";
                }
            }
            foreach ($refs['blocking'] as $blockKey) {
                if (!isset($this->taskKeyMap[$blockKey])) {
                    $this->depWarnings[] = "task '{$ownerKey}': blocking external_key '{$blockKey}' not found in import";
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    private function normalizePriority(mixed $val): int
    {
        if (is_string($val)) {
            return match (strtolower(trim($val))) {
                'low'    => Task::PRIORITY_LOW,
                'high'   => Task::PRIORITY_HIGH,
                default  => Task::PRIORITY_MEDIUM,
            };
        }
        return (int) $val ?: Task::PRIORITY_MEDIUM;
    }

    /**
     * Convert a version value to a positive integer.
     * Strings like "v1" → 1; non-numeric strings get next available version.
     */
    private function normalizeVersion(mixed $val, int $taskId, string $agentType): int
    {
        if (is_int($val) && $val > 0) {
            return $val;
        }

        // Try extracting a leading integer from strings like "v1", "v2"
        if (is_string($val) && preg_match('/(\d+)/', $val, $m)) {
            $num = (int) $m[1];
            if ($num > 0) {
                // Check if this version is already taken for this task+agent
                $exists = TaskPrompt::where('task_id', $taskId)
                    ->where('agent_type', $agentType)
                    ->where('version', $num)
                    ->exists();
                if (!$exists) {
                    return $num;
                }
            }
        }

        // Fallback: next available version
        return TaskPrompt::nextVersionFor($taskId, $agentType);
    }

    /**
     * Encode to JSON string if value is array; pass through if already a string.
     */
    private function encodeJson(mixed $value): ?string
    {
        if ($value === null) return null;
        return is_array($value) ? json_encode($value) : $value;
    }

    private function reset(): void
    {
        $this->epicsCreated   = 0;
        $this->epicsUpdated   = 0;
        $this->tasksCreated   = 0;
        $this->tasksUpdated   = 0;
        $this->promptsCreated = 0;
        $this->promptsUpdated = 0;
        $this->depWarnings    = [];
        $this->taskKeyMap     = [];
        $this->taskDepsMap    = [];
    }
}
