<?php

namespace App\Services;

class InstagramFeedImageNormalizer
{
    public const TARGET_WIDTH = 1080;

    public const TARGET_HEIGHT = 1350;

    /** width / height — Instagram feed portrait 4:5 */
    public const TARGET_RATIO = 0.8;

    /** Gần vuông: crop cover về 1080×1350 */
    protected const SQUARE_RATIO_MIN = 0.88;

    protected const SQUARE_RATIO_MAX = 1.12;

    /** Quá ngang: nền blur + fit bên trong */
    protected const LANDSCAPE_RATIO_MIN = 1.12;

    public function isNormalized(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $mime = mime_content_type($absolutePath) ?: '';
        if (! str_contains($mime, 'jpeg') && ! str_contains($mime, 'jpg')) {
            return false;
        }

        $size = @getimagesize($absolutePath);
        if ($size === false) {
            return false;
        }

        return (int) $size[0] === self::TARGET_WIDTH
            && (int) $size[1] === self::TARGET_HEIGHT;
    }

    public function normalizeFile(string $sourceAbsolute, string $destAbsolute): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $image = $this->loadImage($sourceAbsolute);
        if ($image === null) {
            return false;
        }

        $normalized = $this->normalize($image);
        imagedestroy($image);

        if ($normalized === null) {
            return false;
        }

        $dir = dirname($destAbsolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved = imagejpeg($normalized, $destAbsolute, 90);
        imagedestroy($normalized);

        return $saved && is_file($destAbsolute);
    }

    public function normalize(\GdImage $source): ?\GdImage
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);

        if ($srcW <= 0 || $srcH <= 0) {
            return null;
        }

        $ratio = $srcW / $srcH;

        if ($ratio >= self::SQUARE_RATIO_MIN && $ratio <= self::SQUARE_RATIO_MAX) {
            return $this->coverCrop($source, $srcW, $srcH);
        }

        if ($ratio > self::LANDSCAPE_RATIO_MIN) {
            return $this->fitWithBlurBackground($source, $srcW, $srcH);
        }

        if ($ratio < self::TARGET_RATIO) {
            return $this->fitHeightWithPadding($source, $srcW, $srcH);
        }

        return $this->coverCrop($source, $srcW, $srcH);
    }

    protected function createCanvas(): \GdImage
    {
        $canvas = imagecreatetruecolor(self::TARGET_WIDTH, self::TARGET_HEIGHT);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        return $canvas;
    }

    protected function coverCrop(\GdImage $source, int $srcW, int $srcH): \GdImage
    {
        $canvas = $this->createCanvas();
        $dstRatio = self::TARGET_RATIO;
        $srcRatio = $srcW / $srcH;

        if ($srcRatio > $dstRatio) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $dstRatio);
            $srcX = (int) floor(($srcW - $cropW) / 2);
            $srcY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $dstRatio);
            $srcX = 0;
            $srcY = (int) floor(($srcH - $cropH) / 2);
        }

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $srcX,
            $srcY,
            self::TARGET_WIDTH,
            self::TARGET_HEIGHT,
            $cropW,
            $cropH,
        );

        return $canvas;
    }

    protected function fitWithBlurBackground(\GdImage $source, int $srcW, int $srcH): \GdImage
    {
        $canvas = $this->createCanvas();

        $coverScale = max(
            self::TARGET_WIDTH / $srcW,
            self::TARGET_HEIGHT / $srcH,
        );
        $coverW = (int) ceil($srcW * $coverScale);
        $coverH = (int) ceil($srcH * $coverScale);
        $coverX = (int) floor((self::TARGET_WIDTH - $coverW) / 2);
        $coverY = (int) floor((self::TARGET_HEIGHT - $coverH) / 2);

        $background = imagecreatetruecolor(self::TARGET_WIDTH, self::TARGET_HEIGHT);
        imagecopyresampled(
            $background,
            $source,
            $coverX,
            $coverY,
            0,
            0,
            $coverW,
            $coverH,
            $srcW,
            $srcH,
        );

        for ($i = 0; $i < 8; $i++) {
            @imagefilter($background, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($canvas, $background, 0, 0, 0, 0, self::TARGET_WIDTH, self::TARGET_HEIGHT);
        imagedestroy($background);

        $containScale = min(
            self::TARGET_WIDTH / $srcW,
            self::TARGET_HEIGHT / $srcH,
        );
        $fitW = (int) round($srcW * $containScale);
        $fitH = (int) round($srcH * $containScale);
        $fitX = (int) floor((self::TARGET_WIDTH - $fitW) / 2);
        $fitY = (int) floor((self::TARGET_HEIGHT - $fitH) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $fitX,
            $fitY,
            0,
            0,
            $fitW,
            $fitH,
            $srcW,
            $srcH,
        );

        return $canvas;
    }

    protected function fitHeightWithPadding(\GdImage $source, int $srcW, int $srcH): \GdImage
    {
        $canvas = $this->createCanvas();

        $fitH = self::TARGET_HEIGHT;
        $fitW = (int) round($srcW * ($fitH / $srcH));
        $fitX = (int) floor((self::TARGET_WIDTH - $fitW) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            max(0, $fitX),
            0,
            0,
            0,
            min($fitW, self::TARGET_WIDTH),
            $fitH,
            $srcW,
            $srcH,
        );

        return $canvas;
    }

    protected function loadImage(string $absolutePath): ?\GdImage
    {
        $mime = mime_content_type($absolutePath) ?: '';

        return match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => @imagecreatefromjpeg($absolutePath) ?: null,
            str_contains($mime, 'png') => @imagecreatefrompng($absolutePath) ?: null,
            str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($absolutePath) ?: null,
            str_contains($mime, 'gif') => @imagecreatefromgif($absolutePath) ?: null,
            default => null,
        };
    }
}
