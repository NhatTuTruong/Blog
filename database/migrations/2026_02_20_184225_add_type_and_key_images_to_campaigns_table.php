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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->enum('type', ['coupon', 'key'])->default('coupon')->after('status');
            $table->string('background_image')->nullable()->after('cover_image');
            $table->json('key_product_images')->nullable()->after('product_images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['type', 'background_image', 'key_product_images']);
        });
    }
};
