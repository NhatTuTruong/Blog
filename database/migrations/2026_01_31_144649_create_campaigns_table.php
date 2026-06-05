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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('status', ['draft', 'active', 'paused'])->default('draft');
            $table->string('country')->nullable();
            $table->string('language')->default('en');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('intro')->nullable();
            $table->json('benefits')->nullable();
            $table->string('cta_text')->default('Get Deal Now');
            $table->text('affiliate_url');
            $table->string('coupon_code')->nullable();
            $table->boolean('coupon_enabled')->default(false);
            $table->string('template')->default('default');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
