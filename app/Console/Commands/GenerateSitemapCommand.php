<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Support\SitemapGenerator;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Tạo lại public/sitemap.xml (trang tĩnh, review/*, blogs/* và URL hệ thống)';

    public function handle(SitemapGenerator $generator): int
    {
        $base = rtrim((string) config('app.url'), '/');
        $this->info('APP_URL: '.$base);

        $published = Blog::query()->where('is_published', true)->count();
        $reviews = Blog::query()->where('is_published', true)->where('post_type', Blog::TYPE_REVIEW)->count();
        $blogs = Blog::query()->where('is_published', true)->where('post_type', Blog::TYPE_BLOG)->count();

        $path = $generator->writeToPublic();

        $this->info("Đã ghi sitemap: {$path}");
        $this->line("  • Tổng bài publish: {$published} (review: {$reviews}, blogs: {$blogs})");
        $this->line('  • Ví dụ review: '.$base.'/review/{slug}');
        $this->line('  • Ví dụ blogs: '.$base.'/blogs/{slug}');

        return self::SUCCESS;
    }
}
