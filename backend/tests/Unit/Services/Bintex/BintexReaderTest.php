<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Bintex;

use App\Services\Bintex\BintexReader;
use PHPUnit\Framework\TestCase;

class BintexReaderTest extends TestCase
{
    // -- readUint8 --

    public function test_readUint8(): void
    {
        $br = new BintexReader("\x00\x7f\xff");
        $this->assertSame(0x00, $br->readUint8());
        $this->assertSame(0x7f, $br->readUint8());
        $this->assertSame(0xff, $br->readUint8());
        $this->assertSame(3, $br->getPos());
    }

    // -- readUint16 --

    public function test_readUint16(): void
    {
        // Big-endian: 0x0100 = 256, 0xFFFF = 65535
        $br = new BintexReader("\x01\x00\xFF\xFF");
        $this->assertSame(256, $br->readUint16());
        $this->assertSame(65535, $br->readUint16());
    }

    // -- readInt --

    public function test_readInt_positive(): void
    {
        // 0x00000001 = 1
        $br = new BintexReader("\x00\x00\x00\x01");
        $this->assertSame(1, $br->readInt());
    }

    public function test_readInt_negative(): void
    {
        // 0xFFFFFFFF = -1 as signed
        $br = new BintexReader("\xFF\xFF\xFF\xFF");
        $this->assertSame(-1, $br->readInt());
    }

    public function test_readInt_large(): void
    {
        // 0x7FFFFFFF = 2147483647
        $br = new BintexReader("\x7F\xFF\xFF\xFF");
        $this->assertSame(2147483647, $br->readInt());
    }

    // -- readVarUint --

    public function test_readVarUint_1byte(): void
    {
        // 0xxxxxxx — values 0-127
        $br = new BintexReader("\x00\x7f\x42");
        $this->assertSame(0, $br->readVarUint());
        $this->assertSame(127, $br->readVarUint());
        $this->assertSame(0x42, $br->readVarUint());
    }

    public function test_readVarUint_2byte(): void
    {
        // 10xxxxxx + next byte = 14-bit value
        // 0x80 0x00 = (0x00 << 8) | 0x00 = 0
        // 0x80 0xFF = (0x00 << 8) | 0xFF = 255
        // 0xBF 0xFF = (0x3F << 8) | 0xFF = 16383
        $br = new BintexReader("\x80\x00\x80\xFF\xBF\xFF");
        $this->assertSame(0, $br->readVarUint());
        $this->assertSame(255, $br->readVarUint());
        $this->assertSame(16383, $br->readVarUint());
    }

    public function test_readVarUint_3byte(): void
    {
        // 110xxxxx + 2 bytes = 21-bit value
        // 0xC0 0x00 0x00 = 0
        // 0xC0 0x01 0x00 = 256
        $br = new BintexReader("\xC0\x00\x00\xC0\x01\x00");
        $this->assertSame(0, $br->readVarUint());
        $this->assertSame(256, $br->readVarUint());
    }

    public function test_readVarUint_4byte(): void
    {
        // 1110xxxx + 3 bytes
        // 0xE0 0x00 0x01 0x00 = 256
        $br = new BintexReader("\xE0\x00\x01\x00");
        $this->assertSame(256, $br->readVarUint());
    }

    public function test_readVarUint_5byte(): void
    {
        // 0xF0 + 4 bytes = full 32-bit
        // 0xF0 0x00 0x00 0x01 0x00 = 256
        $br = new BintexReader("\xF0\x00\x00\x01\x00");
        $this->assertSame(256, $br->readVarUint());
    }

    // -- readValueInt --

    public function test_readValueInt_special_values(): void
    {
        // 0x0e = 0, 0x01-0x07 = immediate 1-7, 0x0f = -1
        $br = new BintexReader("\x0e\x01\x07\x0f");
        $this->assertSame(0, $br->readValueInt());
        $this->assertSame(1, $br->readValueInt());
        $this->assertSame(7, $br->readValueInt());
        $this->assertSame(-1, $br->readValueInt());
    }

    public function test_readValueInt_1byte_positive(): void
    {
        // 0x10 0x42 = 66
        $br = new BintexReader("\x10\x42");
        $this->assertSame(66, $br->readValueInt());
    }

    public function test_readValueInt_1byte_negative(): void
    {
        // 0x11 0x42 = ~66 = -67
        $br = new BintexReader("\x11\x42");
        $this->assertSame(-67, $br->readValueInt());
    }

    public function test_readValueInt_2byte_positive(): void
    {
        // 0x20 0x01 0x00 = 256
        $br = new BintexReader("\x20\x01\x00");
        $this->assertSame(256, $br->readValueInt());
    }

    public function test_readValueInt_4byte(): void
    {
        // 0x40 0x00 0x00 0x00 0x0A = 10
        $br = new BintexReader("\x40\x00\x00\x00\x0A");
        $this->assertSame(10, $br->readValueInt());
    }

    // -- readValueString --

    public function test_readValueString_null(): void
    {
        $br = new BintexReader("\x0c");
        $this->assertNull($br->readValueString());
    }

    public function test_readValueString_empty(): void
    {
        $br = new BintexReader("\x0d");
        $this->assertSame('', $br->readValueString());
    }

    public function test_readValueString_8bit_short(): void
    {
        // 0x53 = 8-bit string with len=3
        $br = new BintexReader("\x53abc");
        $this->assertSame('abc', $br->readValueString());
    }

    public function test_readValueString_16bit_short(): void
    {
        // 0x62 = 16-bit string with len=2 (4 bytes of UTF-16BE)
        // "Hi" in UTF-16BE = 0x00 0x48 0x00 0x69
        $br = new BintexReader("\x62\x00\x48\x00\x69");
        $this->assertSame('Hi', $br->readValueString());
    }

    public function test_readValueString_8bit_with_length_byte(): void
    {
        // 0x70, len=5, "Hello"
        $br = new BintexReader("\x70\x05Hello");
        $this->assertSame('Hello', $br->readValueString());
    }

    // -- readValueIntArray --

    public function test_readValueIntArray_uint8(): void
    {
        // 0xc0, len=3, bytes: 1 2 3
        $br = new BintexReader("\xc0\x03\x01\x02\x03");
        $result = $br->readValueIntArray();
        $this->assertSame([1, 2, 3], $result);
    }

    public function test_readValueIntArray_uint16(): void
    {
        // 0xc1, len=2, uint16: 0x0100=256, 0x00FF=255
        $br = new BintexReader("\xc1\x02\x01\x00\x00\xFF");
        $result = $br->readValueIntArray();
        $this->assertSame([256, 255], $result);
    }

    public function test_readValueIntArray_int32(): void
    {
        // 0xc4, len=1, int32: 0x00000001=1
        $br = new BintexReader("\xc4\x01\x00\x00\x00\x01");
        $result = $br->readValueIntArray();
        $this->assertSame([1], $result);
    }

    // -- readValueSimpleMap --

    public function test_readValueSimpleMap_empty(): void
    {
        // 0x90 = empty map
        $br = new BintexReader("\x90");
        $result = $br->readValueSimpleMap();
        $this->assertSame([], $result);
    }

    public function test_readValueSimpleMap_with_entries(): void
    {
        // 0x91, size=1, key_len=3, key="abc", value=int 0x0e (=0)
        $br = new BintexReader("\x91\x01\x03abc\x0e");
        $result = $br->readValueSimpleMap();
        $this->assertArrayHasKey('abc', $result);
        $this->assertSame(0, $result['abc']);
    }

    // -- readValue (generic) --

    public function test_readValue_dispatches_correctly(): void
    {
        // int value: 0x03 = immediate 3
        $br = new BintexReader("\x03");
        $this->assertSame(3, $br->readValue());
    }

    // -- readRaw --

    public function test_readRaw(): void
    {
        $br = new BintexReader("Hello World");
        $this->assertSame('Hello', $br->readRaw(5));
        $this->assertSame(' ', $br->readRaw(1));
        $this->assertSame('World', $br->readRaw(5));
    }

    // -- readShortString --

    public function test_readShortString_empty(): void
    {
        $br = new BintexReader("\x00");
        $this->assertSame('', $br->readShortString());
    }

    public function test_readShortString(): void
    {
        // len=2, then 2 UTF-16BE chars: "Ab" = 0x0041 0x0062
        $br = new BintexReader("\x02\x00\x41\x00\x62");
        $this->assertSame('Ab', $br->readShortString());
    }

    // -- readAutoString --

    public function test_readAutoString_8bit_short(): void
    {
        // kind=0x01, len=3, "abc"
        $br = new BintexReader("\x01\x03abc");
        $this->assertSame('abc', $br->readAutoString());
    }

    public function test_readAutoString_16bit_short(): void
    {
        // kind=0x02, len=1, "A" in UTF-16BE = 0x0041
        $br = new BintexReader("\x02\x01\x00\x41");
        $this->assertSame('A', $br->readAutoString());
    }

    // -- Position tracking --

    public function test_position_tracking(): void
    {
        $br = new BintexReader("\x00\x01\x02\x03\x04\x05");
        $this->assertSame(0, $br->getPos());
        $br->readUint8();
        $this->assertSame(1, $br->getPos());
        $br->readUint16();
        $this->assertSame(3, $br->getPos());
        $br->skip(2);
        $this->assertSame(5, $br->getPos());
        $this->assertSame(1, $br->remaining());
    }

    // -- Error handling --

    public function test_readUint8_eof_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $br = new BintexReader('');
        $br->readUint8();
    }

    public function test_readInt_eof_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $br = new BintexReader("\x00\x01");
        $br->readInt();
    }
}
