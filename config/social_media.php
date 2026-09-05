<?php

return [
    /** Hiển thị option "Video" trong Loại media tự động (Đăng bài mạng xã hội). */
    'auto_video_option_enabled' => (bool) env('SOCIAL_VIDEO_UI_ENABLED', true),

    /** Sau N phút «Đang đăng» mà chưa xong → đánh dấu thất bại, chuyển bài tiếp theo. */
    'queue_stale_minutes' => (int) env('SOCIAL_MEDIA_QUEUE_STALE_MINUTES', 10),
];
