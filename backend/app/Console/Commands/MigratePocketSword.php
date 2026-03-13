<?php

namespace App\Console\Commands;

use App\Enums\ModuleType;
use App\Models\Module;
use App\Models\ModuleSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Migrate data from a PocketSword installation to HisWord.
 *
 * Reads SWORD module .conf files and imports module metadata into the
 * HisWord modules table. Optionally copies binary data files.
 *
 * Usage:
 *   php artisan hisword:migrate-pocketsword {path} [--copy-data] [--dry-run]
 */
class MigratePocketSword extends Command
{
    protected $signature = 'hisword:migrate-pocketsword
                            {path : Path to the PocketSword/SWORD module directory}
                            {--copy-data : Copy binary data files to HisWord storage}
                            {--dry-run : Preview what would be imported without making changes}';

    protected $description = 'Import SWORD modules from a PocketSword installation into HisWord';

    public function handle(): int
    {
        $sourcePath = $this->argument('path');
        $copyData = $this->option('copy-data');
        $dryRun = $this->option('dry-run');

        if (!is_dir($sourcePath)) {
            $this->error("Directory not found: {$sourcePath}");
            return self::FAILURE;
        }

        $modsDir = $this->findModsDir($sourcePath);
        if (!$modsDir) {
            $this->error('Could not find mods.d directory. Expected: {path}/mods.d/');
            return self::FAILURE;
        }

        $confFiles = glob($modsDir . '/*.conf');
        if (empty($confFiles)) {
            $this->warn('No .conf files found.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Found ' . count($confFiles) . ' SWORD module(s).');

        $imported = 0;
        $skipped = 0;

        foreach ($confFiles as $confFile) {
            $conf = $this->parseConf($confFile);
            $key = $conf['module_key'] ?? basename($confFile, '.conf');

            if (Module::where('key', $key)->exists()) {
                $this->line("  SKIP: {$key} (already exists)");
                $skipped++;
                continue;
            }

            $type = $this->resolveModuleType($conf);

            if ($dryRun) {
                $this->line("  WOULD IMPORT: {$key} ({$type->value})");
                $imported++;
                continue;
            }

            $module = Module::create([
                'key' => $key,
                'name' => $conf['Description'] ?? $key,
                'description' => $conf['About'] ?? null,
                'type' => $type,
                'language' => $conf['Lang'] ?? 'en',
                'version' => $conf['Version'] ?? null,
                'engine' => 'sword',
                'mod_drv' => $conf['ModDrv'] ?? null,
                'data_path' => $conf['DataPath'] ?? null,
                'source_type_format' => $conf['SourceType'] ?? null,
                'compress_type' => $conf['CompressType'] ?? null,
                'block_type' => $conf['BlockType'] ?? null,
                'versification' => $conf['Versification'] ?? 'KJV',
                'encoding' => $conf['Encoding'] ?? 'UTF-8',
                'direction' => $conf['Direction'] ?? 'LtoR',
                'category' => $conf['Category'] ?? null,
                'minimum_version' => $conf['MinimumVersion'] ?? null,
                'cipher_key' => $conf['CipherKey'] ?? null,
                'about' => $conf['About'] ?? null,
                'copyright' => $conf['DistributionLicense'] ?? null,
                'conf_data' => $conf,
                'is_installed' => false,
                'is_bundled' => false,
            ]);

            if ($copyData && ($conf['DataPath'] ?? null)) {
                $this->copyModuleData($sourcePath, $conf['DataPath'], $module);
            }

            $this->line("  IMPORTED: {$key}");
            $imported++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Done. Imported: {$imported}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function findModsDir(string $basePath): ?string
    {
        $candidates = [
            $basePath . '/mods.d',
            $basePath . '/share/sword/mods.d',
            $basePath . '/.sword/mods.d',
        ];

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    private function parseConf(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $conf = [];
        $currentKey = null;

        foreach ($lines as $line) {
            // Module name header: [ModuleName]
            if (preg_match('/^\[(.+)\]$/', trim($line), $m)) {
                $conf['module_key'] = $m[1];
                continue;
            }

            // Continuation line (starts with whitespace)
            if ($currentKey && preg_match('/^\s+(.*)$/', $line, $m)) {
                $conf[$currentKey] .= "\n" . $m[1];
                continue;
            }

            // Key=Value line
            if (preg_match('/^(\w+)\s*=\s*(.*)$/', trim($line), $m)) {
                $currentKey = $m[1];
                $conf[$currentKey] = $m[2];
            }
        }

        return $conf;
    }

    private function resolveModuleType(array $conf): ModuleType
    {
        $modDrv = strtolower($conf['ModDrv'] ?? '');
        $category = strtolower($conf['Category'] ?? '');

        if (str_contains($modDrv, 'com')) {
            return ModuleType::Commentary;
        }
        if (str_contains($modDrv, 'ld')) {
            return ModuleType::Dictionary;
        }
        if ($category === 'devotional' || $category === 'daily devotional') {
            return ModuleType::Devotional;
        }
        if (str_contains($modDrv, 'genbook')) {
            return ModuleType::GenBook;
        }

        return ModuleType::Bible;
    }

    private function copyModuleData(string $sourcePath, string $dataPath, Module $module): void
    {
        $fullSource = rtrim($sourcePath, '/\\') . '/' . ltrim($dataPath, './');
        if (!is_dir($fullSource)) {
            $this->warn("    Data dir not found: {$fullSource}");
            return;
        }

        $disk = Storage::disk(config('bible.module_storage_disk', 'local'));
        $targetBase = config('bible.module_storage_path', 'sword-modules');
        $targetPath = $targetBase . '/' . strtolower($module->key);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullSource, \FilesystemIterator::SKIP_DOTS)
        );

        $count = 0;
        foreach ($files as $file) {
            $relativePath = str_replace($fullSource, '', $file->getPathname());
            $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
            $disk->put($targetPath . '/' . $relativePath, file_get_contents($file->getPathname()));
            $count++;
        }

        $module->update([
            'data_path' => $targetPath,
            'is_installed' => true,
        ]);

        $this->line("    Copied {$count} file(s) to storage.");
    }
}
