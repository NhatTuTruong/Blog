<?php

namespace App\Services;

use App\Support\BundledMediaBinary;
use App\Support\PublicStorage;
use App\Support\SocialMediaVideoDrawtext;
use App\Support\SocialMediaVideoProbe;
use App\Support\SocialMediaVideoTitleFont;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SocialMediaVideoPrepareService
{
    public ?string $lastError = null;

    protected bool $lastEncodeSkippedTitleOverlay = false;

    public function isEnabled(): bool
    {
        return (bool) config('social_media_video.enabled', true);
    }

    /**
     * Chuẩn bị video Reels: cắt 1s, crop/blur 9:16, tiêu đề dưới (drawtext), encode lại.
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

        if (! $this->encoderAvailable()) {
            $this->lastError = 'Media encoder chưa sẵn sàng. Trên máy local chạy: php artisan social:media-encoder-install --for-linux --force rồi upload thư mục bin/ lên hosting.';

            throw new \RuntimeException($this->lastError);
        }

        PublicStorage::ensureDirectory($platformDirectory);
        $readyRelative = "{$platformDirectory}/item-{$item->id}-ready.mp4";
        $readyAbsolute = PublicStorage::absolutePath($readyRelative);
        $sourceAbsolute = PublicStorage::absolutePath($sourcePath);

        $title = null;
        if ($this->bottomTitleOverlayEnabled()) {
            if (SocialMediaVideoTitleFont::resolvePath() === null) {
                Log::warning('SocialMediaVideoPrepareService title font missing — encoding without bottom overlay', [
                    'queue_item_id' => $item->id ?? null,
                ]);
            } else {
                $title = app(SocialMediaVideoTitleService::class)->resolveForQueueItem($item);
            }
        }

        if ($this->prepareFile($sourceAbsolute, $readyAbsolute, $title)) {
            $item->update(['video_path' => $readyRelative]);

            Log::info('SocialMediaVideoPrepareService prepared video', [
                'queue_item_id' => $item->id ?? null,
                'source' => $sourcePath,
                'output' => $readyRelative,
                'overlay_title' => $title,
                'overlay_skipped' => $this->lastEncodeSkippedTitleOverlay,
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

    public function prepareFile(string $inputAbsolute, string $outputAbsolute, ?string $overlayTitle = null): bool
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

        $this->lastEncodeSkippedTitleOverlay = false;
        SocialMediaVideoDrawtext::cleanupTempFiles();

        $titleForOverlay = null;
        if ($this->bottomTitleOverlayEnabled() && SocialMediaVideoTitleFont::resolvePath() !== null) {
            $titleForOverlay = filled($overlayTitle)
                ? $overlayTitle
                : app(SocialMediaVideoTitleService::class)->randomFallbackTitle();
        } elseif ($this->bottomTitleOverlayEnabled()) {
            Log::warning('SocialMediaVideoPrepareService title font missing — encoding without bottom overlay');
        }

        $watermarkAbsolute = $this->resolveWatermarkAbsolutePath();
        $musicAbsolute = $this->shouldReplaceAudioWithMusic()
            ? $this->resolveBackgroundMusicAbsolutePath()
            : null;

        if ($this->shouldReplaceAudioWithMusic() && $musicAbsolute === null) {
            Log::warning('SocialMediaVideoPrepareService background music missing — output will have no audio', [
                'directory' => config('social_media_video.background_music_directory'),
            ]);
        } elseif ($musicAbsolute !== null) {
            Log::info('SocialMediaVideoPrepareService background music selected', [
                'file' => basename($musicAbsolute),
            ]);
        }

        try {
            if ($this->encodeWithOptionalTitleFallback(
                $inputAbsolute,
                $outputAbsolute,
                $useCropPreset,
                $watermarkAbsolute,
                $titleForOverlay,
                $musicAbsolute,
            )) {
                return true;
            }
        } finally {
            SocialMediaVideoDrawtext::cleanupTempFiles();
        }

        return false;
    }

    protected function encodeWithOptionalTitleFallback(
        string $inputAbsolute,
        string $outputAbsolute,
        bool $useCropPreset,
        ?string $watermarkAbsolute,
        ?string $titleForOverlay,
        ?string $musicAbsolute,
    ): bool {
        if ($this->runMediaEncoderCommand(
            $this->buildMediaEncoderCommand(
                $inputAbsolute,
                $outputAbsolute,
                $useCropPreset,
                $watermarkAbsolute,
                $titleForOverlay,
                $musicAbsolute,
            ),
            $outputAbsolute,
        )) {
            return true;
        }

        if (! filled($titleForOverlay)) {
            return false;
        }

        if (! config('social_media_video.bottom_title_fallback_without_overlay', true)) {
            return false;
        }

        SocialMediaVideoDrawtext::cleanupTempFiles();
        $this->lastEncodeSkippedTitleOverlay = true;

        Log::warning('SocialMediaVideoPrepareService retrying encode without bottom title overlay', [
            'title' => $titleForOverlay,
            'previous_error' => $this->lastError,
        ]);

        if (is_file($outputAbsolute)) {
            @unlink($outputAbsolute);
        }

        return $this->runMediaEncoderCommand(
            $this->buildMediaEncoderCommand(
                $inputAbsolute,
                $outputAbsolute,
                $useCropPreset,
                $watermarkAbsolute,
                null,
                $musicAbsolute,
            ),
            $outputAbsolute,
        );
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function runMediaEncoderCommand(array $command, string $outputAbsolute): bool
    {
        $process = new Process($command);
        $process->setTimeout(max(60, (int) config('social_media_video.timeout_seconds', 900)));

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            $this->lastError = 'Media encoder lỗi: '.$this->summarizeEncoderError(trim($process->getErrorOutput() ?: $e->getMessage()));
            Log::warning('SocialMediaVideoPrepareService media encoder failed', [
                'command' => implode(' ', $command),
                'error' => $this->lastError,
            ]);

            if (is_file($outputAbsolute)) {
                @unlink($outputAbsolute);
            }

            return false;
        } finally {
            SocialMediaVideoDrawtext::cleanupTempFiles();
        }

        if (! is_file($outputAbsolute) || filesize($outputAbsolute) < 10_000) {
            $this->lastError = 'Media encoder không tạo được file output hợp lệ.';

            return false;
        }

        return true;
    }

    public function encoderAvailable(): bool
    {
        return app(BundledMediaBinary::class)->isEncoderAvailable();
    }

    /** @deprecated Use encoderAvailable() */
    public function ffmpegAvailable(): bool
    {
        return $this->encoderAvailable();
    }

    protected function mediaEncoderPath(): string
    {
        $path = app(BundledMediaBinary::class)->mediaEncoderPath();

        if ($path === null) {
            throw new \RuntimeException('Media encoder chưa sẵn sàng.');
        }

        return $path;
    }

    protected function videoProbe(): SocialMediaVideoProbe
    {
        return app(SocialMediaVideoProbe::class);
    }

    protected function bottomTitleOverlayEnabled(): bool
    {
        return (bool) config('social_media_video.bottom_title_overlay_enabled', true);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    protected function probeDimensions(string $inputAbsolute): ?array
    {
        $dimensions = $this->videoProbe()->dimensions($inputAbsolute);

        if ($dimensions === null) {
            $this->lastError = 'Không đọc được kích thước video (getID3).';

            return null;
        }

        return $dimensions;
    }

    protected function probeDuration(string $inputAbsolute): ?float
    {
        return $this->videoProbe()->duration($inputAbsolute);
    }

    protected function resolveInputDurationLimit(string $inputAbsolute, int $skipStartSeconds): ?float
    {
        $trimEnd = max(0, (int) config('social_media_video.trim_end_seconds', 3));
        if ($trimEnd <= 0) {
            return null;
        }

        $duration = $this->probeDuration($inputAbsolute);
        if ($duration === null) {
            return null;
        }

        $usable = $duration - $skipStartSeconds - $trimEnd;

        return max(1.0, $usable);
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
    protected function buildMediaEncoderCommand(
        string $inputAbsolute,
        string $outputAbsolute,
        bool $useCropPreset,
        ?string $watermarkAbsolute,
        ?string $overlayTitle,
        ?string $musicAbsolute,
    ): array {
        $encoder = $this->mediaEncoderPath();
        $skip = max(0, (int) config('social_media_video.skip_start_seconds', 1));
        $targetW = (int) config('social_media_video.target_width', 1080);
        $targetH = (int) config('social_media_video.target_height', 1920);
        $durationLimit = $this->resolveInputDurationLimit($inputAbsolute, $skip);

        $musicInput = null;
        $watermarkInput = null;
        $nextInputIndex = 1;

        $command = [
            $encoder, '-y',
            '-ss', (string) $skip,
        ];

        if ($durationLimit !== null) {
            $command[] = '-t';
            $command[] = sprintf('%.3f', $durationLimit);
        }

        $command[] = '-i';
        $command[] = $inputAbsolute;

        if ($musicAbsolute !== null) {
            $command[] = '-stream_loop';
            $command[] = '-1';
            $command[] = '-i';
            $command[] = $musicAbsolute;
            $musicInput = $nextInputIndex++;
        }

        if ($watermarkAbsolute !== null) {
            $command[] = '-i';
            $command[] = $watermarkAbsolute;
            $watermarkInput = $nextInputIndex++;
        }

        $command[] = '-filter_complex';
        $command[] = $this->buildFilterComplex(
            $useCropPreset,
            $targetW,
            $targetH,
            $musicInput,
            $watermarkInput,
            $overlayTitle,
        );
        $command[] = '-map';
        $command[] = '[vout]';

        if ($musicInput !== null) {
            $command[] = '-map';
            $command[] = '[aout]';
            $command[] = '-shortest';
        } else {
            $command[] = '-an';
        }

        return array_merge($command, [
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-crf', (string) config('social_media_video.crf', 21),
            '-preset', (string) config('social_media_video.encode_preset', 'fast'),
            '-threads', (string) max(1, (int) config('social_media_video.encode_threads', 2)),
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
        ?int $musicInput,
        ?int $watermarkInput,
        ?string $overlayTitle,
    ): string {
        if ($useCropPreset) {
            $parts = ['[0:v]'.$this->sourceVideoTransformFilter().$this->cropPresetFilterSimple().'[vid]'];
        } else {
            $parts = [
                '[0:v]'.$this->sourceVideoTransformFilter().'split=2[orig][blursrc];'
                ."[blursrc]scale={$targetW}:{$targetH}:force_original_aspect_ratio=increase,crop={$targetW}:{$targetH},boxblur=20:10[bg];"
                ."[orig]scale={$targetW}:-2:force_original_aspect_ratio=decrease[fg];"
                .'[bg][fg]overlay=(W-w)/2:(H-h)/2[vid]',
            ];
        }

        $current = 'vid';

        if ($watermarkInput !== null) {
            $watermarkWidth = max(48, (int) round($targetW * ((int) config('social_media_video.watermark_width_percent', 12) / 100)));
            if ($watermarkWidth % 2 === 1) {
                $watermarkWidth++;
            }
            $margin = (int) config('social_media_video.watermark_margin', 32);
            $parts[] = "[{$watermarkInput}:v]format=rgba,scale={$watermarkWidth}:-2[wm]";
            $parts[] = "[{$current}][wm]overlay=W-w-{$margin}:{$margin}:format=auto[vwm]";
            $current = 'vwm';
        }

        if (filled($overlayTitle)) {
            $titleFilters = SocialMediaVideoDrawtext::buildBottomTitleFilters($current, 'vpre', $targetW, $targetH, $overlayTitle);
            if ($titleFilters !== null) {
                [$titleFilterChain, $current] = $titleFilters;
                $parts[] = $titleFilterChain;
            } else {
                Log::warning('SocialMediaVideoPrepareService bottom title filters unavailable', [
                    'title' => $overlayTitle,
                ]);
            }
        }

        $parts[] = $this->videoEnhancementFilter($current);

        if ($musicInput !== null) {
            $volume = max(0.1, min(2.0, (float) config('social_media_video.background_music_volume', 0.85)));
            $parts[] = "[{$musicInput}:a]volume={$volume}[aout]";
        }

        return implode(';', $parts);
    }

    protected function videoEnhancementFilter(string $inputLabel): string
    {
        $speed = max(0.5, min(2.0, (float) config('social_media_video.playback_speed', 1.2)));
        $contrast = max(0.5, min(2.0, (float) config('social_media_video.contrast', 1.08)));
        $brightness = max(-0.5, min(0.5, (float) config('social_media_video.brightness', 0.02)));
        $saturation = max(0.0, min(3.0, (float) config('social_media_video.saturation', 1.06)));
        $gammaR = max(0.5, min(2.0, (float) config('social_media_video.gamma_r', 1.05)));
        $gammaG = max(0.5, min(2.0, (float) config('social_media_video.gamma_g', 1.01)));
        $gammaB = max(0.5, min(2.0, (float) config('social_media_video.gamma_b', 0.94)));
        $targetW = (int) config('social_media_video.target_width', 1080);
        $targetH = (int) config('social_media_video.target_height', 1920);

        return "[{$inputLabel}]eq=contrast={$contrast}:brightness={$brightness}:saturation={$saturation}:gamma_r={$gammaR}:gamma_g={$gammaG}:gamma_b={$gammaB},scale={$targetW}:{$targetH},format=yuv420p,setpts=PTS/{$speed}[vout]";
    }

    protected function shouldReplaceAudioWithMusic(): bool
    {
        return (bool) config('social_media_video.replace_audio_with_music', true);
    }

    protected function resolveBackgroundMusicAbsolutePath(): ?string
    {
        $directory = (string) config('social_media_video.background_music_directory', public_path('audio/social'));
        if (! is_dir($directory)) {
            return null;
        }

        /** @var array<int, string> $extensions */
        $extensions = config('social_media_video.background_music_extensions', ['mp3', 'm4a', 'wav']);
        $extensions = array_map('strtolower', $extensions);

        $files = [];
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, $extensions, true)) {
                $files[] = $path;
            }
        }

        if ($files === []) {
            return null;
        }

        return $files[array_rand($files)];
    }

    protected function sourceVideoTransformFilter(): string
    {
        if (! (bool) config('social_media_video.flip_horizontal', true)) {
            return '';
        }

        return 'hflip,';
    }

    protected function cropPresetFilterSimple(): string
    {
        $scale = (string) config('social_media_video.crop_scale', '1134:2016');
        $crop = (string) config('social_media_video.crop_box', '1080:1920:27:48');

        return "scale={$scale},crop={$crop},format=yuv420p";
    }

    protected function summarizeEncoderError(string $raw): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $raw) ?: [])));
        $important = array_values(array_filter($lines, function (string $line): bool {
            $lower = strtolower($line);

            return str_contains($lower, 'error')
                || str_contains($lower, 'invalid')
                || str_contains($lower, 'failed')
                || str_contains($lower, 'nothing was written');
        }));

        if ($important !== []) {
            return implode(' | ', array_slice($important, -4));
        }

        return strlen($raw) > 400 ? substr($raw, 0, 400).'...' : $raw;
    }

    protected function resolveWatermarkAbsolutePath(): ?string
    {
        if (! config('social_media_video.watermark_enabled', false)) {
            return null;
        }

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
