<?php

namespace App\Services\Sword;

use App\Models\Module;
use App\Models\ModuleSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Browses SWORD module repositories to discover available modules.
 *
 * Fetches mods.d.tar.gz from repository, parses all .conf files,
 * and stores module metadata in the database.
 */
class RepositoryBrowser
{
    private ConfParser $confParser;

    public function __construct(ConfParser $confParser)
    {
        $this->confParser = $confParser;
    }

    /**
     * Refresh module catalog from all active sources.
     *
     * @return array{refreshed: int, modules_found: int, errors: array}
     */
    public function refreshAll(): array
    {
        $sources = ModuleSource::where('is_active', true)->get();
        $totalModules = 0;
        $errors = [];

        foreach ($sources as $source) {
            try {
                $count = $this->refreshSource($source);
                $totalModules += $count;
            } catch (\Throwable $e) {
                $errors[] = "{$source->caption}: {$e->getMessage()}";
                Log::error("SWORD repository refresh failed for {$source->caption}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'refreshed' => $sources->count(),
            'modules_found' => $totalModules,
            'errors' => $errors,
        ];
    }

    /**
     * Refresh module catalog from a single source.
     *
     * @return int Number of modules found/updated
     */
    public function refreshSource(ModuleSource $source): int
    {
        $tarUrl = rtrim($source->server, '/') . '/' . ltrim($source->directory, '/') . 'mods.d.tar.gz';

        Log::info("Fetching SWORD module catalog from {$tarUrl}");

        $response = Http::timeout(120)->withOptions([
            'verify' => false,
        ])->get($tarUrl);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch {$tarUrl}: HTTP {$response->status()}");
        }

        // Save tar.gz to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'sword_mods_');
        file_put_contents($tempFile, $response->body());

        try {
            $configs = $this->extractConfFiles($tempFile);
            $count = $this->processConfigs($configs, $source);

            $source->update(['last_refreshed' => now()]);

            return $count;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Extract .conf files from a tar.gz archive.
     *
     * @return array<string, string> filename => content
     */
    private function extractConfFiles(string $tarGzPath): array
    {
        $configs = [];

        // Decompress gzip
        $gzData = file_get_contents($tarGzPath);
        $tarData = @gzdecode($gzData);
        if ($tarData === false) {
            throw new \RuntimeException('Failed to decompress mods.d.tar.gz');
        }

        // Parse tar (basic tar format: 512-byte headers + data blocks)
        $offset = 0;
        $tarLen = strlen($tarData);

        while ($offset < $tarLen - 512) {
            $header = substr($tarData, $offset, 512);
            $offset += 512;

            // Check for zero block (end of archive)
            if (trim($header, "\0") === '') {
                break;
            }

            // Parse tar header
            $name = rtrim(substr($header, 0, 100), "\0");
            $sizeOctal = rtrim(substr($header, 124, 12), "\0 ");
            $size = octdec($sizeOctal);
            $typeFlag = substr($header, 156, 1);

            // Handle long filenames (GNU tar extension)
            // Check for USTAR prefix
            $prefix = rtrim(substr($header, 345, 155), "\0");
            if ($prefix !== '') {
                $name = $prefix . '/' . $name;
            }

            // Skip directories
            if ($typeFlag === '5' || $size === 0) {
                continue;
            }

            // Read file data
            $dataBlocks = (int) ceil($size / 512) * 512;
            if ($offset + $dataBlocks > $tarLen) {
                break;
            }

            $fileData = substr($tarData, $offset, $size);
            $offset += $dataBlocks;

            // Only process .conf files
            if (str_ends_with(strtolower($name), '.conf')) {
                $basename = basename($name);
                $configs[$basename] = $fileData;
            }
        }

        return $configs;
    }

    /**
     * Process parsed .conf files and upsert modules in database.
     */
    private function processConfigs(array $configs, ModuleSource $source): int
    {
        $count = 0;

        foreach ($configs as $filename => $content) {
            try {
                $parsed = $this->confParser->parse($content);
                $metadata = $this->confParser->extractMetadata($parsed);

                if (empty($metadata['key'])) {
                    continue;
                }

                $this->upsertModule($metadata, $source);
                $count++;
            } catch (\Throwable $e) {
                Log::warning("Failed to parse SWORD conf {$filename}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Upsert a module record from parsed metadata.
     */
    private function upsertModule(array $metadata, ModuleSource $source): Module
    {
        $bundled = in_array($metadata['key'], config('bible.bundled_modules', []), true);

        return Module::updateOrCreate(
            ['key' => $metadata['key']],
            [
                'name' => mb_substr($metadata['name'], 0, 255),
                'description' => mb_substr($metadata['description'], 0, 1000),
                'type' => $metadata['type'],
                'language' => mb_substr($metadata['language'], 0, 10),
                'version' => mb_substr($metadata['version'] ?? '1.0', 0, 20),
                'mod_drv' => $metadata['mod_drv'],
                'data_path' => $metadata['data_path'],
                'source_type_format' => $metadata['source_type'],
                'compress_type' => $metadata['compress_type'],
                'block_type' => $metadata['block_type'],
                'versification' => $metadata['versification'] ?? 'KJV',
                'encoding' => $metadata['encoding'] ?? 'UTF-8',
                'direction' => $metadata['direction'] ?? 'LtoR',
                'category' => $metadata['category'],
                'minimum_version' => $metadata['minimum_version'],
                'install_size' => $metadata['install_size'],
                'about' => mb_substr($metadata['description'], 0, 65535),
                'features' => $metadata['features'],
                'global_option_filters' => $metadata['global_option_filters'],
                'conf_data' => $metadata['conf_data'],
                'is_bundled' => $bundled,
                'module_source_id' => $source->id,
                'source_url' => rtrim($source->server, '/') . '/' . ltrim($source->directory, '/'),
            ]
        );
    }

    /**
     * Get available modules filtered by type and/or language.
     */
    public function getAvailableModules(?string $type = null, ?string $language = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Module::query();

        if ($type) {
            $query->where('type', $type);
        }
        if ($language) {
            $query->where('language', $language);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Search modules by keyword (name, description, key).
     */
    public function searchModules(string $keyword): \Illuminate\Database\Eloquent\Collection
    {
        return Module::where('name', 'like', "%{$keyword}%")
            ->orWhere('description', 'like', "%{$keyword}%")
            ->orWhere('key', 'like', "%{$keyword}%")
            ->orderBy('name')
            ->get();
    }
}
