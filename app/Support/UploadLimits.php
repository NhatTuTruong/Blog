<?php

namespace App\Support;

class UploadLimits
{
    public static function maxUploadMegabytes(): float
    {
        return self::iniSizeToMegabytes((string) ini_get('upload_max_filesize'));
    }

    public static function postMaxMegabytes(): float
    {
        return self::iniSizeToMegabytes((string) ini_get('post_max_size'));
    }

    public static function effectiveVideoMaxMegabytes(): float
    {
        return min(self::maxUploadMegabytes(), self::postMaxMegabytes(), 100.0);
    }

    public static function isVideoUploadAllowed(float $sizeMegabytes): bool
    {
        return $sizeMegabytes <= self::effectiveVideoMaxMegabytes();
    }

    public static function mediaUploadHelperText(): string
    {
        $max = self::effectiveVideoMaxMegabytes();
        $maxLabel = $max >= 1 ? (int) round($max).'MB' : round($max, 1).'MB';

        return "Chỉ 1 file: ảnh (JPG/PNG) hoặc video (MP4/MOV, ≤{$maxLabel}).";
    }

    /**
     * @return array<string, string>
     */
    public static function mediaMimeTypeMap(): array
    {
        return [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'qt' => 'video/quicktime',
            'm4v' => 'video/mp4',
            'webm' => 'video/webm',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function mediaFileExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'qt', 'm4v', 'webm'];
    }

    public static function mediaMaxSizeKilobytes(): int
    {
        return (int) floor(min(102400, self::effectiveVideoMaxMegabytes() * 1024));
    }

    /**
     * @return array<int, string>
     */
    public static function mediaAcceptedMimeTypes(): array
    {
        return array_values(array_unique(array_merge(
            array_values(self::mediaMimeTypeMap()),
            [
                'image/jpg',
                'image/pjpeg',
                'image/x-png',
                'video/x-quicktime',
                'video/x-m4v',
                'application/mp4',
            ],
        )));
    }

    protected static function iniSizeToMegabytes(string $value): float
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 100.0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => $number * 1024,
            'm' => $number,
            'k' => $number / 1024,
            default => $number / (1024 * 1024),
        };
    }
}
