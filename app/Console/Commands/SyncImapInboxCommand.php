<?php

namespace App\Console\Commands;

use App\Models\UserSetting;
use App\Services\IncomingMailService;
use App\Support\MailSettings;
use Illuminate\Console\Command;

class SyncImapInboxCommand extends Command
{
    protected $signature = 'imap:sync-inbox';

    protected $description = 'Đồng bộ email nhận từ hộp thư IMAP (chạy nền)';

    public function handle(IncomingMailService $service): int
    {
        $interval = (int) config('imap.auto_sync_seconds', 120);

        if ($interval <= 0) {
            $this->comment('IMAP_AUTO_SYNC_SECONDS=0 — tự động đồng bộ nền đang tắt.');

            return self::SUCCESS;
        }

        $cacheKey = 'imap_inbox_sync_global';

        if (cache()->has($cacheKey)) {
            return self::SUCCESS;
        }

        if (! $service->imapExtensionAvailable()) {
            $this->warn('PHP extension imap chưa được bật.');

            return self::FAILURE;
        }

        $userIds = UserSetting::query()
            ->where('key', 'mail_username')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('user_id')
            ->unique()
            ->values();

        $synced = 0;

        foreach ($userIds as $userId) {
            if (! MailSettings::isConfigured((int) $userId)) {
                continue;
            }

            $result = $service->sync(userId: (int) $userId);

            if ($result['new'] > 0 || $result['updated'] > 0) {
                $synced++;
                $this->info("User #{$userId}: {$result['new']} mới, {$result['updated']} cập nhật ({$result['mode']}).");
            }
        }

        if ($userIds->isEmpty() && $service->isConfigured()) {
            $result = $service->sync();
            $synced = 1;
            $this->info("Đồng bộ xong: {$result['new']} email mới".($result['updated'] > 0 ? ", {$result['updated']} cập nhật" : '')." ({$result['mode']}).");
        }

        if ($synced === 0 && ! $service->isConfigured()) {
            $this->warn('Chưa có tài khoản email được cấu hình.');

            return self::SUCCESS;
        }

        cache()->put($cacheKey, true, $interval);

        return self::SUCCESS;
    }
}
