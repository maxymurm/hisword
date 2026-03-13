<?php

namespace App\Services\Sword\Readers;

/**
 * Reads SWORD zLD (compressed lexicon/dictionary) modules.
 *
 * Binary format for zLD:
 *   - dict.zdx: Key index (sorted keys for binary search)
 *     - Each entry: null-terminated key string + 4-byte uint32 offset into dict.zdt
 *   - dict.zdt: Compressed data blocks
 *     - Each block: 4-byte uint32 block_entry_count, then for each entry:
 *       4-byte uint32 key_size, key_data, 4-byte uint32 value_size, value_data
 *     - Blocks are zlib compressed
 *
 * Alternative format (simpler):
 *   - dict.zdx: Fixed-size key index (key padded/truncated + 8-byte offset+size)
 *   - dict.zdt: Compressed blocks containing key-value pairs
 */
class ZLDReader implements DictionaryReaderInterface
{
    private string $dataPath;
    private ?string $cipherKey;
    private ?array $keyIndex = null;

    public function __construct(string $dataPath, ?string $cipherKey = null)
    {
        $this->dataPath = rtrim($dataPath, '/\\');
        $this->cipherKey = $cipherKey;
    }

    public function getDriverName(): string
    {
        return 'zLD';
    }

    public function readEntry(string $key): ?string
    {
        $zdxPath = $this->findFile('dict.zdx');
        $zdtPath = $this->findFile('dict.zdt');

        if (!$zdxPath || !$zdtPath) {
            return null;
        }

        // Build key index if not cached
        if ($this->keyIndex === null) {
            $this->keyIndex = $this->buildKeyIndex($zdxPath, $zdtPath);
        }

        $normalizedKey = $this->normalizeKey($key);

        if (!isset($this->keyIndex[$normalizedKey])) {
            // Try case-insensitive lookup
            foreach ($this->keyIndex as $k => $v) {
                if (strcasecmp($k, $normalizedKey) === 0) {
                    return $v;
                }
            }
            return null;
        }

        return $this->keyIndex[$normalizedKey];
    }

    public function getKeys(): array
    {
        $zdxPath = $this->findFile('dict.zdx');
        $zdtPath = $this->findFile('dict.zdt');

        if (!$zdxPath || !$zdtPath) {
            return [];
        }

        if ($this->keyIndex === null) {
            $this->keyIndex = $this->buildKeyIndex($zdxPath, $zdtPath);
        }

        return array_keys($this->keyIndex);
    }

    /**
     * Build the complete key-value index from the zdx/zdt files.
     * For large dictionaries, this loads everything into memory.
     */
    private function buildKeyIndex(string $zdxPath, string $zdtPath): array
    {
        $index = [];

        // Read zdx to get block offsets
        $zdxData = file_get_contents($zdxPath);
        if ($zdxData === false) {
            return [];
        }

        // Parse zdx: entries are null-terminated key + 4-byte offset
        $zdxLen = strlen($zdxData);
        $pos = 0;
        $entries = [];

        while ($pos < $zdxLen) {
            // Read null-terminated key
            $keyEnd = strpos($zdxData, "\0", $pos);
            if ($keyEnd === false) {
                break;
            }
            $key = substr($zdxData, $pos, $keyEnd - $pos);
            $pos = $keyEnd + 1;

            // Read 4-byte offset into zdt
            if ($pos + 4 > $zdxLen) {
                break;
            }
            $offsetData = unpack('Voffset', substr($zdxData, $pos, 4));
            $pos += 4;

            if ($offsetData) {
                $entries[] = ['key' => $key, 'offset' => $offsetData['offset']];
            }
        }

        // Read zdt and decompress blocks
        $zdtData = file_get_contents($zdtPath);
        if ($zdtData === false) {
            return [];
        }

        $zdtLen = strlen($zdtData);

        // Group entries by block offset and read each block
        $blockOffsets = array_unique(array_column($entries, 'offset'));
        sort($blockOffsets);

        $blocks = [];
        foreach ($blockOffsets as $i => $offset) {
            if ($offset >= $zdtLen) {
                continue;
            }

            // Determine block size: distance to next block or end of file
            $nextOffset = $zdtLen;
            if (isset($blockOffsets[$i + 1])) {
                $nextOffset = $blockOffsets[$i + 1];
            }

            $blockSize = $nextOffset - $offset;
            if ($blockSize <= 0) {
                continue;
            }

            // Read block header: 4-byte entry count, 4-byte compressed size
            if ($offset + 8 > $zdtLen) {
                continue;
            }

            $header = unpack('VentryCount/VcompSize', substr($zdtData, $offset, 8));
            if (!$header) {
                continue;
            }

            $compData = substr($zdtData, $offset + 8, $header['compSize']);
            $decompressed = @gzuncompress($compData);
            if ($decompressed === false) {
                $decompressed = @gzinflate($compData);
            }
            if ($decompressed === false) {
                continue;
            }

            $blocks[$offset] = [
                'data' => $decompressed,
                'entryCount' => $header['entryCount'],
            ];
        }

        // Parse decompressed blocks to extract key-value pairs
        foreach ($blocks as $block) {
            $data = $block['data'];
            $dataLen = strlen($data);
            $pos = 0;

            for ($e = 0; $e < $block['entryCount'] && $pos < $dataLen; $e++) {
                // Key size (4 bytes)
                if ($pos + 4 > $dataLen) break;
                $keySize = unpack('V', substr($data, $pos, 4))['V'] ?? 0;
                $pos += 4;

                // Key data
                if ($pos + $keySize > $dataLen) break;
                $key = rtrim(substr($data, $pos, $keySize), "\0");
                $pos += $keySize;

                // Value size (4 bytes)
                if ($pos + 4 > $dataLen) break;
                $valueSize = unpack('V', substr($data, $pos, 4))['V'] ?? 0;
                $pos += 4;

                // Value data
                if ($pos + $valueSize > $dataLen) break;
                $value = substr($data, $pos, $valueSize);
                $pos += $valueSize;

                if ($this->cipherKey !== null) {
                    $value = $this->applyCipher($value);
                }

                $value = rtrim($value, "\0");

                if ($key !== '' && $value !== '') {
                    $index[$this->normalizeKey($key)] = $value;
                }
            }
        }

        return $index;
    }

    /**
     * Normalize a dictionary key for lookup (trim whitespace, etc.).
     */
    private function normalizeKey(string $key): string
    {
        return trim($key);
    }

    /**
     * Find a file in the data directory (case-insensitive search).
     */
    private function findFile(string $filename): ?string
    {
        $path = $this->dataPath . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($path)) {
            return $path;
        }

        // Case-insensitive search
        $dir = $this->dataPath;
        if (!is_dir($dir)) {
            return null;
        }

        foreach (scandir($dir) as $file) {
            if (strcasecmp($file, $filename) === 0) {
                return $dir . DIRECTORY_SEPARATOR . $file;
            }
        }

        return null;
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
