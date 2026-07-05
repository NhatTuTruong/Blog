<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Coupon sync API (web coupon → blog + MXH)
    |--------------------------------------------------------------------------
    |
    | Web coupon gọi POST /api/coupons/sync với header:
    |   Authorization: Bearer {COUPON_SYNC_API_TOKEN}
    |
    | Body (mảng items hoặc một bản ghi đơn):
    |   platforms[] — "blog", "instagram", "facebook" (mặc định: theo cấu hình .env)
    |   items[].domain, items[].aff_link (có thể ""), items[].coupon_codes[] (có thể [])
    |   items[].type — "video" hoặc "image" (mặc định: image)
    |   IG/FB luôn dùng tài khoản đầu tiên (sort_order) đã bật và cấu hình hợp lệ.
    |
    */
    'enabled' => (bool) env('COUPON_SYNC_ENABLED', true),

    'api_token' => env('COUPON_SYNC_API_TOKEN'),

    /** User sở hữu hàng đợi (Gemini, IG, FB). null = admin đầu tiên. */
    'user_id' => env('COUPON_SYNC_USER_ID') !== null && env('COUPON_SYNC_USER_ID') !== ''
        ? (int) env('COUPON_SYNC_USER_ID')
        : null,

    'enqueue_blog' => (bool) env('COUPON_SYNC_ENQUEUE_BLOG', true),

    'enqueue_instagram' => (bool) env('COUPON_SYNC_ENQUEUE_INSTAGRAM', true),

    'enqueue_facebook' => (bool) env('COUPON_SYNC_ENQUEUE_FACEBOOK', true),

    /** Phút trễ trước khi xếp hàng IG/FB (sau blog nếu bật). */
    'social_delay_minutes' => max(0, (int) env('COUPON_SYNC_SOCIAL_DELAY_MINUTES', 0)),

    /** Bỏ qua domain đã có trong hàng đợi (trừ trạng thái thất bại). Admin có thể ghi đè trong Cài đặt hệ thống. */
    'dedupe_domain' => (bool) env('COUPON_SYNC_DEDUPE_DOMAIN', true),
];
