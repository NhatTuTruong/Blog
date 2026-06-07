<?php

namespace App\Console\Commands;

use App\Services\InstagramQueueService;
use Illuminate\Console\Command;

class ProcessInstagramQueueCommand extends Command
{
    protected $signature = 'instagram:process-queue';

    protected $description = 'Xử lý 1 bài Instagram đến hạn trong hàng đợi';

    public function handle(InstagramQueueService $service): int
    {
        $result = $service->processNextDue();

        if (! $result['processed']) {
            $this->line('Không có bài Instagram nào đến hạn trong hàng đợi.');

            return self::SUCCESS;
        }

        $item = $result['item'];

        if ($result['media_id']) {
            $this->info("Đã đăng Instagram media #{$result['media_id']} (queue #{$item?->id})");
        } else {
            $this->error("Thất bại queue #{$item?->id}: ".($service->lastError ?? $item?->error_message ?? 'unknown'));
            $this->warn('Hàng đợi đã dừng — các bài đang chờ đã bị hủy để tránh kẹt.');
        }

        return self::SUCCESS;
    }
}
