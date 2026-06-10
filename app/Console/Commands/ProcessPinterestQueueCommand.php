<?php

namespace App\Console\Commands;

use App\Services\PinterestQueueService;
use Illuminate\Console\Command;

class ProcessPinterestQueueCommand extends Command
{
    protected $signature = 'pinterest:process-queue';

    protected $description = 'Xử lý một Pin Pinterest đến hạn trong hàng đợi';

    public function handle(PinterestQueueService $service): int
    {
        if (! \App\Support\PinterestSettings::isConfigured()) {
            return self::SUCCESS;
        }

        $result = $service->processNextDue();

        if (! $result['processed']) {
            return self::SUCCESS;
        }

        $item = $result['item'];
        if ($result['media_id']) {
            $this->info("Đã đăng Pinterest Pin #{$result['media_id']} (queue #{$item?->id})");
        } elseif ($item?->status === 'failed') {
            $this->error("Pinterest queue #{$item->id} thất bại: {$item->error_message}");
        }

        return self::SUCCESS;
    }
}
