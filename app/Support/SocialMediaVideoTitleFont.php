<?php

namespace App\Support;

class SocialMediaVideoTitleFont
{
    public static function resolvePath(): ?string
    {
        $configured = trim((string) config('social_media_video.title_font_path', ''));

        $candidates = array_filter([
            $configured !== '' ? $configured : null,
            public_path('fonts/social/DejaVuSans.ttf'),
            public_path('fonts/social/DejaVuSans-Bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/msttcorefonts/Arial_Bold.ttf',
            'C:\\Windows\\Fonts\\segoeuib.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function wrapTitle(string $title, int $maxWidth, int $fontSize, ?string $fontPath = null): array
    {
        $fontPath ??= self::resolvePath();

        if ($fontPath !== null && extension_loaded('gd') && function_exists('imagettfbbox')) {
            return self::wrapTitleWithFontMetrics($title, $fontPath, $fontSize, $maxWidth);
        }

        $maxChars = max(12, (int) floor($maxWidth / max(18, (int) round($fontSize * 0.52))));
        $words = preg_split('/\s+/u', trim($title)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) <= $maxChars || $current === '') {
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

    /**
     * @return array<int, string>
     */
    protected static function wrapTitleWithFontMetrics(
        string $title,
        string $fontPath,
        int $fontSize,
        int $maxWidth,
    ): array {
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

        $lineCount = count($lines);

        if ($lineCount > 2) {
            $fontSize = max(26, $fontSize - 8);

            return self::wrapTitleWithFontMetrics($title, $fontPath, $fontSize, $maxWidth);
        }

        if ($lineCount > 1) {
            $fontSize = max(30, $fontSize - 4);

            return self::wrapTitleWithFontMetrics($title, $fontPath, $fontSize, $maxWidth);
        }

        return array_slice($lines, 0, 3);
    }
}
