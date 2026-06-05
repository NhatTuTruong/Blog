<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('import_id')->nullable()->after('id');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedBigInteger('import_id')->nullable()->after('id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('import_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('import_id');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('import_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('import_id');
        });
    }
};
