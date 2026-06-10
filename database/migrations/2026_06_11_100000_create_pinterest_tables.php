<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinterest_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('access_token');
            $table->string('board_id', 64);
            $table->string('board_name')->nullable();
            $table->string('username')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pinterest_queue_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pinterest_account_id')->nullable()->constrained('pinterest_accounts')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('brand_domain')->nullable();
            $table->text('content_idea')->nullable();
            $table->string('aff_link', 2048)->nullable();
            $table->json('coupon_codes')->nullable();
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->text('caption')->nullable();
            $table->boolean('used_default_caption')->default(false);
            $table->string('pinterest_pin_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('batch_id');
        });

        Schema::create('pinterest_saved_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('records');
            $table->unsignedSmallInteger('record_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinterest_saved_lists');
        Schema::dropIfExists('pinterest_queue_items');
        Schema::dropIfExists('pinterest_accounts');
    }
};
