<?php

namespace App\Services\Sword\Readers;

use App\Services\Sword\Versification\KjvVersification;
use App\Services\Sword\Versification\VersificationInterface;

/**
 * Reads SWORD rawCom (uncompressed commentary) modules.
 *
 * Same as rawText but for commentaries. Uses .vss index + data files.
 * rawCom4 variant uses 4-byte size fields (8 bytes per entry instead of 6).
 */
class RawComReader implements ReaderInterface
{
    private VersificationInterface $versification;
    private string $dataPath;
    private bool $is4Byte;
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
        return $this->is4Byte ? 'rawCom4' : 'rawCom';
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

        // Check chapter heading
        $headingIndex = $this->versification->flatIndex($osisId, $chapter, 0);
        $heading = $this->readByIndex($testament, $headingIndex);
        if ($heading !== null && $heading !== '') {
            $verses[0] = $heading;
        }

        for ($v = 1; $v <= $verseCount; $v++) {
            $flatIndex = $this->versification->flatIndex($osisId, $chapter, $v);
            $text = $this->readByIndex($testament, $flatIndex);
            if ($text !== null && $text !== '') {
                $verses[$v] = $text;
            }
        }

        return $verses;
    }

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

        if ($this->cipherKey !== null) {
            $keyLen = strlen($this->cipherKey);
            $result = '';
            for ($i = 0; $i < strlen($text); $i++) {
                $result .= chr(ord($text[$i]) ^ ord($this->cipherKey[$i % $keyLen]));
            }
            $text = $result;
        }

        return rtrim($text, "\0") ?: null;
    }
}
