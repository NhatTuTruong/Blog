<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_blog_queue_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('brand_domain');
            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name')->nullable();
            $table->text('content_idea')->nullable();
            $table->string('aff_link', 2048)->nullable();
            $table->json('coupon_codes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('blog_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_blog_queue_items');
    }
};
