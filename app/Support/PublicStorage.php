<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PublicStorage
{
    public static function root(): string
    {
        return public_path('storage');
    }

    public static function legacyRoot(): string
    {
        return storage_path('app/public');
    }

    public static function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    public static function absolutePath(string $relativePath): string
    {
        $relativePath = static::normalizePath($relativePath);

        return $relativePath === ''
            ? static::root()
            : static::root().'/'.$relativePath;
    }

    public static function ensureDirectory(string $relativeDir = ''): void
    {
        $relativeDir = static::normalizePath($relativeDir);
        $absolute = $relativeDir === '' ? static::root() : static::absolutePath($relativeDir);

        if (! is_dir($absolute)) {
            File::makeDirectory($absolute, 0755, true);
        }
    }

    public static function exists(string $relativePath): bool
    {
        $path = static::normalizePath($relativePath);
        if ($path === '') {
            return false;
        }

        if (is_file(static::absolutePath($path))) {
            return true;
        }

        return static::syncFromLegacy($path) !== null;
    }

    /**
     * Đảm bảo file nằm trong public/storage (copy từ vị trí cũ nếu cần).
     */
    public static function syncUploadedPath(mixed $relativePath): ?string
    {
        if (is_array($relativePath)) {
            $relativePath = $relativePath[0] ?? null;
        }

        if (! is_string($relativePath) || trim($relativePath) === '') {
            return null;
        }

        $path = static::normalizePath($relativePath);
        static::ensureDirectory('');

        if (is_file(static::absolutePath($path))) {
            return $path;
        }

        return static::syncFromLegacy($path);
    }

    public static function syncFromLegacy(string $relativePath): ?string
    {
        $path = static::normalizePath($relativePath);
        if ($path === '') {
            return null;
        }

        $destination = static::absolutePath($path);
        if (is_file($destination)) {
            return $path;
        }

        $legacy = static::legacyRoot().'/'.$path;
        if (! is_file($legacy)) {
            return null;
        }

        $parent = dirname($path);
        static::ensureDirectory($parent === '.' ? '' : $parent);
        File::copy($legacy, $destination);

        return is_file($destination) ? $path : null;
    }

    public static function url(string $relativePath): string
    {
        $path = static::normalizePath($relativePath);
        static::syncFromLegacy($path);

        return asset('storage/'.$path);
    }

    public static function put(string $relativePath, string $contents): bool
    {
        $path = static::normalizePath($relativePath);
        $parent = dirname($path);
        static::ensureDirectory($parent === '.' ? '' : $parent);

        return file_put_contents(static::absolutePath($path), $contents) !== false;
    }

    public static function copyUploadedFile(UploadedFile|TemporaryUploadedFile $file, string $relativePath): string
    {
        $path = static::normalizePath($relativePath);
        $parentPath = dirname($path);
        $parentPath = $parentPath === '.' ? '' : $parentPath;
        static::ensureDirectory($parentPath);

        $file->move(static::absolutePath($parentPath), basename($path));

        return $path;
    }

    public static function storeUploadedFile(
        UploadedFile|TemporaryUploadedFile $file,
        string $directory,
        ?string $filename = null,
    ): string {
        $directory = static::normalizePath($directory);
        $filename = $filename ?? $file->hashName();
        $relativePath = $directory !== '' ? $directory.'/'.$filename : $filename;

        return static::copyUploadedFile($file, $relativePath);
    }
}
