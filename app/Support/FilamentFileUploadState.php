<?php

namespace App\Support;

use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FilamentFileUploadState
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRecordsFeaturedImage(array $records): array
    {
        return array_map(function (array $record): array {
            $path = self::extractSinglePath($record['featured_image'] ?? null);
            $record['featured_image'] = $path ? PublicStorage::syncUploadedPath($path) : null;

            return $record;
        }, $records);
    }

    /**
     * Thay hook hydrate mặc định của FileUpload (bị ghi đè khi dùng afterStateHydrated).
     * Path đã lưu → Livewire temp để UI giống upload thủ công (Tải lên thành công + nút ×).
     */
    public static function hydrateFeaturedImageField(BaseFileUpload $component, string | array | null $state): void
    {
        if (blank($state)) {
            $component->state([]);

            return;
        }

        if (is_array($state) && collect($state)->contains(fn (mixed $file): bool => $file instanceof TemporaryUploadedFile)) {
            $component->state($state);

            return;
        }

        $shouldFetchFileInformation = $component->shouldFetchFileInformation();

        $files = collect(Arr::wrap($state))
            ->filter(static function (mixed $file) use ($component, $shouldFetchFileInformation): bool {
                if (! is_string($file) || blank($file)) {
                    return false;
                }

                if (PublicStorage::exists($file)) {
                    return true;
                }

                if (! $shouldFetchFileInformation) {
                    return true;
                }

                try {
                    return $component->getDisk()->exists($file);
                } catch (UnableToCheckFileExistence) {
                    return false;
                }
            })
            ->mapWithKeys(static fn (string $file): array => [((string) Str::uuid()) => $file])
            ->all();

        $component->state($files);

        self::hydrateAsPendingUpload($component);
    }

    public static function hydrateAsPendingUpload(BaseFileUpload $component): void
    {
        $current = $component->getState();

        if (! is_array($current) || $current === []) {
            return;
        }

        $converted = [];
        $changed = false;

        foreach ($current as $key => $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $converted[$key] = $file;

                continue;
            }

            if (! is_string($file) || blank($file)) {
                continue;
            }

            $path = PublicStorage::syncUploadedPath($file);

            if (! $path || ! PublicStorage::exists($path)) {
                $converted[$key] = $file;

                continue;
            }

            $pending = self::createPendingUploadFromStoredPath($path);

            if ($pending === null) {
                $converted[$key] = $file;

                continue;
            }

            $converted = array_merge($converted, $pending);
            $changed = true;
        }

        if ($changed) {
            $component->state($converted);
        }
    }

    /**
     * @return array<string, TemporaryUploadedFile>|null
     */
    public static function createPendingUploadFromStoredPath(string $relativePath): ?array
    {
        $relativePath = PublicStorage::syncUploadedPath($relativePath) ?? $relativePath;

        if (! PublicStorage::exists($relativePath)) {
            return null;
        }

        $absolute = PublicStorage::absolutePath($relativePath);
        $originalName = basename($relativePath);
        $mime = mime_content_type($absolute) ?: 'image/jpeg';

        $uploadedFile = new UploadedFile($absolute, $originalName, $mime, null, true);
        $tempFilename = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($uploadedFile);

        $storage = FileUploadConfiguration::storage();
        $destPath = FileUploadConfiguration::path($tempFilename);

        try {
            $contents = file_get_contents($absolute);

            if ($contents === false) {
                return null;
            }

            $storage->put($destPath, $contents);
        } catch (\Throwable) {
            return null;
        }

        if (! $storage->exists($destPath)) {
            return null;
        }

        PublicStorage::delete($relativePath);

        return [(string) Str::uuid() => TemporaryUploadedFile::createFromLivewire($tempFilename)];
    }

    public static function extractSinglePath(mixed $state): ?string
    {
        if ($state instanceof TemporaryUploadedFile) {
            return null;
        }

        if (is_string($state)) {
            $path = trim($state);

            return $path !== '' ? $path : null;
        }

        if (! is_array($state)) {
            return null;
        }

        foreach ($state as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                return null;
            }
        }

        $path = collect($state)
            ->filter(fn (mixed $value): bool => is_string($value) && filled(trim($value)))
            ->map(fn (string $value): string => trim($value))
            ->first();

        return is_string($path) && $path !== '' ? $path : null;
    }
}
