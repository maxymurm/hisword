<?php

namespace App\Services\Sword\Readers;

use App\Services\Sword\Versification\KjvVersification;
use App\Services\Sword\Versification\VersificationInterface;

/**
 * Reads SWORD zText (compressed text) modules.
 *
 * Binary file format for zText:
 *   - .bzs: Block start index (12 bytes/entry: uint32 offset, uint32 comp_size, uint32 uncomp_size)
 *   - .bzv: Verse index (10 bytes/entry: uint32 block_num, uint32 offset_in_block, uint16 text_size)
 *   - .bzz: Compressed data blocks (zlib deflated)
 *
 * Separate files exist for OT (ot.bzs, ot.bzv, ot.bzz) and NT (nt.bzs, nt.bzv, nt.bzz).
 *
 * To read a verse:
 * 1. Compute the flat index for the verse using the versification system
 * 2. Read the .bzv entry at (index * 10) to get block_num, offset_in_block, text_size
 * 3. Read the .bzs entry at (block_num * 12) to get block_offset, compressed_size, uncompressed_size
 * 4. Read compressed_size bytes from .bzz at block_offset
 * 5. Decompress with zlib
 * 6. Extract text_size bytes starting at offset_in_block from the decompressed data
 */
class ZTextReader implements ReaderInterface
{
    private VersificationInterface $versification;
    private string $dataPath;
    private ?string $cipherKey;

    /**
     * Cache of decompressed blocks to avoid re-reading during bulk operations.
     * Keyed by "{testament}:{block_num}".
     *
     * @var array<string, string>
     */
    private array $blockCache = [];

    public function __construct(string $dataPath, ?string $cipherKey = null, ?VersificationInterface $versification = null)
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->cipherKey = $cipherKey;
        $this->versification = $versification ?? new KjvVersification();
    }

    public function getDriverName(): string
    {
        return 'zText';
    }

    public function readVerse(string $osisId, int $chapter, int $verse): ?string
    {
        $testament = $this->versification->getTestament($osisId);
        $flatIndex = $this->versification->flatIndex($osisId, $chapter, $verse);

        return $this->readByIndex($testament, $flatIndex);
    }

    public function readChapter(string $osisId, int $chapter): array
    {
        $testament = $this->versification->getTestament($osisId);
        $verseCount = $this->versification->getVerseCount($osisId, $chapter);
        $verses = [];

        for ($v = 1; $v <= $verseCount; $v++) {
            $flatIndex = $this->versification->flatIndex($osisId, $chapter, $v);
            $text = $this->readByIndex($testament, $flatIndex);
            if ($text !== null && $text !== '') {
                $verses[$v] = $text;
            }
        }

        return $verses;
    }

    /**
     * Read all chapters for a book (used during import).
     *
     * @return array<int, array<int, string>> chapter => [verse => text]
     */
    public function readBook(string $osisId): array
    {
        $chapterCount = $this->versification->getChapterCount($osisId);
        $chapters = [];

        for ($c = 1; $c <= $chapterCount; $c++) {
            $chapterVerses = $this->readChapter($osisId, $c);
            if (!empty($chapterVerses)) {
                $chapters[$c] = $chapterVerses;
            }
        }

        // Clear block cache after processing a whole book
        $this->blockCache = [];

        return $chapters;
    }

    /**
     * Read verse text by flat index.
     */
    private function readByIndex(string $testament, int $flatIndex): ?string
    {
        $bzvPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament . '.bzv';
        $bzsPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament . '.bzs';
        $bzzPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament . '.bzz';

        if (!file_exists($bzvPath) || !file_exists($bzsPath) || !file_exists($bzzPath)) {
            return null;
        }

        // Read the verse index entry (10 bytes)
        $bzvEntry = $this->readBytes($bzvPath, $flatIndex * 10, 10);
        if ($bzvEntry === null || strlen($bzvEntry) < 10) {
            return null;
        }

        $verseData = unpack('VblockNum/VoffsetInBlock/vtextSize', $bzvEntry);
        if (!$verseData || $verseData['textSize'] === 0) {
            return null;
        }

        $blockNum = $verseData['blockNum'];
        $offsetInBlock = $verseData['offsetInBlock'];
        $textSize = $verseData['textSize'];

        // Get decompressed block data
        $cacheKey = "{$testament}:{$blockNum}";
        if (!isset($this->blockCache[$cacheKey])) {
            $blockData = $this->decompressBlock($bzsPath, $bzzPath, $blockNum);
            if ($blockData === null) {
                return null;
            }
            $this->blockCache[$cacheKey] = $blockData;

            // Keep cache bounded (max 20 blocks)
            if (count($this->blockCache) > 20) {
                $oldest = array_key_first($this->blockCache);
                unset($this->blockCache[$oldest]);
            }
        }

        $blockData = $this->blockCache[$cacheKey];

        // Extract verse text from decompressed block
        if ($offsetInBlock + $textSize > strlen($blockData)) {
            return null;
        }

        $text = substr($blockData, $offsetInBlock, $textSize);

        // Apply cipher key if present (XOR)
        if ($this->cipherKey !== null) {
            $text = $this->applyCipher($text);
        }

        // Remove null bytes
        $text = rtrim($text, "\0");

        return $text ?: null;
    }

    /**
     * Decompress a data block from the .bzz file.
     */
    private function decompressBlock(string $bzsPath, string $bzzPath, int $blockNum): ?string
    {
        // Read block start entry (12 bytes)
        $bzsEntry = $this->readBytes($bzsPath, $blockNum * 12, 12);
        if ($bzsEntry === null || strlen($bzsEntry) < 12) {
            return null;
        }

        $blockInfo = unpack('VblockOffset/VcompSize/VuncompSize', $bzsEntry);
        if (!$blockInfo || $blockInfo['compSize'] === 0) {
            return null;
        }

        // Read compressed data
        $compressed = $this->readBytes($bzzPath, $blockInfo['blockOffset'], $blockInfo['compSize']);
        if ($compressed === null) {
            return null;
        }

        // Decompress (ZIP = zlib)
        $decompressed = @gzuncompress($compressed);
        if ($decompressed === false) {
            // Try raw deflate
            $decompressed = @gzinflate($compressed);
            if ($decompressed === false) {
                return null;
            }
        }

        return $decompressed;
    }

    /**
     * Read bytes from a file at a given offset.
     */
    private function readBytes(string $path, int $offset, int $length): ?string
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return null;
        }

        fseek($fh, $offset);
        $data = fread($fh, $length);
        fclose($fh);

        return $data !== false ? $data : null;
    }

    /**
     * Apply XOR cipher (for locked modules).
     */
    private function applyCipher(string $data): string
    {
        $keyLen = strlen($this->cipherKey);
        $result = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $result .= chr(ord($data[$i]) ^ ord($this->cipherKey[$i % $keyLen]));
        }
        return $result;
    }
}
