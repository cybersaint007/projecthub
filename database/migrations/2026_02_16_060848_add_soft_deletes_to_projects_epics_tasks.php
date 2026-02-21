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
        // Add soft deletes to projects
        Schema::table('projects', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to epics
        Schema::table('epics', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to tasks
        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('epics', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
