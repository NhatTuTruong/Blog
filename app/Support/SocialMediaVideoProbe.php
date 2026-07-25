<?php

namespace App\Support;

class SocialMediaVideoProbe
{
    public function duration(string $absolutePath): ?float
    {
        $info = $this->analyze($absolutePath);
        $duration = (float) ($info['playtime_seconds'] ?? 0);

        return $duration > 0 ? $duration : null;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function dimensions(string $absolutePath): ?array
    {
        $info = $this->analyze($absolutePath);

        $width = (int) ($info['video']['resolution_x'] ?? 0);
        $height = (int) ($info['video']['resolution_y'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [$width, $height];
    }

    public function fileSizeBytes(string $absolutePath): int
    {
        return is_file($absolutePath) ? (int) @filesize($absolutePath) : 0;
    }

    public function formatName(string $absolutePath): ?string
    {
        $info = $this->analyze($absolutePath);

        return isset($info['fileformat']) ? (string) $info['fileformat'] : null;
    }

    public function videoCodec(string $absolutePath): ?string
    {
        $info = $this->analyze($absolutePath);

        return isset($info['video']['fourcc_lookup']) ? (string) $info['video']['fourcc_lookup'] : null;
    }

    public function isValidForEncoding(string $absolutePath): array
    {
        $errors = [];
        $warnings = [];

        if (! is_file($absolutePath)) {
            return ['valid' => false, 'errors' => ['File không tồn tại']];
        }

        $size = $this->fileSizeBytes($absolutePath);
        $maxSize = (int) config('social_media_video.max_source_size_bytes', 500 * 1024 * 1024);
        if ($size > $maxSize) {
            $errors[] = sprintf(
                'Video quá lớn (%s > %s). Cần giảm kích thước trước khi đăng.',
                $this->formatBytes($size),
                $this->formatBytes($maxSize)
            );
        }

        $format = $this->formatName($absolutePath);
        $validFormats = ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp'];
        if ($format !== null && ! in_array(strtolower($format), $validFormats, true)) {
            $errors[] = "Định dạng video không được hỗ trợ: {$format}";
        }

        $duration = $this->duration($absolutePath);
        $maxDuration = (int) config('social_media_video.max_source_duration_seconds', 300);
        if ($duration !== null && $duration > $maxDuration) {
            $warnings[] = sprintf(
                'Video quá dài (%s > %ds). Video sẽ bị cắt ngắn.',
                $this->formatDuration($duration),
                $maxDuration
            );
        }

        $dimensions = $this->dimensions($absolutePath);
        if ($dimensions === null) {
            $errors[] = 'Không đọc được kích thước video.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'size' => $size,
            'duration' => $duration,
            'format' => $format,
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).' GB';
    }

    protected function formatDuration(float $seconds): string
    {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        return $minutes > 0 ? "{$minutes}p{$secs}s" : "{$secs}s";
    }

    /**
     * @return array<string, mixed>
     */
    protected function analyze(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            return [];
        }

        $getID3 = new \getID3();
        $getID3->setOption([
            'option_md5_data' => false,
            'option_md5_data_source' => false,
            'option_tags_html' => false,
        ]);

        $info = $getID3->analyze($absolutePath);

        return is_array($info) ? $info : [];
    }
}
