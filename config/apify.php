<?php

return [
    'api_token' => env('APIFY_API_TOKEN'),

    'google_images_actor_id' => env('APIFY_GOOGLE_IMAGES_ACTOR_ID', 'tnudF2IxzORPhg4r8'),

    'max_results_per_query' => (int) env('APIFY_MAX_RESULTS_PER_QUERY', 3),

    'run_wait_seconds' => (int) env('APIFY_RUN_WAIT_SECONDS', 180),
];
