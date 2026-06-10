<?php

namespace App\Console\Commands;

use App\Services\EmailRecurringService;
use Illuminate\Console\Command;

class ProcessRecurringEmailCommand extends Command
{
    protected $signature = 'email:process-recurring';

    protected $description = 'Gửi lại các email theo lịch lặp đã đến hạn';

    public function handle(EmailRecurringService $service): int
    {
        $processed = $service->processDueSchedules();

        if ($processed > 0) {
            $this->info("Đã xử lý {$processed} lịch gửi lại email.");
        }

        return self::SUCCESS;
    }
}
