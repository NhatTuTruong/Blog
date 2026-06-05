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
        // Thêm deleted_at cho các bảng chính để hỗ trợ xóa mềm (soft delete)
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('clicks', function (Blueprint $table) {
            if (! Schema::hasColumn('clicks', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('clicks', function (Blueprint $table) {
            if (Schema::hasColumn('clicks', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};

