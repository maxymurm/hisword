package org.androidbible

import org.androidbible.util.Ari
import kotlin.test.Test
import kotlin.test.assertEquals

class AriTest {

    @Test
    fun testEncode() {
        // Genesis 1:1 -> bookId=1, chapter=1, verse=1
        val ari = Ari.encode(1, 1, 1)
        assertEquals(1, Ari.decodeBook(ari))
        assertEquals(1, Ari.decodeChapter(ari))
        assertEquals(1, Ari.decodeVerse(ari))
    }

    @Test
    fun testEncodeGenesis50_26() {
        // Genesis 50:26 -> bookId=1, chapter=50, verse=26
        val ari = Ari.encode(1, 50, 26)
        assertEquals(1, Ari.decodeBook(ari))
        assertEquals(50, Ari.decodeChapter(ari))
        assertEquals(26, Ari.decodeVerse(ari))
    }

    @Test
    fun testEncodeRevelation22_21() {
        // Revelation 22:21 -> bookId=66, chapter=22, verse=21
        val ari = Ari.encode(66, 22, 21)
        assertEquals(66, Ari.decodeBook(ari))
        assertEquals(22, Ari.decodeChapter(ari))
        assertEquals(21, Ari.decodeVerse(ari))
    }

    @Test
    fun testDecode() {
        val ari = Ari.encode(43, 3, 16) // John 3:16
        val (book, chapter, verse) = Ari.decode(ari)
        assertEquals(43, book)
        assertEquals(3, chapter)
        assertEquals(16, verse)
    }

    @Test
    fun testEncodeChapterOnly() {
        // Chapter reference with verse=0
        val ari = Ari.encode(1, 1, 0)
        assertEquals(1, Ari.decodeBook(ari))
        assertEquals(1, Ari.decodeChapter(ari))
        assertEquals(0, Ari.decodeVerse(ari))
    }

    @Test
    fun testToReference() {
        val ari = Ari.encode(43, 3, 16)
        assertEquals("John 3:16", Ari.toReference(ari, "John"))
    }

    @Test
    fun testToReferenceChapterOnly() {
        val ari = Ari.encode(1, 1, 0)
        assertEquals("Genesis 1", Ari.toReference(ari, "Genesis"))
    }

    @Test
    fun testEncodeRange() {
        val (start, end) = Ari.encodeRange(19, 23, 1, 6) // Psalm 23:1-6
        assertEquals(19, Ari.decodeBook(start))
        assertEquals(23, Ari.decodeChapter(start))
        assertEquals(1, Ari.decodeVerse(start))
        assertEquals(6, Ari.decodeVerse(end))
    }
}
