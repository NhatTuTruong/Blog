<?php

namespace App\Console\Commands;

use App\Support\BundledMediaBinary;
use Illuminate\Console\Command;

class CheckSocialMediaEncoderCommand extends Command
{
    protected $signature = 'social:media-encoder-check';

    protected $description = 'Kiểm tra media encoder (bin/ffmpeg) trên server';

    public function handle(BundledMediaBinary $binaries): int
    {
        $this->line('Machine: '.php_uname('m'));
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
            $this->line('Chạy trên máy local (Windows): php artisan social:media-encoder-install --for-linux --force');
            $this->line('Sau đó upload bin/ffmpeg lên hosting.');

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
