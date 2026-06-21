<?php

return [
    'enabled' => (bool) env('SOCIAL_VIDEO_PREPARE_ENABLED', true),

    'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),

    'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),

    'skip_start_seconds' => (int) env('SOCIAL_VIDEO_SKIP_START_SECONDS', 1),

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

    'watermark_margin' => (int) env('SOCIAL_VIDEO_WATERMARK_MARGIN', 40),

    /** Chiều rộng watermark ≈ % chiều rộng video output */
    'watermark_width_percent' => (int) env('SOCIAL_VIDEO_WATERMARK_WIDTH_PERCENT', 15),
];
