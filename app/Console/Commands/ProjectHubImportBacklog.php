<?php

namespace App\Console\Commands;

use App\Exceptions\BacklogImportException;
use App\Services\BacklogImportService;
use Illuminate\Console\Command;

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
    public function handle(BacklogImportService $service)
    {
        $filePath = $this->argument('file');
        $isDryRun = $this->option('dry-run');

        try {
            $result = $service->importFromFile($filePath, $isDryRun);

            if ($isDryRun) {
                $this->info('=== DRY RUN MODE ===');
                $this->newLine();
            } else {
                $this->info('Starting import...');
                $this->newLine();
            }

            // Display messages
            foreach ($result->messages as $message) {
                $this->line($message);
            }

            if ($isDryRun) {
                $this->newLine();
                $this->info('Dry-run completed. No changes were made to the database.');
            } else {
                $this->newLine();
                $this->info('=== Import Completed ===');
                $this->line("Project: " . ($result->projectCreated ? '1' : '0'));
                $this->line("Epics: {$result->epicsCreated}");
                $this->line("Tasks: {$result->tasksCreated}");
                $this->line("Time: {$result->elapsedTime}s");
            }

            return Command::SUCCESS;
        } catch (BacklogImportException $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Unexpected error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
