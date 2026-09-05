<?php

namespace App\Services\Concerns;

use App\Services\QueueStaleRecoveryService;
use App\Support\SocialMediaQueueConfig;
use Illuminate\Database\Eloquent\Model;

trait ManagesSocialMediaQueueStaleItems
{
    protected function socialQueueStaleMinutes(): int
    {
        return SocialMediaQueueConfig::staleMinutes();
    }

    protected function recoverSocialStaleProcessingItems(string $modelClass): int
    {
        return app(QueueStaleRecoveryService::class)->failStaleItems(
            modelClass: $modelClass,
            staleMinutes: $this->socialQueueStaleMinutes(),
            message: SocialMediaQueueConfig::staleMessage(),
            failStalePending: false,
            staleTimestampColumn: 'processing_started_at',
        );
    }

    protected function hasActiveSocialProcessing(string $modelClass): bool
    {
        return $modelClass::query()
            ->where('status', $modelClass::STATUS_PROCESSING)
            ->exists();
    }

    protected function claimSocialQueueItem(Model $item, string $modelClass): bool
    {
        $now = now();

        return $modelClass::query()
            ->where('id', $item->getKey())
            ->where('status', $modelClass::STATUS_PENDING)
            ->update([
                'status' => $modelClass::STATUS_PROCESSING,
                'processing_started_at' => $now,
                'updated_at' => $now,
            ]) === 1;
    }

    protected function beginSocialQueueItemProcessing(): void
    {
        @set_time_limit(SocialMediaQueueConfig::phpTimeLimitSeconds());
    }
}
