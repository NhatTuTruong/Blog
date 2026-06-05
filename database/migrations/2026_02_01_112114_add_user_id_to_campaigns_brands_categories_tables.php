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
        if (! Schema::hasTable('campaigns_brands_categories_tables')) {
            return;
        }

        Schema::table('campaigns_brands_categories_tables', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns_brands_categories_tables', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('campaigns_brands_categories_tables')) {
            return;
        }

        Schema::table('campaigns_brands_categories_tables', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns_brands_categories_tables', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
