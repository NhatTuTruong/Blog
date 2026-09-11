<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_post_email_unlocks', function (Blueprint $table) {
            $table->string('country', 120)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('blog_post_email_unlocks', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
