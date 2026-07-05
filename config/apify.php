<?php

return [
    'api_token' => env('APIFY_API_TOKEN'),

    'google_images_actor_id' => env('APIFY_GOOGLE_IMAGES_ACTOR_ID', '1zP0mfnAf2xvIwvJu'),

    'google_images' => [
        'image_size' => env('APIFY_GOOGLE_IMAGES_SIZE', 'large'),
        'image_type' => env('APIFY_GOOGLE_IMAGES_TYPE', 'photo'),
        'language' => env('APIFY_GOOGLE_IMAGES_LANGUAGE', 'en'),
        'country' => env('APIFY_GOOGLE_IMAGES_COUNTRY', 'us'),
    ],

    'tiktok_actor_id' => env('APIFY_TIKTOK_ACTOR_ID', 'GdWCkxBtKWOsKjdch'),

    'tiktok_results_per_page' => (int) env('APIFY_TIKTOK_RESULTS_PER_PAGE', 1),

    'tiktok_default_hashtag' => env('APIFY_TIKTOK_DEFAULT_HASHTAG', 'fyp'),

    'tiktok_download_videos' => (bool) env('APIFY_TIKTOK_DOWNLOAD_VIDEOS', true),

    'max_results_per_query' => (int) env('APIFY_MAX_RESULTS_PER_QUERY', 3),

    'run_wait_seconds' => (int) env('APIFY_RUN_WAIT_SECONDS', 180),

    'tiktok_results_cache_seconds' => (int) env('APIFY_TIKTOK_RESULTS_CACHE_SECONDS', 3600),

    'video_download_timeout_seconds' => (int) env('APIFY_VIDEO_DOWNLOAD_TIMEOUT_SECONDS', 600),

    'video_download_retries' => (int) env('APIFY_VIDEO_DOWNLOAD_RETRIES', 3),
];
