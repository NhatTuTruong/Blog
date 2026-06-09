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

        return "Chỉ 1 file: ảnh (JPG/PNG) hoặc video (MP4/MOV, ≤{$maxLabel}). Bỏ trống = ảnh mặc định. Video tự xóa sau khi đăng.";
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
