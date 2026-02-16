<?php

namespace App\Console\Commands;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProjectHubImportBacklog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projecthub:import {file} {--dry-run : Show preview without writing to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import project backlog from JSON file (minimal mode: create only)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $isDryRun = $this->option('dry-run');

        // Validate file exists
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        // Read and parse JSON
        $jsonContent = File::get($filePath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        // Validate JSON structure
        if (!isset($data['project']) || !isset($data['epics'])) {
            $this->error('Invalid JSON structure: missing "project" or "epics"');
            return Command::FAILURE;
        }

        $startTime = microtime(true);

        try {
            // Validate all emails first (even in dry-run)
            $this->info('Validating users...');
            $validationErrors = $this->validateUsers($data);
            if (!empty($validationErrors)) {
                foreach ($validationErrors as $error) {
                    $this->error($error);
                }
                return Command::FAILURE;
            }

            if ($isDryRun) {
                return $this->handleDryRun($data);
            }

            return $this->handleImport($data, $startTime);
        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
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
            foreach ($data['epics'] as $epicIndex => $epic) {
                if (isset($epic['owner_email'])) {
                    $emails[] = $epic['owner_email'];
                }

                // Collect task assignee emails
                if (isset($epic['tasks']) && is_array($epic['tasks'])) {
                    foreach ($epic['tasks'] as $taskIndex => $task) {
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
    private function handleDryRun(array $data): int
    {
        $this->info('=== DRY RUN MODE ===');
        $this->newLine();

        $projectData = $data['project'];
        $epicsData = $data['epics'] ?? [];

        // Count tasks
        $taskCount = 0;
        foreach ($epicsData as $epic) {
            $taskCount += count($epic['tasks'] ?? []);
        }

        $this->info("Will create:");
        $this->line("  - 1 project: {$projectData['code']} - {$projectData['name']}");
        $this->line("  - " . count($epicsData) . " epic(s)");
        $this->line("  - {$taskCount} task(s)");
        $this->newLine();

        // Preview project
        $this->info("Project preview:");
        $this->line("  Code: {$projectData['code']}");
        $this->line("  Name: {$projectData['name']}");
        if (isset($projectData['description'])) {
            $this->line("  Description: " . substr($projectData['description'], 0, 50) . '...');
        }
        if (isset($projectData['owner_email'])) {
            $this->line("  Owner: {$projectData['owner_email']}");
        }
        $this->newLine();

        // Preview epics
        if (!empty($epicsData)) {
            $this->info("Epics preview:");
            foreach ($epicsData as $index => $epic) {
                $this->line("  " . ($index + 1) . ". {$epic['title']}");
                if (isset($epic['owner_email'])) {
                    $this->line("     Owner: {$epic['owner_email']}");
                }
                if (isset($epic['tasks']) && is_array($epic['tasks'])) {
                    $this->line("     Tasks: " . count($epic['tasks']));
                }
            }
        }

        $this->newLine();
        $this->info('Dry-run completed. No changes were made to the database.');

        return Command::SUCCESS;
    }

    /**
     * Handle actual import
     */
    private function handleImport(array $data, float $startTime): int
    {
        $this->info('Starting import...');
        $this->newLine();

        $projectData = $data['project'];
        $epicsData = $data['epics'] ?? [];

        return DB::transaction(function () use ($projectData, $epicsData, $startTime) {
            // Check project code uniqueness
            if (isset($projectData['code'])) {
                $existingProject = Project::where('code', $projectData['code'])->first();
                if ($existingProject) {
                    $this->error("Project with code '{$projectData['code']}' already exists. Import stopped.");
                    throw new \Exception("Duplicate project code: {$projectData['code']}");
                }
            }

            // Get owner user
            $ownerUser = null;
            if (isset($projectData['owner_email'])) {
                $ownerUser = User::where('email', $projectData['owner_email'])->first();
                if (!$ownerUser) {
                    $this->error("Owner user not found: {$projectData['owner_email']}");
                    throw new \Exception("User not found: {$projectData['owner_email']}");
                }
            }

            // Create project
            $project = Project::create([
                'code' => $projectData['code'] ?? null,
                'name' => $projectData['name'],
                'description' => $projectData['description'] ?? null,
                'owner_id' => $ownerUser?->id,
            ]);

            $this->info("✓ Created project: {$project->code} - {$project->name}");

            $epicCount = 0;
            $taskCount = 0;

            // Create epics and tasks
            foreach ($epicsData as $epicData) {
                // Get epic owner
                $epicOwner = null;
                if (isset($epicData['owner_email'])) {
                    $epicOwner = User::where('email', $epicData['owner_email'])->first();
                    if (!$epicOwner) {
                        $this->error("Epic owner not found: {$epicData['owner_email']}");
                        throw new \Exception("User not found: {$epicData['owner_email']}");
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
                $this->info("  ✓ Created epic: {$epic->title}");

                // Create tasks
                if (isset($epicData['tasks']) && is_array($epicData['tasks'])) {
                    foreach ($epicData['tasks'] as $taskData) {
                        // Get assignee
                        $assignee = null;
                        if (isset($taskData['assignee_email'])) {
                            $assignee = User::where('email', $taskData['assignee_email'])->first();
                            if (!$assignee) {
                                $this->error("Task assignee not found: {$taskData['assignee_email']}");
                                throw new \Exception("User not found: {$taskData['assignee_email']}");
                            }
                        }

                        // Create task with defaults
                        $task = Task::create([
                            'epic_id' => $epic->id,
                            'title' => $taskData['title'],
                            'description' => $taskData['description'] ?? null,
                            'status' => 'Backlog', // Default status
                            'priority' => 'medium', // Default priority
                            'agent' => 'human', // Default agent
                            'assignee_id' => $assignee?->id,
                        ]);

                        $taskCount++;
                    }
                }
            }

            $elapsedTime = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('=== Import Completed ===');
            $this->line("Project: 1");
            $this->line("Epics: {$epicCount}");
            $this->line("Tasks: {$taskCount}");
            $this->line("Time: {$elapsedTime}s");

            return Command::SUCCESS;
        });
    }
}
