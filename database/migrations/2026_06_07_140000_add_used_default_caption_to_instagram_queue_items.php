<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_queue_items', function (Blueprint $table) {
            $table->boolean('used_default_caption')->default(false)->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_queue_items', function (Blueprint $table) {
            $table->dropColumn('used_default_caption');
        });
    }
};
