<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['instagram_queue_items', 'facebook_queue_items', 'pinterest_queue_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('queue_source', 16)->default('manual')->after('batch_id');
                $table->index(['queue_source', 'status', 'scheduled_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['instagram_queue_items', 'facebook_queue_items', 'pinterest_queue_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['queue_source', 'status', 'scheduled_at']);
                $table->dropColumn('queue_source');
            });
        }
    }
};
