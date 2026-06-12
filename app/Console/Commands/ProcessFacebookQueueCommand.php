<?php

namespace App\Console\Commands;

use App\Services\FacebookQueueService;
use Illuminate\Console\Command;

class ProcessFacebookQueueCommand extends Command
{
    protected $signature = 'facebook:process-queue';

    protected $description = 'Xử lý 1 bài Facebook đến hạn trong hàng đợi';

    public function handle(FacebookQueueService $service): int
    {
        $result = $service->processNextDue();

        if (! $result['processed']) {
            $this->line('Không có bài Facebook nào đến hạn trong hàng đợi.');

            return self::SUCCESS;
        }

        $item = $result['item'];

        if ($result['media_id']) {
            $this->info("Đã đăng Facebook post #{$result['media_id']} (queue #{$item?->id})");
        } else {
            $this->error("Thất bại queue #{$item?->id}: ".($service->lastError ?? $item?->error_message ?? 'unknown'));
            $this->warn('Bài này thất bại — hàng đợi vẫn tiếp tục với các bài còn lại.');
        }

        return self::SUCCESS;
    }
}
