<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('leased_by')->nullable()->after('assignee_id');
            $table->string('lease_token', 64)->nullable()->after('leased_by');
            $table->timestamp('leased_until')->nullable()->after('lease_token');
            $table->timestamp('claimed_at')->nullable()->after('leased_until');

            $table->index('lease_token');
            $table->index(['status', 'leased_until']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['lease_token']);
            $table->dropIndex(['status', 'leased_until']);
            $table->dropColumn(['leased_by', 'lease_token', 'leased_until', 'claimed_at']);
        });
    }
};
