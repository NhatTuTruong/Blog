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
            $table->string('logo')->nullable()->after('template');
            $table->string('cover_image')->nullable()->after('logo');
            $table->json('product_images')->nullable()->after('cover_image');
            $table->json('coupons')->nullable()->after('coupon_code'); // Replace single coupon with multiple
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            //
        });
    }
};
