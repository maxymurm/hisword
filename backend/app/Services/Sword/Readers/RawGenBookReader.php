<?php

namespace App\Services\Sword\Readers;

/**
 * Reads SWORD RawGenBook (uncompressed general book) modules.
 *
 * General books use a tree-based node structure:
 *   - {name}.bdt: Raw text data
 *   - {name}.bks: Block/key index mapping node keys to data offsets
 *
 * GenBooks are typically navigated by tree path (e.g., "/Chapter 1/Section 1").
 * For simplicity, we treat them as flat key-value stores since the tree
 * structure is encoded in the key paths.
 */
class RawGenBookReader implements DictionaryReaderInterface
{
    private string $dataPath;
    private string $moduleName;
    private ?string $cipherKey;
    private ?array $keyIndex = null;

    public function __construct(string $dataPath, string $moduleName, ?string $cipherKey = null)
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->moduleName = $moduleName;
        $this->cipherKey = $cipherKey;
    }

    public function getDriverName(): string
    {
        return 'RawGenBook';
    }

    public function readEntry(string $key): ?string
    {
        $this->ensureIndex();

        if (isset($this->keyIndex[$key])) {
            return $this->readData($this->keyIndex[$key]['offset'], $this->keyIndex[$key]['size']);
        }

        // Try case-insensitive and path-normalized matches
        $normalizedKey = '/' . ltrim($key, '/');
        foreach ($this->keyIndex as $k => $v) {
            if (strcasecmp($k, $key) === 0 || strcasecmp($k, $normalizedKey) === 0) {
                return $this->readData($v['offset'], $v['size']);
            }
        }

        return null;
    }

    public function getKeys(): array
    {
        $this->ensureIndex();
        return array_keys($this->keyIndex);
    }

    /**
     * Build key index from the .bks file.
     */
    private function ensureIndex(): void
    {
        if ($this->keyIndex !== null) {
            return;
        }

        $this->keyIndex = [];

        $bksPath = $this->findFile($this->moduleName . '.bks');
        if (!$bksPath) {
            return;
        }

        $bksData = file_get_contents($bksPath);
        if ($bksData === false) {
            return;
        }

        $len = strlen($bksData);
        $pos = 0;

        while ($pos < $len) {
            // Node structure: varies by SWORD version
            // Most common: 4-byte key_size, key_data, 4-byte data_offset, 4-byte data_size
            if ($pos + 4 > $len) break;

            $keySizeData = unpack('V', substr($bksData, $pos, 4));
            $keySize = $keySizeData[1] ?? 0;
            $pos += 4;

            if ($keySize === 0 || $keySize > 1024 || $pos + $keySize > $len) {
                break;
            }

            $key = rtrim(substr($bksData, $pos, $keySize), "\0");
            $pos += $keySize;

            if ($pos + 8 > $len) break;

            $entry = unpack('Voffset/Vsize', substr($bksData, $pos, 8));
            $pos += 8;

            if ($entry && $key !== '') {
                $this->keyIndex[$key] = [
                    'offset' => $entry['offset'],
                    'size' => $entry['size'],
                ];
            }

            // Skip any remaining node data (tree links: parent, left, right)
            // Skip 12 bytes for tree pointers (3 × 4-byte node IDs)
            if ($pos + 12 <= $len) {
                $pos += 12;
            }
        }
    }

    /**
     * Read text data from the .bdt file.
     */
    private function readData(int $offset, int $size): ?string
    {
        if ($size === 0) {
            return null;
        }

        $bdtPath = $this->findFile($this->moduleName . '.bdt');
        if (!$bdtPath) {
            return null;
        }

        $fh = @fopen($bdtPath, 'rb');
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
}
