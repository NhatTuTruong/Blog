<?php

namespace App\Services;

use App\Support\BundledMediaBinary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

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

    public function install(bool $force = false): bool
    {
        $this->lastError = null;

        if (! $force && $this->isRunnable()) {
            return true;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->lastError = 'Trên Windows dev, dùng ffmpeg có sẵn trong PATH. Lệnh install chỉ dành cho Linux server.';

            return false;
        }

        $arch = $this->detectDownloadArch();
        if ($arch === null) {
            $this->lastError = 'Không nhận diện được kiến trúc CPU: '.php_uname('m');

            return false;
        }

        $url = $this->downloadUrlForArch($arch);
        $tmpRoot = storage_path('app/media-encoder-install');
        $workDir = $tmpRoot.'/'.uniqid('build_', true);

        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            $this->lastError = 'Không tạo được thư mục tạm.';

            return false;
        }

        $archivePath = $workDir.'/ffmpeg-static.tar.xz';

        try {
            if (! $this->downloadArchive($url, $archivePath)) {
                return false;
            }

            if (! $this->extractArchive($archivePath, $workDir)) {
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

            if (! $this->isRunnable()) {
                $this->lastError = 'Đã cài bin/ffmpeg nhưng không chạy được trên server (Exec format error hoặc host chặn exec).';

                return false;
            }

            Log::info('MediaEncoderInstallService installed ffmpeg', [
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

        return "https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-{$arch}-static.tar.xz";
    }

    protected function downloadArchive(string $url, string $archivePath): bool
    {
        try {
            $response = Http::timeout(900)
                ->withOptions(['sink' => $archivePath])
                ->get($url);

            if (! $response->successful() || ! is_file($archivePath) || filesize($archivePath) < 1_000_000) {
                $this->lastError = 'Không tải được ffmpeg static build.';

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = 'Lỗi tải ffmpeg: '.$e->getMessage();

            return false;
        }
    }

    protected function extractArchive(string $archivePath, string $workDir): bool
    {
        $process = new Process(['tar', '-xJf', $archivePath, '-C', $workDir]);
        $process->setTimeout(600);

        try {
            $process->mustRun();

            return true;
        } catch (\Throwable $e) {
            $this->lastError = 'Không giải nén được ffmpeg (cần lệnh tar trên server): '.trim($process->getErrorOutput() ?: $e->getMessage());

            return false;
        }
    }

    protected function findExtractedBinary(string $workDir): ?string
    {
        $patterns = [
            $workDir.'/ffmpeg-*-static/ffmpeg',
            $workDir.'/*/ffmpeg',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                if (is_file($path) && filesize($path) > 1_000_000) {
                    return $path;
                }
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
            $this->lastError = 'Không copy được ffmpeg vào bin/.';

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
