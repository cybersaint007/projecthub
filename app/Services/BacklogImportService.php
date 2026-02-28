<?php

namespace App\Services;

use App\Exceptions\BacklogImportException;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BacklogImportService
{
    /**
     * Import from file path
     */
    public function importFromFile(string $path, bool $dryRun = false): ImportResult
    {
        if (!File::exists($path)) {
            throw new BacklogImportException("File not found: {$path}");
        }

        $jsonContent = File::get($path);
        return $this->importFromJsonString($jsonContent, $dryRun);
    }

    /**
     * Import from JSON string
     */
    public function importFromJsonString(string $json, bool $dryRun = false): ImportResult
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BacklogImportException('Invalid JSON: ' . json_last_error_msg());
        }

        // Validate JSON structure
        if (!isset($data['project']) || !isset($data['epics'])) {
            throw new BacklogImportException('Invalid JSON structure: missing "project" or "epics"');
        }

        // Validate all emails first (even in dry-run)
        $validationErrors = $this->validateUsers($data);
        if (!empty($validationErrors)) {
            throw new BacklogImportException(implode('; ', $validationErrors));
        }

        if ($dryRun) {
            return $this->handleDryRun($data);
        }

        return $this->handleImport($data);
    }

    /**
     * Validate all user emails in the JSON data
     */
    private function validateUsers(array $data): array
    {
        $errors = [];
        $emails = [];

        // Collect project owner email
        if (isset($data['project']['owner_email'])) {
            $emails[] = $data['project']['owner_email'];
        }

        // Collect epic owner emails
        if (isset($data['epics']) && is_array($data['epics'])) {
            foreach ($data['epics'] as $epic) {
                if (isset($epic['owner_email'])) {
                    $emails[] = $epic['owner_email'];
                }

                // Collect task assignee emails
                if (isset($epic['tasks']) && is_array($epic['tasks'])) {
                    foreach ($epic['tasks'] as $task) {
                        if (isset($task['assignee_email'])) {
                            $emails[] = $task['assignee_email'];
                        }
                    }
                }
            }
        }

        // Check all emails exist
        $uniqueEmails = array_unique($emails);
        $existingUsers = User::whereIn('email', $uniqueEmails)->pluck('email')->toArray();

        foreach ($uniqueEmails as $email) {
            if (!in_array($email, $existingUsers)) {
                $errors[] = "User not found: {$email}";
            }
        }

        return $errors;
    }

    /**
     * Handle dry-run mode
     */
    private function handleDryRun(array $data): ImportResult
    {
        $projectData = $data['project'];
        $epicsData = $data['epics'] ?? [];

        // Count tasks
        $taskCount = 0;
        foreach ($epicsData as $epic) {
            $taskCount += count($epic['tasks'] ?? []);
        }

        $messages = [
            "Will create:",
            "  - 1 project: {$projectData['code']} - {$projectData['name']}",
            "  - " . count($epicsData) . " epic(s)",
            "  - {$taskCount} task(s)",
        ];

        return new ImportResult(
            projectCreated: false,
            epicsCreated: count($epicsData),
            tasksCreated: $taskCount,
            projectCode: $projectData['code'] ?? null,
            messages: $messages,
            elapsedTime: 0
        );
    }

    /**
     * Handle actual import
     */
    private function handleImport(array $data): ImportResult
    {
        $startTime = microtime(true);
        $projectData = $data['project'];
        $epicsData = $data['epics'] ?? [];
        $messages = [];

        return DB::transaction(function () use ($projectData, $epicsData, $startTime, &$messages) {
            // Check project code uniqueness (including soft-deleted projects)
            if (isset($projectData['code'])) {
                $existingProject = Project::withTrashed()->where('code', $projectData['code'])->first();
                if ($existingProject) {
                    throw new BacklogImportException("Project with code '{$projectData['code']}' already exists. Import stopped.");
                }
            }

            // Get owner user
            $ownerUser = null;
            if (isset($projectData['owner_email'])) {
                $ownerUser = User::where('email', $projectData['owner_email'])->first();
                if (!$ownerUser) {
                    throw new BacklogImportException("Owner user not found: {$projectData['owner_email']}");
                }
            }

            // Create project
            $project = Project::create([
                'code' => $projectData['code'] ?? null,
                'name' => $projectData['name'],
                'description' => $projectData['description'] ?? null,
                'owner_id' => $ownerUser?->id,
            ]);

            $messages[] = "✓ Created project: {$project->code} - {$project->name}";

            $epicCount = 0;
            $taskCount = 0;

            // Create epics and tasks
            foreach ($epicsData as $epicData) {
                // Get epic owner
                $epicOwner = null;
                if (isset($epicData['owner_email'])) {
                    $epicOwner = User::where('email', $epicData['owner_email'])->first();
                    if (!$epicOwner) {
                        throw new BacklogImportException("Epic owner not found: {$epicData['owner_email']}");
                    }
                }

                // Create epic
                $epic = Epic::create([
                    'project_id' => $project->id,
                    'title' => $epicData['title'],
                    'description' => $epicData['description'] ?? null,
                    'owner_id' => $epicOwner?->id,
                ]);

                $epicCount++;
                $messages[] = "  ✓ Created epic: {$epic->title}";

                // Create tasks
                if (isset($epicData['tasks']) && is_array($epicData['tasks'])) {
                    foreach ($epicData['tasks'] as $taskData) {
                        // Get assignee
                        $assignee = null;
                        if (isset($taskData['assignee_email'])) {
                            $assignee = User::where('email', $taskData['assignee_email'])->first();
                            if (!$assignee) {
                                throw new BacklogImportException("Task assignee not found: {$taskData['assignee_email']}");
                            }
                        }

                        // Create task with defaults
                        Task::create([
                            'epic_id' => $epic->id,
                            'title' => $taskData['title'],
                            'description' => $taskData['description'] ?? null,
                            'status' => 'Backlog',
                            'priority' => Task::PRIORITY_MEDIUM,
                            'agent' => 'human',
                            'assignee_id' => $assignee?->id,
                        ]);

                        $taskCount++;
                    }
                }
            }

            $elapsedTime = round(microtime(true) - $startTime, 2);

            return new ImportResult(
                projectCreated: true,
                epicsCreated: $epicCount,
                tasksCreated: $taskCount,
                projectCode: $project->code,
                messages: $messages,
                elapsedTime: $elapsedTime
            );
        });
    }
}
