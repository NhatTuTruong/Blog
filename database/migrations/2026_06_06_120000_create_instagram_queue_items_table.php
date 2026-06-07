<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_queue_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('brand_domain')->nullable();
            $table->text('content_idea')->nullable();
            $table->string('aff_link', 2048)->nullable();
            $table->json('coupon_codes')->nullable();
            $table->string('image_path');
            $table->text('caption')->nullable();
            $table->string('instagram_media_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_queue_items');
    }
};
