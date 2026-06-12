<?php

use App\Models\User;
use App\Support\AdminSettings;
use App\Support\UserSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    protected array $integrationKeys = [
        'gemini_model',
        'gemini_timeout',
        'instagram_enabled',
        'instagram_graph_version',
        'instagram_queue_interval_minutes',
        'instagram_public_base_url',
        'instagram_default_image_url',
        'facebook_enabled',
        'facebook_graph_version',
        'facebook_queue_interval_minutes',
        'facebook_public_base_url',
        'pinterest_enabled',
        'pinterest_queue_interval_minutes',
        'pinterest_public_base_url',
        'pinterest_api_base_url',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_from_name',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_sync_limit',
        'imap_auto_sync_seconds',
        'imap_ui_poll_seconds',
        'imap_notifications_poll_seconds',
    ];

    /** @var array<int, string> */
    protected array $encryptedKeys = [
        'gemini_api_key',
        'gemini_api_key_2',
        'gemini_api_key_3',
        'gemini_api_key_auto_blog',
        'gemini_api_key_instagram',
        'gemini_api_key_facebook',
        'gemini_api_key_pinterest',
        'mail_password',
    ];

    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        foreach (['instagram_accounts', 'facebook_accounts', 'pinterest_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            });
        }

        Schema::table('received_emails', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $this->migrateLegacyData();
    }

    public function down(): void
    {
        Schema::table('received_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        foreach (['pinterest_accounts', 'facebook_accounts', 'instagram_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('owner_user_id');
            });
        }

        Schema::dropIfExists('user_settings');
    }

    protected function migrateLegacyData(): void
    {
        $adminId = User::query()->where('is_admin', true)->orderBy('id')->value('id');

        if ($adminId === null) {
            $adminId = User::query()->orderBy('id')->value('id');
        }

        if ($adminId === null) {
            return;
        }

        foreach ($this->integrationKeys as $key) {
            $value = AdminSettings::get($key);
            if ($value !== null && $value !== '') {
                UserSettings::set((int) $adminId, $key, $value);
            }
        }

        foreach ($this->encryptedKeys as $key) {
            $value = AdminSettings::getEncrypted($key);
            if (is_string($value) && $value !== '') {
                UserSettings::setEncrypted((int) $adminId, $key, $value);
            }
        }

        DB::table('instagram_accounts')->whereNull('owner_user_id')->update(['owner_user_id' => $adminId]);
        DB::table('facebook_accounts')->whereNull('owner_user_id')->update(['owner_user_id' => $adminId]);
        DB::table('pinterest_accounts')->whereNull('owner_user_id')->update(['owner_user_id' => $adminId]);
        DB::table('received_emails')->whereNull('user_id')->update(['user_id' => $adminId]);

        if (Schema::hasTable('received_emails')) {
            Schema::table('received_emails', function (Blueprint $table) {
                $table->dropUnique(['folder', 'imap_uid']);
            });
            Schema::table('received_emails', function (Blueprint $table) {
                $table->unique(['user_id', 'folder', 'imap_uid']);
            });
        }
    }
};
