<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id', 64)->unique();
            $table->string('worker_name');
            $table->string('worker_type', 32);
            $table->string('agent_family', 32)->nullable();
            $table->string('host')->nullable();
            $table->string('status', 20)->default('offline');
            $table->json('capabilities')->nullable();
            $table->unsignedTinyInteger('current_load')->default(0);
            $table->unsignedTinyInteger('max_concurrent_tasks')->default(1);
            $table->timestamp('last_heartbeat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
