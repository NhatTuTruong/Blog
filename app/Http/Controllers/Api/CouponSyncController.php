<?php



namespace App\Http\Controllers\Api;



use App\Http\Controllers\Controller;

use App\Http\Requests\CouponSyncRequest;

use App\Services\CouponSyncService;

use Illuminate\Http\JsonResponse;



class CouponSyncController extends Controller

{

    public function store(CouponSyncRequest $request, CouponSyncService $service): JsonResponse

    {

        $records = $request->normalizedItems();

        $results = $service->sync($records);



        $anyEnqueued = $results['blog']['enqueued']

            || $results['instagram']['enqueued']

            || $results['facebook']['enqueued'];



        $allSkippedDuplicate = (bool) ($results['all_skipped_duplicate'] ?? false);



        if ($anyEnqueued) {

            $message = 'Coupon đã được đưa vào hàng đợi xử lý.';

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

            'data' => $results,

        ], $status);

    }

}


