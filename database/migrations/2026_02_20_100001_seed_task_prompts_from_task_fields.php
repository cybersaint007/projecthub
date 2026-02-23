<?php

use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seed task_prompts from existing task context/instructions/acceptance_criteria
     * so that the new AI Prompts block shows the same content as the legacy generator.
     */
    public function up(): void
    {
        $tasks = Task::with('epic')->get();

        foreach ($tasks as $task) {
            $exists = DB::table('task_prompts')
                ->where('task_id', $task->id)
                ->whereIn('agent_type', ['claude_code', 'cursor_2'])
                ->exists();
            if ($exists) {
                continue;
            }

            $ctx = $task->context ?: 'N/A';
            $inst = $task->instructions ?: 'N/A';
            $ac = $task->acceptance_criteria ?: 'N/A';

            $claudeContent = "## Task: {$task->title}\n\n";
            $claudeContent .= "### Background / Context\n{$ctx}\n\n";
            $claudeContent .= "### Goal / Instructions\n{$inst}\n\n";
            $claudeContent .= "### Acceptance Criteria\n{$ac}\n\n";
            $claudeContent .= "### Constraints\n";
            $claudeContent .= "- Keep it MVP. Do not add extra features beyond what is specified.\n";
            $claudeContent .= "- Follow existing project conventions and patterns.\n";
            $claudeContent .= "- Avoid over-engineering or premature abstractions.\n\n";
            $claudeContent .= "### Deliverables\n";
            $claudeContent .= "- All modified/created files (controllers, models, views, migrations, routes)\n";
            $claudeContent .= "- Seed data if applicable\n";
            $claudeContent .= "- Update README if new setup steps are needed\n";
            $claudeContent .= "- Verify with: php artisan serve + manual testing";

            $cursorContent = "Task: {$task->title}\n\n";
            $cursorContent .= "Context: {$ctx}\n\n";
            $cursorContent .= "Instructions:\n{$inst}\n\n";
            $cursorContent .= "TODO Checklist:\n";
            if ($task->acceptance_criteria) {
                foreach (explode("\n", $task->acceptance_criteria) as $line) {
                    $line = trim($line);
                    if ($line) {
                        $cursorContent .= "- [ ] {$line}\n";
                    }
                }
            } else {
                $cursorContent .= "- [ ] Implement the task as described\n";
                $cursorContent .= "- [ ] Verify it works\n";
            }
            $cursorContent .= "\nFiles to modify: Search the project for relevant files before making changes.\n";
            $cursorContent .= "Testing: php artisan serve, then manually verify the affected routes.\n";
            $cursorContent .= "Scope: Only make the changes described above. Do not expand scope or add extra features.";

            $hasCreatedBy = Schema::hasColumn('task_prompts', 'created_by');

            $row1 = [
                'task_id' => $task->id,
                'agent_type' => 'claude_code',
                'format_type' => 'structured',
                'title' => 'Claude Code (generated)',
                'content' => $claudeContent,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $row2 = [
                'task_id' => $task->id,
                'agent_type' => 'cursor_2',
                'format_type' => 'structured',
                'title' => 'Cursor 2 (generated)',
                'content' => $cursorContent,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasCreatedBy) {
                $row1['created_by'] = null;
                $row2['created_by'] = null;
            }

            DB::table('task_prompts')->insert([$row1, $row2]);
        }
    }

    public function down(): void
    {
        DB::table('task_prompts')->where('title', 'like', '%(generated)%')->delete();
    }
};
