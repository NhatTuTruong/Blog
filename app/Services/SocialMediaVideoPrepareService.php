<?php

namespace App\Services;

use App\Support\PublicStorage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SocialMediaVideoPrepareService
{
    public ?string $lastError = null;

    public function isEnabled(): bool
    {
        return (bool) config('social_media_video.enabled', true);
    }

    /**
     * Chuẩn bị video Reels: cắt 1s, crop/blur 9:16, watermark, encode lại.
     * Trả về path storage-relative của file đã xử lý.
     */
    public function prepareForQueueItem(object $item, string $platformDirectory): string
    {
        $this->lastError = null;

        $sourcePath = $this->normalizeStoragePath($item->video_path ?? null);
        if ($sourcePath === null) {
            throw new \RuntimeException('Không có file video để xử lý.');
        }

        if ($this->isPreparedPath($sourcePath)) {
            return $sourcePath;
        }

        if (! $this->isEnabled()) {
            return $sourcePath;
        }

        if (! $this->ffmpegAvailable()) {
            $this->lastError = 'FFmpeg chưa cài trên server (sudo apt install -y ffmpeg).';

            throw new \RuntimeException($this->lastError);
        }

        PublicStorage::ensureDirectory($platformDirectory);
        $readyRelative = "{$platformDirectory}/item-{$item->id}-ready.mp4";
        $readyAbsolute = PublicStorage::absolutePath($readyRelative);
        $sourceAbsolute = PublicStorage::absolutePath($sourcePath);

        if ($this->prepareFile($sourceAbsolute, $readyAbsolute)) {
            $item->update(['video_path' => $readyRelative]);

            Log::info('SocialMediaVideoPrepareService prepared video', [
                'queue_item_id' => $item->id ?? null,
                'source' => $sourcePath,
                'output' => $readyRelative,
                'source_bytes' => is_file($sourceAbsolute) ? filesize($sourceAbsolute) : null,
                'output_bytes' => filesize($readyAbsolute),
            ]);

            if ($sourcePath !== $readyRelative && PublicStorage::exists($sourcePath)) {
                PublicStorage::delete($sourcePath);
            }

            return $readyRelative;
        }

        throw new \RuntimeException($this->lastError ?? 'Không xử lý được video.');
    }

    public function prepareFile(string $inputAbsolute, string $outputAbsolute): bool
    {
        $this->lastError = null;

        if (! is_file($inputAbsolute)) {
            $this->lastError = 'File video nguồn không tồn tại.';

            return false;
        }

        $dimensions = $this->probeDimensions($inputAbsolute);
        if ($dimensions === null) {
            return false;
        }

        [$width, $height] = $dimensions;
        $useCropPreset = $this->isNearVerticalNineSixteen($width, $height);

        $outputDir = dirname(str_replace('\\', '/', $outputAbsolute));
        if (! is_dir($outputDir) && ! @mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
            $this->lastError = 'Không tạo được thư mục output video.';

            return false;
        }

        if (is_file($outputAbsolute)) {
            @unlink($outputAbsolute);
        }

        $watermarkAbsolute = $this->resolveWatermarkAbsolutePath();
        $command = $this->buildFfmpegCommand(
            $inputAbsolute,
            $outputAbsolute,
            $useCropPreset,
            $watermarkAbsolute,
        );

        $process = new Process($command);
        $process->setTimeout(max(60, (int) config('social_media_video.timeout_seconds', 900)));

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            $this->lastError = 'FFmpeg lỗi: '.trim($process->getErrorOutput() ?: $e->getMessage());
            Log::warning('SocialMediaVideoPrepareService ffmpeg failed', [
                'command' => implode(' ', $command),
                'error' => $this->lastError,
            ]);

            if (is_file($outputAbsolute)) {
                @unlink($outputAbsolute);
            }

            return false;
        }

        if (! is_file($outputAbsolute) || filesize($outputAbsolute) < 10_000) {
            $this->lastError = 'FFmpeg không tạo được file output hợp lệ.';

            return false;
        }

        return true;
    }

    public function ffmpegAvailable(): bool
    {
        $binary = (string) config('social_media_video.ffmpeg_binary', 'ffmpeg');

        try {
            $process = new Process([$binary, '-version']);
            $process->setTimeout(15);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    protected function probeDimensions(string $inputAbsolute): ?array
    {
        $binary = (string) config('social_media_video.ffprobe_binary', 'ffprobe');

        $process = new Process([
            $binary,
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height',
            '-of', 'csv=s=x:p=0',
            $inputAbsolute,
        ]);
        $process->setTimeout(30);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            $this->lastError = 'Không đọc được metadata video: '.$e->getMessage();

            return null;
        }

        $output = trim($process->getOutput());
        if (! str_contains($output, 'x')) {
            $this->lastError = 'FFprobe không trả về kích thước video.';

            return null;
        }

        [$width, $height] = array_map('intval', explode('x', $output, 2));

        if ($width <= 0 || $height <= 0) {
            $this->lastError = 'Kích thước video không hợp lệ.';

            return null;
        }

        return [$width, $height];
    }

    protected function isNearVerticalNineSixteen(int $width, int $height): bool
    {
        $ratio = $width / $height;
        $min = (float) config('social_media_video.vertical_ratio_min', 0.52);
        $max = (float) config('social_media_video.vertical_ratio_max', 0.62);

        return $ratio >= $min && $ratio <= $max;
    }

    /**
     * @return array<int, string>
     */
    protected function buildFfmpegCommand(
        string $inputAbsolute,
        string $outputAbsolute,
        bool $useCropPreset,
        ?string $watermarkAbsolute,
    ): array {
        $ffmpeg = (string) config('social_media_video.ffmpeg_binary', 'ffmpeg');
        $skip = max(0, (int) config('social_media_video.skip_start_seconds', 1));
        $targetW = (int) config('social_media_video.target_width', 1080);
        $targetH = (int) config('social_media_video.target_height', 1920);
        $needsFilterComplex = ! $useCropPreset || $watermarkAbsolute !== null;

        $command = [
            $ffmpeg, '-y',
            '-ss', (string) $skip,
            '-i', $inputAbsolute,
        ];

        if ($watermarkAbsolute !== null) {
            $command[] = '-i';
            $command[] = $watermarkAbsolute;
        }

        if ($needsFilterComplex) {
            $command[] = '-filter_complex';
            $command[] = $this->buildFilterComplex($useCropPreset, $targetW, $targetH, $watermarkAbsolute);
            $command[] = '-map';
            $command[] = '[vout]';
        } else {
            $command[] = '-vf';
            $command[] = $this->cropPresetFilterSimple();
            $command[] = '-map';
            $command[] = '0:v:0';
        }

        return array_merge($command, [
            '-map', '0:a?',
            '-c:v', 'libx264',
            '-crf', (string) config('social_media_video.crf', 21),
            '-preset', (string) config('social_media_video.encode_preset', 'medium'),
            '-c:a', 'aac',
            '-b:a', (string) config('social_media_video.audio_bitrate', '128k'),
            '-movflags', '+faststart',
            $outputAbsolute,
        ]);
    }

    protected function buildFilterComplex(
        bool $useCropPreset,
        int $targetW,
        int $targetH,
        ?string $watermarkAbsolute,
    ): string {
        $base = $useCropPreset
            ? '[0:v]'.$this->cropPresetFilterSimple().'[base]'
            : $this->blurBackgroundFilter($targetW, $targetH);

        if ($watermarkAbsolute === null) {
            return str_replace('[base]', '[vout]', $base);
        }

        $watermarkWidth = max(48, (int) round($targetW * ((int) config('social_media_video.watermark_width_percent', 15) / 100)));
        $margin = (int) config('social_media_video.watermark_margin', 40);

        return $base.';[1:v]scale='.$watermarkWidth.':-1[wm];[base][wm]overlay=W-w-'.$margin.':H-h-'.$margin.'[vout]';
    }

    protected function cropPresetFilterSimple(): string
    {
        $scale = (string) config('social_media_video.crop_scale', '1134:2016');
        $crop = (string) config('social_media_video.crop_box', '1080:1920:27:48');

        return "scale={$scale},crop={$crop}";
    }

    protected function blurBackgroundFilter(int $width, int $height): string
    {
        return '[0:v]split=2[orig][blursrc];'
            ."[blursrc]scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},boxblur=20:10[bg];"
            ."[orig]scale={$width}:-2:force_original_aspect_ratio=decrease[fg];"
            .'[bg][fg]overlay=(W-w)/2:(H-h)/2[base]';
    }

    protected function resolveWatermarkAbsolutePath(): ?string
    {
        $configured = trim((string) config('social_media_video.watermark_path', ''));
        $candidates = array_filter([
            $configured !== '' ? $configured : null,
            public_path('images/social/watermark.png'),
            public_path('images/social/watermark.webp'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function isPreparedPath(string $path): bool
    {
        return str_contains(strtolower($path), '-ready.mp4');
    }

    protected function normalizeStoragePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        return PublicStorage::exists($path) ? $path : null;
    }
}
