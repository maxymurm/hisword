<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Management Configuration
    |--------------------------------------------------------------------------
    */

    /*
    | Module types supported
    */
    'types' => [
        'bible',
        'commentary',
        'dictionary',
        'devotional',
        'genbook',
    ],

    /*
    | Module storage disk
    */
    'storage_disk' => env('MODULES_STORAGE_DISK', 'local'),

    /*
    | Module storage path
    */
    'storage_path' => env('MODULES_STORAGE_PATH', 'modules'),

    /*
    | Auto-check for updates (in hours)
    */
    'update_check_interval' => env('MODULES_UPDATE_CHECK_HOURS', 24),

    /*
    | Maximum module file size (in MB)
    */
    'max_file_size_mb' => env('MODULES_MAX_FILE_SIZE_MB', 200),
];
