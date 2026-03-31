<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverExpiredLeases extends Command
{
    protected $signature = 'agent:recover-leases';

    protected $description = 'Return tasks with expired agent leases back to Ready';

    public function handle(): int
    {
        $tasks = Task::where('leased_until', '<', now())
            ->whereNotNull('leased_by')
            ->get();

        $recovered = 0;

        foreach ($tasks as $task) {
            DB::transaction(function () use ($task) {
                $worker = $task->leased_by;

                if ($task->status === 'InProgress') {
                    $task->status = 'Ready';
                }

                $task->leased_by = null;
                $task->lease_token = null;
                $task->leased_until = null;
                $task->save();

                TaskLog::create([
                    'task_id'  => $task->id,
                    'user_id'  => null,
                    'log_type' => 'system',
                    'content'  => "Lease expired for worker {$worker}. Task returned to Ready.",
                ]);
            });

            $recovered++;
        }

        $this->info("Recovered {$recovered} tasks");

        return self::SUCCESS;
    }
}
