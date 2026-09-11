<?php

namespace App\Support;

class DefaultImageGenerator
{
    /**
     * @return array<int, string> Absolute paths written.
     */
    public static function generateAll(): array
    {
        $root = public_path();
        $written = [];

        $jobs = [
            [$root.'/images/default.webp', 400, 300, [self::class, 'drawBlogPlaceholder']],
            [$root.'/category-images/default.webp', 400, 300, [self::class, 'drawBlogPlaceholder']],
            [$root.'/images/category-images/default.webp', 400, 300, [self::class, 'drawBlogPlaceholder']],
            [$root.'/images/categories/default.webp', 400, 225, [self::class, 'drawCategoryPlaceholder']],
            [$root.'/images/default-brand.webp', 80, 80, [self::class, 'drawBrandPlaceholder']],
            [$root.'/images/placeholder.webp', 80, 80, [self::class, 'drawAvatarPlaceholder']],
            [$root.'/images/instagram/default1.webp', 400, 300, [self::class, 'drawBlogPlaceholder']],
            [$root.'/images/instagram/default2.webp', 400, 300, [self::class, 'drawBlogPlaceholderAlt']],
            [$root.'/images/instagram/default3.webp', 400, 300, [self::class, 'drawBlogPlaceholderWarm']],
        ];

        foreach ($jobs as [$path, $width, $height, $drawer]) {
            if (self::saveWebp($path, $width, $height, $drawer)) {
                $written[] = $path;
            }
        }

        return $written;
    }

    /**
     * @param  callable(\GdImage, int, int): void  $drawer
     */
    protected static function saveWebp(string $path, int $width, int $height, callable $drawer): bool
    {
        if (! function_exists('imagewebp')) {
            return false;
        }

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return false;
        }

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return false;
        }

        $drawer($image, $width, $height);

        $saved = imagewebp($image, $path, 85);
        imagedestroy($image);

        return $saved && is_file($path);
    }

    public static function drawBlogPlaceholder(\GdImage $image, int $width, int $height): void
    {
        $background = imagecolorallocate($image, 229, 231, 235);
        $card = imagecolorallocate($image, 156, 163, 175);
        $muted = imagecolorallocate($image, 209, 213, 219);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        $cardW = (int) round($width * 0.25);
        $cardH = (int) round($height * 0.33);
        $cardX = (int) round(($width - $cardW) / 2);
        $cardY = (int) round(($height - $cardH) / 2);
        imagefilledrectangle($image, $cardX, $cardY, $cardX + $cardW, $cardY + $cardH, $card);

        $circleR = (int) round(min($cardW, $cardH) * 0.22);
        $circleX = (int) round($width / 2);
        $circleY = $cardY + (int) round($cardH * 0.28);
        imagefilledellipse($image, $circleX, $circleY, $circleR * 2, $circleR * 2, $muted);

        $lineY = $circleY + $circleR + (int) round($cardH * 0.12);
        imagefilledrectangle($image, $cardX + (int) round($cardW * 0.2), $lineY, $cardX + (int) round($cardW * 0.8), $lineY + 8, $muted);
        imagefilledrectangle($image, $cardX + (int) round($cardW * 0.35), $lineY + 16, $cardX + (int) round($cardW * 0.65), $lineY + 24, $muted);

        $footerY = (int) round($height * 0.73);
        imagefilledrectangle($image, (int) round($width * 0.25), $footerY, (int) round($width * 0.75), $footerY + 12, $muted);
        imagefilledrectangle($image, (int) round($width * 0.32), $footerY + 20, (int) round($width * 0.68), $footerY + 28, $background);
    }

    public static function drawBlogPlaceholderAlt(\GdImage $image, int $width, int $height): void
    {
        $background = imagecolorallocate($image, 219, 234, 254);
        $card = imagecolorallocate($image, 96, 165, 250);
        $muted = imagecolorallocate($image, 191, 219, 254);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($image, (int) round($width * 0.18), (int) round($height * 0.22), (int) round($width * 0.82), (int) round($height * 0.78), $card);
        imagefilledellipse($image, (int) round($width / 2), (int) round($height * 0.42), (int) round($width * 0.18), (int) round($width * 0.18), $muted);
        imagefilledrectangle($image, (int) round($width * 0.34), (int) round($height * 0.58), (int) round($width * 0.66), (int) round($height * 0.62), $muted);
    }

    public static function drawBlogPlaceholderWarm(\GdImage $image, int $width, int $height): void
    {
        $background = imagecolorallocate($image, 254, 243, 199);
        $card = imagecolorallocate($image, 251, 191, 36);
        $muted = imagecolorallocate($image, 253, 224, 71);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($image, (int) round($width * 0.2), (int) round($height * 0.25), (int) round($width * 0.8), (int) round($height * 0.75), $card);
        imagefilledellipse($image, (int) round($width / 2), (int) round($height * 0.4), (int) round($width * 0.16), (int) round($width * 0.16), $muted);
        imagefilledrectangle($image, (int) round($width * 0.36), (int) round($height * 0.56), (int) round($width * 0.64), (int) round($height * 0.6), $muted);
    }

    public static function drawCategoryPlaceholder(\GdImage $image, int $width, int $height): void
    {
        $background = imagecolorallocate($image, 243, 244, 246);
        $card = imagecolorallocate($image, 229, 231, 235);
        $line = imagecolorallocate($image, 156, 163, 175);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($image, (int) round($width * 0.2), (int) round($height * 0.27), (int) round($width * 0.8), (int) round($height * 0.73), $card);
        imageline($image, (int) round($width * 0.3), (int) round($height * 0.47), (int) round($width * 0.7), (int) round($height * 0.47), $line);
        imageline($image, (int) round($width * 0.35), (int) round($height * 0.58), (int) round($width * 0.65), (int) round($height * 0.58), $line);
        imageline($image, (int) round($width * 0.4), (int) round($height * 0.69), (int) round($width * 0.6), (int) round($height * 0.69), $line);
    }

    public static function drawBrandPlaceholder(\GdImage $image, int $width, int $height): void
    {
        $background = imagecolorallocate($image, 240, 253, 244);
        $green = imagecolorallocate($image, 22, 163, 74);
        $light = imagecolorallocate($image, 240, 253, 244);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledpolygon($image, [40, 16, 16, 34, 64, 34], 3, $green);
        imagefilledrectangle($image, 24, 38, 56, 64, $green);
        imagefilledrectangle($image, 34, 44, 46, 64, $light);
        imagefilledrectangle($image, 27, 42, 32, 47, $light);
        imagefilledrectangle($image, 48, 42, 53, 47, $light);
    }

    public static function drawAvatarPlaceholder(\GdImage $image, int $width, int $height): void
    {
        $background = imagecolorallocate($image, 243, 244, 246);
        $icon = imagecolorallocate($image, 156, 163, 175);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledellipse($image, 40, 36, 16, 16, $icon);
        imagefilledrectangle($image, 28, 48, 52, 64, $icon);
    }
}
