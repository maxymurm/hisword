<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Bintex;

use App\Services\Bintex\SnappyDecompressor;
use PHPUnit\Framework\TestCase;

class SnappyDecompressorTest extends TestCase
{
    /**
     * Test decompressing a simple literal-only block.
     * Snappy format: varint(uncompressed_length), then tag=0 (literal) chunks.
     */
    public function test_decompress_literal_only(): void
    {
        // Uncompressed: "Hello" (5 bytes)
        // Snappy encoding:
        //   varint(5) = 0x05
        //   literal chunk: tag_byte = (4 << 2) | 0 = 0x10 (len-1=4, shifted left 2, type=0)
        //   then 5 bytes: "Hello"
        $compressed = "\x05\x10Hello";
        $result = SnappyDecompressor::decompress($compressed);
        $this->assertSame('Hello', $result);
    }

    /**
     * Test decompressing a block with a back-reference (copy type 1).
     * "abcabc" — first "abc" is literal, second is a copy of offset=3, len=3.
     */
    public function test_decompress_with_copy1(): void
    {
        // Uncompressed: "abcabc" (6 bytes)
        // Snappy:
        //   varint(6) = 0x06
        //   literal: tag = (2 << 2) | 0 = 0x08, data = "abc" (3 bytes, len-1=2)
        //   copy type 1: tag byte = (len-4) << 2 | offset_high_bits << 5 | 1
        //     len=3 → len-4 = -1 (can't use type 1 for len<4)
        // Actually, copy type 1 requires length 4-11. So let me use type 2 instead.
        // copy type 2: tag = ((len-1) << 2) | 2, then 2-byte LE offset
        //   len=3 → (2 << 2) | 2 = 0x0A, offset=3 → 0x03 0x00
        $compressed = "\x06\x08abc\x0A\x03\x00";
        $result = SnappyDecompressor::decompress($compressed);
        $this->assertSame('abcabc', $result);
    }

    /**
     * Test run-length encoding (copy with offset=1).
     * "aaaa" — literal "a", then copy offset=1, len=3.
     */
    public function test_decompress_run_length(): void
    {
        // Uncompressed: "aaaa" (4 bytes)
        // literal: tag = (0 << 2) | 0 = 0x00, data = "a" (len=1, len-1=0)
        // copy type 2: tag = (2 << 2) | 2 = 0x0A, offset=1 → 0x01 0x00
        $compressed = "\x04\x00a\x0A\x01\x00";
        $result = SnappyDecompressor::decompress($compressed);
        $this->assertSame('aaaa', $result);
    }

    /**
     * Test that empty data produces empty output.
     */
    public function test_decompress_empty(): void
    {
        // varint(0) = 0x00
        $compressed = "\x00";
        $result = SnappyDecompressor::decompress($compressed);
        $this->assertSame('', $result);
    }

    /**
     * Test larger literal (len > 60 uses extra byte).
     */
    public function test_decompress_large_literal(): void
    {
        // 100 bytes of 'x'
        $data = str_repeat('x', 100);
        // varint(100) = 0x64
        // literal: len-1=99 > 59, so use encoding: tag = (60 << 2) | 0 = 0xF0, then 1 byte: len-1=99
        $compressed = "\x64\xF0\x63" . $data;
        $result = SnappyDecompressor::decompress($compressed);
        $this->assertSame($data, $result);
    }

    /**
     * Test overlapping copy.
     * Pattern "abababababab" using copy of offset=2 over length > offset.
     */
    public function test_decompress_overlapping_copy(): void
    {
        // Uncompressed: "abababababab" (12 bytes)
        // literal "ab" (2 bytes): tag = (1 << 2) | 0 = 0x04, data = "ab"
        // copy type 2: len=10, offset=2: tag = (9 << 2) | 2 = 0x26, offset = 0x02 0x00
        $compressed = "\x0C\x04ab\x26\x02\x00";
        $result = SnappyDecompressor::decompress($compressed);
        $this->assertSame('abababababab', $result);
    }

    /**
     * Test that superfluous input data throws.
     */
    public function test_decompress_superfluous_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        // 1 byte output but two literal chunks makes targetIndex exceed targetLength
        // varint(1)=0x01, literal len=1: tag=0x00, data="a", then another literal
        SnappyDecompressor::decompress("\x01\x00a\x00b");
    }
}
