<?php

namespace App\Services\Sword\Readers;

use App\Services\Sword\Versification\KjvVersification;
use App\Services\Sword\Versification\VersificationInterface;

/**
 * Reads SWORD rawText (uncompressed text) modules.
 *
 * Binary file format for rawText:
 *   - .vss: Verse offset/size index (6 bytes/entry: uint32 offset, uint16 size)
 *   - Data file: Raw uncompressed text
 *
 * rawText4 variant uses 8 bytes per entry (uint32 offset, uint32 size).
 *
 * Separate OT/NT files: ot.vss + ot, nt.vss + nt
 */
class RawTextReader implements ReaderInterface
{
    private VersificationInterface $versification;
    private string $dataPath;
    private bool $is4Byte; // rawText4 uses 4-byte size field
    private ?string $cipherKey;

    public function __construct(string $dataPath, bool $is4Byte = false, ?string $cipherKey = null, ?VersificationInterface $versification = null)
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->is4Byte = $is4Byte;
        $this->cipherKey = $cipherKey;
        $this->versification = $versification ?? new KjvVersification();
    }

    public function getDriverName(): string
    {
        return $this->is4Byte ? 'rawText4' : 'rawText';
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
     * Read all chapters for a book.
     *
     * @return array<int, array<int, string>>
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

        return $chapters;
    }

    /**
     * Read verse text by flat index.
     */
    private function readByIndex(string $testament, int $flatIndex): ?string
    {
        $vssPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament . '.vss';
        $datPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament;

        if (!file_exists($vssPath) || !file_exists($datPath)) {
            return null;
        }

        $entrySize = $this->is4Byte ? 8 : 6;
        $fh = @fopen($vssPath, 'rb');
        if (!$fh) {
            return null;
        }

        fseek($fh, $flatIndex * $entrySize);
        $data = fread($fh, $entrySize);
        fclose($fh);

        if ($data === false || strlen($data) < $entrySize) {
            return null;
        }

        if ($this->is4Byte) {
            $entry = unpack('Voffset/Vsize', $data);
        } else {
            $entry = unpack('Voffset/vsize', $data);
        }

        if (!$entry || $entry['size'] === 0) {
            return null;
        }

        // Read the text data
        $fh = @fopen($datPath, 'rb');
        if (!$fh) {
            return null;
        }

        fseek($fh, $entry['offset']);
        $text = fread($fh, $entry['size']);
        fclose($fh);

        if ($text === false) {
            return null;
        }

        // Apply cipher key if present
        if ($this->cipherKey !== null) {
            $text = $this->applyCipher($text);
        }

        $text = rtrim($text, "\0");

        return $text ?: null;
    }

    /**
     * Apply XOR cipher.
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
