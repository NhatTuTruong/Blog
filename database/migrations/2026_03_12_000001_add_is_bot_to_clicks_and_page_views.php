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
        Schema::table('clicks', function (Blueprint $table) {
            if (! Schema::hasColumn('clicks', 'is_bot')) {
                $table->boolean('is_bot')->default(false)->after('city')->index();
            }
        });

        Schema::table('page_views', function (Blueprint $table) {
            if (! Schema::hasColumn('page_views', 'is_bot')) {
                $table->boolean('is_bot')->default(false)->after('is_bounce')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clicks', function (Blueprint $table) {
            if (Schema::hasColumn('clicks', 'is_bot')) {
                $table->dropColumn('is_bot');
            }
        });

        Schema::table('page_views', function (Blueprint $table) {
            if (Schema::hasColumn('page_views', 'is_bot')) {
                $table->dropColumn('is_bot');
            }
        });
    }
};

