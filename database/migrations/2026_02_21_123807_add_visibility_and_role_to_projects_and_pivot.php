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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('visibility', 20)->default('private')->after('owner_id');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->string('role', 20)->default('viewer')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
