<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponSyncRequest;
use App\Services\CouponSyncService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CouponSyncController extends Controller
{
    public function store(CouponSyncRequest $request, CouponSyncService $service): JsonResponse
    {
        try {
            $records = $request->normalizedItems();
            $results = $service->sync($records, $request->normalizedPlatforms());

            $anyEnqueued = $results['blog']['enqueued']
                || $results['instagram']['enqueued']
                || $results['facebook']['enqueued'];

            $allSkippedDuplicate = (bool) ($results['all_skipped_duplicate'] ?? false);
            $platformErrors = $service->platformErrors($results);

            if ($anyEnqueued) {
                $message = 'Coupon đã được đưa vào hàng đợi xử lý.';
                if ($platformErrors !== []) {
                    $message .= ' Một số nền tảng chưa xếp hàng: '.implode(' | ', array_values($platformErrors));
                }
                $success = true;
                $status = 200;
            } elseif ($allSkippedDuplicate) {
                $message = 'Tất cả domain đã có trong hàng đợi — không tạo thêm (domain thất bại vẫn được đẩy lại).';
                $success = true;
                $status = 200;
            } else {
                $message = $service->lastError ?? 'Không thể xếp hàng.';
                $success = false;
                $status = 422;
            }

            return response()->json([
                'success' => $success,
                'message' => $message,
                'errors' => $platformErrors !== [] ? $platformErrors : null,
                'data' => $results,
            ], $status);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi đồng bộ coupon. Vui lòng thử lại sau.',
                'errors' => [
                    'system' => app()->hasDebugModeEnabled()
                        ? $e->getMessage()
                        : 'Không thể xử lý yêu cầu.',
                ],
            ], 500);
        }
    }
}
