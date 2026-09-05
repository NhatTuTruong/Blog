<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['instagram_queue_items', 'facebook_queue_items', 'pinterest_queue_items'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'processing_started_at')) {
                    $blueprint->timestamp('processing_started_at')->nullable()->after('processed_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['instagram_queue_items', 'facebook_queue_items', 'pinterest_queue_items'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'processing_started_at')) {
                    $blueprint->dropColumn('processing_started_at');
                }
            });
        }
    }
};
