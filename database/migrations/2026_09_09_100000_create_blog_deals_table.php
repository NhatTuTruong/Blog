<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('coupon_code', 100)->nullable();
            $table->string('shop_url', 2048);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['blog_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_deals');
    }
};
