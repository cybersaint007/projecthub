<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_reviews', function (Blueprint $table) {
            $table->boolean('route_exists')->default(false)->after('note');
            $table->boolean('ui_exists')->default(false)->after('route_exists');
            $table->boolean('service_exists')->default(false)->after('ui_exists');
            $table->boolean('test_exists')->default(false)->after('service_exists');
        });
    }

    public function down(): void
    {
        Schema::table('task_reviews', function (Blueprint $table) {
            $table->dropColumn(['route_exists', 'ui_exists', 'service_exists', 'test_exists']);
        });
    }
};
