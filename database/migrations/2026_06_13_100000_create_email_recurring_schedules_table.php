<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name');
            $table->json('recipients');
            $table->json('variable_values')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->json('extra_attachment_paths')->nullable();
            $table->unsignedSmallInteger('interval_hours')->default(24);
            $table->timestamp('next_send_at');
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('send_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['stopped_at', 'next_send_at']);
        });

        Schema::table('email_send_logs', function (Blueprint $table) {
            $table->foreignId('email_recurring_schedule_id')
                ->nullable()
                ->after('user_id')
                ->constrained('email_recurring_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_send_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_recurring_schedule_id');
        });

        Schema::dropIfExists('email_recurring_schedules');
    }
};
