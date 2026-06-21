<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['instagram_queue_items', 'facebook_queue_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('media_type', 16)->default('image')->after('video_path');
            });
        }
    }

    public function down(): void
    {
        foreach (['instagram_queue_items', 'facebook_queue_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('media_type');
            });
        }
    }
};
