<?php

namespace App\Services\Sword\Readers;

use App\Services\Sword\Versification\KjvVersification;
use App\Services\Sword\Versification\VersificationInterface;

/**
 * Reads SWORD zCom (compressed commentary) modules.
 *
 * Same binary format as zText but used for commentaries.
 * Commentary entries are keyed by verse reference just like Bible text.
 * Entries may span multiple verses (verse 0 can contain chapter-level commentary).
 */
class ZComReader implements ReaderInterface
{
    private VersificationInterface $versification;
    private string $dataPath;
    private ?string $cipherKey;
    private array $blockCache = [];

    public function __construct(string $dataPath, ?string $cipherKey = null, ?VersificationInterface $versification = null)
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->cipherKey = $cipherKey;
        $this->versification = $versification ?? new KjvVersification();
    }

    public function getDriverName(): string
    {
        return 'zCom';
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

        // Also check chapter heading (verse 0)
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

        $this->blockCache = [];
        return $verses;
    }

    private function readByIndex(string $testament, int $flatIndex): ?string
    {
        $bzvPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament . '.bzv';
        $bzsPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament . '.bzs';
        $bzzPath = $this->dataPath . DIRECTORY_SEPARATOR . $testament . '.bzz';

        if (!file_exists($bzvPath) || !file_exists($bzsPath) || !file_exists($bzzPath)) {
            return null;
        }

        $bzvEntry = $this->readBytes($bzvPath, $flatIndex * 10, 10);
        if ($bzvEntry === null || strlen($bzvEntry) < 10) {
            return null;
        }

        $verseData = unpack('VblockNum/VoffsetInBlock/vtextSize', $bzvEntry);
        if (!$verseData || $verseData['textSize'] === 0) {
            return null;
        }

        $cacheKey = "{$testament}:{$verseData['blockNum']}";
        if (!isset($this->blockCache[$cacheKey])) {
            $blockData = $this->decompressBlock($bzsPath, $bzzPath, $verseData['blockNum']);
            if ($blockData === null) {
                return null;
            }
            $this->blockCache[$cacheKey] = $blockData;
            if (count($this->blockCache) > 20) {
                unset($this->blockCache[array_key_first($this->blockCache)]);
            }
        }

        $blockData = $this->blockCache[$cacheKey];
        if ($verseData['offsetInBlock'] + $verseData['textSize'] > strlen($blockData)) {
            return null;
        }

        $text = substr($blockData, $verseData['offsetInBlock'], $verseData['textSize']);

        if ($this->cipherKey !== null) {
            $text = $this->applyCipher($text);
        }

        return rtrim($text, "\0") ?: null;
    }

    private function decompressBlock(string $bzsPath, string $bzzPath, int $blockNum): ?string
    {
        $bzsEntry = $this->readBytes($bzsPath, $blockNum * 12, 12);
        if ($bzsEntry === null || strlen($bzsEntry) < 12) {
            return null;
        }

        $blockInfo = unpack('VblockOffset/VcompSize/VuncompSize', $bzsEntry);
        if (!$blockInfo || $blockInfo['compSize'] === 0) {
            return null;
        }

        $compressed = $this->readBytes($bzzPath, $blockInfo['blockOffset'], $blockInfo['compSize']);
        if ($compressed === null) {
            return null;
        }

        $decompressed = @gzuncompress($compressed);
        if ($decompressed === false) {
            $decompressed = @gzinflate($compressed);
        }

        return $decompressed !== false ? $decompressed : null;
    }

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
