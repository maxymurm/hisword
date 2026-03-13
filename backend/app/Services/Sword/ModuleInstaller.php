<?php

namespace App\Services\Sword;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Module;
use App\Models\Verse;
use App\Services\Sword\Filters\FilterFactory;
use App\Services\Sword\Readers\ReaderFactory;
use App\Services\Sword\Versification\KjvVersification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Downloads, extracts, and indexes SWORD modules.
 *
 * Pipeline:
 * 1. Download ZIP from repository rawzip packages
 * 2. Extract to storage/app/sword-modules/{modulename}/
 * 3. Parse .conf file for metadata
 * 4. For Bible/Commentary modules: read binary data and index into verses table
 * 5. Update module record as installed
 */
class ModuleInstaller
{
    private ConfParser $confParser;
    private KjvVersification $versification;

    /** @var array<string, array{message: string, percent: int, status: string}> */
    private static array $progressState = [];

    public function __construct(ConfParser $confParser)
    {
        $this->confParser = $confParser;
        $this->versification = new KjvVersification();
    }

    /**
     * Get current progress state for a module.
     */
    public static function getProgress(string $moduleKey): ?array
    {
        return self::$progressState[strtoupper($moduleKey)] ?? null;
    }

    /**
     * Get all active progress states.
     */
    public static function getAllProgress(): array
    {
        return self::$progressState;
    }

    /**
     * Clear progress state for a module.
     */
    public static function clearProgress(string $moduleKey): void
    {
        unset(self::$progressState[strtoupper($moduleKey)]);
    }

    /**
     * Update and broadcast progress.
     */
    private function updateProgress(string $moduleKey, string $message, int $percent, string $status = 'in_progress', ?\Closure $onProgress = null): void
    {
        $data = [
            'message' => $message,
            'percent' => $percent,
            'status' => $status,
            'module' => $moduleKey,
            'timestamp' => now()->toISOString(),
        ];
        self::$progressState[strtoupper($moduleKey)] = $data;

        // Write to cache for cross-process visibility (SSE polling)
        cache()->put("sword_progress_{$moduleKey}", $data, 300);

        if ($onProgress) {
            $onProgress($message, $percent);
        }
    }

    /**
     * Install a module from a local ZIP file (e.g., bundled resources).
     */
    public function installFromZip(string $zipPath, bool $force = false, ?\Closure $onProgress = null): Module
    {
        if (!file_exists($zipPath)) {
            throw new \RuntimeException("ZIP file not found: {$zipPath}");
        }

        $onProgress ??= fn () => null;

        // Extract to temporary location to discover the module key
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sword_zip_' . md5($zipPath) . '_' . time();
        mkdir($tempDir, 0775, true);

        try {
            $this->updateProgress('_zip_install', 'Extracting archive...', 10, 'in_progress', $onProgress);

            $zip = new ZipArchive();
            $result = $zip->open($zipPath);
            if ($result !== true) {
                throw new \RuntimeException("Failed to open ZIP: error code {$result}");
            }
            $zip->extractTo($tempDir);
            $zip->close();

            // Find the .conf file to determine module key
            $confFile = $this->findConfFile($tempDir, '');
            if (!$confFile) {
                throw new \RuntimeException("No .conf file found in ZIP archive");
            }

            $parsed = $this->confParser->parseFile($confFile);
            $confData = $this->confParser->extractMetadata($parsed);
            $moduleKey = $confData['key'] ?? pathinfo($confFile, PATHINFO_FILENAME);

            $this->updateProgress($moduleKey, "Installing {$moduleKey}...", 20, 'in_progress', $onProgress);

            // Find or create the module record
            $module = Module::firstOrCreate(
                ['key' => $moduleKey],
                [
                    'name' => $confData['name'] ?? $moduleKey,
                    'type' => $confData['type'] ?? 'bible',
                    'language' => $confData['language'] ?? 'en',
                    'version' => $confData['version'] ?? '1.0',
                    'is_bundled' => true,
                ]
            );

            // Only skip if fully installed: metadata exists, data path is set, and files exist on disk
            if ($module->is_installed && !$force) {
                $disk = Storage::disk(config('bible.module_storage_disk', 'local'));
                $existingPath = $module->data_path;
                $hasDataOnDisk = $existingPath && $disk->exists($existingPath);
                $hasMetadata = $module->mod_drv !== null;

                if ($hasDataOnDisk && $hasMetadata) {
                    $this->updateProgress($moduleKey, "{$moduleKey} already installed.", 100, 'completed', $onProgress);
                    return $module;
                }
                // Module record says installed but data is incomplete — re-install
                Log::info("Re-installing {$moduleKey}: missing " . (!$hasDataOnDisk ? 'data files' : 'metadata'));
            }

            // Move extracted data to permanent storage
            $storagePath = $this->getStoragePath($moduleKey);
            $disk = Storage::disk(config('bible.module_storage_disk', 'local'));
            $destPath = $disk->path($storagePath);

            if ($disk->exists($storagePath)) {
                $disk->deleteDirectory($storagePath);
            }
            if (!is_dir($destPath)) {
                mkdir($destPath, 0775, true);
            }

            // Copy all files from temp to permanent
            $this->recursiveCopy($tempDir, $destPath);

            $this->updateProgress($moduleKey, 'Parsing configuration...', 35, 'in_progress', $onProgress);
            $this->updateModuleFromConf($module, $confData);

            $modDrv = $confData['mod_drv'] ?? $module->mod_drv;
            if ($modDrv && ReaderFactory::isTextDriver($modDrv)) {
                $this->updateProgress($moduleKey, 'Indexing text content...', 40, 'in_progress', $onProgress);

                // Use the closure wrapper that also updates cache progress
                $progressWrapper = function (string $msg, int $pct) use ($moduleKey, $onProgress) {
                    $this->updateProgress($moduleKey, $msg, $pct, 'in_progress', $onProgress);
                };

                $this->indexTextModule($module, $destPath, $confData, $progressWrapper);
            }

            $module->update([
                'is_installed' => true,
                'is_bundled' => true,
                'data_path' => $storagePath,
            ]);

            $this->updateProgress($moduleKey, "{$moduleKey} installed successfully.", 100, 'completed', $onProgress);
            return $module->fresh();
        } finally {
            // Clean up temp directory
            $this->recursiveDelete($tempDir);
        }
    }

    /**
     * Install all bundled modules from the Resources directory.
     */
    public function installBundled(?\Closure $onProgress = null): array
    {
        $resourcesPath = base_path('../Resources');
        if (!is_dir($resourcesPath)) {
            // Try alternative paths
            $resourcesPath = base_path('../../Resources');
        }

        $bundledModules = config('bible.bundled_modules', ['KJV', 'MHCC', 'Robinson', 'StrongsRealHebrew', 'StrongsRealGreek']);

        // Map module keys to ZIP filenames (handling case differences)
        $zipMap = [
            'KJV' => 'KJV.zip',
            'MHCC' => 'MHCC.zip',
            'Robinson' => 'Robinson.zip',
            'StrongsRealHebrew' => 'strongsrealhebrew.zip',
            'StrongsRealGreek' => 'strongsrealgreek.zip',
        ];

        $results = [];
        $total = count($bundledModules);
        $current = 0;

        foreach ($bundledModules as $moduleKey) {
            $current++;
            $zipName = $zipMap[$moduleKey] ?? $moduleKey . '.zip';
            $zipPath = $resourcesPath . DIRECTORY_SEPARATOR . $zipName;

            if (!file_exists($zipPath)) {
                Log::warning("Bundled ZIP not found: {$zipPath}");
                $results[$moduleKey] = ['status' => 'missing', 'message' => "ZIP not found: {$zipName}"];
                continue;
            }

            try {
                $progressWrapper = function (string $msg, int $pct) use ($moduleKey, $onProgress, $current, $total) {
                    $overallPct = (int) ((($current - 1) / $total) * 100 + ($pct / $total));
                    if ($onProgress) {
                        $onProgress("[{$current}/{$total}] {$msg}", $overallPct);
                    }
                };

                $module = $this->installFromZip($zipPath, false, $progressWrapper);
                $results[$moduleKey] = ['status' => 'installed', 'module' => $module];
            } catch (\Throwable $e) {
                Log::error("Failed to install bundled {$moduleKey}: {$e->getMessage()}");
                $results[$moduleKey] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Recursively copy a directory.
     */
    private function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            mkdir($dst, 0775, true);
        }
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * Recursively delete a directory.
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Download and install a module by key.
     *
     * @param string    $moduleKey  Module key (e.g., "KJV")
     * @param bool      $force      Force re-install even if already installed
     * @param \Closure|null $onProgress  Progress callback: fn(string $message, int $percent)
     * @return Module
     */
    public function install(string $moduleKey, bool $force = false, ?\Closure $onProgress = null): Module
    {
        $module = Module::where('key', $moduleKey)->first();

        if (!$module) {
            throw new \RuntimeException("Module '{$moduleKey}' not found. Run sword:refresh-sources first.");
        }

        if ($module->is_installed && !$force) {
            throw new \RuntimeException("Module '{$moduleKey}' is already installed. Use --force to re-install.");
        }

        $onProgress ??= fn () => null;

        $this->updateProgress($moduleKey, "Downloading {$moduleKey}...", 5, 'in_progress', $onProgress);
        $zipPath = $this->download($module, function (int $dlPercent) use ($moduleKey, $onProgress) {
            $pct = 5 + (int) ($dlPercent * 0.25); // 5-30%
            $this->updateProgress($moduleKey, "Downloading {$moduleKey}... {$dlPercent}%", $pct, 'in_progress', $onProgress);
        });

        try {
            $this->updateProgress($moduleKey, "Extracting {$moduleKey}...", 30, 'in_progress', $onProgress);
            $extractPath = $this->extract($module, $zipPath);

            $this->updateProgress($moduleKey, 'Parsing configuration...', 40, 'in_progress', $onProgress);
            $confData = $this->parseModuleConf($extractPath, $moduleKey);

            $this->updateProgress($moduleKey, 'Updating module metadata...', 50, 'in_progress', $onProgress);
            $this->updateModuleFromConf($module, $confData);

            // Index text content for Bible and Commentary modules
            $modDrv = $confData['mod_drv'] ?? $module->mod_drv;
            if (ReaderFactory::isTextDriver($modDrv)) {
                $this->updateProgress($moduleKey, 'Indexing text content...', 55, 'in_progress', $onProgress);

                $progressWrapper = function (string $msg, int $pct) use ($moduleKey, $onProgress) {
                    $this->updateProgress($moduleKey, $msg, $pct, 'in_progress', $onProgress);
                };

                $this->indexTextModule($module, $extractPath, $confData, $progressWrapper);
            }

            $module->update([
                'is_installed' => true,
                'data_path' => $this->getStoragePath($moduleKey),
            ]);

            $this->updateProgress($moduleKey, "{$moduleKey} installed successfully.", 100, 'completed', $onProgress);

            return $module->fresh();
        } finally {
            @unlink($zipPath);
        }
    }

    /**
     * Remove an installed module.
     */
    public function remove(string $moduleKey, bool $keepData = false): void
    {
        $module = Module::where('key', $moduleKey)->first();

        if (!$module) {
            throw new \RuntimeException("Module '{$moduleKey}' not found.");
        }

        if (!$keepData) {
            // Delete Bible content from database
            Verse::where('module_id', $module->id)->delete();
            Chapter::whereHas('book', fn ($q) => $q->where('module_id', $module->id))->delete();
            Book::where('module_id', $module->id)->delete();
        }

        // Delete files
        $storagePath = $this->getStoragePath($moduleKey);
        $disk = Storage::disk(config('bible.module_storage_disk', 'local'));
        if ($disk->exists($storagePath)) {
            $disk->deleteDirectory($storagePath);
        }

        $module->update([
            'is_installed' => false,
            'data_path' => null,
        ]);
    }

    /**
     * Download module ZIP from repository with optional progress tracking.
     */
    private function download(Module $module, ?\Closure $onDownloadProgress = null): string
    {
        $zipUrl = $this->resolveDownloadUrl($module);

        Log::info("Downloading SWORD module from {$zipUrl}");

        $tempPath = tempnam(sys_get_temp_dir(), "sword_{$module->key}_") . '.zip';
        $fh = fopen($tempPath, 'w');

        $options = [
            'verify' => false,
            'sink' => $fh,
            'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($onDownloadProgress) {
                // Not all adapters provide this, but Guzzle does
            },
            'progress' => function (int $downloadTotal, int $downloadedBytes) use ($onDownloadProgress) {
                if ($onDownloadProgress && $downloadTotal > 0) {
                    $pct = (int) (($downloadedBytes / $downloadTotal) * 100);
                    $onDownloadProgress($pct);
                }
            },
        ];

        $response = Http::timeout(300)->withOptions($options)->get($zipUrl);

        if (is_resource($fh)) {
            fclose($fh);
        }

        if (!$response->successful()) {
            @unlink($tempPath);
            throw new \RuntimeException("Failed to download {$module->key}: HTTP {$response->status()}");
        }

        $fileSize = filesize($tempPath);
        $module->update(['file_size' => $fileSize]);

        return $tempPath;
    }

    /**
     * Extract ZIP to module storage directory.
     */
    private function extract(Module $module, string $zipPath): string
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new \RuntimeException("Failed to open ZIP for {$module->key}: error code {$result}");
        }

        $disk = Storage::disk(config('bible.module_storage_disk', 'local'));
        $storagePath = $this->getStoragePath($module->key);

        // Clean existing directory
        if ($disk->exists($storagePath)) {
            $disk->deleteDirectory($storagePath);
        }

        $extractTo = $disk->path($storagePath);

        // Create directory
        if (!is_dir($extractTo)) {
            mkdir($extractTo, 0775, true);
        }

        $zip->extractTo($extractTo);
        $zip->close();

        return $extractTo;
    }

    /**
     * Find and parse the .conf file from extracted module directory.
     */
    private function parseModuleConf(string $extractPath, string $moduleKey): array
    {
        // Search for .conf files - they could be in mods.d/ or root
        $confFile = $this->findConfFile($extractPath, $moduleKey);

        if (!$confFile) {
            Log::warning("No .conf file found for {$moduleKey}, using existing metadata");
            return [];
        }

        $parsed = $this->confParser->parseFile($confFile);
        return $this->confParser->extractMetadata($parsed);
    }

    /**
     * Find config file recursively.
     */
    private function findConfFile(string $basePath, string $moduleKey): ?string
    {
        // Try mods.d/ directory first
        $modsDir = $basePath . DIRECTORY_SEPARATOR . 'mods.d';
        if (is_dir($modsDir)) {
            foreach (scandir($modsDir) as $file) {
                if (str_ends_with(strtolower($file), '.conf')) {
                    return $modsDir . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        // Try root level
        $lowerKey = strtolower($moduleKey);
        foreach (scandir($basePath) as $file) {
            if (str_ends_with(strtolower($file), '.conf')) {
                return $basePath . DIRECTORY_SEPARATOR . $file;
            }
        }

        // Recursive search
        $dirs = glob($basePath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $found = $this->findConfFile($dir, $moduleKey);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Update module record from parsed .conf metadata.
     */
    private function updateModuleFromConf(Module $module, array $confData): void
    {
        if (empty($confData)) {
            return;
        }

        $updates = array_filter([
            'name' => $confData['name'] ?? null,
            'description' => isset($confData['description']) ? mb_substr($confData['description'], 0, 1000) : null,
            'type' => $confData['type'] ?? null,
            'language' => $confData['language'] ?? null,
            'version' => $confData['version'] ?? null,
            'mod_drv' => $confData['mod_drv'] ?? null,
            'source_type_format' => $confData['source_type'] ?? null,
            'compress_type' => $confData['compress_type'] ?? null,
            'block_type' => $confData['block_type'] ?? null,
            'versification' => $confData['versification'] ?? null,
            'encoding' => $confData['encoding'] ?? null,
            'direction' => $confData['direction'] ?? null,
            'about' => $confData['description'] ?? null,
            'features' => $confData['features'] ?? null,
            'global_option_filters' => $confData['global_option_filters'] ?? null,
            'conf_data' => $confData['conf_data'] ?? null,
            'install_size' => $confData['install_size'] ?? null,
            'cipher_key' => $confData['cipher_key'] ?? null,
        ], fn ($v) => $v !== null);

        if (!empty($updates)) {
            $module->update($updates);
        }
    }

    /**
     * Index a text-based module (Bible or Commentary) into the database.
     * Reads binary data using the appropriate reader and stores verses.
     */
    private function indexTextModule(Module $module, string $extractPath, array $confData, \Closure $onProgress): void
    {
        $modDrv = $confData['mod_drv'] ?? $module->mod_drv;
        $sourceType = $confData['source_type'] ?? $module->source_type_format ?? 'Plain';
        $cipherKey = $confData['cipher_key'] ?? $module->cipher_key;

        // Resolve the data path within the extracted directory
        $dataPath = $this->resolveDataPath($extractPath, $confData['data_path'] ?? '', $module->key);

        if (!$dataPath || !is_dir($dataPath)) {
            Log::warning("Data path not found for {$module->key}: {$dataPath}");
            return;
        }

        $reader = ReaderFactory::createTextReader($modDrv, $dataPath, $cipherKey);
        $filter = FilterFactory::create($sourceType);

        // Clear existing content for this module
        Verse::where('module_id', $module->id)->delete();
        Chapter::whereHas('book', fn ($q) => $q->where('module_id', $module->id))->delete();
        Book::where('module_id', $module->id)->delete();

        $allBooks = $this->versification->getAllBooks();
        $totalBooks = count($allBooks);
        $processedBooks = 0;
        $totalVerses = 0;

        foreach ($allBooks as $osisId) {
            $processedBooks++;
            $percent = 60 + (int) (($processedBooks / $totalBooks) * 35);
            $bookName = $this->versification->getBookName($osisId);
            $onProgress("Indexing {$bookName}...", $percent);

            $chapterCount = $this->versification->getChapterCount($osisId);
            $testament = $this->versification->getTestament($osisId);
            $bookOrder = $this->versification->getBookOrder($osisId);

            // Try to read at least one verse to check if this book has content
            $hasContent = false;
            $bookVerseCount = 0;
            $chaptersData = [];

            for ($c = 1; $c <= $chapterCount; $c++) {
                $verses = $reader->readChapter($osisId, $c);
                if (!empty($verses)) {
                    $hasContent = true;
                    $chaptersData[$c] = $verses;
                    $bookVerseCount += count($verses);
                }
            }

            if (!$hasContent) {
                continue;
            }

            // Create Book record
            $book = Book::create([
                'module_id' => $module->id,
                'osis_id' => $osisId,
                'name' => $bookName,
                'abbreviation' => $osisId,
                'testament' => strtoupper($testament),
                'book_order' => $bookOrder,
                'chapter_count' => count($chaptersData),
            ]);

            // Create chapters and verses
            $verseBatch = [];
            foreach ($chaptersData as $chapterNum => $verses) {
                $chapter = Chapter::create([
                    'book_id' => $book->id,
                    'chapter_number' => $chapterNum,
                    'verse_count' => count($verses),
                ]);

                foreach ($verses as $verseNum => $rawText) {
                    $renderedText = $filter->toHtml($rawText, [
                        'strongs' => true,
                        'morph' => true,
                        'footnotes' => true,
                        'headings' => true,
                        'redLetters' => true,
                    ]);

                    $verseBatch[] = [
                        'module_id' => $module->id,
                        'book_osis_id' => $osisId,
                        'chapter_number' => $chapterNum,
                        'verse_number' => $verseNum,
                        'text_raw' => $rawText,
                        'text_rendered' => $renderedText,
                    ];

                    if (count($verseBatch) >= 500) {
                        Verse::insert($verseBatch);
                        $totalVerses += count($verseBatch);
                        $verseBatch = [];
                    }
                }
            }

            // Insert remaining
            if (!empty($verseBatch)) {
                Verse::insert($verseBatch);
                $totalVerses += count($verseBatch);
            }
        }

        Log::info("Indexed {$module->key}: {$totalVerses} verses");
    }

    /**
     * Resolve the data directory for a module within its extracted directory.
     */
    private function resolveDataPath(string $extractPath, string $confDataPath, string $moduleKey): ?string
    {
        // The .conf DataPath is relative, like ./modules/texts/ztext/kjv/
        $cleanPath = str_replace('./', '', $confDataPath);
        $cleanPath = rtrim($cleanPath, '/');

        // Try the conf path directly
        $candidate = $extractPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
        if (is_dir($candidate)) {
            return $candidate;
        }

        // Common patterns to search
        $patterns = [
            "modules/texts/ztext/" . strtolower($moduleKey),
            "modules/texts/rawtext/" . strtolower($moduleKey),
            "modules/comments/zcom/" . strtolower($moduleKey),
            "modules/comments/rawcom/" . strtolower($moduleKey),
            "modules/lexdict/zld/" . strtolower($moduleKey),
            "modules/lexdict/rawld/" . strtolower($moduleKey),
            "modules/genbook/rawgenbook/" . strtolower($moduleKey),
        ];

        foreach ($patterns as $pattern) {
            $candidate = $extractPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pattern);
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        // Recursive search for the data files
        return $this->findDataDir($extractPath, $moduleKey);
    }

    /**
     * Search recursively for a directory containing SWORD data files.
     */
    private function findDataDir(string $basePath, string $moduleKey): ?string
    {
        $dataFiles = ['ot.bzv', 'nt.bzv', 'ot.vss', 'nt.vss', 'dict.idx', 'dict.zdx'];

        foreach ($dataFiles as $file) {
            $found = $this->findFileRecursive($basePath, $file);
            if ($found) {
                return dirname($found);
            }
        }

        return null;
    }

    /**
     * Find a file recursively by name.
     */
    private function findFileRecursive(string $dir, string $filename): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_file($path) && strcasecmp($item, $filename) === 0) {
                return $path;
            }
            if (is_dir($path)) {
                $found = $this->findFileRecursive($path, $filename);
                if ($found) return $found;
            }
        }

        return null;
    }

    /**
     * Resolve the download URL for a module's ZIP package.
     */
    private function resolveDownloadUrl(Module $module): string
    {
        $source = $module->moduleSource;

        if ($source) {
            $base = rtrim($source->server, '/');
            $dir = $source->directory;

            // Convert /raw/ path to /packages/rawzip/
            if (str_contains($dir, '/raw/')) {
                $dir = str_replace('/raw/', '/packages/rawzip/', $dir);
            } elseif (!str_contains($dir, '/rawzip/')) {
                // Fallback: construct from server base
                $dir = '/ftpmirror/pub/sword/packages/rawzip/';
            }

            return $base . '/' . ltrim($dir, '/') . $module->key . '.zip';
        }

        // Default CrossWire URL
        return "https://crosswire.org/ftpmirror/pub/sword/packages/rawzip/{$module->key}.zip";
    }

    /**
     * Get storage path for a module.
     */
    private function getStoragePath(string $moduleKey): string
    {
        $basePath = config('bible.module_storage_path', 'sword-modules');
        return $basePath . '/' . strtolower($moduleKey);
    }
}
