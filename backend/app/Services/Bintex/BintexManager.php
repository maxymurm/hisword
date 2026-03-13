<?php

declare(strict_types=1);

namespace App\Services\Bintex;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * High-level manager for YES1/YES2 (Bintex) Bible modules.
 *
 * Provides a unified API to read from installed YES files,
 * parallel to SwordManager for SWORD modules.
 */
class BintexManager
{
    /**
     * Open a YES2 module file and return a reader.
     */
    public function openYes2(string $path): Yes2Reader
    {
        $fullPath = $this->resolveModulePath($path);
        return Yes2Reader::fromFile($fullPath);
    }

    /**
     * Open a YES1 module file and return a reader.
     */
    public function openYes1(string $path): Yes1Reader
    {
        $fullPath = $this->resolveModulePath($path);
        return Yes1Reader::fromFile($fullPath);
    }

    /**
     * Auto-detect file format (YES1 or YES2) and return the appropriate reader.
     */
    public function open(string $path): Yes2Reader|Yes1Reader
    {
        $fullPath = $this->resolveModulePath($path);
        $data = file_get_contents($fullPath);
        if ($data === false) {
            throw new RuntimeException("Cannot read file: {$fullPath}");
        }

        return self::detectFormat($data) === 2
            ? new Yes2Reader($data)
            : new Yes1Reader($data);
    }

    /**
     * Detect whether binary data is YES1 or YES2 format.
     *
     * @return int 1 for YES1, 2 for YES2
     */
    public static function detectFormat(string $data): int
    {
        if (strlen($data) < 8) {
            throw new RuntimeException('File too small to detect YES format');
        }

        $header7 = substr($data, 0, 7);
        $expectedPrefix = "\x98\x58\x0D\x0A\x00\x5D\xE0";

        if ($header7 !== $expectedPrefix) {
            throw new RuntimeException('Not a valid YES file — header mismatch');
        }

        $versionByte = ord($data[7]);
        if ($versionByte === 0x02) {
            return 2;
        }
        if ($versionByte === 0x01) {
            return 1;
        }

        throw new RuntimeException("Unknown YES version byte: 0x" . dechex($versionByte));
    }

    /**
     * Read a single verse.
     *
     * @return string|null The verse text or null if not found
     */
    public function readVerse(string $path, int $bookId, int $chapter1, int $verse1): ?string
    {
        $reader = $this->open($path);
        $bookIndex = $this->findBookIndex($reader, $bookId);
        if ($bookIndex === null) {
            return null;
        }

        $verses = $this->loadVerseTextForReader($reader, $bookId, $bookIndex, $chapter1);
        $verseIndex = $verse1 - 1;

        return $verses[$verseIndex] ?? null;
    }

    /**
     * Read an entire chapter.
     *
     * @return array<int, string> Verse number (1-based) => verse text
     */
    public function readChapter(string $path, int $bookId, int $chapter1): array
    {
        $reader = $this->open($path);
        $bookIndex = $this->findBookIndex($reader, $bookId);
        if ($bookIndex === null) {
            return [];
        }

        $verses = $this->loadVerseTextForReader($reader, $bookId, $bookIndex, $chapter1);
        $result = [];
        foreach ($verses as $i => $text) {
            $result[$i + 1] = $text;
        }
        return $result;
    }

    /**
     * Get version/module info.
     *
     * @return array{shortName: ?string, longName: ?string, description: ?string, locale: ?string, book_count: int, format: int}
     */
    public function getModuleInfo(string $path): array
    {
        $reader = $this->open($path);
        $info = $reader->getVersionInfo();
        $info['format'] = ($reader instanceof Yes2Reader) ? 2 : 1;
        return $info;
    }

    /**
     * List all books in a module.
     *
     * @return array<int, array{bookId: int, shortName: ?string, chapter_count: int}>
     */
    public function listBooks(string $path): array
    {
        $reader = $this->open($path);
        $books = $reader->getBooksInfo();
        $result = [];

        foreach ($books as $book) {
            $result[] = [
                'bookId' => $book['bookId'],
                'shortName' => $book['shortName'] ?? null,
                'chapter_count' => $book['chapter_count'],
            ];
        }

        return $result;
    }

    /**
     * List all installed Bintex module files.
     *
     * @return string[] Relative paths within the bintex storage
     */
    public function listInstalledModules(): array
    {
        $disk = $this->getDisk();
        $basePath = $this->getBasePath();

        if (!$disk->exists($basePath)) {
            return [];
        }

        $files = $disk->files($basePath);
        $modules = [];

        foreach ($files as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['yes', 'yes1', 'yes2', 'yec'], true)) {
                $modules[] = $file;
            }
        }

        return $modules;
    }

    /**
     * Verify a YES file can be opened and read.
     *
     * @return array{valid: bool, format: int|null, error: ?string, info: ?array}
     */
    public function verify(string $path): array
    {
        try {
            $reader = $this->open($path);
            $info = $reader->getVersionInfo();
            $books = $reader->getBooksInfo();

            return [
                'valid' => true,
                'format' => ($reader instanceof Yes2Reader) ? 2 : 1,
                'error' => null,
                'info' => [
                    'shortName' => $info['shortName'] ?? null,
                    'longName' => $info['longName'] ?? null,
                    'locale' => $info['locale'] ?? null,
                    'book_count' => count($books),
                    'textEncoding' => $info['textEncoding'] ?? 2,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'format' => null,
                'error' => $e->getMessage(),
                'info' => null,
            ];
        }
    }

    // -- Private helpers --

    private function resolveModulePath(string $path): string
    {
        // If path is already absolute and exists, use it
        if (file_exists($path)) {
            return $path;
        }

        // Try resolving via storage disk
        $disk = $this->getDisk();
        $basePath = $this->getBasePath();
        $fullPath = $disk->path($basePath . '/' . $path);

        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // Also try without base path
        $fullPath = $disk->path($path);
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        throw new RuntimeException("Bintex module file not found: {$path}");
    }

    private function getDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        return Storage::disk(config('bintex.module_disk', 'local'));
    }

    private function getBasePath(): string
    {
        return config('bintex.module_path', 'bintex-modules');
    }

    /**
     * Find the book index for a given bookId.
     * YES2 uses array index; YES1 uses bookId as key.
     */
    private function findBookIndex(Yes2Reader|Yes1Reader $reader, int $bookId): ?int
    {
        $books = $reader->getBooksInfo();

        if ($reader instanceof Yes1Reader) {
            // YES1 indexes by bookId directly
            return isset($books[$bookId]) ? $bookId : null;
        }

        // YES2 — find the array index matching bookId
        foreach ($books as $index => $book) {
            if ($book['bookId'] === $bookId) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Load verse text using the appropriate method for each reader type.
     *
     * @return string[]
     */
    private function loadVerseTextForReader(Yes2Reader|Yes1Reader $reader, int $bookId, int $bookIndex, int $chapter1): array
    {
        if ($reader instanceof Yes1Reader) {
            return $reader->loadVerseText($bookId, $chapter1);
        }
        return $reader->loadVerseText($bookIndex, $chapter1);
    }
}
