<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign key nếu tồn tại
        $this->dropForeignIfExists('imports', 'user_id');

        // 2. Change column -> nullable
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        // 3. Add lại foreign key
        Schema::table('imports', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 1. Drop foreign key nếu tồn tại
        $this->dropForeignIfExists('imports', 'user_id');

        // 2. Change column -> NOT NULL
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        // 3. Add lại foreign key cascade
        Schema::table('imports', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Drop foreign key nếu tồn tại (tránh lỗi 1091)
     */
    private function dropForeignIfExists(string $table, string $column): void
    {
        $exists = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        if (!empty($exists)) {
            Schema::table($table, function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });
        }
    }
};
