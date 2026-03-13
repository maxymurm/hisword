package org.androidbible.data.bintex

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class BintexReaderTest {

    @Test
    fun readUint8() {
        val data = byteArrayOf(0x42, 0xFF.toByte())
        val br = BintexReader(data)
        assertEquals(0x42, br.readUint8())
        assertEquals(0xFF, br.readUint8())
    }

    @Test
    fun readUint16BigEndian() {
        val data = byteArrayOf(0x01, 0x02)
        val br = BintexReader(data)
        assertEquals(0x0102, br.readUint16())
    }

    @Test
    fun readInt32BigEndian() {
        val data = byteArrayOf(0x00, 0x00, 0x01, 0x00)
        val br = BintexReader(data)
        assertEquals(256, br.readInt())
    }

    @Test
    fun readInt32Negative() {
        // -1 in two's complement BE
        val data = byteArrayOf(0xFF.toByte(), 0xFF.toByte(), 0xFF.toByte(), 0xFF.toByte())
        val br = BintexReader(data)
        assertEquals(-1, br.readInt())
    }

    @Test
    fun readVarUint7bit() {
        val data = byteArrayOf(0x7F) // 127
        val br = BintexReader(data)
        assertEquals(127, br.readVarUint())
    }

    @Test
    fun readVarUint14bit() {
        // 10xxxxxx: (0x80 | 0x01) 0x00 = 256
        val data = byteArrayOf(0x81.toByte(), 0x00)
        val br = BintexReader(data)
        assertEquals(256, br.readVarUint())
    }

    @Test
    fun readValueIntZero() {
        val data = byteArrayOf(0x0E) // special value 0
        val br = BintexReader(data)
        assertEquals(0, br.readValueInt())
    }

    @Test
    fun readValueIntImmediate() {
        val data = byteArrayOf(0x05) // immediate 5
        val br = BintexReader(data)
        assertEquals(5, br.readValueInt())
    }

    @Test
    fun readValueIntMinusOne() {
        val data = byteArrayOf(0x0F) // special value -1
        val br = BintexReader(data)
        assertEquals(-1, br.readValueInt())
    }

    @Test
    fun readValueInt1BytePositive() {
        val data = byteArrayOf(0x10, 0x2A) // 42
        val br = BintexReader(data)
        assertEquals(42, br.readValueInt())
    }

    @Test
    fun readValueInt1ByteNegative() {
        val data = byteArrayOf(0x11, 0x09) // ~9 = -10
        val br = BintexReader(data)
        assertEquals(-10, br.readValueInt())
    }

    @Test
    fun readValueStringNull() {
        val data = byteArrayOf(0x0C)
        val br = BintexReader(data)
        assertNull(br.readValueString())
    }

    @Test
    fun readValueStringEmpty() {
        val data = byteArrayOf(0x0D)
        val br = BintexReader(data)
        assertEquals("", br.readValueString())
    }

    @Test
    fun readValueString8BitInline() {
        // 0x53 = 8-bit string, len=3; 'A', 'B', 'C'
        val data = byteArrayOf(0x53, 0x41, 0x42, 0x43)
        val br = BintexReader(data)
        assertEquals("ABC", br.readValueString())
    }

    @Test
    fun readValueString16BitInline() {
        // 0x62 = 16-bit string, len=2; 'H'=0x0048, 'i'=0x0069
        val data = byteArrayOf(0x62, 0x00, 0x48, 0x00, 0x69)
        val br = BintexReader(data)
        assertEquals("Hi", br.readValueString())
    }

    @Test
    fun readValueSimpleMapEmpty() {
        val data = byteArrayOf(0x90.toByte())
        val br = BintexReader(data)
        val map = br.readValueSimpleMap()
        assertTrue(map.isEmpty())
    }

    @Test
    fun readValueSimpleMapWithEntry() {
        // 0x91, size=1, key_len=1, key='a', value=0x0E (int 0)
        val data = byteArrayOf(0x91.toByte(), 0x01, 0x01, 0x61, 0x0E)
        val br = BintexReader(data)
        val map = br.readValueSimpleMap()
        assertEquals(1, map.size)
        assertEquals(0, map["a"])
    }

    @Test
    fun readRawBytes() {
        val data = byteArrayOf(0x01, 0x02, 0x03, 0x04)
        val br = BintexReader(data, offset = 1)
        val raw = br.readRaw(2)
        assertEquals(2, raw.size)
        assertEquals(0x02, raw[0])
        assertEquals(0x03, raw[1])
    }

    @Test
    fun seekAndSkip() {
        val data = byteArrayOf(0x0A, 0x0B, 0x0C, 0x0D, 0x0E)
        val br = BintexReader(data)
        br.skip(2)
        assertEquals(2, br.pos)
        br.seek(4)
        assertEquals(4, br.pos)
        assertEquals(0x0E, br.readUint8())
    }
}
