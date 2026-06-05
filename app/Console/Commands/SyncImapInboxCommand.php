<?php

namespace App\Console\Commands;

use App\Services\IncomingMailService;
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

        if (! $service->isConfigured()) {
            $this->warn('Chưa cấu hình IMAP trong .env.');

            return self::FAILURE;
        }

        $result = $service->sync();

        cache()->put($cacheKey, true, $interval);

        $this->info("Đồng bộ xong: {$result['new']} email mới".($result['updated'] > 0 ? ", {$result['updated']} cập nhật" : '')." ({$result['mode']}).");

        if ($result['errors'] !== []) {
            $this->warn(implode("\n", array_slice($result['errors'], 0, 3)));
        }

        return self::SUCCESS;
    }
}
