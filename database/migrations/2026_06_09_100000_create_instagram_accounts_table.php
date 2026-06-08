<?php

use App\Models\InstagramAccount;
use App\Support\AdminSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('access_token');
            $table->string('user_id', 64)->nullable();
            $table->string('username', 120)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('instagram_queue_items', function (Blueprint $table) {
            $table->foreignId('instagram_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('instagram_accounts')
                ->nullOnDelete();
        });

        $legacyToken = AdminSettings::getEncrypted('instagram_access_token');
        if (is_string($legacyToken) && trim($legacyToken) !== '') {
            InstagramAccount::query()->create([
                'name' => 'Tài khoản chính',
                'access_token' => $legacyToken,
                'user_id' => filled(AdminSettings::get('instagram_user_id'))
                    ? trim((string) AdminSettings::get('instagram_user_id'))
                    : null,
                'enabled' => true,
                'sort_order' => 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('instagram_queue_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instagram_account_id');
        });

        Schema::dropIfExists('instagram_accounts');
    }
};
