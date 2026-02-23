<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_prompts')) {
            return;
        }

        Schema::create('task_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('agent_type', 32)->index();
            $table->string('format_type', 32)->default('structured')->index();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'agent_type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_prompts');
    }
};
