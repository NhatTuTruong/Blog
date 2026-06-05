<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-1.5-flash-latest'),
    /** Model hỗ trợ sinh ảnh (dùng cho blog auto: nội dung + ảnh đại diện 1 request) */
    'model_image' => env('GEMINI_MODEL_IMAGE', 'gemini-2.5-flash-image'),
    /** Thời gian chờ tối đa mỗi request (giây). Bài blog dài thường cần 90–180s. */
    'timeout' => (int) env('GEMINI_TIMEOUT_SECONDS', 120),
    'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT_SECONDS', 30),
];

