<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocked_ips', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId) {
            DB::table('blocked_ips')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        Schema::table('blocked_ips', function (Blueprint $table) {
            $table->dropUnique(['ip']);
        });

        Schema::table('blocked_ips', function (Blueprint $table) {
            $table->unique(['ip', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('blocked_ips', function (Blueprint $table) {
            $table->dropUnique(['ip', 'user_id']);
            $table->unique('ip');
            $table->dropForeign(['user_id']);
        });
    }
};
