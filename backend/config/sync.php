<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sync Engine Configuration
    |--------------------------------------------------------------------------
    */

    /*
    | Conflict resolution strategy: 'lww' (Last-Writer-Wins), 'manual'
    */
    'conflict_strategy' => env('SYNC_CONFLICT_STRATEGY', 'lww'),

    /*
    | Maximum number of operations per sync request
    */
    'max_batch_size' => env('SYNC_MAX_BATCH_SIZE', 500),

    /*
    | Rate limit: sync requests per minute
    */
    'rate_limit' => env('SYNC_RATE_LIMIT', 100),

    /*
    | How long to keep sync logs (in days)
    */
    'log_retention_days' => env('SYNC_LOG_RETENTION_DAYS', 90),

    /*
    | Maximum devices per user
    */
    'max_devices' => env('SYNC_MAX_DEVICES', 10),

    /*
    | Syncable entity types
    */
    'syncable_types' => [
        'bookmarks',
        'bookmark_folders',
        'highlights',
        'notes',
        'pins',
        'history',
        'user_preferences',
        'reading_plan_progress',
    ],
];
