<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Bible Module
    |--------------------------------------------------------------------------
    |
    | The default Bible module to use when no module is specified.
    |
    */
    'default_module' => env('BIBLE_DEFAULT_MODULE', 'KJV'),

    /*
    |--------------------------------------------------------------------------
    | Bundled Modules
    |--------------------------------------------------------------------------
    |
    | Modules that ship with the application.
    |
    */
    'bundled_modules' => [
        'KJV',
        'MHCC',
        'StrongsRealHebrew',
        'StrongsRealGreek',
        'Robinson',
    ],

    /*
    |--------------------------------------------------------------------------
    | Versification System
    |--------------------------------------------------------------------------
    */
    'default_versification' => 'KJV',

    /*
    |--------------------------------------------------------------------------
    | Reading Speed
    |--------------------------------------------------------------------------
    |
    | Average reading speed for time estimates (words per minute).
    |
    */
    'reading_speed_wpm' => 200,

    /*
    |--------------------------------------------------------------------------
    | Highlight Colors
    |--------------------------------------------------------------------------
    */
    'highlight_colors' => [
        'yellow'  => '#FFEB3B',
        'green'   => '#4CAF50',
        'blue'    => '#2196F3',
        'pink'    => '#E91E63',
        'purple'  => '#9C27B0',
        'orange'  => '#FF9800',
        'red'     => '#F44336',
        'teal'    => '#009688',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Sources
    |--------------------------------------------------------------------------
    */
    'default_sources' => [
        [
            'caption' => 'CrossWire Bible Society',
            'type'    => 'HTTP',
            'server'  => 'https://crosswire.org',
            'directory' => '/ftpmirror/pub/sword/raw/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Storage
    |--------------------------------------------------------------------------
    */
    'module_storage_disk' => env('BIBLE_MODULE_DISK', 'local'),
    'module_storage_path' => env('BIBLE_MODULE_PATH', 'sword-modules'),

    /*
    |--------------------------------------------------------------------------
    | Old & New Testament Book OSIS IDs
    |--------------------------------------------------------------------------
    */
    'ot_books' => [
        'Gen','Exod','Lev','Num','Deut','Josh','Judg','Ruth',
        '1Sam','2Sam','1Kgs','2Kgs','1Chr','2Chr','Ezra','Neh',
        'Esth','Job','Ps','Prov','Eccl','Song',
        'Isa','Jer','Lam','Ezek','Dan',
        'Hos','Joel','Amos','Obad','Jonah','Mic',
        'Nah','Hab','Zeph','Hag','Zech','Mal',
    ],

    'nt_books' => [
        'Matt','Mark','Luke','John','Acts',
        'Rom','1Cor','2Cor','Gal','Eph','Phil','Col',
        '1Thess','2Thess','1Tim','2Tim','Titus','Phlm',
        'Heb','Jas','1Pet','2Pet','1John','2John','3John','Jude','Rev',
    ],

    /*
    |--------------------------------------------------------------------------
    | OSIS ID → Human-Readable Name
    |--------------------------------------------------------------------------
    */
    'osis_to_name' => [
        'Gen' => 'Genesis', 'Exod' => 'Exodus', 'Lev' => 'Leviticus',
        'Num' => 'Numbers', 'Deut' => 'Deuteronomy', 'Josh' => 'Joshua',
        'Judg' => 'Judges', 'Ruth' => 'Ruth', '1Sam' => '1 Samuel',
        '2Sam' => '2 Samuel', '1Kgs' => '1 Kings', '2Kgs' => '2 Kings',
        '1Chr' => '1 Chronicles', '2Chr' => '2 Chronicles', 'Ezra' => 'Ezra',
        'Neh' => 'Nehemiah', 'Esth' => 'Esther', 'Job' => 'Job',
        'Ps' => 'Psalms', 'Prov' => 'Proverbs', 'Eccl' => 'Ecclesiastes',
        'Song' => 'Song of Solomon', 'Isa' => 'Isaiah', 'Jer' => 'Jeremiah',
        'Lam' => 'Lamentations', 'Ezek' => 'Ezekiel', 'Dan' => 'Daniel',
        'Hos' => 'Hosea', 'Joel' => 'Joel', 'Amos' => 'Amos',
        'Obad' => 'Obadiah', 'Jonah' => 'Jonah', 'Mic' => 'Micah',
        'Nah' => 'Nahum', 'Hab' => 'Habakkuk', 'Zeph' => 'Zephaniah',
        'Hag' => 'Haggai', 'Zech' => 'Zechariah', 'Mal' => 'Malachi',
        'Matt' => 'Matthew', 'Mark' => 'Mark', 'Luke' => 'Luke',
        'John' => 'John', 'Acts' => 'Acts', 'Rom' => 'Romans',
        '1Cor' => '1 Corinthians', '2Cor' => '2 Corinthians', 'Gal' => 'Galatians',
        'Eph' => 'Ephesians', 'Phil' => 'Philippians', 'Col' => 'Colossians',
        '1Thess' => '1 Thessalonians', '2Thess' => '2 Thessalonians',
        '1Tim' => '1 Timothy', '2Tim' => '2 Timothy', 'Titus' => 'Titus',
        'Phlm' => 'Philemon', 'Heb' => 'Hebrews', 'Jas' => 'James',
        '1Pet' => '1 Peter', '2Pet' => '2 Peter', '1John' => '1 John',
        '2John' => '2 John', '3John' => '3 John', 'Jude' => 'Jude',
        'Rev' => 'Revelation',
    ],
];
