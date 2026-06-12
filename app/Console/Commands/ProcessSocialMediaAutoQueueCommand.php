<?php

namespace App\Console\Commands;

use App\Services\SocialMediaAutoQueueService;
use Illuminate\Console\Command;

class ProcessSocialMediaAutoQueueCommand extends Command
{
    protected $signature = 'social:auto-queue';

    protected $description = 'Thêm bài vào hàng đợi auto MXH từ danh sách đã lưu (tạm dừng khi hàng đợi thủ công đang chạy)';

    public function handle(SocialMediaAutoQueueService $service): int
    {
        $enqueued = $service->tickAll();

        if ($enqueued === 0) {
            $this->line('Không có bài auto MXH nào được thêm vào hàng đợi.');

            if (filled($service->lastError)) {
                $this->comment($service->lastError);
            }

            return self::SUCCESS;
        }

        $this->info("Đã thêm {$enqueued} bài vào hàng đợi auto MXH.");

        return self::SUCCESS;
    }
}
