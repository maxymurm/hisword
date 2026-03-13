<?php

declare(strict_types=1);

namespace App\Services\Bintex;

use RuntimeException;

/**
 * Pure-PHP Snappy decompression — port of de.jarnbjo.jsnappy.SnappyDecompressor.
 *
 * Supports literal copies and back-references (copy with 1/2/4-byte offsets).
 */
class SnappyDecompressor
{
    /**
     * Decompress a Snappy-compressed byte string.
     */
    public static function decompress(string $input, int $offset = 0, ?int $length = null): string
    {
        $length ??= strlen($input) - $offset;
        $sourceIndex = $offset;
        $max = $offset + $length;

        // Read uncompressed length (varint)
        $targetLength = 0;
        $shift = 0;
        do {
            if ($sourceIndex >= $max) {
                throw new RuntimeException('Snappy: truncated varint in header');
            }
            $byte = ord($input[$sourceIndex]);
            $targetLength |= ($byte & 0x7f) << $shift;
            $shift += 7;
            $sourceIndex++;
        } while (($byte & 0x80) !== 0);

        $out = str_repeat("\0", $targetLength);
        $targetIndex = 0;

        while ($sourceIndex < $max) {
            if ($targetIndex >= $targetLength) {
                throw new RuntimeException("Snappy: superfluous input data at offset {$sourceIndex}");
            }

            $tag = ord($input[$sourceIndex]);
            $elementType = $tag & 3;

            switch ($elementType) {
                case 0: // Literal
                    $literalLen = ($tag >> 2) & 0x3f;
                    $sourceIndex++;
                    switch ($literalLen) {
                        case 60:
                            $literalLen = ord($input[$sourceIndex++]);
                            $literalLen++;
                            break;
                        case 61:
                            $literalLen = ord($input[$sourceIndex++]);
                            $literalLen |= ord($input[$sourceIndex++]) << 8;
                            $literalLen++;
                            break;
                        case 62:
                            $literalLen = ord($input[$sourceIndex++]);
                            $literalLen |= ord($input[$sourceIndex++]) << 8;
                            $literalLen |= ord($input[$sourceIndex++]) << 16;
                            $literalLen++;
                            break;
                        case 63:
                            $literalLen = ord($input[$sourceIndex++]);
                            $literalLen |= ord($input[$sourceIndex++]) << 8;
                            $literalLen |= ord($input[$sourceIndex++]) << 16;
                            $literalLen |= ord($input[$sourceIndex++]) << 24;
                            $literalLen++;
                            break;
                        default:
                            $literalLen++;
                            break;
                    }
                    // Copy literal bytes
                    for ($i = 0; $i < $literalLen; $i++) {
                        $out[$targetIndex++] = $input[$sourceIndex++];
                    }
                    break;

                case 1: // Copy with 1-byte offset
                    $copyLen = 4 + (($tag >> 2) & 7);
                    $copyOffset = ($tag & 0xe0) << 3;
                    $sourceIndex++;
                    $copyOffset |= ord($input[$sourceIndex++]);
                    self::copyWithinBuffer($out, $targetIndex, $copyOffset, $copyLen);
                    $targetIndex += $copyLen;
                    break;

                case 2: // Copy with 2-byte LE offset
                    $copyLen = (($tag >> 2) & 0x3f) + 1;
                    $sourceIndex++;
                    $copyOffset = ord($input[$sourceIndex++]);
                    $copyOffset |= ord($input[$sourceIndex++]) << 8;
                    self::copyWithinBuffer($out, $targetIndex, $copyOffset, $copyLen);
                    $targetIndex += $copyLen;
                    break;

                case 3: // Copy with 4-byte LE offset
                    $copyLen = (($tag >> 2) & 0x3f) + 1;
                    $sourceIndex++;
                    $copyOffset = ord($input[$sourceIndex++]);
                    $copyOffset |= ord($input[$sourceIndex++]) << 8;
                    $copyOffset |= ord($input[$sourceIndex++]) << 16;
                    $copyOffset |= ord($input[$sourceIndex++]) << 24;
                    self::copyWithinBuffer($out, $targetIndex, $copyOffset, $copyLen);
                    $targetIndex += $copyLen;
                    break;
            }
        }

        return substr($out, 0, $targetLength);
    }

    /**
     * Handle overlapping back-reference copies within the output buffer.
     */
    private static function copyWithinBuffer(string &$buffer, int $targetIndex, int $offset, int $length): void
    {
        $srcIndex = $targetIndex - $offset;
        if ($length <= $offset) {
            // Non-overlapping: direct copy
            for ($i = 0; $i < $length; $i++) {
                $buffer[$targetIndex + $i] = $buffer[$srcIndex + $i];
            }
        } elseif ($offset === 1) {
            // Run-length encoding: repeat single byte
            $byte = $buffer[$srcIndex];
            for ($i = 0; $i < $length; $i++) {
                $buffer[$targetIndex + $i] = $byte;
            }
        } else {
            // Overlapping copy — must copy byte by byte
            $remaining = $length;
            while ($remaining > 0) {
                $chunk = min($remaining, $offset);
                for ($i = 0; $i < $chunk; $i++) {
                    $buffer[$targetIndex + $i] = $buffer[$srcIndex + $i];
                }
                $targetIndex += $chunk;
                $remaining -= $chunk;
            }
        }
    }
}
