<?php

namespace App\Console\Commands;

use App\Services\MediaEncoderInstallService;
use App\Support\BundledMediaBinary;
use Illuminate\Console\Command;

class InstallSocialMediaEncoderCommand extends Command
{
    protected $signature = 'social:media-encoder-install
                            {--force : Ghi đè bin/ffmpeg hiện tại}
                            {--for-linux : Chuẩn bị binary Linux amd64 trên máy local để upload lên hosting}';

    protected $description = 'Chuẩn bị media encoder trong bin/ffmpeg (chạy trên local, upload lên hosting — không cần cài trên server)';

    public function handle(MediaEncoderInstallService $installer, BundledMediaBinary $binaries): int
    {
        $forLinux = (bool) $this->option('for-linux');

        $this->line('Local machine: '.php_uname('m').' ('.PHP_OS_FAMILY.')');
        $this->line('Target file: '.$installer->targetBinaryPath());

        if ($forLinux) {
            $this->line('Mode: prepare Linux amd64 binary for hosting upload');
            $this->line('URL: '.$installer->downloadUrlForArch('amd64'));
        } else {
            $arch = $installer->detectDownloadArch();
            if ($arch === null) {
                $this->error('Không nhận diện được kiến trúc CPU. Dùng --for-linux trên Windows.');

                return self::FAILURE;
            }

            $this->line('Mode: install for current machine ('.$arch.')');
            $this->line('URL: '.$installer->downloadUrlForArch($arch));
        }

        $this->warn('Đang tải media encoder (~50MB)...');

        if (! $installer->install((bool) $this->option('force'), $forLinux)) {
            $this->error($installer->lastError ?? 'Không chuẩn bị được media encoder.');

            return self::FAILURE;
        }

        $size = is_file($installer->targetBinaryPath()) ? filesize($installer->targetBinaryPath()) : 0;
        $this->info('Đã tạo: '.$installer->targetBinaryPath().' ('.number_format($size).' bytes)');

        if ($forLinux && PHP_OS_FAMILY === 'Windows') {
            $this->newLine();
            $this->line('Upload lên hosting cùng project: thư mục bin/ffmpeg');
            $this->line('Trên hosting KHÔNG cần chạy composer install script hay tải thêm gì.');

            return self::SUCCESS;
        }

        if ($binaries->isEncoderAvailable()) {
            $this->info('Media encoder OK: '.$binaries->mediaEncoderPath());
        }

        return self::SUCCESS;
    }
}
