package org.androidbible.util

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotEquals

/**
 * Extended ARI tests for edge cases and boundary conditions.
 */
class AriExtendedTest {

    @Test
    fun testEncodeMaxValues() {
        // Max book=255, chapter=255, verse=255
        val ari = Ari.encode(255, 255, 255)
        assertEquals(255, Ari.decodeBook(ari))
        assertEquals(255, Ari.decodeChapter(ari))
        assertEquals(255, Ari.decodeVerse(ari))
    }

    @Test
    fun testEncodeMinValues() {
        val ari = Ari.encode(0, 0, 0)
        assertEquals(0, ari)
        assertEquals(0, Ari.decodeBook(ari))
        assertEquals(0, Ari.decodeChapter(ari))
        assertEquals(0, Ari.decodeVerse(ari))
    }

    @Test
    fun testAllOTBooks() {
        // Old Testament books 1-39
        for (bookId in 1..39) {
            val ari = Ari.encode(bookId, 1, 1)
            assertEquals(bookId, Ari.decodeBook(ari))
        }
    }

    @Test
    fun testAllNTBooks() {
        // New Testament books 40-66
        for (bookId in 40..66) {
            val ari = Ari.encode(bookId, 1, 1)
            assertEquals(bookId, Ari.decodeBook(ari))
        }
    }

    @Test
    fun testUniqueEncodings() {
        // Different references should produce different ARIs
        val gen11 = Ari.encode(1, 1, 1)
        val gen12 = Ari.encode(1, 1, 2)
        val gen21 = Ari.encode(1, 2, 1)
        val exo11 = Ari.encode(2, 1, 1)

        assertNotEquals(gen11, gen12)
        assertNotEquals(gen11, gen21)
        assertNotEquals(gen11, exo11)
    }

    @Test
    fun testRoundTrip() {
        val testCases = listOf(
            Triple(1, 1, 1),      // Genesis 1:1
            Triple(43, 3, 16),    // John 3:16
            Triple(66, 22, 21),   // Revelation 22:21
            Triple(19, 119, 176), // Psalm 119:176 (longest chapter)
            Triple(23, 40, 31),   // Isaiah 40:31
        )

        for ((book, chapter, verse) in testCases) {
            val ari = Ari.encode(book, chapter, verse)
            val (decodedBook, decodedChapter, decodedVerse) = Ari.decode(ari)
            assertEquals(book, decodedBook, "Book mismatch for $book:$chapter:$verse")
            assertEquals(chapter, decodedChapter, "Chapter mismatch for $book:$chapter:$verse")
            assertEquals(verse, decodedVerse, "Verse mismatch for $book:$chapter:$verse")
        }
    }

    @Test
    fun testEncodeRangeMultipleVerses() {
        val (start, end) = Ari.encodeRange(43, 3, 16, 18) // John 3:16-18
        assertEquals(43, Ari.decodeBook(start))
        assertEquals(3, Ari.decodeChapter(start))
        assertEquals(16, Ari.decodeVerse(start))
        assertEquals(43, Ari.decodeBook(end))
        assertEquals(3, Ari.decodeChapter(end))
        assertEquals(18, Ari.decodeVerse(end))
    }

    @Test
    fun testEncodeRangeSingleVerse() {
        val (start, end) = Ari.encodeRange(1, 1, 1, 1) // Genesis 1:1-1
        assertEquals(start, end)
    }

    @Test
    fun testToReferenceVariousBooks() {
        assertEquals("Genesis 1:1", Ari.toReference(Ari.encode(1, 1, 1), "Genesis"))
        assertEquals("Psalms 23:1", Ari.toReference(Ari.encode(19, 23, 1), "Psalms"))
        assertEquals("Revelation 22:21", Ari.toReference(Ari.encode(66, 22, 21), "Revelation"))
    }

    @Test
    fun testBitLayout() {
        // Verify the bit layout: bookId << 16 | chapter << 8 | verse
        val ari = Ari.encode(1, 2, 3)
        assertEquals((1 shl 16) or (2 shl 8) or 3, ari)
        assertEquals(0x10203, ari)
    }

    @Test
    fun testDecodePrecomputedValues() {
        // Known ARI values from the legacy app
        // John 3:16 = bookId=43 (0x2B), chapter=3, verse=16 (0x10)
        val john316 = 0x2B0310
        assertEquals(43, Ari.decodeBook(john316))
        assertEquals(3, Ari.decodeChapter(john316))
        assertEquals(16, Ari.decodeVerse(john316))
    }
}
