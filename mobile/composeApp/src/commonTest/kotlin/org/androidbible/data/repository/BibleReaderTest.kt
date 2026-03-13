package org.androidbible.data.repository

import org.androidbible.domain.repository.ModuleInfo
import org.androidbible.util.Ari
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class SwordBibleReaderTest {

    @Test
    fun bookDefForId_genesis() {
        val def = SwordBibleReader.bookDefForId(1)
        assertNotNull(def)
        assertEquals("Gen", def.osisId)
        assertEquals("Genesis", def.name)
        assertEquals(50, def.chapterCount)
    }

    @Test
    fun bookDefForId_revelation() {
        val def = SwordBibleReader.bookDefForId(66)
        assertNotNull(def)
        assertEquals("Rev", def.osisId)
        assertEquals("Revelation of John", def.name)
    }

    @Test
    fun bookDefForId_matthew() {
        val def = SwordBibleReader.bookDefForId(40)
        assertNotNull(def)
        assertEquals("Matt", def.osisId)
    }

    @Test
    fun bookDefForId_invalid_returns_null() {
        assertNull(SwordBibleReader.bookDefForId(0))
        assertNull(SwordBibleReader.bookDefForId(67))
        assertNull(SwordBibleReader.bookDefForId(-1))
    }

    @Test
    fun bookIdForOsis_genesis() {
        val id = SwordBibleReader.bookIdForOsis("Gen")
        assertEquals(1, id)
    }

    @Test
    fun bookIdForOsis_revelation() {
        val id = SwordBibleReader.bookIdForOsis("Rev")
        assertEquals(66, id)
    }

    @Test
    fun bookIdForOsis_caseInsensitive() {
        val id = SwordBibleReader.bookIdForOsis("gen")
        assertEquals(1, id)
    }

    @Test
    fun bookIdForOsis_unknown_returns_null() {
        assertNull(SwordBibleReader.bookIdForOsis("UnknownBook"))
    }

    @Test
    fun roundTrip_bookId_osisId() {
        for (bookId in 1..66) {
            val def = SwordBibleReader.bookDefForId(bookId)
            assertNotNull(def, "Missing def for bookId=$bookId")
            val roundTrip = SwordBibleReader.bookIdForOsis(def.osisId)
            assertEquals(bookId, roundTrip, "Round-trip failed for ${def.osisId}")
        }
    }
}

class AriEncodingTest {

    @Test
    fun encode_genesis_1_1() {
        // bookId=1, chapter=1, verse=1
        val ari = Ari.encode(1, 1, 1)
        assertEquals(1, Ari.decodeBook(ari))
        assertEquals(1, Ari.decodeChapter(ari))
        assertEquals(1, Ari.decodeVerse(ari))
    }

    @Test
    fun encode_revelation_22_21() {
        val ari = Ari.encode(66, 22, 21)
        assertEquals(66, Ari.decodeBook(ari))
        assertEquals(22, Ari.decodeChapter(ari))
        assertEquals(21, Ari.decodeVerse(ari))
    }

    @Test
    fun both_engines_same_ari() {
        // Genesis 1:1 has the same ARI regardless of which engine reads it
        val gen1_1 = Ari.encode(1, 1, 1)
        assertEquals((1 shl 16) or (1 shl 8) or 1, gen1_1)

        // Verify SWORD mapping for this bookId
        val def = SwordBibleReader.bookDefForId(1)
        assertNotNull(def)
        assertEquals("Gen", def.osisId)

        // Verify round-trip through ARI
        val (book, chapter, verse) = Ari.decode(gen1_1)
        assertEquals(1, book)
        assertEquals(1, chapter)
        assertEquals(1, verse)
    }

    @Test
    fun psalm_119_176() {
        // Psalms = bookId 19, chapter 119, verse 176
        val ari = Ari.encode(19, 119, 176)
        assertEquals(19, Ari.decodeBook(ari))
        assertEquals(119, Ari.decodeChapter(ari))
        assertEquals(176, Ari.decodeVerse(ari))

        val def = SwordBibleReader.bookDefForId(19)
        assertNotNull(def)
        assertEquals("Ps", def.osisId)
    }
}

class ModuleInfoTest {

    @Test
    fun moduleInfo_sword() {
        val info = ModuleInfo(
            key = "kjv",
            name = "King James Version",
            description = "1611 KJV",
            language = "en",
            engine = "sword",
            hasOT = true,
            hasNT = true,
        )
        assertEquals("sword", info.engine)
        assertTrue(info.hasOT)
        assertTrue(info.hasNT)
    }

    @Test
    fun moduleInfo_bintex() {
        val info = ModuleInfo(
            key = "tb",
            name = "Terjemahan Baru",
            description = "Indonesian Bible",
            language = "id",
            engine = "bintex",
            hasOT = true,
            hasNT = true,
        )
        assertEquals("bintex", info.engine)
        assertEquals("id", info.language)
    }

    @Test
    fun bibleReaderFactory_dispatch() {
        val factory = org.androidbible.domain.repository.BibleReaderFactory(
            swordReader = org.androidbible.domain.repository.NoOpBibleReader,
            bintexReader = org.androidbible.domain.repository.NoOpBibleReader,
        )
        val sword = factory.readerFor("sword")
        val bintex = factory.readerFor("bintex")
        assertNotNull(sword)
        assertNotNull(bintex)

        val all = factory.allReaders()
        assertEquals(2, all.size)
        assertTrue(all.containsKey("sword"))
        assertTrue(all.containsKey("bintex"))
    }

    @Test
    fun noOpBibleReader_returns_empty() {
        val reader = org.androidbible.domain.repository.NoOpBibleReader
        kotlinx.coroutines.runBlocking {
            val chapter = reader.readChapter("test", 1, 1)
            assertTrue(chapter.isEmpty())
            assertNull(reader.readVerse("test", Ari.encode(1, 1, 1)))
            assertFalse(reader.hasDataFiles("test"))
            assertTrue(reader.search("test", "word").isEmpty())
            assertNull(reader.getModuleInfo("test"))
        }
    }
}

class BintexDetectTest {

    @Test
    fun detectAndCreate_tooSmall() {
        val result = BintexRepositoryImpl.detectAndCreate(ByteArray(4))
        assertNull(result)
    }

    @Test
    fun detectAndCreate_invalidHeader() {
        val data = ByteArray(16) { 0x00 }
        val result = BintexRepositoryImpl.detectAndCreate(data)
        assertNull(result)
    }
}
