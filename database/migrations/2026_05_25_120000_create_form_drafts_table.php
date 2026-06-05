<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('draft_key', 120);
            $table->json('data');
            $table->timestamps();

            $table->unique(['user_id', 'draft_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_drafts');
    }
};
