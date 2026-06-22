<?php

namespace App\Support;

class SocialMediaVideoDrawtext
{
    /**
     * @return array{0: string, 1: string}|null [filterChain, outputLabel]
     */
    public static function buildBottomTitleFilters(
        string $inputLabel,
        string $outputLabel,
        int $targetW,
        int $targetH,
        string $title,
    ): ?array {
        $fontPath = SocialMediaVideoTitleFont::resolvePath();
        if ($fontPath === null) {
            return null;
        }

        $heightPercent = max(12, min(30, (int) config('social_media_video.bottom_overlay_height_percent', 18)));
        $overlayHeight = max(120, (int) round($targetH * ($heightPercent / 100)));
        $overlayTop = max(0, $targetH - $overlayHeight);
        $opacity = max(0.0, min(1.0, (float) config('social_media_video.bottom_overlay_max_opacity', 0.2)));
        $fontSize = (int) config('social_media_video.title_font_size', 38);
        $paddingX = (int) round($targetW * 0.06);
        $maxTextWidth = $targetW - ($paddingX * 2);
        $lines = SocialMediaVideoTitleFont::wrapTitle($title, $maxTextWidth, $fontSize, $fontPath);

        if ($lines === []) {
            return null;
        }

        $lineCount = count($lines);
        if ($lineCount > 2) {
            $fontSize = max(26, $fontSize - 8);
            $lines = SocialMediaVideoTitleFont::wrapTitle($title, $maxTextWidth, $fontSize, $fontPath);
            $lineCount = count($lines);
        } elseif ($lineCount > 1) {
            $fontSize = max(30, $fontSize - 4);
            $lines = SocialMediaVideoTitleFont::wrapTitle($title, $maxTextWidth, $fontSize, $fontPath);
            $lineCount = count($lines);
        }

        if ($lines === []) {
            return null;
        }

        $fontFile = self::escapeFontPath($fontPath);
        $lineHeight = (int) round($fontSize * 1.35);
        $blockHeight = $lineCount * $lineHeight;
        $startY = $overlayTop + (int) round((($overlayHeight - $blockHeight) / 2) + $fontSize);

        $parts = [
            "[{$inputLabel}]drawbox=x=0:y={$overlayTop}:w={$targetW}:h={$overlayHeight}:color=black@{$opacity}:t=fill[vbox]",
        ];

        $current = 'vbox';

        foreach ($lines as $index => $line) {
            $nextLabel = $index === $lineCount - 1 ? $outputLabel : 'vtxt'.$index;
            $y = $startY + ($index * $lineHeight);
            $text = self::escapeDrawtext($line);

            $parts[] = "[{$current}]drawtext=fontfile={$fontFile}:text={$text}:fontsize={$fontSize}:fontcolor=white:borderw=2:bordercolor=black@0.5:x=(w-text_w)/2:y={$y}[{$nextLabel}]";
            $current = $nextLabel;
        }

        return [implode(';', $parts), $outputLabel];
    }

    protected static function escapeFontPath(string $fontPath): string
    {
        $normalized = str_replace('\\', '/', $fontPath);

        if (preg_match('#^[A-Za-z]:/#', $normalized) === 1) {
            $normalized = preg_replace('#^([A-Za-z]):#', '$1\\:', $normalized) ?? $normalized;
        }

        return "'".str_replace("'", "\\'", $normalized)."'";
    }

    protected static function escapeDrawtext(string $text): string
    {
        $escaped = str_replace(
            ['\\', ':', "'", '%', "\r", "\n"],
            ['\\\\', '\\:', "\\'", '\\%', '', ' '],
            trim($text),
        );

        return "'".$escaped."'";
    }
}
