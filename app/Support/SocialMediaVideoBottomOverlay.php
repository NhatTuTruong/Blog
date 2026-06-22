<?php

namespace App\Support;

class SocialMediaVideoBottomOverlay
{
    /**
     * Tạo PNG nền đen 20% opacity + tiêu đề trắng căn giữa vùng dưới video.
     */
    public static function generate(int $videoWidth, int $videoHeight, string $title): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $fontPath = self::resolveFontPath();
        if ($fontPath === null) {
            return null;
        }

        $heightPercent = max(12, min(30, (int) config('social_media_video.bottom_overlay_height_percent', 18)));
        $overlayHeight = max(120, (int) round($videoHeight * ($heightPercent / 100)));
        $opacity = max(0.0, min(1.0, (float) config('social_media_video.bottom_overlay_max_opacity', 0.2)));

        $image = imagecreatetruecolor($videoWidth, $overlayHeight);
        if ($image === false) {
            return null;
        }

        imagesavealpha($image, true);
        imagealphablending($image, false);

        self::drawFlatBackground($image, $videoWidth, $overlayHeight, $opacity);
        self::drawTitle($image, $videoWidth, $overlayHeight, $title, $fontPath);

        $path = tempnam(sys_get_temp_dir(), 'vid_btm_');
        if ($path === false) {
            imagedestroy($image);

            return null;
        }

        $pngPath = $path.'.png';
        @unlink($path);

        if (! imagepng($image, $pngPath)) {
            imagedestroy($image);
            @unlink($pngPath);

            return null;
        }

        imagedestroy($image);

        return $pngPath;
    }

    protected static function drawFlatBackground(\GdImage $image, int $width, int $height, float $opacity): void
    {
        $alpha = (int) round((1 - $opacity) * 127);
        $color = imagecolorallocatealpha($image, 0, 0, 0, max(0, min(127, $alpha)));

        imagefilledrectangle($image, 0, 0, max(0, $width - 1), max(0, $height - 1), $color);
    }

    protected static function drawTitle(\GdImage $image, int $width, int $height, string $title, string $fontPath): void
    {
        $paddingX = (int) round($width * 0.06);
        $maxTextWidth = $width - ($paddingX * 2);
        $baseFontSize = (int) config('social_media_video.title_font_size', 38);
        $lines = self::wrapTitle($title, $fontPath, $baseFontSize, $maxTextWidth);

        if ($lines === []) {
            return;
        }

        $lineCount = count($lines);
        $fontSize = $lineCount > 2
            ? max(26, $baseFontSize - 8)
            : ($lineCount > 1 ? max(30, $baseFontSize - 4) : $baseFontSize);

        if ($lineCount > 1) {
            $lines = self::wrapTitle($title, $fontPath, $fontSize, $maxTextWidth);
            $lineCount = count($lines);
        }

        $lineHeight = (int) round($fontSize * 1.35);
        $blockHeight = $lineCount * $lineHeight;

        $firstBox = imagettfbbox($fontSize, 0, $fontPath, $lines[0]);
        if (! is_array($firstBox)) {
            return;
        }

        $ascent = max(abs((int) $firstBox[7]), abs((int) $firstBox[1]), $fontSize);
        $startBaselineY = (int) round((($height - $blockHeight) / 2) + $ascent);

        $white = imagecolorallocatealpha($image, 255, 255, 255, 0);
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 40);

        foreach ($lines as $index => $line) {
            $box = imagettfbbox($fontSize, 0, $fontPath, $line);
            if (! is_array($box)) {
                continue;
            }

            $textWidth = abs($box[2] - $box[0]);
            $x = (int) round(($width - $textWidth) / 2);
            $y = $startBaselineY + ($index * $lineHeight);

            imagettftext($image, $fontSize, 0, $x + 1, $y + 1, $shadow, $fontPath, $line);
            imagettftext($image, $fontSize, 0, $x, $y, $white, $fontPath, $line);
        }
    }

    /**
     * @return array<int, string>
     */
    protected static function wrapTitle(string $title, string $fontPath, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);

            if (! is_array($box)) {
                continue;
            }

            $textWidth = abs($box[2] - $box[0]);

            if ($textWidth <= $maxWidth || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, 3);
    }

    protected static function resolveFontPath(): ?string
    {
        $candidates = array_filter([
            config('social_media_video.title_font_path'),
            'C:\\Windows\\Fonts\\segoeuib.ttf',
            'C:\\Windows\\Fonts\\seguisb.ttf',
            'C:\\Windows\\Fonts\\Segoe UI Bold.ttf',
            'C:\\Windows\\Fonts\\calibrib.ttf',
            'C:\\Windows\\Fonts\\georgiab.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/msttcorefonts/Arial_Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
