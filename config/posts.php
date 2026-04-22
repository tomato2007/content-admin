<?php

declare(strict_types=1);

return [
    'source' => [
        'connection' => env('POSTS_SOURCE_CONNECTION', 'posts_source'),
        'qualified_table' => env('POSTS_SOURCE_QUALIFIED_TABLE', 'public.posts'),
        'schema' => env('POSTS_SOURCE_SCHEMA', 'public'),
        'table' => env('POSTS_SOURCE_TABLE', 'posts'),
        'content_column' => env('POSTS_SOURCE_CONTENT_COLUMN', 'content'),
        'media_url_column' => env('POSTS_SOURCE_MEDIA_URL_COLUMN', 'media_url'),
        'published_at_column' => env('POSTS_SOURCE_PUBLISHED_AT_COLUMN', 'published_at'),
    ],
    'scheduler' => [
        'max_posts_per_run' => (int) env('POSTS_SOURCE_MAX_POSTS_PER_RUN', 1),
        'quiet_hours_start_utc' => env('POSTS_SOURCE_QUIET_HOURS_START_UTC', '00:00'),
        'quiet_hours_end_utc' => env('POSTS_SOURCE_QUIET_HOURS_END_UTC', '06:00'),
    ],
];
