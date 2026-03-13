<?php

namespace App\Console\Commands;

use App\Enums\ModuleType;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Module;
use App\Models\Note;
use App\Services\Bintex\BintexManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Migrate data from an androidbible installation to HisWord.
 *
 * Imports:
 *   - YES/YES2 module files as bintex modules
 *   - Markers (bookmarks, notes, highlights) from exported JSON
 *
 * Usage:
 *   php artisan hisword:migrate-androidbible {path} [--markers=markers.json] [--user-id=] [--dry-run]
 */
class MigrateAndroidBible extends Command
{
    protected $signature = 'hisword:migrate-androidbible
                            {path : Path to androidbible data directory (containing .yes/.yes2 files)}
                            {--markers= : Path to exported markers JSON file}
                            {--user-id= : UUID of the user to assign markers to}
                            {--dry-run : Preview without making changes}';

    protected $description = 'Import YES modules and user data from an androidbible installation';

    public function handle(BintexManager $bintexManager): int
    {
        $path = $this->argument('path');
        $dryRun = $this->option('dry-run');

        if (!is_dir($path)) {
            $this->error("Directory not found: {$path}");
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Migrating androidbible data...');

        // Import YES modules
        $this->importModules($path, $bintexManager, $dryRun);

        // Import markers if specified
        $markersFile = $this->option('markers');
        if ($markersFile) {
            $this->importMarkers($markersFile, $dryRun);
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Migration complete.');

        return self::SUCCESS;
    }

    private function importModules(string $path, BintexManager $bintexManager, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Scanning for YES module files...');

        $extensions = ['yes', 'yes1', 'yes2', 'yec'];
        $files = [];

        foreach ($extensions as $ext) {
            foreach (glob($path . '/*.' . $ext) as $file) {
                $files[] = $file;
            }
        }

        if (empty($files)) {
            $this->warn('No YES module files found.');
            return;
        }

        $this->info('Found ' . count($files) . ' module file(s).');
        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $basename = basename($file);

            try {
                $verification = $bintexManager->verify($file);

                if (!$verification['valid']) {
                    $this->warn("  SKIP: {$basename} — {$verification['error']}");
                    $skipped++;
                    continue;
                }

                $info = $verification['info'];
                $key = 'ab-' . strtolower($info['shortName'] ?? pathinfo($basename, PATHINFO_FILENAME));

                if (Module::where('key', $key)->exists()) {
                    $this->line("  SKIP: {$key} (already exists)");
                    $skipped++;
                    continue;
                }

                $driver = $verification['format'] === 2 ? 'yes2' : 'yes1';

                if ($dryRun) {
                    $this->line("  WOULD IMPORT: {$key} ({$driver}, {$info['book_count']} books)");
                    $imported++;
                    continue;
                }

                // Copy file to storage
                $disk = Storage::disk(config('bintex.module_disk', 'local'));
                $storagePath = config('bintex.module_path', 'bintex-modules') . '/' . $basename;
                $disk->put($storagePath, file_get_contents($file));

                Module::create([
                    'key' => $key,
                    'name' => $info['longName'] ?? $info['shortName'] ?? $basename,
                    'description' => $info['longName'] ?? null,
                    'type' => ModuleType::Bible,
                    'language' => $info['locale'] ?? 'en',
                    'engine' => 'bintex',
                    'driver' => $driver,
                    'data_path' => $storagePath,
                    'file_size' => filesize($file),
                    'is_installed' => true,
                    'is_bundled' => false,
                ]);

                $this->line("  IMPORTED: {$key} ({$info['shortName']}, {$info['book_count']} books)");
                $imported++;
            } catch (\Throwable $e) {
                $this->error("  ERROR: {$basename} — {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->info("Modules: imported={$imported}, skipped={$skipped}");
    }

    private function importMarkers(string $markersFile, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Importing markers...');

        if (!file_exists($markersFile)) {
            $this->error("Markers file not found: {$markersFile}");
            return;
        }

        $userId = $this->option('user-id');
        if (!$userId) {
            $this->error('--user-id is required when importing markers.');
            return;
        }

        $data = json_decode(file_get_contents($markersFile), true);
        if (!is_array($data)) {
            $this->error('Invalid markers JSON file.');
            return;
        }

        $markers = $data['markers'] ?? $data;
        $bookmarks = 0;
        $notes = 0;
        $highlights = 0;

        foreach ($markers as $marker) {
            $kind = $marker['kind'] ?? null;
            $gid = $marker['gid'] ?? Str::uuid()->toString();

            if ($dryRun) {
                match ($kind) {
                    0 => $bookmarks++,
                    1 => $notes++,
                    2 => $highlights++,
                    default => null,
                };
                continue;
            }

            $ari = $marker['ari'] ?? 0;
            $bookId = ($ari >> 16) & 0xFF;
            $chapter = ($ari >> 8) & 0xFF;
            $verse = $ari & 0xFF;
            $osisId = self::BOOK_ID_TO_OSIS[$bookId] ?? "Book{$bookId}";

            match ($kind) {
                0 => $this->importBookmark($userId, $gid, $osisId, $chapter, $verse, $marker, $bookmarks),
                1 => $this->importNote($userId, $gid, $osisId, $chapter, $verse, $marker, $notes),
                2 => $this->importHighlight($userId, $gid, $osisId, $chapter, $verse, $marker, $highlights),
                default => null,
            };
        }

        $this->info("Markers: bookmarks={$bookmarks}, notes={$notes}, highlights={$highlights}");
    }

    private function importBookmark(string $userId, string $gid, string $osisId, int $chapter, int $verse, array $marker, int &$count): void
    {
        Bookmark::firstOrCreate(
            ['id' => $gid],
            [
                'user_id' => $userId,
                'book_osis_id' => $osisId,
                'chapter_number' => $chapter,
                'verse_start' => $verse,
                'verse_end' => $verse,
                'label' => $marker['caption'] ?? '',
                'module_key' => $marker['versionId'] ?? null,
            ],
        );
        $count++;
    }

    private function importNote(string $userId, string $gid, string $osisId, int $chapter, int $verse, array $marker, int &$count): void
    {
        Note::firstOrCreate(
            ['id' => $gid],
            [
                'user_id' => $userId,
                'book_osis_id' => $osisId,
                'chapter_number' => $chapter,
                'verse_start' => $verse,
                'verse_end' => $verse,
                'title' => $marker['caption'] ?? '',
                'content' => $marker['content'] ?? '',
            ],
        );
        $count++;
    }

    private function importHighlight(string $userId, string $gid, string $osisId, int $chapter, int $verse, array $marker, int &$count): void
    {
        // Map androidbible color index to color name
        $colorMap = [1 => 'yellow', 2 => 'green', 3 => 'blue', 4 => 'pink', 5 => 'purple', 6 => 'orange'];
        $color = $colorMap[$marker['colorRgb'] ?? 1] ?? 'yellow';

        Highlight::firstOrCreate(
            ['id' => $gid],
            [
                'user_id' => $userId,
                'book_osis_id' => $osisId,
                'chapter_number' => $chapter,
                'verse_number' => $verse,
                'color' => $color,
                'module_key' => $marker['versionId'] ?? null,
            ],
        );
        $count++;
    }

    /** Map androidbible bookId (1-66) to OSIS IDs. */
    private const BOOK_ID_TO_OSIS = [
        1 => 'Gen', 2 => 'Exod', 3 => 'Lev', 4 => 'Num', 5 => 'Deut',
        6 => 'Josh', 7 => 'Judg', 8 => 'Ruth', 9 => '1Sam', 10 => '2Sam',
        11 => '1Kgs', 12 => '2Kgs', 13 => '1Chr', 14 => '2Chr', 15 => 'Ezra',
        16 => 'Neh', 17 => 'Esth', 18 => 'Job', 19 => 'Ps', 20 => 'Prov',
        21 => 'Eccl', 22 => 'Song', 23 => 'Isa', 24 => 'Jer', 25 => 'Lam',
        26 => 'Ezek', 27 => 'Dan', 28 => 'Hos', 29 => 'Joel', 30 => 'Amos',
        31 => 'Obad', 32 => 'Jonah', 33 => 'Mic', 34 => 'Nah', 35 => 'Hab',
        36 => 'Zeph', 37 => 'Hag', 38 => 'Zech', 39 => 'Mal',
        40 => 'Matt', 41 => 'Mark', 42 => 'Luke', 43 => 'John', 44 => 'Acts',
        45 => 'Rom', 46 => '1Cor', 47 => '2Cor', 48 => 'Gal', 49 => 'Eph',
        50 => 'Phil', 51 => 'Col', 52 => '1Thess', 53 => '2Thess', 54 => '1Tim',
        55 => '2Tim', 56 => 'Titus', 57 => 'Phlm', 58 => 'Heb', 59 => 'Jas',
        60 => '1Pet', 61 => '2Pet', 62 => '1John', 63 => '2John', 64 => '3John',
        65 => 'Jude', 66 => 'Rev',
    ];
}
