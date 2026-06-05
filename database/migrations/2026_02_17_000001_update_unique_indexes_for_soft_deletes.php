<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Chuyển unique(slug) sang unique(slug, deleted_at) để cho phép tạo lại khi bản ghi cũ đã bị xóa mềm

        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'slug') && Schema::hasColumn('brands', 'deleted_at')) {
                try {
                    $table->dropUnique(['slug']);
                } catch (\Throwable $e) {
                    // Nếu tên index khác, bỏ qua để tránh migrate lỗi
                }

                $table->unique(['slug', 'deleted_at']);
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'slug') && Schema::hasColumn('categories', 'deleted_at')) {
                try {
                    $table->dropUnique(['slug']);
                } catch (\Throwable $e) {
                    //
                }

                $table->unique(['slug', 'deleted_at']);
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'slug') && Schema::hasColumn('campaigns', 'deleted_at')) {
                try {
                    $table->dropUnique(['slug']);
                } catch (\Throwable $e) {
                    //
                }

                $table->unique(['slug', 'deleted_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'slug') && Schema::hasColumn('brands', 'deleted_at')) {
                try {
                    $table->dropUnique(['slug', 'deleted_at']);
                } catch (\Throwable $e) {
                    //
                }

                $table->unique('slug');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'slug') && Schema::hasColumn('categories', 'deleted_at')) {
                try {
                    $table->dropUnique(['slug', 'deleted_at']);
                } catch (\Throwable $e) {
                    //
                }

                $table->unique('slug');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'slug') && Schema::hasColumn('campaigns', 'deleted_at')) {
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

