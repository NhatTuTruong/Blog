<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->string('url_path', 255);
            $table->unsignedSmallInteger('status_code')->default(0)->index();
            $table->string('error', 255)->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();

            $table->unique('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_checks');
    }
};

