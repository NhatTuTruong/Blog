<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pinterest_accounts', function (Blueprint $table) {
            $table->string('board_id', 64)->nullable()->change();
        });

        Schema::table('pinterest_queue_items', function (Blueprint $table) {
            $table->string('board_id', 64)->nullable()->after('pinterest_account_id');
            $table->string('board_name')->nullable()->after('board_id');
        });
    }

    public function down(): void
    {
        Schema::table('pinterest_queue_items', function (Blueprint $table) {
            $table->dropColumn(['board_id', 'board_name']);
        });

        Schema::table('pinterest_accounts', function (Blueprint $table) {
            $table->string('board_id', 64)->nullable(false)->change();
        });
    }
};
