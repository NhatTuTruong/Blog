<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class QueueStaleRecoveryService
{
    public const STALE_MINUTES = 5;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function failStaleItems(string $modelClass): int
    {
        $threshold = now()->subMinutes(self::STALE_MINUTES);
        $message = 'Quá '.self::STALE_MINUTES.' phút ở trạng thái «Chờ đăng» hoặc «Đang đăng» — chuyển sang bài tiếp theo.';

        $count = 0;

        $processing = $modelClass::query()
            ->where('status', $modelClass::STATUS_PROCESSING)
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($processing as $item) {
            $item->update([
                'status' => $modelClass::STATUS_FAILED,
                'processed_at' => now(),
                'error_message' => $message,
            ]);
            $count++;
        }

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

        if ($count > 0) {
            Log::warning('QueueStaleRecoveryService marked stale queue items as failed', [
                'model' => $modelClass,
                'count' => $count,
                'stale_minutes' => self::STALE_MINUTES,
            ]);
        }

        return $count;
    }
}
