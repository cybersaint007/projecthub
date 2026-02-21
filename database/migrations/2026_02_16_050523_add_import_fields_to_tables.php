<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add code and owner_id to projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
            $table->foreignId('owner_id')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });

        // Add owner_id to epics table
        Schema::table('epics', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
        });

        // Add assignee_id to tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('epic_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->dropColumn('assignee_id');
        });

        Schema::table('epics', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['code', 'owner_id']);
        });
    }
};
