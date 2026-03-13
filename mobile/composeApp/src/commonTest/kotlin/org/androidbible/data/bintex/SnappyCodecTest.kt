package org.androidbible.data.bintex

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class SnappyCodecTest {

    @Test
    fun decompressLiteralOnly() {
        // Snappy block: varint uncompressed_length=5, literal tag for 5 bytes
        // Literal tag: elementType=0, len=(5-1)<<2 = 0x10
        // So: [05] [10] [H] [e] [l] [l] [o]
        val input = byteArrayOf(
            0x05,                                     // uncompressed length = 5
            0x10,                                     // literal: len = (0x10 >> 2) & 0x3F = 4, +1 = 5 bytes
            0x48, 0x65, 0x6C, 0x6C, 0x6F              // "Hello"
        )
        val result = SnappyCodec.decompress(input)
        assertEquals("Hello", result.decodeToString())
    }

    @Test
    fun decompressCopyWithOffset1() {
        // Create: uncompressed = "aaa" (3 bytes)
        // Literal "a" (1 byte), then copy 2 bytes at offset 1
        // Literal tag: len=0, (0<<2)|0 = 0x00 → len=1
        // Copy type 1: tag = elementType(1) | (len-4)<<2 is not possible for len<4
        // Actually for type 1, len = 4 + ((tag >> 2) & 7), min is 4
        // So use type 2 instead: elementType=2, len = ((tag>>2)&0x3f)+1 = 2 → tag = (1<<2)|2 = 0x06
        // offset LE = 0x01, 0x00

        // Actually let's do: "aaaa" = literal "a" + copy 3 at offset 1
        // Literal: tag = (0 << 2) | 0 = 0x00, then 'a'
        // Copy type 2: len = ((tag>>2)&0x3f)+1 = 3 → (tag>>2)&0x3f = 2 → tag = (2<<2)|2 = 0x0A
        // offset LE: 0x01, 0x00
        val input = byteArrayOf(
            0x04,       // uncompressed length = 4
            0x00,       // literal: len = (0>>2)&0x3f = 0, +1 = 1 byte
            0x61,       // 'a'
            0x0A,       // copy type 2: len = (0x0A>>2)&0x3f = 2, +1 = 3; elementType = 2
            0x01, 0x00  // offset LE = 1
        )
        val result = SnappyCodec.decompress(input)
        assertEquals("aaaa", result.decodeToString())
    }

    @Test
    fun decompressEmptyInput() {
        // Uncompressed length = 0
        val input = byteArrayOf(0x00)
        val result = SnappyCodec.decompress(input)
        assertEquals(0, result.size)
    }

    @Test
    fun roundTripConsistency() {
        // Test with a known Snappy-compressed block
        // Literal 10 bytes "0123456789"
        // tag: len=9 → (9 << 2) | 0 = 0x24
        val text = "0123456789"
        val textBytes = text.encodeToByteArray()
        val input = byteArrayOf(
            0x0A,       // uncompressed length = 10
            0x24,       // literal: len = (0x24>>2)&0x3f = 9, +1 = 10 bytes
            *textBytes
        )
        val result = SnappyCodec.decompress(input)
        assertEquals(text, result.decodeToString())
    }

    @Test
    fun decompressExtendedLiteralLength() {
        // literal length >= 60 uses extended encoding
        // len=60: tag = (60 << 2) | 0 = 0xF0, then 1 extra byte for length
        val payload = ByteArray(70) { (it % 26 + 65).toByte() } // A-Z repeating
        val tag60 = 0xF0.toByte()         // literal with len=60 → read 1 more byte
        val extraLen = (70 - 1).toByte()   // actual length - 1 = 69

        val input = ByteArray(2 + 70 + 1)  // varint(70) + tag + extra_len + data
        input[0] = 70                       // uncompressed length varint
        input[1] = tag60
        input[2] = extraLen
        payload.copyInto(input, 3)

        val result = SnappyCodec.decompress(input)
        assertEquals(70, result.size)
        assertTrue(payload.contentEquals(result))
    }
}
