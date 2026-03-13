<?php

namespace App\Services\Sword\Readers;

/**
 * Interface for reading SWORD module binary data.
 */
interface ReaderInterface
{
    /**
     * Read raw text for a single verse/entry.
     *
     * @param string $osisId  OSIS book ID (for text/commentary modules)
     * @param int    $chapter Chapter number (1-based)
     * @param int    $verse   Verse number (1-based)
     * @return string|null Raw markup text, or null if not found
     */
    public function readVerse(string $osisId, int $chapter, int $verse): ?string;

    /**
     * Read all verses for a chapter.
     *
     * @param string $osisId  OSIS book ID
     * @param int    $chapter Chapter number (1-based)
     * @return array<int, string> Array of verse_number => raw text
     */
    public function readChapter(string $osisId, int $chapter): array;

    /**
     * Get the module driver name.
     */
    public function getDriverName(): string;
}
