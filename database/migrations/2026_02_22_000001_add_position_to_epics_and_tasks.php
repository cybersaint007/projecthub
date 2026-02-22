<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('milestone_tag');
            $table->index(['project_id', 'position']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('epic_id');
            $table->index(['epic_id', 'position']);
        });

        $this->backfillPositions();
    }

    public function down(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'position']);
            $table->dropColumn('position');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['epic_id', 'position']);
            $table->dropColumn('position');
        });
    }

    private function backfillPositions(): void
    {
        DB::transaction(function () {
            $projectIds = DB::table('epics')->distinct()->pluck('project_id');
            foreach ($projectIds as $projectId) {
                $epics = DB::table('epics')
                    ->where('project_id', $projectId)
                    ->orderBy('id')
                    ->get();
                foreach ($epics as $index => $epic) {
                    DB::table('epics')
                        ->where('id', $epic->id)
                        ->update(['position' => ($index + 1) * 10]);
                }
            }

            $epicIds = DB::table('tasks')->distinct()->pluck('epic_id');
            foreach ($epicIds as $epicId) {
                $tasks = DB::table('tasks')
                    ->where('epic_id', $epicId)
                    ->orderBy('id')
                    ->get();
                foreach ($tasks as $index => $task) {
                    DB::table('tasks')
                        ->where('id', $task->id)
                        ->update(['position' => ($index + 1) * 10]);
                }
            }
        });
    }
};
