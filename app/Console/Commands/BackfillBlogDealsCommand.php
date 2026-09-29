<?php

namespace App\Console\Commands;

use App\Models\AutoBlogQueueItem;
use App\Models\Blog;
use App\Services\BlogDealsSyncService;
use Illuminate\Console\Command;

class BackfillBlogDealsCommand extends Command
{
    protected $signature = 'blogs:backfill-deals {--blog= : Blog ID cụ thể}';

    protected $description = 'Gắn lại deal/coupon cho bài đã publish (từ queue auto blog hoặc deals_data hiện có)';

    public function handle(BlogDealsSyncService $dealsSync): int
    {
        $blogId = $this->option('blog');

        if ($blogId) {
            $blog = Blog::query()->find($blogId);
            if (! $blog) {
                $this->error("Không tìm thấy blog #{$blogId}.");

                return self::FAILURE;
            }

            $this->syncBlog($blog, $dealsSync);

            return self::SUCCESS;
        }

        $items = AutoBlogQueueItem::query()
            ->where('status', AutoBlogQueueItem::STATUS_COMPLETED)
            ->whereNotNull('blog_id')
            ->with('blog')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            $this->line('Không có bài auto blog đã hoàn thành để backfill.');

            return self::SUCCESS;
        }

        foreach ($items as $item) {
            if (! $item->blog) {
                continue;
            }

            $this->syncBlogFromQueueItem($item, $dealsSync);
        }

        $this->info('Hoàn tất backfill deals.');

        return self::SUCCESS;
    }

    protected function syncBlog(Blog $blog, BlogDealsSyncService $dealsSync): void
    {
        $item = AutoBlogQueueItem::query()
            ->where('blog_id', $blog->id)
            ->latest('id')
            ->first();

        if ($item) {
            $this->syncBlogFromQueueItem($item, $dealsSync);

            return;
        }

        $this->warn("Blog #{$blog->id} không có queue item — mở admin và thêm deal thủ công.");
    }

    protected function syncBlogFromQueueItem(AutoBlogQueueItem $item, BlogDealsSyncService $dealsSync): void
    {
        $blog = $item->blog;
        if (! $blog) {
            return;
        }

        $payload = $dealsSync->buildDealsPayload(
            is_array($item->deals_data) ? $item->deals_data : [],
            is_array($item->coupon_codes) ? $item->coupon_codes : [],
            $item->aff_link,
            $item->brand_domain,
        );

        if ($payload === []) {
            $this->line("  Bỏ qua blog #{$blog->id} — không có coupon/deal.");

            return;
        }

        $dealsSync->sync($blog, $payload);
        $this->info("  Đã sync ".count($payload)." deal cho blog #{$blog->id}: {$blog->title}");
    }
}
