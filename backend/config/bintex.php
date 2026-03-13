<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bintex Module Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk where YES1/YES2 module files are stored.
    |
    */
    'module_disk' => env('BINTEX_MODULE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Bintex Module Storage Path
    |--------------------------------------------------------------------------
    |
    | The path within the disk where Bintex module files are stored.
    |
    */
    'module_path' => env('BINTEX_MODULE_PATH', 'bintex-modules'),

    /*
    |--------------------------------------------------------------------------
    | Bintex Catalog URL
    |--------------------------------------------------------------------------
    |
    | URL for downloading the bintex module catalog (if any).
    |
    */
    'catalog_url' => env('BINTEX_CATALOG_URL'),
];
