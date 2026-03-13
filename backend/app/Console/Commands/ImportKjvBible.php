<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportKjvBible extends Command
{
    protected $signature = 'bible:import-kjv {--force : Re-import even if KJV already exists}';
    protected $description = 'Download and import the public-domain KJV Bible from GitHub';

    // Book number (1-66) => [osis_id, name, abbreviation, testament, chapter_count]
    private array $bookMeta = [
        1  => ['Gen',   'Genesis',         'Gen',  'OT', 50],
        2  => ['Exod',  'Exodus',          'Exod', 'OT', 40],
        3  => ['Lev',   'Leviticus',       'Lev',  'OT', 27],
        4  => ['Num',   'Numbers',         'Num',  'OT', 36],
        5  => ['Deut',  'Deuteronomy',     'Deut', 'OT', 34],
        6  => ['Josh',  'Joshua',          'Josh', 'OT', 24],
        7  => ['Judg',  'Judges',          'Judg', 'OT', 21],
        8  => ['Ruth',  'Ruth',            'Ruth', 'OT',  4],
        9  => ['1Sam',  '1 Samuel',        '1Sam', 'OT', 31],
        10 => ['2Sam',  '2 Samuel',        '2Sam', 'OT', 24],
        11 => ['1Kgs',  '1 Kings',         '1Kgs', 'OT', 22],
        12 => ['2Kgs',  '2 Kings',         '2Kgs', 'OT', 25],
        13 => ['1Chr',  '1 Chronicles',    '1Chr', 'OT', 29],
        14 => ['2Chr',  '2 Chronicles',    '2Chr', 'OT', 36],
        15 => ['Ezra',  'Ezra',            'Ezra', 'OT', 10],
        16 => ['Neh',   'Nehemiah',        'Neh',  'OT', 13],
        17 => ['Esth',  'Esther',          'Esth', 'OT', 10],
        18 => ['Job',   'Job',             'Job',  'OT', 42],
        19 => ['Ps',    'Psalms',          'Ps',   'OT',150],
        20 => ['Prov',  'Proverbs',        'Prov', 'OT', 31],
        21 => ['Eccl',  'Ecclesiastes',    'Eccl', 'OT', 12],
        22 => ['Song',  'Song of Solomon', 'Song', 'OT',  8],
        23 => ['Isa',   'Isaiah',          'Isa',  'OT', 66],
        24 => ['Jer',   'Jeremiah',        'Jer',  'OT', 52],
        25 => ['Lam',   'Lamentations',    'Lam',  'OT',  5],
        26 => ['Ezek',  'Ezekiel',         'Ezek', 'OT', 48],
        27 => ['Dan',   'Daniel',          'Dan',  'OT', 12],
        28 => ['Hos',   'Hosea',           'Hos',  'OT', 14],
        29 => ['Joel',  'Joel',            'Joel', 'OT',  3],
        30 => ['Amos',  'Amos',            'Amos', 'OT',  9],
        31 => ['Obad',  'Obadiah',         'Obad', 'OT',  1],
        32 => ['Jonah', 'Jonah',           'Jonah','OT',  4],
        33 => ['Mic',   'Micah',           'Mic',  'OT',  7],
        34 => ['Nah',   'Nahum',           'Nah',  'OT',  3],
        35 => ['Hab',   'Habakkuk',        'Hab',  'OT',  3],
        36 => ['Zeph',  'Zephaniah',       'Zeph', 'OT',  3],
        37 => ['Hag',   'Haggai',          'Hag',  'OT',  2],
        38 => ['Zech',  'Zechariah',       'Zech', 'OT', 14],
        39 => ['Mal',   'Malachi',         'Mal',  'OT',  4],
        40 => ['Matt',  'Matthew',         'Matt', 'NT', 28],
        41 => ['Mark',  'Mark',            'Mark', 'NT', 16],
        42 => ['Luke',  'Luke',            'Luke', 'NT', 24],
        43 => ['John',  'John',            'John', 'NT', 21],
        44 => ['Acts',  'Acts',            'Acts', 'NT', 28],
        45 => ['Rom',   'Romans',          'Rom',  'NT', 16],
        46 => ['1Cor',  '1 Corinthians',   '1Cor', 'NT', 16],
        47 => ['2Cor',  '2 Corinthians',   '2Cor', 'NT', 13],
        48 => ['Gal',   'Galatians',       'Gal',  'NT',  6],
        49 => ['Eph',   'Ephesians',       'Eph',  'NT',  6],
        50 => ['Phil',  'Philippians',     'Phil', 'NT',  4],
        51 => ['Col',   'Colossians',      'Col',  'NT',  4],
        52 => ['1Thess','1 Thessalonians', '1Thess','NT', 5],
        53 => ['2Thess','2 Thessalonians', '2Thess','NT', 3],
        54 => ['1Tim',  '1 Timothy',       '1Tim', 'NT',  6],
        55 => ['2Tim',  '2 Timothy',       '2Tim', 'NT',  4],
        56 => ['Titus', 'Titus',           'Titus','NT',  3],
        57 => ['Phlm',  'Philemon',        'Phlm', 'NT',  1],
        58 => ['Heb',   'Hebrews',         'Heb',  'NT', 13],
        59 => ['Jas',   'James',           'Jas',  'NT',  5],
        60 => ['1Pet',  '1 Peter',         '1Pet', 'NT',  5],
        61 => ['2Pet',  '2 Peter',         '2Pet', 'NT',  3],
        62 => ['1John', '1 John',          '1John','NT',  5],
        63 => ['2John', '2 John',          '2John','NT',  1],
        64 => ['3John', '3 John',          '3John','NT',  1],
        65 => ['Jude',  'Jude',            'Jude', 'NT',  1],
        66 => ['Rev',   'Revelation',      'Rev',  'NT', 22],
    ];

    public function handle(): int
    {
        // Check if already imported
        $exists = DB::table('modules')->where('key', 'KJV')->exists();
        if ($exists && ! $this->option('force')) {
            $this->info('KJV already imported. Use --force to re-import.');
            return 0;
        }

        if ($exists) {
            $this->warn('Removing existing KJV data...');
            $moduleId = DB::table('modules')->where('key', 'KJV')->value('id');
            DB::table('verses')->where('module_id', $moduleId)->delete();
            DB::table('books')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('key', 'KJV')->delete();
        }

        // ── 1. Load local JSON file ──────────────────────────────────────────
        $jsonPath = database_path('kjv.json');
        if (! file_exists($jsonPath)) {
            $this->error("KJV JSON file not found at: {$jsonPath}");
            $this->line("Download it with:");
            $this->line("  Invoke-WebRequest -Uri 'https://raw.githubusercontent.com/thiagobodruk/bible/master/json/en_kjv.json' -OutFile '{$jsonPath}'");
            return 1;
        }

        $this->info("Loading KJV JSON (" . number_format(filesize($jsonPath)) . " bytes)...");
        $raw = file_get_contents($jsonPath);
        // Strip UTF-8 BOM if present
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        $books = json_decode($raw, true);
        if (! $books) {
            $this->error("Failed to parse KJV JSON: " . json_last_error_msg());
            return 1;
        }

        // ── 2. Insert module ─────────────────────────────────────────────────
        $moduleId = (string) Str::uuid();
        DB::table('modules')->insert([
            'id'           => $moduleId,
            'key'          => 'KJV',
            'name'         => 'King James Version',
            'description'  => 'The King James Version (KJV) of the Holy Bible, 1611. Public domain.',
            'type'         => 'bible',
            'language'     => 'en',
            'version'      => '1611',
            'is_installed' => true,
            'is_bundled'   => true,
            'features'     => '[]',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // ── 3. Insert books ──────────────────────────────────────────────────
        $bookInserts = [];
        foreach ($this->bookMeta as $num => [$osis, $name, $abbrev, $testament, $chapterCount]) {
            $bookInserts[] = [
                'module_id'     => $moduleId,
                'osis_id'       => $osis,
                'name'          => $name,
                'abbreviation'  => $abbrev,
                'testament'     => $testament,
                'book_order'    => $num,
                'chapter_count' => $chapterCount,
            ];
        }
        DB::table('books')->insert($bookInserts);

        // Fetch book ids back by osis_id
        $bookRows = DB::table('books')->where('module_id', $moduleId)->get(['id', 'osis_id']);
        $osisToId = [];
        foreach ($bookRows as $row) {
            $osisToId[$row->osis_id] = $row->id;
        }

        // ── 4. Parse JSON and insert chapters + verses ───────────────────────
        // JSON format: [{name, abbrev, chapters: [[verse0, verse1,...], ...]}, ...]
        // Books are in order (index 0 = Genesis, index 65 = Revelation)
        $verseBatch   = [];
        $chapterInserts = [];
        $batchSize    = 500;
        $total        = 0;

        $totalVerses = 0;
        foreach ($books as $bookJson) {
            foreach ($bookJson['chapters'] as $chapterVerses) {
                $totalVerses += count($chapterVerses);
            }
        }

        $this->output->progressStart($totalVerses);

        foreach ($books as $bookIndex => $bookJson) {
            $bookNum = $bookIndex + 1; // 1-based
            if (! isset($this->bookMeta[$bookNum])) continue;

            $osis   = $this->bookMeta[$bookNum][0];
            $bookId = $osisToId[$osis] ?? null;

            foreach ($bookJson['chapters'] as $chapIndex => $chapterVerses) {
                $chapNum    = $chapIndex + 1; // 1-based
                $verseCount = count($chapterVerses);

                if ($bookId) {
                    $chapterInserts[] = [
                        'book_id'        => $bookId,
                        'chapter_number' => $chapNum,
                        'verse_count'    => $verseCount,
                    ];
                }

                foreach ($chapterVerses as $verseIndex => $verseText) {
                    $verseNum = $verseIndex + 1; // 1-based

                    $verseBatch[] = [
                        'module_id'      => $moduleId,
                        'book_osis_id'   => $osis,
                        'chapter_number' => $chapNum,
                        'verse_number'   => $verseNum,
                        'text_raw'       => $verseText,
                        'text_rendered'  => $verseText,
                    ];

                    if (count($verseBatch) >= $batchSize) {
                        DB::table('verses')->insert($verseBatch);
                        $total += count($verseBatch);
                        $verseBatch = [];
                    }

                    $this->output->progressAdvance();
                }
            }
        }

        // Flush remaining verses
        if (! empty($verseBatch)) {
            DB::table('verses')->insert($verseBatch);
            $total += count($verseBatch);
        }

        $this->output->progressFinish();

        // ── 5. Insert chapters ───────────────────────────────────────────────
        foreach (array_chunk($chapterInserts, 500) as $chunk) {
            DB::table('chapters')->insert($chunk);
        }

        $this->info("\n✓ Imported {$total} verses, " . count($chapterInserts) . " chapters, 66 books.");
        $this->info("KJV is now available at https://backend.test/read");

        return 0;
    }
}
