<?php

namespace App\Console;

use App\Support\AdminSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $imapInterval = (int) config('imap.auto_sync_seconds', 120);

        if ($imapInterval > 0) {
            $schedule->command('imap:sync-inbox')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        }

        $schedule->command('blogs:process-queue')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('instagram:process-queue')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('facebook:process-queue')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('blogs:generate-daily --respect-daily-limit')
            ->hourly()
            ->withoutOverlapping()
            ->when(function (): bool {
                if (! (bool) AdminSettings::get('auto_blog_enabled', true)) {
                    return false;
                }

                $startHour = max(0, min(23, (int) AdminSettings::get('auto_blog_window_start_hour', 6)));
                $endHour = max(0, min(23, (int) AdminSettings::get('auto_blog_window_end_hour', 18)));
                $hour = now()->hour;

                if ($startHour <= $endHour) {
                    return $hour >= $startHour && $hour <= $endHour;
                }

                return $hour >= $startHour || $hour <= $endHour;
            });
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}
