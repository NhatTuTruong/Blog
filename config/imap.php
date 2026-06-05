<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IMAP (nhận mail)
    |--------------------------------------------------------------------------
    | Gmail: bật IMAP trong cài đặt, dùng App Password nếu bật 2FA.
    | Mặc định dùng chung MAIL_USERNAME / MAIL_PASSWORD với SMTP gửi mail.
    |
    | IMAP_SYNC_LIMIT
    |   - Lần đồng bộ ĐẦU TIÊN (chưa có email trong DB): tải tối đa N email mới nhất.
    |   - Các lần sau (incremental): tối đa N email MỚI chưa có trong DB mỗi lần chạy.
    |   - KHÔNG phải mỗi lần đều tải lại 100 email cũ.
    |
    | IMAP_AUTO_SYNC_SECONDS
    |   - Chu kỳ tự đồng bộ nền (giây), chạy qua Laravel Scheduler (không cần mở tab).
    |   - Đặt 0 để tắt tự động; chỉ đồng bộ khi bấm nút hoặc chạy: php artisan imap:sync-inbox
    |
    | Để chạy nền trên server: thêm cron * * * * * php /path/to/artisan schedule:run
    | Hoặc dev: php artisan schedule:work
    */

    'host' => env('IMAP_HOST', 'imap.gmail.com'),

    'port' => (int) env('IMAP_PORT', 993),

    'encryption' => env('IMAP_ENCRYPTION', 'ssl'),

    'validate_cert' => env('IMAP_VALIDATE_CERT', true),

    'username' => env('IMAP_USERNAME', env('MAIL_USERNAME')),

    'password' => env('IMAP_PASSWORD', env('MAIL_PASSWORD')),

    'folder' => env('IMAP_FOLDER', 'INBOX'),

    'sync_limit' => (int) env('IMAP_SYNC_LIMIT', 50),

    'auto_sync_seconds' => (int) env('IMAP_AUTO_SYNC_SECONDS', 120),

    /** true = chỉ tải email mới (UID > UID cao nhất trong DB). */
    'incremental_sync' => env('IMAP_INCREMENTAL_SYNC', true),

    /**
     * Tự làm mới bảng tab Nhận mail (chỉ đọc DB, không gọi IMAP).
     * Khi scheduler thêm email mới, bảng cập nhật trong vòng N giây — không cần F5.
     */
    'ui_poll_seconds' => (int) env('IMAP_UI_POLL_SECONDS', 15),

    /** Chuông thông báo admin (giây) — hiện email mới trên mọi trang admin. */
    'notifications_poll_seconds' => (int) env('IMAP_NOTIFICATIONS_POLL_SECONDS', 10),

];
