<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // projects
        Schema::table('projects', function (Blueprint $table) {
            $table->string('external_key')->nullable()->unique()->after('code');
            $table->string('status', 20)->nullable()->after('visibility');
            $table->json('tags')->nullable()->after('status');
        });

        // epics
        Schema::table('epics', function (Blueprint $table) {
            $table->string('external_key')->nullable()->unique()->after('id');
            $table->string('status', 20)->nullable()->after('milestone_tag');
            $table->unsignedTinyInteger('priority')->nullable()->after('status');
            $table->json('tags')->nullable()->after('priority');
            $table->json('goals')->nullable()->after('tags');
        });

        // tasks
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('external_key')->nullable()->unique()->after('id');
            $table->string('stage', 20)->nullable()->after('status');
            $table->string('execution_mode', 20)->nullable()->after('stage');
            $table->string('assignee_type', 20)->nullable()->after('assignee_id');
            $table->string('assignee_value')->nullable()->after('assignee_type');
            $table->json('dependencies')->nullable()->after('context');
            $table->json('blocking')->nullable()->after('dependencies');
            $table->json('artifacts')->nullable()->after('blocking');
            $table->json('review_metadata')->nullable()->after('artifacts');
            $table->json('custom_fields')->nullable()->after('review_metadata');
            $table->string('estimate_size', 8)->nullable()->after('custom_fields');
            $table->float('estimate_hours')->unsigned()->nullable()->after('estimate_size');

            $table->index('stage');
            $table->index('external_key');
        });

        // task_prompts
        Schema::table('task_prompts', function (Blueprint $table) {
            $table->string('external_key')->nullable()->unique()->after('id');
            $table->string('purpose', 32)->nullable()->after('agent_type');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['external_key']);
            $table->dropColumn(['external_key', 'status', 'tags']);
        });

        Schema::table('epics', function (Blueprint $table) {
            $table->dropUnique(['external_key']);
            $table->dropColumn(['external_key', 'status', 'priority', 'tags', 'goals']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['stage']);
            $table->dropIndex(['external_key']);
            $table->dropUnique(['external_key']);
            $table->dropColumn([
                'external_key', 'stage', 'execution_mode',
                'assignee_type', 'assignee_value',
                'dependencies', 'blocking', 'artifacts',
                'review_metadata', 'custom_fields',
                'estimate_size', 'estimate_hours',
            ]);
        });

        Schema::table('task_prompts', function (Blueprint $table) {
            $table->dropUnique(['external_key']);
            $table->dropColumn(['external_key', 'purpose']);
        });
    }
};
