<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConvertPublicCategoryImagesToWebp extends Command
{
    protected $signature = 'images:convert-categories-webp
                            {--dry-run : Chỉ liệt kê, không ghi/xóa file}
                            {--keep-sources : Giữ file gốc sau khi tạo .webp}';

    protected $description = 'Convert raster images in public/images/categories to WebP';

    /** @var array<int, string> */
    private const CONVERTIBLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'avif', 'bmp'];

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('PHP GD with WebP support is required.');

            return self::FAILURE;
        }

        $directory = public_path('images/categories');

        if (! is_dir($directory)) {
            $this->error('Directory not found: public/images/categories');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $keepSources = (bool) $this->option('keep-sources');

        $groups = $this->groupImageFiles($directory);

        if ($groups === []) {
            $this->info('No category images to convert.');

            return self::SUCCESS;
        }

        $converted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($groups as $basename => $files) {
            $sources = array_values(array_filter(
                $files,
                fn (array $file) => in_array($file['ext'], self::CONVERTIBLE_EXTENSIONS, true)
            ));

            $webpFiles = array_values(array_filter(
                $files,
                fn (array $file) => $file['ext'] === 'webp'
            ));

            if ($sources === []) {
                $skipped++;

                continue;
            }

            usort($sources, fn (array $a, array $b) => $b['size'] <=> $a['size']);
            $source = $sources[0];
            $dest = $directory.DIRECTORY_SEPARATOR.$basename.'.webp';

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] %s → %s.webp (from %s, %d source(s))',
                    $basename,
                    $basename,
                    basename($source['path']),
                    count($sources)
                ));
                $converted++;

                continue;
            }

            if (! $this->convertToWebp($source['path'], $dest)) {
                $this->warn('Failed: '.basename($source['path']));
                $failed++;

                continue;
            }

            $this->line('Wrote: images/categories/'.$basename.'.webp');

            if (! $keepSources) {
                foreach ($sources as $item) {
                    @unlink($item['path']);
                }
                foreach ($webpFiles as $item) {
                    if (realpath($item['path']) !== realpath($dest)) {
                        @unlink($item['path']);
                    }
                }
            }

            $converted++;
        }

        $this->info(sprintf(
            'Done. converted=%d skipped=%d failed=%d%s',
            $converted,
            $skipped,
            $failed,
            $dryRun ? ' (dry-run)' : ''
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array{path: string, ext: string, size: int}>>
     */
    private function groupImageFiles(string $directory): array
    {
        $groups = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (! is_file($path)) {
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

            if ($ext === '' || $ext === 'csv' || $ext === 'svg') {
                continue;
            }

            if ($ext !== 'webp' && ! in_array($ext, self::CONVERTIBLE_EXTENSIONS, true)) {
                continue;
            }

            $basename = pathinfo($entry, PATHINFO_FILENAME);

            if (! filled($basename)) {
                continue;
            }

            $groups[$basename][] = [
                'path' => $path,
                'ext' => $ext,
                'size' => (int) filesize($path),
            ];
        }

        ksort($groups);

        return $groups;
    }

    private function convertToWebp(string $sourceAbsolute, string $destAbsolute): bool
    {
        $image = $this->loadImage($sourceAbsolute);

        if ($image === null) {
            return false;
        }

        $image = $this->ensureTruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $saved = @imagewebp($image, $destAbsolute, 85);
        imagedestroy($image);

        return $saved && is_file($destAbsolute);
    }

    private function loadImage(string $absolutePath): ?\GdImage
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = mime_content_type($absolutePath) ?: '';

        return match (true) {
            $ext === 'webp' && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($absolutePath) ?: null,
            $ext === 'png' || str_contains($mime, 'png') => @imagecreatefrompng($absolutePath) ?: null,
            in_array($ext, ['jpg', 'jpeg'], true) || str_contains($mime, 'jpeg') => @imagecreatefromjpeg($absolutePath) ?: null,
            $ext === 'gif' || str_contains($mime, 'gif') => @imagecreatefromgif($absolutePath) ?: null,
            $ext === 'avif' && function_exists('imagecreatefromavif') => @imagecreatefromavif($absolutePath) ?: null,
            $ext === 'bmp' && function_exists('imagecreatefrombmp') => @imagecreatefrombmp($absolutePath) ?: null,
            default => null,
        };
    }

    private function ensureTruecolor(\GdImage $image): \GdImage
    {
        if (imageistruecolor($image)) {
            return $image;
        }

        if (function_exists('imagepalettetotruecolor') && @imagepalettetotruecolor($image)) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $truecolor = imagecreatetruecolor($width, $height);

        if ($truecolor === false) {
            return $image;
        }

        imagealphablending($truecolor, false);
        imagesavealpha($truecolor, true);
        $transparent = imagecolorallocatealpha($truecolor, 0, 0, 0, 127);
        imagefilledrectangle($truecolor, 0, 0, $width, $height, $transparent);
        imagealphablending($truecolor, true);
        imagecopy($truecolor, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        return $truecolor;
    }
}
