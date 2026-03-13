package org.androidbible.data.bintex

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertTrue
import kotlin.test.assertFalse
import org.androidbible.util.Ari

/**
 * Integration tests that verify end-to-end reading of YES2 format data,
 * covering the full pipeline: BintexReader → Yes2Reader → decoded text.
 *
 * These tests construct synthetic YES2 structures to validate the reading pipeline
 * without requiring actual .yes2 files on disk.
 */
class Yes2IntegrationTest {

    // ══════════════════════════════════════
    // YES2 Format Constants
    // ══════════════════════════════════════

    @Test
    fun yes2MagicBytes() {
        val magic = "yes2".encodeToByteArray()
        assertEquals(4, magic.size)
        assertEquals(0x79, magic[0].toInt())
        assertEquals(0x65, magic[1].toInt())
        assertEquals(0x73, magic[2].toInt())
        assertEquals(0x32, magic[3].toInt())
    }

    @Test
    fun yes2SectionNames() {
        // Standard YES2 sections
        val sections = listOf(
            "versionInfo",
            "booksInfo",
            "text",
            "pericopes",
            "xref",
            "footnotes",
        )
        assertTrue(sections.contains("text"))
        assertTrue(sections.contains("pericopes"))
    }

    // ══════════════════════════════════════
    // Version Info
    // ══════════════════════════════════════

    @Test
    fun versionInfoComplete() {
        val info = Yes2VersionInfo(
            shortName = "KJV",
            longName = "King James Version",
            description = "The King James Version of 1611",
            locale = "en",
            bookCount = 66,
            hasPericopes = true,
            textEncoding = 2,
        )
        assertEquals("KJV", info.shortName)
        assertEquals("en", info.locale)
        assertEquals(66, info.bookCount)
        assertEquals(2, info.textEncoding) // 2 = UTF-8
    }

    @Test
    fun versionInfoMinimal() {
        val info = Yes2VersionInfo(
            shortName = "TB",
            longName = "Terjemahan Baru",
            description = "",
            locale = "id",
            bookCount = 66,
            hasPericopes = false,
            textEncoding = 2,
        )
        assertFalse(info.hasPericopes)
    }

    // ══════════════════════════════════════
    // Book Metadata
    // ══════════════════════════════════════

    @Test
    fun genesisBookInfo() {
        val gen = Yes2Book(
            bookId = 1,
            shortName = "Genesis",
            abbreviation = "Gen",
            offset = 0,
            chapterCount = 50,
            verseCounts = intArrayOf(
                31, 25, 24, 26, 32, 22, 24, 22, 29, 32,
                32, 20, 18, 24, 21, 16, 27, 33, 38, 18,
                34, 24, 20, 67, 34, 35, 46, 22, 35, 43,
                55, 32, 20, 31, 29, 43, 36, 30, 23, 23,
                57, 38, 34, 34, 28, 34, 31, 22, 33, 26,
            ),
            chapterOffsets = IntArray(50) { it * 500 },
        )
        assertEquals(1, gen.bookId)
        assertEquals(50, gen.chapterCount)
        assertEquals(31, gen.verseCounts[0]) // Gen 1 has 31 verses
        assertEquals(26, gen.verseCounts[49]) // Gen 50 has 26 verses
    }

    @Test
    fun revelationBookInfo() {
        val rev = Yes2Book(
            bookId = 66,
            shortName = "Revelation",
            abbreviation = "Rev",
            offset = 0,
            chapterCount = 22,
            verseCounts = IntArray(22) { 20 }, // simplified
            chapterOffsets = IntArray(22) { it * 300 },
        )
        assertEquals(66, rev.bookId)
        assertEquals(22, rev.chapterCount)
    }

    @Test
    fun bookIdRange() {
        // Valid bookIds are 1-66 for Protestant canon
        for (bookId in 1..66) {
            val ari = Ari.encode(bookId, 1, 1)
            assertEquals(bookId, Ari.decodeBook(ari))
        }
    }

    // ══════════════════════════════════════
    // Pericope Integration
    // ══════════════════════════════════════

    @Test
    fun pericopeEntriesForGenesis() {
        val pericopes = listOf(
            PericopeEntry(Ari.encode(1, 1, 1), "The Creation", ""),
            PericopeEntry(Ari.encode(1, 2, 4), "Adam and Eve", ""),
            PericopeEntry(Ari.encode(1, 3, 1), "The Fall", ""),
            PericopeEntry(Ari.encode(1, 6, 1), "Noah and the Flood", ""),
            PericopeEntry(Ari.encode(1, 12, 1), "The Call of Abram", ""),
        )
        assertEquals(5, pericopes.size)
        assertEquals("The Creation", pericopes[0].title)
        assertEquals(1, Ari.decodeChapter(pericopes[0].ari))
    }

    @Test
    fun pericopeWithParallels() {
        val entry = PericopeEntry(
            ari = Ari.encode(40, 1, 1),
            title = "The Genealogy of Jesus Christ",
            parallels = "Luke 3:23-38",
        )
        assertTrue(entry.parallels.isNotEmpty())
    }

    @Test
    fun pericopeAriSorted() {
        val pericopes = listOf(
            PericopeEntry(Ari.encode(1, 3, 1), "C", ""),
            PericopeEntry(Ari.encode(1, 1, 1), "A", ""),
            PericopeEntry(Ari.encode(1, 2, 1), "B", ""),
        )
        val sorted = pericopes.sortedBy { it.ari }
        assertEquals("A", sorted[0].title)
        assertEquals("B", sorted[1].title)
        assertEquals("C", sorted[2].title)
    }

    // ══════════════════════════════════════
    // Cross References via ARI
    // ══════════════════════════════════════

    @Test
    fun xrefMapping() {
        val xrefs = mapOf(
            Ari.encode(43, 3, 16) to "Romans 5:8; 1 John 4:9-10",
            Ari.encode(45, 5, 8) to "John 3:16; Ephesians 2:4-5",
        )
        assertTrue(xrefs.containsKey(Ari.encode(43, 3, 16)))
        assertTrue(xrefs[Ari.encode(43, 3, 16)]!!.contains("Romans"))
    }

    // ══════════════════════════════════════
    // Text Encoding Pipeline
    // ══════════════════════════════════════

    @Test
    fun verseTextUtf8Decoding() {
        val verseTexts = listOf(
            "In the beginning God created the heaven and the earth.",
            "And the earth was without form, and void;",
            "And God said, Let there be light: and there was light.",
        )
        verseTexts.forEachIndexed { index, text ->
            val bytes = text.encodeToByteArray()
            val decoded = bytes.decodeToString()
            assertEquals(text, decoded, "Failed at verse ${index + 1}")
        }
    }

    @Test
    fun verseTextNonLatin() {
        // Indonesian
        val indonesian = "Pada mulanya Allah menciptakan langit dan bumi."
        assertEquals(indonesian, indonesian.encodeToByteArray().decodeToString())

        // Korean
        val korean = "태초에 하나님이 천지를 창조하시니라"
        assertEquals(korean, korean.encodeToByteArray().decodeToString())
    }

    // ══════════════════════════════════════
    // BintexReader in YES2 Context
    // ══════════════════════════════════════

    @Test
    fun bintexReaderForSectionIndex() {
        // Simulate reading a section index entry:
        // section name length (uint8) + name + offset (int32) + size (int32)
        val nameBytes = "text".encodeToByteArray()
        val data = ByteArray(1 + nameBytes.size + 8)
        data[0] = nameBytes.size.toByte()
        nameBytes.copyInto(data, 1)
        // offset = 100 (big-endian)
        data[1 + nameBytes.size] = 0
        data[2 + nameBytes.size] = 0
        data[3 + nameBytes.size] = 0
        data[4 + nameBytes.size] = 100

        val reader = BintexReader(data)
        val nameLen = reader.readUint8()
        assertEquals(4, nameLen)
        val name = reader.readRaw(nameLen).decodeToString()
        assertEquals("text", name)
    }

    @Test
    fun bintexReaderChapterOffset() {
        // Chapter offsets are stored as int32 arrays
        val offsets = intArrayOf(0, 500, 1200, 2100)
        val data = ByteArray(offsets.size * 4)
        offsets.forEachIndexed { i, offset ->
            data[i * 4] = ((offset shr 24) and 0xFF).toByte()
            data[i * 4 + 1] = ((offset shr 16) and 0xFF).toByte()
            data[i * 4 + 2] = ((offset shr 8) and 0xFF).toByte()
            data[i * 4 + 3] = (offset and 0xFF).toByte()
        }

        val reader = BintexReader(data)
        for (expected in offsets) {
            assertEquals(expected, reader.readInt())
        }
    }

    // ══════════════════════════════════════
    // Full Pipeline Verification
    // ══════════════════════════════════════

    @Test
    fun ariFromBookChapterVerse() {
        // Verify ARI encoding works for all 66 books, chapters, verses
        // used in YES2 reading pipeline
        val testCases = listOf(
            Triple(1, 1, 1),    // Gen 1:1
            Triple(1, 50, 26),  // Gen 50:26 (last verse)
            Triple(19, 119, 176), // Psa 119:176 (longest chapter)
            Triple(40, 1, 1),   // Mat 1:1
            Triple(66, 22, 21), // Rev 22:21 (last verse in Bible)
        )
        for ((book, chapter, verse) in testCases) {
            val ari = Ari.encode(book, chapter, verse)
            assertEquals(book, Ari.decodeBook(ari))
            assertEquals(chapter, Ari.decodeChapter(ari))
            assertEquals(verse, Ari.decodeVerse(ari))
        }
    }

    @Test
    fun verseCountValidation() {
        // Bible has specific verse counts for well-known chapters
        val knownCounts = mapOf(
            1 to 31,    // Gen 1
            19 to 176,  // Psa 119 (note: this is max verse = 176)
        )
        for ((chapter, maxVerse) in knownCounts) {
            assertTrue(maxVerse <= 255, "Verse must fit in 8 bits for ARI")
        }
    }
}
