<?php

namespace App\Services\Sword\Readers;

/**
 * Reads SWORD rawLD / rawLD4 (uncompressed lexicon/dictionary) modules.
 *
 * rawLD binary format:
 *   - {name}.idx: Key index. Each entry: null-terminated key + 4-byte offset + 4-byte size
 *   - {name}.dat: Uncompressed text data
 *
 * rawLD4 binary format:
 *   - {name}.idx: Fixed 8-byte records: 4-byte LE offset + 4-byte LE size
 *   - {name}.dat: At each (offset, size), the entry starts with the key on the
 *                 first line (newline-terminated), followed by the HTML content
 */
class RawLDReader implements DictionaryReaderInterface
{
    private string $dataPath;
    private bool $is4Byte;
    private ?string $cipherKey;
    private string $moduleKey;
    private ?array $keyIndex = null;

    public function __construct(string $dataPath, bool $is4Byte = false, ?string $cipherKey = null, string $moduleKey = '')
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->is4Byte = $is4Byte;
        $this->cipherKey = $cipherKey;
        $this->moduleKey = strtolower($moduleKey);
    }

    public function getDriverName(): string
    {
        return $this->is4Byte ? 'rawLD4' : 'rawLD';
    }

    public function readEntry(string $key): ?string
    {
        $this->ensureIndex();

        // Build a list of candidate keys to try
        $candidates = $this->buildKeyCandidates($key);

        foreach ($candidates as $candidate) {
            if (isset($this->keyIndex[$candidate])) {
                return $this->readData($this->keyIndex[$candidate]['offset'], $this->keyIndex[$candidate]['size']);
            }
        }

        // Case-insensitive fallback
        foreach ($candidates as $candidate) {
            foreach ($this->keyIndex as $k => $v) {
                if (strcasecmp($k, $candidate) === 0) {
                    return $this->readData($v['offset'], $v['size']);
                }
            }
        }

        return null;
    }

    /**
     * Build a list of candidate key variants to try for lookup.
     * Handles Strong's numbers with/without leading zeros and H/G prefix.
     *
     * @return array<string>
     */
    private function buildKeyCandidates(string $key): array
    {
        $key = trim($key);
        $candidates = [$key];

        // Strong's number normalization
        // Keys may come as H03254, H3254, 03254, 3254 — try all variants
        if (preg_match('/^([GH])(\d+)$/i', $key, $m)) {
            $prefix = $m[1];
            $num = $m[2];
            $numStripped = ltrim($num, '0') ?: '0';

            // Without prefix
            $candidates[] = $num;
            $candidates[] = $numStripped;

            // With prefix, stripped zeros
            $candidates[] = $prefix . $numStripped;

            // Padded variants (4 and 5 digits)
            foreach ([4, 5] as $padLen) {
                $padded = str_pad($numStripped, $padLen, '0', STR_PAD_LEFT);
                $candidates[] = $prefix . $padded;
                $candidates[] = $padded;
            }
        } elseif (preg_match('/^(\d+)$/', $key, $m)) {
            // Numeric only — try with and without leading zeros
            $numStripped = ltrim($m[1], '0') ?: '0';
            $candidates[] = $numStripped;
            foreach ([4, 5] as $padLen) {
                $candidates[] = str_pad($numStripped, $padLen, '0', STR_PAD_LEFT);
            }
            // Also try with H and G prefix
            $candidates[] = 'H' . $m[1];
            $candidates[] = 'G' . $m[1];
        }

        return array_unique($candidates);
    }

    public function getKeys(): array
    {
        $this->ensureIndex();
        return array_keys($this->keyIndex);
    }

    /**
     * Build key index from idx + dat files.
     */
    private function ensureIndex(): void
    {
        if ($this->keyIndex !== null) {
            return;
        }

        $this->keyIndex = [];

        $idxPath = $this->findFile('dict.idx') ?? $this->findIdxFileByModuleKey();
        if (!$idxPath) {
            return;
        }

        $idxData = file_get_contents($idxPath);
        if ($idxData === false) {
            return;
        }

        if ($this->is4Byte) {
            $this->parseRawLD4Index($idxData);
        } else {
            $this->parseRawLDIndex($idxData);
        }
    }

    /**
     * Parse rawLD index: each entry = null-terminated key + 4-byte offset + 4-byte size.
     */
    private function parseRawLDIndex(string $idxData): void
    {
        $len = strlen($idxData);
        $pos = 0;

        while ($pos < $len) {
            $keyEnd = strpos($idxData, "\0", $pos);
            if ($keyEnd === false) {
                break;
            }
            $key = substr($idxData, $pos, $keyEnd - $pos);
            $pos = $keyEnd + 1;

            if ($pos + 8 > $len) {
                break;
            }
            $entry = unpack('Voffset/Vsize', substr($idxData, $pos, 8));
            $pos += 8;

            if ($entry && $key !== '') {
                $this->keyIndex[trim($key)] = [
                    'offset' => $entry['offset'],
                    'size' => $entry['size'],
                    'key_in_dat' => false,
                ];
            }
        }
    }

    /**
     * Parse rawLD4 index: fixed 8-byte records (offset + size), keys are in dat file.
     */
    private function parseRawLD4Index(string $idxData): void
    {
        $len = strlen($idxData);
        if ($len < 8) {
            return;
        }

        $datPath = $this->findFile('dict.dat') ?? $this->findDatFileByModuleKey();
        if (!$datPath) {
            return;
        }

        $datFh = @fopen($datPath, 'rb');
        if (!$datFh) {
            return;
        }

        $numEntries = intdiv($len, 8);

        for ($i = 0; $i < $numEntries; $i++) {
            $rec = unpack('Voffset/Vsize', substr($idxData, $i * 8, 8));
            if (!$rec || $rec['size'] === 0) {
                continue;
            }

            // Read the key from the dat file (first line of the entry)
            fseek($datFh, $rec['offset']);
            // Read enough to find the key (keys are short)
            $peek = fread($datFh, min($rec['size'], 256));
            if ($peek === false) {
                continue;
            }

            // Key is on the first line, terminated by \n or \r\n
            $nlPos = strpos($peek, "\n");
            if ($nlPos !== false) {
                $key = rtrim(substr($peek, 0, $nlPos), "\r");
            } else {
                // No newline — entire entry might be the key
                $key = rtrim($peek, "\0\r\n");
            }

            $key = trim($key);
            if ($key !== '') {
                // For rawLD4, store offset to the content AFTER the key line
                $contentOffset = $rec['offset'] + ($nlPos !== false ? $nlPos + 1 : strlen($key));
                $contentSize = $rec['size'] - ($nlPos !== false ? $nlPos + 1 : strlen($key));

                $this->keyIndex[$key] = [
                    'offset' => $contentOffset,
                    'size' => max(0, $contentSize),
                    'key_in_dat' => true,
                ];
            }
        }

        fclose($datFh);
    }

    /**
     * Read value data from dict.dat at given offset and size.
     */
    private function readData(int $offset, int $size): ?string
    {
        if ($size === 0) {
            return null;
        }

        $datPath = $this->findFile('dict.dat') ?? $this->findDatFileByModuleKey();
        if (!$datPath) {
            return null;
        }

        $fh = @fopen($datPath, 'rb');
        if (!$fh) {
            return null;
        }

        fseek($fh, $offset);
        $data = fread($fh, $size);
        fclose($fh);

        if ($data === false) {
            return null;
        }

        if ($this->cipherKey !== null) {
            $keyLen = strlen($this->cipherKey);
            $result = '';
            for ($i = 0; $i < strlen($data); $i++) {
                $result .= chr(ord($data[$i]) ^ ord($this->cipherKey[$i % $keyLen]));
            }
            $data = $result;
        }

        return rtrim($data, "\0") ?: null;
    }

    /**
     * Find a file case-insensitively in the data directory.
     */
    private function findFile(string $filename): ?string
    {
        $path = $this->dataPath . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($path)) {
            return $path;
        }

        if (!is_dir($this->dataPath)) {
            return null;
        }

        foreach (scandir($this->dataPath) as $file) {
            if (strcasecmp($file, $filename) === 0) {
                return $this->dataPath . DIRECTORY_SEPARATOR . $file;
            }
        }

        return null;
    }

    /**
     * Try to find the .idx file by module key name (e.g. strongsrealhebrew.idx).
     */
    private function findIdxFileByModuleKey(): ?string
    {
        if ($this->moduleKey !== '') {
            $result = $this->findFile($this->moduleKey . '.idx');
            if ($result) return $result;
        }

        // Last resort: find any .idx file in the directory
        if (is_dir($this->dataPath)) {
            foreach (scandir($this->dataPath) as $file) {
                if (str_ends_with(strtolower($file), '.idx')) {
                    return $this->dataPath . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        return null;
    }

    /**
     * Try to find the .dat file by module key name (e.g. strongsrealhebrew.dat).
     */
    private function findDatFileByModuleKey(): ?string
    {
        if ($this->moduleKey !== '') {
            $result = $this->findFile($this->moduleKey . '.dat');
            if ($result) return $result;
        }

        // Last resort: find any .dat file in the directory
        if (is_dir($this->dataPath)) {
            foreach (scandir($this->dataPath) as $file) {
                if (str_ends_with(strtolower($file), '.dat')) {
                    return $this->dataPath . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        return null;
    }
}
