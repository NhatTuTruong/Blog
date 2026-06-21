<?php

namespace App\Services;

use App\Models\FacebookQueueItem;
use App\Models\InstagramQueueItem;
use App\Models\PinterestQueueItem;
use App\Support\PublicStorage;
use App\Support\SocialMediaQueueSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SocialMediaQueueRepublishService
{
    public function canRepublish(InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item): bool
    {
        return in_array($item->status, [
            InstagramQueueItem::STATUS_COMPLETED,
            InstagramQueueItem::STATUS_FAILED,
        ], true);
    }

    public function republish(InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item): void
    {
        if ($item->status === InstagramQueueItem::STATUS_PROCESSING) {
            throw new \RuntimeException('Bài đang xử lý, không thể đăng lại.');
        }

        if (! $this->canRepublish($item)) {
            throw new \RuntimeException('Chỉ có thể đăng lại bài đã hoàn thành hoặc thất bại.');
        }

        $updates = [
            'status' => InstagramQueueItem::STATUS_PENDING,
            'scheduled_at' => $this->priorityScheduledAt($item::class),
            'sort_order' => 0,
            'processed_at' => null,
            'error_message' => null,
            'video_path' => $this->resolveVideoPathForRepublish($item),
            'queue_source' => filled($item->queue_source)
                ? $item->queue_source
                : SocialMediaQueueSource::MANUAL,
        ];

        if ($item instanceof InstagramQueueItem) {
            $updates['instagram_media_id'] = null;
        } elseif ($item instanceof FacebookQueueItem) {
            $updates['facebook_post_id'] = null;
        } elseif ($item instanceof PinterestQueueItem) {
            $updates['pinterest_pin_id'] = null;
        }

        $item->update($updates);
    }

    protected function resolveVideoPathForRepublish(
        InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item,
    ): ?string {
        $path = trim(str_replace('\\', '/', (string) ($item->video_path ?? '')));

        if ($path === '') {
            return null;
        }

        return PublicStorage::exists($path) ? $path : null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function priorityScheduledAt(string $modelClass): Carbon
    {
        /** @var InstagramQueueItem|FacebookQueueItem|PinterestQueueItem|null $firstPending */
        $firstPending = $modelClass::query()
            ->where('status', InstagramQueueItem::STATUS_PENDING)
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->first();

        if ($firstPending === null || $firstPending->scheduled_at === null) {
            return now();
        }

        return $firstPending->scheduled_at->copy()->subMinute();
    }
}
