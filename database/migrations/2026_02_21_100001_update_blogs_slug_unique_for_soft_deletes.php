<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            if (Schema::hasColumn('blogs', 'slug') && Schema::hasColumn('blogs', 'deleted_at')) {
                try {
                    $table->dropUnique(['slug']);
                } catch (\Throwable $e) {
                    // Nếu tên index khác, bỏ qua
                }

                $table->unique(['slug', 'deleted_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            if (Schema::hasColumn('blogs', 'slug') && Schema::hasColumn('blogs', 'deleted_at')) {
                try {
                    $table->dropUnique(['slug', 'deleted_at']);
                } catch (\Throwable $e) {
                    //
                }

                $table->unique('slug');
            }
        });
    }
};
