<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            if (! Schema::hasColumn('imports', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('rollback_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
