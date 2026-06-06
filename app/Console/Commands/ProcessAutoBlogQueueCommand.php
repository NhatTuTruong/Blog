<?php

namespace App\Console\Commands;

use App\Services\AutoBlogQueueService;
use Illuminate\Console\Command;

class ProcessAutoBlogQueueCommand extends Command
{
    protected $signature = 'blogs:process-queue';

    protected $description = 'Xử lý 1 bài viết đến hạn trong hàng đợi đăng tự động';

    public function handle(AutoBlogQueueService $service): int
    {
        $result = $service->processNextDue();

        if (! $result['processed']) {
            $this->line('Không có bài nào đến hạn trong hàng đợi.');

            return self::SUCCESS;
        }

        $item = $result['item'];

        if ($result['blog']) {
            $this->info("Đã tạo bài #{$result['blog']->id}: {$result['blog']->title} (queue #{$item?->id})");
        } else {
            $this->error("Thất bại queue #{$item?->id}: ".($service->lastError ?? $item?->error_message ?? 'unknown'));
            $this->warn('Hàng đợi đã dừng — các bài đang chờ đã bị hủy để tránh kẹt.');
        }

        return self::SUCCESS;
    }
}
