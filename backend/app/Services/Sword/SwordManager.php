<?php

namespace App\Services\Sword;

use App\Models\Module;
use App\Services\Sword\Filters\FilterFactory;
use App\Services\Sword\Filters\FilterInterface;
use App\Services\Sword\Readers\DictionaryReaderInterface;
use App\Services\Sword\Readers\ReaderFactory;
use App\Services\Sword\Readers\ReaderInterface;
use App\Services\Sword\Versification\VersificationInterface;
use App\Services\Sword\Versification\VersificationRegistry;
use Illuminate\Support\Facades\Storage;

/**
 * High-level SWORD module manager.
 *
 * Provides a unified API to read from installed SWORD modules
 * by resolving the correct binary reader, markup filter, and versification.
 */
class SwordManager
{
    private VersificationRegistry $versificationRegistry;

    public function __construct(?VersificationRegistry $versificationRegistry = null)
    {
        $this->versificationRegistry = $versificationRegistry ?? new VersificationRegistry();
    }

    /**
     * Get the versification for a module based on its conf Versification= value.
     */
    public function getVersification(Module $module): VersificationInterface
    {
        $name = $module->versification ?? 'KJV';
        return $this->versificationRegistry->get($name);
    }

    /**
     * Get a text reader for a Bible or Commentary module.
     */
    public function getTextReader(Module $module): ReaderInterface
    {
        $dataPath = $this->resolveInstalledDataPath($module);
        $modDrv = $module->mod_drv ?? 'zText';
        $versification = $this->getVersification($module);

        return ReaderFactory::createTextReader($modDrv, $dataPath, $module->cipher_key, $versification);
    }

    /**
     * Get a dictionary reader for a Lexicon/Dictionary/GenBook module.
     */
    public function getDictionaryReader(Module $module): DictionaryReaderInterface
    {
        $dataPath = $this->resolveInstalledDataPath($module);
        $modDrv = $module->mod_drv ?? 'rawLD';

        return ReaderFactory::createDictionaryReader($modDrv, $dataPath, $module->key, $module->cipher_key);
    }

    /**
     * Get a markup filter for a module based on its SourceType.
     */
    public function getFilter(Module $module): FilterInterface
    {
        $sourceType = $module->source_type_format ?? 'Plain';
        return FilterFactory::create($sourceType);
    }

    /**
     * Read a verse from a module and return raw + rendered text.
     *
     * @return array{raw: string|null, html: string|null, plain: string|null}
     */
    public function readVerse(Module $module, string $osisId, int $chapter, int $verse): array
    {
        $reader = $this->getTextReader($module);
        $filter = $this->getFilter($module);

        $raw = $reader->readVerse($osisId, $chapter, $verse);

        if ($raw === null) {
            return ['raw' => null, 'html' => null, 'plain' => null];
        }

        return [
            'raw' => $raw,
            'html' => $filter->toHtml($raw, ['strongs' => true, 'morph' => true]),
            'plain' => $filter->toPlainText($raw),
        ];
    }

    /**
     * Read an entire chapter from a module.
     *
     * @return array<int, array{raw: string, html: string, plain: string, strongs_data: list<array{word: string, strongs: string[], morph: string|null}>}>
     */
    public function readChapter(Module $module, string $osisId, int $chapter): array
    {
        $reader = $this->getTextReader($module);
        $filter = $this->getFilter($module);

        $rawVerses = $reader->readChapter($osisId, $chapter);
        $result = [];

        foreach ($rawVerses as $verseNum => $raw) {
            $strongsData = method_exists($filter, 'extractStrongs')
                ? $filter->extractStrongs($raw)
                : [];

            $result[$verseNum] = [
                'raw' => $raw,
                'html' => $filter->toHtml($raw, ['strongs' => true, 'morph' => true]),
                'plain' => $filter->toPlainText($raw),
                'strongs_data' => $strongsData,
            ];
        }

        return $result;
    }

    /**
     * Read a dictionary entry.
     *
     * @return array{raw: string|null, html: string|null}
     */
    public function readDictionaryEntry(Module $module, string $key): array
    {
        $reader = $this->getDictionaryReader($module);
        $filter = $this->getFilter($module);

        $raw = $reader->readEntry($key);

        if ($raw === null) {
            return ['raw' => null, 'html' => null];
        }

        return [
            'raw' => $raw,
            'html' => $filter->toHtml($raw),
        ];
    }

    /**
     * Get all keys from a dictionary module.
     *
     * @return array<string>
     */
    public function getDictionaryKeys(Module $module): array
    {
        $reader = $this->getDictionaryReader($module);
        return $reader->getKeys();
    }

    /**
     * Check if a module has binary SWORD data files available.
     */
    public function hasDataFiles(Module $module): bool
    {
        try {
            $dataPath = $this->resolveInstalledDataPath($module);
            return is_dir($dataPath);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resolve the absolute file path to a module's binary data directory.
     */
    private function resolveInstalledDataPath(Module $module): string
    {
        if ($module->data_path) {
            $disk = Storage::disk(config('bible.module_storage_disk', 'local'));
            $fullPath = $disk->path($module->data_path);

            // The data_path in storage might need to be resolved to the actual data dir
            if (is_dir($fullPath)) {
                // Look for binary files in subdirectories
                $dataDir = $this->findDataDirectory($fullPath, $module->key);
                if ($dataDir) {
                    return $dataDir;
                }
                return $fullPath;
            }
        }

        // Fallback: standard path
        $disk = Storage::disk(config('bible.module_storage_disk', 'local'));
        $basePath = config('bible.module_storage_path', 'sword-modules');
        $fullPath = $disk->path($basePath . '/' . strtolower($module->key));

        if (is_dir($fullPath)) {
            $dataDir = $this->findDataDirectory($fullPath, $module->key);
            return $dataDir ?? $fullPath;
        }

        throw new \RuntimeException("Module data not found for {$module->key}");
    }

    /**
     * Search for the directory containing actual binary data files within an
     * installed module directory tree.
     */
    private function findDataDirectory(string $basePath, string $moduleKey): ?string
    {
        $indicators = ['ot.bzv', 'nt.bzv', 'ot.bzz', 'nt.bzz', 'ot.vss', 'nt.vss', 'ot', 'nt', 'dict.idx', 'dict.zdx'];

        // Also search for module-key-named files (e.g. strongsrealhebrew.idx)
        if ($moduleKey) {
            $indicators[] = strtolower($moduleKey) . '.idx';
            $indicators[] = strtolower($moduleKey) . '.dat';
        }

        foreach ($indicators as $file) {
            $found = $this->findFileRecursive($basePath, $file);
            if ($found) {
                return dirname($found);
            }
        }

        return null;
    }

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
}
