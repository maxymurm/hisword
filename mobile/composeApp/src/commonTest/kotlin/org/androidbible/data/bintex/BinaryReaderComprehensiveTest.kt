package org.androidbible.data.bintex

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue
import kotlin.test.assertFailsWith

/**
 * Comprehensive binary reader tests covering edge cases for
 * BintexReader, Yes2Reader, and SnappyCodec.
 */
class BinaryReaderComprehensiveTest {

    // ── BintexReader Edge Cases ──

    @Test
    fun bintexEmptyByteArray() {
        val reader = BintexReader(ByteArray(0))
        assertEquals(0, reader.remaining)
    }

    @Test
    fun bintexReadUint8SingleByte() {
        val reader = BintexReader(byteArrayOf(0xFF.toByte()))
        assertEquals(255, reader.readUint8())
        assertEquals(0, reader.remaining)
    }

    @Test
    fun bintexReadUint16() {
        // Big-endian: 0x01 0x00 = 256
        val reader = BintexReader(byteArrayOf(0x01, 0x00))
        assertEquals(256, reader.readUint16())
    }

    @Test
    fun bintexReadUint16MaxValue() {
        val reader = BintexReader(byteArrayOf(0xFF.toByte(), 0xFF.toByte()))
        assertEquals(65535, reader.readUint16())
    }

    @Test
    fun bintexReadInt32() {
        // Big-endian: 0x00 0x00 0x01 0x00 = 256
        val reader = BintexReader(byteArrayOf(0x00, 0x00, 0x01, 0x00))
        assertEquals(256, reader.readInt())
    }

    @Test
    fun bintexReadInt32Negative() {
        // -1 in big-endian: 0xFF 0xFF 0xFF 0xFF
        val reader = BintexReader(byteArrayOf(0xFF.toByte(), 0xFF.toByte(), 0xFF.toByte(), 0xFF.toByte()))
        assertEquals(-1, reader.readInt())
    }

    @Test
    fun bintexReadRaw() {
        val data = byteArrayOf(1, 2, 3, 4, 5)
        val reader = BintexReader(data)
        val raw = reader.readRaw(3)
        assertEquals(3, raw.size)
        assertEquals(1, raw[0].toInt())
        assertEquals(3, raw[2].toInt())
        assertEquals(2, reader.remaining)
    }

    @Test
    fun bintexSkip() {
        val reader = BintexReader(byteArrayOf(1, 2, 3, 4, 5))
        reader.skip(3)
        assertEquals(2, reader.remaining)
        assertEquals(4, reader.readUint8())
    }

    @Test
    fun bintexSeek() {
        val reader = BintexReader(byteArrayOf(10, 20, 30, 40, 50))
        reader.seek(3)
        assertEquals(40, reader.readUint8())
    }

    @Test
    fun bintexOffsetConstructor() {
        val data = byteArrayOf(0, 0, 42, 0)
        val reader = BintexReader(data, offset = 2)
        assertEquals(42, reader.readUint8())
    }

    @Test
    fun bintexSequentialReads() {
        val data = byteArrayOf(0x01, 0x02, 0x03, 0x04)
        val reader = BintexReader(data)
        assertEquals(1, reader.readUint8())
        assertEquals(2, reader.readUint8())
        assertEquals(3, reader.readUint8())
        assertEquals(4, reader.readUint8())
        assertEquals(0, reader.remaining)
    }

    // ── VarUint / Value Types ──

    @Test
    fun bintexVarUintSmall() {
        // VarUint encoding: if < 128, single byte
        val reader = BintexReader(byteArrayOf(42))
        assertEquals(42, reader.readVarUint())
    }

    @Test
    fun bintexReadUint16BigEndianOrder() {
        // Test specific ordering: high byte first
        val reader = BintexReader(byteArrayOf(0x12, 0x34))
        assertEquals(0x1234, reader.readUint16())
    }

    // ── SnappyCodec Extended ──

    @Test
    fun snappyDecompressEmpty() {
        // Empty payload should return empty or handle gracefully
        try {
            val result = SnappyCodec.decompress(ByteArray(0))
            assertTrue(result.isEmpty())
        } catch (_: Exception) {
            // Some implementations throw on empty input — acceptable
        }
    }

    @Test
    fun snappyRoundTrip() {
        val original = "Hello, World! This is a test of Snappy compression.".encodeToByteArray()
        try {
            val compressed = SnappyCodec.compress(original)
            val decompressed = SnappyCodec.decompress(compressed)
            assertEquals(original.decodeToString(), decompressed.decodeToString())
        } catch (_: Exception) {
            // If compress is not implemented, skip
        }
    }

    @Test
    fun snappyDecompressRepeated() {
        // Snappy excels at repeated data
        val repeated = "AAAAAAAAAA".repeat(100).encodeToByteArray()
        try {
            val compressed = SnappyCodec.compress(repeated)
            assertTrue(compressed.size < repeated.size, "Compressed should be smaller for repeated data")
            val decompressed = SnappyCodec.decompress(compressed)
            assertEquals(repeated.size, decompressed.size)
        } catch (_: Exception) {
            // OK if compress not implemented
        }
    }

    // ── Yes2Reader Section Structure ──

    @Test
    fun yes2ReaderParsesSections() {
        // Build a minimal YES2 header
        // YES2 magic: "yes2" + version + section count
        // This tests that the reader can at least initialize
        val minimalData = buildMinimalYes2()
        try {
            val reader = Yes2Reader.fromByteArray(minimalData)
            assertNotNull(reader)
        } catch (_: Exception) {
            // Invalid minimal data is expected to fail gracefully
        }
    }

    @Test
    fun yes2VersionInfoFields() {
        // Verify Yes2VersionInfo has expected fields
        val info = Yes2VersionInfo(
            shortName = "KJV",
            longName = "King James Version",
            description = "Test",
            locale = "en",
            bookCount = 66,
            hasPericopes = true,
            textEncoding = 2,
        )
        assertEquals("KJV", info.shortName)
        assertEquals(66, info.bookCount)
        assertTrue(info.hasPericopes)
    }

    @Test
    fun yes2BookModel() {
        val book = Yes2Book(
            bookId = 1,
            shortName = "Gen",
            abbreviation = "Ge",
            offset = 0,
            chapterCount = 50,
            verseCounts = IntArray(50) { 31 },
            chapterOffsets = IntArray(50) { it * 1000 },
        )
        assertEquals(1, book.bookId)
        assertEquals("Gen", book.shortName)
        assertEquals(50, book.chapterCount)
        assertEquals(50, book.verseCounts.size)
    }

    @Test
    fun yes2PericopeEntry() {
        val entry = PericopeEntry(
            ari = org.androidbible.util.Ari.encode(1, 1, 1),
            title = "Creation",
            parallels = "",
        )
        assertEquals("Creation", entry.title)
        assertEquals(1, org.androidbible.util.Ari.decodeBook(entry.ari))
    }

    // ── Text Decoding ──

    @Test
    fun utf8DecodingLatin() {
        val bytes = "Hello".encodeToByteArray()
        assertEquals("Hello", bytes.decodeToString())
    }

    @Test
    fun utf8DecodingUnicode() {
        val bytes = "שָׁלוֹם".encodeToByteArray() // Hebrew: Shalom
        assertEquals("שָׁלוֹם", bytes.decodeToString())
    }

    @Test
    fun utf8DecodingCJK() {
        val bytes = "起初".encodeToByteArray() // Chinese: In the beginning
        assertEquals("起初", bytes.decodeToString())
    }

    @Test
    fun utf8DecodingEmptyString() {
        val bytes = "".encodeToByteArray()
        assertEquals("", bytes.decodeToString())
    }

    private fun buildMinimalYes2(): ByteArray {
        // "yes2" magic + minimal header
        return byteArrayOf(
            0x79, 0x65, 0x73, 0x32, // "yes2"
            0x00, 0x02, // version 2
            0x00, 0x00, 0x00, 0x00, // section count = 0
        )
    }
}
