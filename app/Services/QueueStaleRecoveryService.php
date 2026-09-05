<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class QueueStaleRecoveryService
{
    public const STALE_MINUTES = 10;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function failStaleItems(
        string $modelClass,
        ?int $staleMinutes = null,
        ?string $message = null,
        bool $failStalePending = true,
        string $staleTimestampColumn = 'updated_at',
    ): int {
        $staleMinutes = max(1, $staleMinutes ?? self::STALE_MINUTES);
        $threshold = now()->subMinutes($staleMinutes);
        $message ??= 'Quá '.$staleMinutes.' phút ở trạng thái «Chờ đăng» hoặc «Đang đăng» — chuyển sang bài tiếp theo.';

        $count = 0;

        $processing = $modelClass::query()
            ->where('status', $modelClass::STATUS_PROCESSING)
            ->where(function ($query) use ($staleTimestampColumn, $threshold): void {
                if ($staleTimestampColumn === 'processing_started_at') {
                    $query->where(function ($inner) use ($threshold): void {
                        $inner->whereNotNull('processing_started_at')
                            ->where('processing_started_at', '<', $threshold);
                    })->orWhere(function ($inner) use ($threshold): void {
                        $inner->whereNull('processing_started_at')
                            ->where('updated_at', '<', $threshold);
                    });

                    return;
                }

                $query->where($staleTimestampColumn, '<', $threshold);
            })
            ->get();

        foreach ($processing as $item) {
            $item->update([
                'status' => $modelClass::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => $message,
            ]);
            $count++;
        }

        if ($failStalePending) {
            $pending = $modelClass::query()
                ->where('status', $modelClass::STATUS_PENDING)
                ->where('scheduled_at', '<=', $threshold)
                ->get();

            foreach ($pending as $item) {
                $item->update([
                    'status' => $modelClass::STATUS_FAILED,
                    'processed_at' => now(),
                    'error_message' => $message,
                ]);
                $count++;
            }
        }

        if ($count > 0) {
            Log::warning('QueueStaleRecoveryService marked stale queue items as failed', [
                'model' => $modelClass,
                'count' => $count,
                'stale_minutes' => $staleMinutes,
                'fail_stale_pending' => $failStalePending,
            ]);
        }

        return $count;
    }
}
