<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('epic_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('Backlog')->index();
            $table->string('agent')->default('human')->index();
            $table->string('priority')->default('medium')->index();
            $table->json('tags')->nullable();
            $table->longText('context')->nullable();
            $table->longText('instructions')->nullable();
            $table->longText('acceptance_criteria')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
