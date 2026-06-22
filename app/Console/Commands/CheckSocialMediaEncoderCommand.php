<?php

namespace App\Console\Commands;

use App\Support\BundledMediaBinary;
use Illuminate\Console\Command;

class CheckSocialMediaEncoderCommand extends Command
{
    protected $signature = 'social:media-encoder-check';

    protected $description = 'Kiểm tra media encoder (ffmpeg) đi kèm Composer có sẵn sàng trên server';

    public function handle(BundledMediaBinary $binaries): int
    {
        $this->line('PHP SAPI: '.PHP_SAPI);
        $this->line('OS: '.PHP_OS_FAMILY);
        $this->line('Base path: '.base_path());
        $this->line('proc_open: '.($this->procOpenAvailable() ? 'yes' : 'no'));
        $this->newLine();

        foreach ($binaries->probeAllCandidates() as $row) {
            $this->line(sprintf(
                '- %s | exists=%s | executable=%s | runnable=%s | size=%s',
                $row['path'],
                $row['exists'] ? 'yes' : 'no',
                $row['executable'] ? 'yes' : 'no',
                $row['runnable'] ? 'yes' : 'no',
                $row['size'] ?? 'n/a',
            ));

            if ($row['note'] !== '') {
                $this->line('  note: '.$row['note']);
            }
        }

        $this->newLine();
        $selected = $binaries->mediaEncoderPath();

        if ($selected === null) {
            $this->error('Media encoder: NOT READY');
            $this->line('Chạy lại: composer install --no-dev');
            $this->line('Sau đó: php scripts/sync-media-binaries.php');
            $this->line('Hoặc: chmod 755 bin/ffmpeg vendor/mathiasgrimm/laravel-cloud-binaries/bin/ffmpeg');

            return self::FAILURE;
        }

        $this->info('Media encoder: '.$selected);

        return self::SUCCESS;
    }

    protected function procOpenAvailable(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return ! in_array('proc_open', $disabled, true);
    }
}
