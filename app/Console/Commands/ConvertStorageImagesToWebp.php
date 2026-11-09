<?php

namespace App\Console\Commands;

use App\Models\AutoBlogQueueItem;
use App\Models\Blog;
use App\Models\FacebookQueueItem;
use App\Models\InstagramQueueItem;
use App\Models\PinterestQueueItem;
use App\Services\SocialMediaImageSourceService;
use App\Support\PublicStorage;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ConvertStorageImagesToWebp extends Command
{
    protected $signature = 'images:convert-storage-webp
                            {--dry-run : Chỉ liệt kê, không ghi/xóa file}
                            {--keep-sources : Giữ file gốc sau khi tạo .webp}
                            {--dir= : Thư mục (mặc định public/storage)}';

    protected $description = 'Chuyển toàn bộ ảnh trong public/storage (và bản sao storage/app/public) sang WebP, cập nhật đường dẫn trong DB';

    /** @var array<int, string> */
    private const CONVERTIBLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'avif'];

    public function handle(SocialMediaImageSourceService $images): int
    {
        if (! function_exists('imagewebp') && ! extension_loaded('imagick')) {
            $this->error('Cần PHP GD (imagewebp) hoặc Imagick để ghi WebP.');

            return self::FAILURE;
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $keepSources = (bool) $this->option('keep-sources');
        $roots = $this->roots();

        if ($roots === []) {
            $this->error('Không tìm thấy thư mục storage để convert.');

            return self::FAILURE;
        }

        $files = [];
        foreach ($roots as $root) {
            $this->info('Quét: '.$root);
            $files = array_merge($files, $this->collectImages($root));
        }
        $files = $this->uniqueFiles($files);

        if ($files === []) {
            $this->info('Không có ảnh jpg/png/gif cần convert.');

            return self::SUCCESS;
        }

        $this->info('Tìm thấy '.count($files).' file.');

        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $replacements = [];

        foreach ($files as $absolute) {
            $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
            $destAbsolute = preg_replace('/\.'.$ext.'$/i', '.webp', $absolute) ?: $absolute.'.webp';
            $oldRelative = $this->storageRelative($absolute);
            $newRelative = $oldRelative !== null
                ? preg_replace('/\.'.$ext.'$/i', '.webp', $oldRelative)
                : null;

            if (is_file($destAbsolute) && filesize($destAbsolute) > 0) {
                if ($dryRun) {
                    $this->line('[dry-run] đã có webp, sẽ xóa gốc: '.$this->displayPath($absolute));
                    $converted++;
                } else {
                    if (! $keepSources) {
                        @unlink($absolute);
                    }
                    $this->line('Đã có webp, xóa gốc: '.$this->displayPath($absolute));
                    $converted++;
                }

                if (is_string($oldRelative) && is_string($newRelative) && $oldRelative !== $newRelative) {
                    $replacements[$oldRelative] = $newRelative;
                }

                continue;
            }

            if ($dryRun) {
                $this->line('[dry-run] '.$this->displayPath($absolute).' → '.basename($destAbsolute));
                $converted++;

                if (is_string($oldRelative) && is_string($newRelative)) {
                    $replacements[$oldRelative] = $newRelative;
                }

                continue;
            }

            try {
                $ok = $images->convertFileToWebp($absolute, $destAbsolute);
            } catch (\Throwable $e) {
                $this->warn('Lỗi: '.$this->displayPath($absolute).' ('.$e->getMessage().')');
                $failed++;
                gc_collect_cycles();

                continue;
            }

            if (! $ok) {
                $this->warn('Lỗi: '.$this->displayPath($absolute).' (file hỏng hoặc quá lớn)');
                $failed++;
                gc_collect_cycles();

                continue;
            }

            if (! $keepSources) {
                @unlink($absolute);
            }

            $this->line('OK: '.$this->displayPath($absolute).' → '.basename($destAbsolute));
            $converted++;
            gc_collect_cycles();

            if (is_string($oldRelative) && is_string($newRelative) && $oldRelative !== $newRelative) {
                $replacements[$oldRelative] = $newRelative;
            }
        }

        if (! $dryRun && $replacements !== []) {
            $updated = $this->rewriteDatabasePaths($replacements);
            $this->info('Đã cập nhật '.$updated.' bản ghi DB (featured_image / content / image_path).');
        } elseif ($dryRun && $replacements !== []) {
            $this->info('[dry-run] sẽ cập nhật '.count($replacements).' đường dẫn trong DB.');
        }

        $this->info(sprintf(
            'Xong. converted=%d skipped=%d failed=%d%s',
            $converted,
            $skipped,
            $failed,
            $dryRun ? ' (dry-run)' : ''
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function roots(): array
    {
        $custom = trim((string) $this->option('dir'));
        if ($custom !== '') {
            $path = is_dir($custom) ? $custom : base_path($custom);

            return is_dir($path) ? [$path] : [];
        }

        $roots = [];
        $seen = [];

        foreach ([PublicStorage::root(), PublicStorage::legacyRoot()] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $real = realpath($dir) ?: $dir;
            if (isset($seen[$real])) {
                continue;
            }

            $seen[$real] = true;
            $roots[] = $dir;
        }

        return $roots;
    }

    /**
     * @param  array<int, string>  $files
     * @return array<int, string>
     */
    protected function uniqueFiles(array $files): array
    {
        $unique = [];
        $seen = [];

        foreach ($files as $path) {
            $real = realpath($path) ?: $path;
            if (isset($seen[$real])) {
                continue;
            }

            $seen[$real] = true;
            $unique[] = $path;
        }

        return $unique;
    }

    /**
     * @return array<int, string>
     */
    protected function collectImages(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (! in_array($ext, self::CONVERTIBLE_EXTENSIONS, true)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    protected function storageRelative(string $absolute): ?string
    {
        $normalized = str_replace('\\', '/', $absolute);

        foreach ([PublicStorage::root(), PublicStorage::legacyRoot()] as $root) {
            $rootNorm = rtrim(str_replace('\\', '/', $root), '/').'/';
            if (str_starts_with($normalized, $rootNorm)) {
                return ltrim(substr($normalized, strlen($rootNorm)), '/');
            }
        }

        return null;
    }

    protected function displayPath(string $absolute): string
    {
        return $this->storageRelative($absolute) ?? $absolute;
    }

    /**
     * @param  array<string, string>  $replacements  oldRelative => newRelative
     */
    protected function rewriteDatabasePaths(array $replacements): int
    {
        $updated = 0;

        foreach ($replacements as $old => $new) {
            $oldUrl = '/storage/'.$old;
            $newUrl = '/storage/'.$new;

            $featured = Blog::query()->where('featured_image', $old)->update(['featured_image' => $new]);
            $updated += $featured;

            $contentHits = Blog::query()
                ->where(function ($query) use ($old, $oldUrl): void {
                    $query->where('content', 'like', '%'.$old.'%')
                        ->orWhere('content', 'like', '%'.$oldUrl.'%');
                })
                ->get(['id', 'content']);

            foreach ($contentHits as $blog) {
                $content = (string) $blog->content;
                $replaced = str_replace([$oldUrl, $old], [$newUrl, $new], $content);
                if ($replaced !== $content) {
                    $blog->update(['content' => $replaced]);
                    $updated++;
                }
            }

            foreach ([
                [AutoBlogQueueItem::class, 'image_path'],
                [InstagramQueueItem::class, 'image_path'],
                [FacebookQueueItem::class, 'image_path'],
                [PinterestQueueItem::class, 'image_path'],
            ] as [$model, $column]) {
                if (! class_exists($model)) {
                    continue;
                }

                try {
                    $updated += $model::query()->where($column, $old)->update([$column => $new]);
                } catch (\Throwable) {
                    // Bảng chưa migrate
                }
            }
        }

        return $updated;
    }
}
