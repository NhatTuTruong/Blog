<?php

namespace App\Console\Commands;

use App\Services\MediaEncoderInstallService;
use App\Support\BundledMediaBinary;
use Illuminate\Console\Command;

class InstallSocialMediaEncoderCommand extends Command
{
    protected $signature = 'social:media-encoder-install {--force : Tải lại ffmpeg kể cả khi đã có} {--if-missing : Chỉ cài khi encoder chưa chạy được}';

    protected $description = 'Tải ffmpeg static build đúng kiến trúc CPU của server vào bin/ffmpeg';

    public function handle(MediaEncoderInstallService $installer, BundledMediaBinary $binaries): int
    {
        $machine = php_uname('m');
        $arch = $installer->detectDownloadArch();

        $this->line('Machine: '.$machine);
        $this->line('Download arch: '.($arch ?? 'unknown'));

        if ($arch === null) {
            $this->error('Không nhận diện được kiến trúc CPU: '.$machine);

            return self::FAILURE;
        }

        if ($this->option('if-missing') && $binaries->isEncoderAvailable()) {
            $this->info('Media encoder đã sẵn sàng: '.$binaries->mediaEncoderPath());

            return self::SUCCESS;
        }

        $this->line('URL: '.$installer->downloadUrlForArch((string) $arch));
        $this->warn('Đang tải ffmpeg static build (~40MB), vui lòng đợi...');

        if (! $installer->install((bool) $this->option('force'))) {
            $this->error($installer->lastError ?? 'Không cài được media encoder.');

            return self::FAILURE;
        }

        $this->info('Media encoder: '.$binaries->mediaEncoderPath());

        return self::SUCCESS;
    }
}
