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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên store
            $table->string('slug')->unique();
            $table->string('category')->nullable(); // Danh mục
            $table->string('events')->nullable(); // Events
            $table->string('image')->nullable(); // Image path
            $table->boolean('approved')->default(false); // Duyệt bài
            $table->text('short_description')->nullable(); // Mô tả ngắn
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
