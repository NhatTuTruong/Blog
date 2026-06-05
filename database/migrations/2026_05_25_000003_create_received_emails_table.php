<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imap_uid');
            $table->string('folder', 120)->default('INBOX');
            $table->string('message_id')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_seen')->default(false);
            $table->unsignedSmallInteger('attachments_count')->default(0);
            $table->timestamps();

            $table->unique(['folder', 'imap_uid']);
            $table->index('received_at');
            $table->index('is_seen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('received_emails');
    }
};
