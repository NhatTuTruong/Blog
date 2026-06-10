<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_blog_queue_items', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('coupon_codes');
        });
    }

    public function down(): void
    {
        Schema::table('auto_blog_queue_items', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
