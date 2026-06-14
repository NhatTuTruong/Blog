<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pinterest UI
    |--------------------------------------------------------------------------
    |
    | Tạm tắt toàn bộ giao diện Pinterest (đăng bài, cài đặt, đăng đồng thời).
    | Backend (scheduler, queue) vẫn có thể chạy nếu đã cấu hình.
    | Bật lại: đặt true hoặc FEATURES_PINTEREST_UI=true trong .env
    |
    */
    'pinterest_ui' => (bool) env('FEATURES_PINTEREST_UI', false),
];
