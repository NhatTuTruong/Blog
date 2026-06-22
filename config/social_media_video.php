<?php

return [
    'enabled' => (bool) env('SOCIAL_VIDEO_PREPARE_ENABLED', true),

    /** Để trống = tự tìm encoder đi kèm Composer (bin/ hoặc vendor/). Không cần cài trên server. */
    'media_encoder_binary' => env('SOCIAL_VIDEO_ENCODER_BINARY') ?: env('FFMPEG_BINARY'),

    /** URL tùy chọn theo kiến trúc CPU nếu cần override */
    'media_encoder_download_urls' => [
        'amd64' => null,
        'arm64' => null,
        'i686' => null,
    ],

    /** @deprecated Dùng media_encoder_binary */
    'ffmpeg_binary' => env('FFMPEG_BINARY'),

    /** @deprecated Metadata đọc bằng getID3 (james-heinrich/getid3) */
    'ffprobe_binary' => env('FFPROBE_BINARY'),

    'skip_start_seconds' => (int) env('SOCIAL_VIDEO_SKIP_START_SECONDS', 1),

    /** Bỏ N giây cuối video gốc (trước khi tăng tốc) */
    'trim_end_seconds' => 3,

    /** Lật ngang (mirror trái/phải) */
    'flip_horizontal' => true,

    'target_width' => 1080,

    'target_height' => 1920,

    'crf' => (int) env('SOCIAL_VIDEO_CRF', 21),

    'encode_preset' => env('SOCIAL_VIDEO_ENCODE_PRESET', 'medium'),

    'audio_bitrate' => env('SOCIAL_VIDEO_AUDIO_BITRATE', '128k'),

    'timeout_seconds' => (int) env('SOCIAL_VIDEO_FFMPEG_TIMEOUT', 900),

    /** Crop preset (~5% scale) for video gần 9:16 */
    'crop_scale' => '1134:2016',
    'crop_box' => '1080:1920:27:48',

    /** width/height ratio min/max để coi là 9:16 */
    'vertical_ratio_min' => 0.52,
    'vertical_ratio_max' => 0.62,

    /** Watermark PNG/JPG — để trống hoặc file không tồn tại thì bỏ qua */
    'watermark_path' => env('SOCIAL_VIDEO_WATERMARK_PATH'),

    'watermark_margin' => (int) env('SOCIAL_VIDEO_WATERMARK_MARGIN', 32),

    /** Chiều rộng watermark ≈ % chiều rộng video output */
    'watermark_width_percent' => (int) env('SOCIAL_VIDEO_WATERMARK_WIDTH_PERCENT', 12),

    /** Nền + tiêu đề phía dưới video */
    'bottom_title_overlay_enabled' => (bool) env('SOCIAL_VIDEO_BOTTOM_TITLE_ENABLED', true),

    /** Chiều cao vùng tiêu đề ≈ % chiều cao output (1080×1920) */
    'bottom_overlay_height_percent' => (int) env('SOCIAL_VIDEO_BOTTOM_OVERLAY_HEIGHT_PERCENT', 18),

    /** Opacity nền đen vùng tiêu đề (0–1) */
    'bottom_overlay_max_opacity' => (float) env('SOCIAL_VIDEO_BOTTOM_OVERLAY_MAX_OPACITY', 0.2),

    /** Font tiêu đề — mặc định Segoe UI Bold / Calibri Bold trên Windows */
    'title_font_path' => env('SOCIAL_VIDEO_TITLE_FONT_PATH'),

    'title_font_size' => (int) env('SOCIAL_VIDEO_TITLE_FONT_SIZE', 38),

    /** Xóa tiếng gốc, ghép nhạc nền từ file trong project */
    'replace_audio_with_music' => true,

    'background_music_volume' => 0.85,

    /** Thư mục nhạc nền — mỗi video chọn ngẫu nhiên 1 file */
    'background_music_directory' => public_path('audio/social'),

    'background_music_extensions' => ['mp3', 'm4a', 'wav', 'aac', 'ogg'],

    /** Tốc độ phát video (1.2 = nhanh hơn 20%) */
    'playback_speed' => 1.2,

    /** Chỉnh sáng/tương phản + tông ấm nhẹ để khác video gốc */
    'contrast' => 1.08,
    'brightness' => 0.02,
    'saturation' => 1.06,
    'gamma_r' => 1.05,
    'gamma_g' => 1.01,
    'gamma_b' => 0.94,

    /** Tiêu đề dự phòng khi không có blog/caption */
    'title_fallbacks' => [
        'Is This Product Actually Worth Buying?',
        'Watch This Before You Buy',
        '3 Things You Should Know Before Ordering',
        'Quick Review: What You Need to Know',
        'I Tried It — Here\'s What Happened',
        'Is It Really as Good as People Say?',
        'Who Is This Product Best For?',
        'A Small Upgrade That Can Make a Big Difference',
        'Don\'t Check Out Until You See This',
        'The Simple Way to Find a Better Deal',
    ],
];
