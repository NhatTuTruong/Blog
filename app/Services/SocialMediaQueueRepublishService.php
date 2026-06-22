<?php

namespace App\Services;

use App\Models\FacebookQueueItem;
use App\Models\InstagramQueueItem;
use App\Models\PinterestQueueItem;
use App\Support\FacebookSettings;
use App\Support\InstagramSettings;
use App\Support\PinterestSettings;
use App\Support\PublicStorage;
use App\Support\SocialMediaQueueSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SocialMediaQueueRepublishService
{
    public function canRepublish(InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item): bool
    {
        return in_array($item->status, [
            InstagramQueueItem::STATUS_COMPLETED,
            InstagramQueueItem::STATUS_FAILED,
        ], true);
    }

    public function republish(
        InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item,
        ?Carbon $scheduledAt = null,
    ): void {
        if ($item->status === InstagramQueueItem::STATUS_PROCESSING) {
            throw new \RuntimeException('Bài đang xử lý, không thể đăng lại.');
        }

        if (! $this->canRepublish($item)) {
            throw new \RuntimeException('Chỉ có thể đăng lại bài đã hoàn thành hoặc thất bại.');
        }

        $updates = [
            'status' => InstagramQueueItem::STATUS_PENDING,
            'scheduled_at' => $scheduledAt ?? $this->priorityScheduledAt($item::class),
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

    /**
     * @param  Collection<int, InstagramQueueItem|FacebookQueueItem|PinterestQueueItem>  $items
     * @return array{success: int, errors: array<int, string>}
     */
    public function republishMany(Collection $items): array
    {
        $success = 0;
        $errors = [];

        $sorted = $items
            ->filter(fn (mixed $item): bool => $item instanceof InstagramQueueItem
                || $item instanceof FacebookQueueItem
                || $item instanceof PinterestQueueItem)
            ->sortBy('id')
            ->values();

        foreach ($sorted->groupBy(fn (InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item): string => $item::class) as $modelClass => $group) {
            /** @var Collection<int, InstagramQueueItem|FacebookQueueItem|PinterestQueueItem> $group */
            $baseTime = $this->priorityScheduledAt($modelClass);
            $interval = $this->queueIntervalMinutesFor($group->first());

            foreach ($group->values() as $index => $item) {
                try {
                    $this->republish($item, $baseTime->copy()->addMinutes($index * $interval));
                    $success++;
                } catch (\Throwable $e) {
                    $errors[] = '#'.$item->id.': '.$e->getMessage();
                }
            }
        }

        return [
            'success' => $success,
            'errors' => $errors,
        ];
    }

    protected function resolveVideoPathForRepublish(
        InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item,
    ): ?string {
        $path = trim(str_replace('\\', '/', (string) ($item->video_path ?? '')));

        if ($path === '' || ! PublicStorage::exists($path)) {
            return null;
        }

        if (! str_contains(strtolower($path), '-ready.mp4')) {
            return $path;
        }

        $sourcePath = preg_replace('/-ready\.mp4$/i', '.mp4', $path);

        if (! PublicStorage::exists($sourcePath)) {
            $sourceAbsolute = PublicStorage::absolutePath($sourcePath);
            $readyAbsolute = PublicStorage::absolutePath($path);
            $sourceDir = dirname(str_replace('\\', '/', $sourceAbsolute));

            if (! is_dir($sourceDir) && ! @mkdir($sourceDir, 0755, true) && ! is_dir($sourceDir)) {
                return $path;
            }

            if (! @copy($readyAbsolute, $sourceAbsolute)) {
                return $path;
            }
        }

        return $sourcePath;
    }

    protected function queueIntervalMinutesFor(
        InstagramQueueItem|FacebookQueueItem|PinterestQueueItem $item,
    ): int {
        $userId = $item->user_id;

        if ($item instanceof InstagramQueueItem) {
            return InstagramSettings::queueIntervalMinutes($userId);
        }

        if ($item instanceof FacebookQueueItem) {
            return FacebookSettings::queueIntervalMinutes($userId);
        }

        return PinterestSettings::queueIntervalMinutes($userId);
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
