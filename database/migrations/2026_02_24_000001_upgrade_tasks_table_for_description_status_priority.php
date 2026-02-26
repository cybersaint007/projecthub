<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure description exists (TEXT nullable) - original migration already has it
        if (! Schema::hasColumn('tasks', 'description')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('description')->nullable()->after('title');
            });
        }

        // Status default 'TODO' for new rows (raw to avoid doctrine/dbal)
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tasks MODIFY status VARCHAR(255) NOT NULL DEFAULT 'TODO'");
        } elseif (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite: leave status default as-is; Task model $attributes provide default for new instances
        } else {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('status')->default('TODO')->change();
            });
        }

        // Convert priority from string to integer: add new column, backfill, drop old, rename
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority_new')->default(3)->after('status');
        });

        $caseSql = Schema::getConnection()->getDriverName() === 'sqlite'
            ? "CASE priority WHEN 'low' THEN 1 WHEN 'high' THEN 5 ELSE 3 END"
            : "CASE WHEN priority = 'low' THEN 1 WHEN priority = 'high' THEN 5 ELSE 3 END";
        DB::table('tasks')->update(['priority_new' => DB::raw($caseSql)]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['priority']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('priority_new', 'priority');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE tasks MODIFY status VARCHAR(255) NOT NULL DEFAULT 'Backlog'");
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority_old')->default('medium')->after('status');
        });

        DB::table('tasks')->update([
            'priority_old' => DB::raw("CASE WHEN priority = 1 THEN 'low' WHEN priority = 5 THEN 'high' ELSE 'medium' END"),
        ]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority');
            $table->renameColumn('priority_old', 'priority');
        });
    }
};
