<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use App\Services\GeminiBlogService;
use App\Support\AdminSettings;
use App\Support\AutoBlogSettings;
use Illuminate\Console\Command;

class GenerateDailyBlogs extends Command
{
    protected $signature = 'blogs:generate-daily {--count=0 : Số bài cần tạo cho lần chạy này (0 = lấy theo cài đặt)} {--respect-daily-limit : Không vượt quá số bài/ngày đã cấu hình}';

    protected $description = 'Tự động tạo blog theo danh mục (Gemini)';

    public function handle(GeminiBlogService $gemini): int
    {
        if (! AdminSettings::hasGeminiApiKey()) {
            $this->warn('Gemini API key cho Đăng bài viết tự động chưa được cấu hình (Cài đặt tích hợp), bỏ qua.');

            return self::SUCCESS;
        }

        $categories = BlogCategory::query()->active()->orderBy('name')->get();
        if ($categories->isEmpty()) {
            $this->warn('Chưa có danh mục bài viết (Blog → Danh mục bài viết), bỏ qua.');

            return self::SUCCESS;
        }

        $variants = AutoBlogSettings::enabledCategoryVariants();
        if ($variants === []) {
            $this->warn('Chưa bật variant nào — bỏ qua.');

            return self::SUCCESS;
        }

        $respectDailyLimit = (bool) $this->option('respect-daily-limit');
        $count = (int) $this->option('count');
        if ($count <= 0) {
            $count = $respectDailyLimit ? 1 : (int) AdminSettings::get('auto_blog_daily_count', 2);
        }

        $count = max(1, $count);
        if ($respectDailyLimit) {
            $dailyLimit = max(1, (int) AdminSettings::get('auto_blog_daily_count', 2));
            $todayKey = 'auto_blog.generated.' . now()->format('Y-m-d');
            $generatedToday = (int) AdminSettings::get($todayKey, 0);
            $remaining = $dailyLimit - $generatedToday;

            if ($remaining <= 0) {
                $this->info('Đã đạt giới hạn số bài blog/ngày.');

                return self::SUCCESS;
            }

            $count = min($count, $remaining);
        }

        $author = User::where('is_admin', true)->first() ?? User::first();

        for ($i = 0; $i < $count; $i++) {
            $blogCategory = $categories->random();
            $categoryName = $blogCategory->name;
            $variant = $variants[array_rand($variants)];

            $this->info("Generating blog ({$variant}) for category: {$categoryName}");

            $result = $gemini->generateBlog($categoryName, $variant);

            if (! $result) {
                $err = $gemini->lastError ?? 'Không rõ lỗi';
                $this->warn("Gemini lỗi, bỏ qua bài này: {$err}");
                continue;
            }

            $blog = new Blog();
            if ($author) {
                $blog->user_id = $author->id;
            }
            $blog->blog_category_id = $blogCategory->id;
            $blog->title = $result['title'];
            $blog->category = $categoryName;
            $blog->content = $result['content'];
            $blog->featured_image = $result['featured_image'] ?? null;
            $blog->is_published = true;
            $blog->views_count = 0;
            $blog->save();

            if ($respectDailyLimit) {
                $todayKey = 'auto_blog.generated.' . now()->format('Y-m-d');
                $generatedToday = (int) AdminSettings::get($todayKey, 0);
                AdminSettings::set($todayKey, $generatedToday + 1);
            }

            $this->info("Đã tạo blog: {$blog->title}");
        }

        return self::SUCCESS;
    }
}
