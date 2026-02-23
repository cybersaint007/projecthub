<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_prompts') || Schema::hasColumn('task_prompts', 'created_by')) {
            return;
        }

        Schema::table('task_prompts', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('version')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('task_prompts') || ! Schema::hasColumn('task_prompts', 'created_by')) {
            return;
        }

        Schema::table('task_prompts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
    }
};
