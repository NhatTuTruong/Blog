<?php

namespace App\Services;

use App\Support\BundledMediaBinary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class MediaEncoderInstallService
{
    public ?string $lastError = null;

    public function targetBinaryPath(): string
    {
        return base_path('bin/ffmpeg');
    }

    public function isRunnable(): bool
    {
        return app(BundledMediaBinary::class)->isEncoderAvailable();
    }

    public function install(bool $force = false, bool $forLinuxDeploy = false): bool
    {
        $this->lastError = null;

        if (! $force && ! $forLinuxDeploy && $this->isRunnable()) {
            return true;
        }

        $arch = $forLinuxDeploy ? 'amd64' : $this->detectDownloadArch();
        if ($arch === null) {
            $this->lastError = 'Không nhận diện được kiến trúc CPU: '.php_uname('m');

            return false;
        }

        $url = $this->downloadUrlForArch($arch);
        $workDir = storage_path('app/media-encoder-install/'.uniqid('build_', true));

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            $this->lastError = 'Không tạo được thư mục tạm.';

            return false;
        }

        $archivePath = $workDir.'/ffmpeg-static.zip';

        try {
            if (! $this->downloadArchive($url, $archivePath)) {
                return false;
            }

            if (! $this->extractZipArchive($archivePath, $workDir)) {
                return false;
            }

            $sourceBinary = $this->findExtractedBinary($workDir);
            if ($sourceBinary === null) {
                $this->lastError = 'Không tìm thấy file ffmpeg sau khi giải nén.';

                return false;
            }

            if (! $this->installBinary($sourceBinary)) {
                return false;
            }

            if ($forLinuxDeploy && PHP_OS_FAMILY === 'Windows') {
                Log::info('MediaEncoderInstallService prepared Linux ffmpeg for deploy upload', [
                    'target' => $this->targetBinaryPath(),
                    'size' => is_file($this->targetBinaryPath()) ? filesize($this->targetBinaryPath()) : null,
                ]);

                return is_file($this->targetBinaryPath());
            }

            if (! $this->isRunnable()) {
                $this->lastError = 'Đã đặt bin/ffmpeg nhưng không chạy được trên môi trường hiện tại.';

                return false;
            }

            Log::info('MediaEncoderInstallService installed media encoder', [
                'arch' => $arch,
                'url' => $url,
                'target' => $this->targetBinaryPath(),
                'machine' => php_uname('m'),
            ]);

            return true;
        } finally {
            $this->removeTree($workDir);
        }
    }

    public function detectDownloadArch(): ?string
    {
        $machine = strtolower(trim((string) php_uname('m')));

        return match (true) {
            in_array($machine, ['x86_64', 'amd64'], true) => 'amd64',
            in_array($machine, ['aarch64', 'arm64'], true) => 'arm64',
            in_array($machine, ['i686', 'i386', 'x86'], true) => 'i686',
            default => null,
        };
    }

    public function downloadUrlForArch(string $arch): string
    {
        $configured = trim((string) config("social_media_video.media_encoder_download_urls.{$arch}", ''));

        if ($configured !== '') {
            return $configured;
        }

        return match ($arch) {
            'arm64' => 'https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-linuxarm64-gpl.zip',
            'i686' => 'https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-linux32-gpl.zip',
            default => 'https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-linux64-gpl.zip',
        };
    }

    protected function downloadArchive(string $url, string $archivePath): bool
    {
        try {
            $response = Http::timeout(900)
                ->withOptions(['sink' => $archivePath])
                ->get($url);

            if (! $response->successful() || ! is_file($archivePath) || filesize($archivePath) < 1_000_000) {
                $this->lastError = 'Không tải được media encoder static build.';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = 'Lỗi tải media encoder: '.$e->getMessage();

            return false;
        }
    }

    protected function extractZipArchive(string $archivePath, string $workDir): bool
    {
        if (! class_exists(ZipArchive::class)) {
            $this->lastError = 'PHP thiếu ext-zip — bật extension zip trên local.';

            return false;
        }

        $zip = new ZipArchive();
        $opened = $zip->open($archivePath);

        if ($opened !== true) {
            $this->lastError = 'Không mở được file zip media encoder.';

            return false;
        }

        if (! $zip->extractTo($workDir)) {
            $zip->close();
            $this->lastError = 'Không giải nén được file zip media encoder.';

            return false;
        }

        $zip->close();

        return true;
    }

    protected function findExtractedBinary(string $workDir): ?string
    {
        $patterns = [
            $workDir.'/*/bin/ffmpeg',
            $workDir.'/*/*/bin/ffmpeg',
            $workDir.'/*/ffmpeg',
            $workDir.'/**/ffmpeg',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern, GLOB_BRACE) ?: [] as $path) {
                if (is_file($path) && filesize($path) > 1_000_000) {
                    return $path;
                }
            }
        }

        return $this->findExtractedBinaryRecursive($workDir);
    }

    protected function findExtractedBinaryRecursive(string $directory): ?string
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $nested = $this->findExtractedBinaryRecursive($path);
                if ($nested !== null) {
                    return $nested;
                }

                continue;
            }

            if (basename($path) === 'ffmpeg' && filesize($path) > 1_000_000) {
                return $path;
            }
        }

        return null;
    }

    protected function installBinary(string $sourceBinary): bool
    {
        $target = $this->targetBinaryPath();
        $targetDir = dirname($target);

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            $this->lastError = 'Không tạo được thư mục bin/.';

            return false;
        }

        if (is_file($target)) {
            @unlink($target);
        }

        if (! copy($sourceBinary, $target)) {
            $this->lastError = 'Không copy được media encoder vào bin/.';

            return false;
        }

        @chmod($target, 0755);

        return is_file($target);
    }

    protected function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path.DIRECTORY_SEPARATOR.$item;
            if (is_dir($full)) {
                $this->removeTree($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
