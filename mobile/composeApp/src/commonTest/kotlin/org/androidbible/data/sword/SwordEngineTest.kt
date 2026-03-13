package org.androidbible.data.sword

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

class SwordModuleConfigTest {

    @Test
    fun parseZTextConfig() {
        val confText = """
            [KJV]
            DataPath=./modules/texts/ztext/kjv/
            ModDrv=zText
            CompressType=ZIP
            BlockType=BOOK
            SourceType=OSIS
            Encoding=UTF-8
            Lang=en
            Description=King James Version (1769) with Strongs Numbers and Morphology
            Version=2.9
            About=King James Version of the Holy Bible
        """.trimIndent()

        val config = SwordModuleConfig.parse(confText)

        assertEquals("KJV", config.moduleName)
        assertEquals(SwordModuleConfig.ModDrv.Z_TEXT, config.modDrv)
        assertEquals("modules/texts/ztext/kjv", config.dataPath)
        assertEquals(SwordModuleConfig.CompressType.ZIP, config.compressType)
        assertEquals(SwordModuleConfig.BlockType.BOOK, config.blockType)
        assertEquals(SwordModuleConfig.SourceType.OSIS, config.sourceType)
        assertEquals("UTF-8", config.encoding)
        assertEquals("en", config.language)
        assertEquals("2.9", config.version)
        assertTrue(config.description.contains("King James"))
    }

    @Test
    fun parseRawComConfig() {
        val confText = """
            [MHCC]
            DataPath=./modules/comments/rawcom/mhcc/
            ModDrv=RawCom
            SourceType=ThML
            Lang=en
            Description=Matthew Henry Concise Commentary
        """.trimIndent()

        val config = SwordModuleConfig.parse(confText)

        assertEquals("MHCC", config.moduleName)
        assertEquals(SwordModuleConfig.ModDrv.RAW_COM, config.modDrv)
        assertEquals("modules/comments/rawcom/mhcc", config.dataPath)
        assertEquals(SwordModuleConfig.SourceType.THML, config.sourceType)
    }

    @Test
    fun parseRawLD4Config() {
        val confText = """
            [StrongsRealGreek]
            DataPath=./modules/lexdict/rawld4/strongsrealgreek/
            ModDrv=RawLD4
            SourceType=TEI
            Lang=en
            Description=Strongs Real Greek
        """.trimIndent()

        val config = SwordModuleConfig.parse(confText)

        assertEquals("StrongsRealGreek", config.moduleName)
        assertEquals(SwordModuleConfig.ModDrv.RAW_LD4, config.modDrv)
        assertEquals(SwordModuleConfig.SourceType.TEI, config.sourceType)
    }

    @Test
    fun parseWithCipherKey() {
        val confText = """
            [LockedModule]
            DataPath=./modules/texts/ztext/locked/
            ModDrv=zText
            CipherKey=
        """.trimIndent()

        val config = SwordModuleConfig.parse(confText)

        assertTrue(config.rawEntries.containsKey("cipherkey"))
        assertEquals("", config.rawEntries["cipherkey"])
    }

    @Test
    fun normalizeDataPath() {
        val confText = """
            [Test]
            DataPath=./modules/texts/ztext/test/
            ModDrv=zText
        """.trimIndent()

        val config = SwordModuleConfig.parse(confText)
        assertEquals("modules/texts/ztext/test", config.dataPath)
    }

    @Test
    fun parseEmptyConfig() {
        val config = SwordModuleConfig.parse("")
        assertEquals("unknown", config.moduleName)
    }
}

class SwordVersificationTest {

    @Test
    fun allBooksCount() {
        assertEquals(66, SwordVersification.allBooks.size)
        assertEquals(39, SwordVersification.otBooks.size)
        assertEquals(27, SwordVersification.ntBooks.size)
    }

    @Test
    fun findBookByOsisId() {
        val (genIdx, gen) = SwordVersification.findBookByOsisId("Gen")!!
        assertEquals(0, genIdx)
        assertEquals("Genesis", gen.name)
        assertEquals(50, gen.chapterCount)

        val (revIdx, rev) = SwordVersification.findBookByOsisId("Rev")!!
        assertEquals(65, revIdx)
        assertEquals("Revelation of John", rev.name)
        assertEquals(22, rev.chapterCount)
    }

    @Test
    fun findBookByOsisIdCaseInsensitive() {
        assertNotNull(SwordVersification.findBookByOsisId("gen"))
        assertNotNull(SwordVersification.findBookByOsisId("GEN"))
    }

    @Test
    fun getTestament() {
        assertEquals(0, SwordVersification.getTestament(0))   // Genesis
        assertEquals(0, SwordVersification.getTestament(38))  // Malachi
        assertEquals(1, SwordVersification.getTestament(39))  // Matthew
        assertEquals(1, SwordVersification.getTestament(65))  // Revelation
    }

    @Test
    fun getTestamentBookIndex() {
        assertEquals(0, SwordVersification.getTestamentBookIndex(0))   // Genesis
        assertEquals(38, SwordVersification.getTestamentBookIndex(38)) // Malachi
        assertEquals(0, SwordVersification.getTestamentBookIndex(39))  // Matthew
        assertEquals(26, SwordVersification.getTestamentBookIndex(65)) // Revelation
    }

    @Test
    fun getVerseCountGenesis1() {
        assertEquals(31, SwordVersification.getVerseCount(0, 1))
    }

    @Test
    fun getVerseCountPsalm119() {
        assertEquals(176, SwordVersification.getVerseCount(18, 119))
    }

    @Test
    fun computeLinearIndexGenesis1_1() {
        val index = SwordVersification.computeLinearIndex(
            testamentBookIndex = 0,
            chapter = 1,
            verse = 1,
            books = SwordVersification.otBooks,
        )
        // testament intro (1) + book intro (1) + chapter intro (1) + verse 1 = 4
        assertEquals(4L, index)
    }

    @Test
    fun computeLinearIndexMatthew1_1() {
        val index = SwordVersification.computeLinearIndex(
            testamentBookIndex = 0,
            chapter = 1,
            verse = 1,
            books = SwordVersification.ntBooks,
        )
        assertEquals(4L, index)
    }

    @Test
    fun findBookByName() {
        val (idx, book) = SwordVersification.findBookByName("Genesis")!!
        assertEquals(0, idx)
        assertEquals("Gen", book.osisId)
    }
}

class CipherUtilsTest {
    @Test
    fun noCipherKeyReturnsOriginal() {
        val config = SwordModuleConfig("test")
        val data = byteArrayOf(1, 2, 3)
        val result = org.androidbible.data.sword.io.CipherUtils.applyCipher(data, config)
        assertTrue(data.contentEquals(result))
    }

    @Test
    fun emptyCipherKeyReturnsOriginal() {
        val config = SwordModuleConfig("test", rawEntries = mapOf("cipherkey" to ""))
        val data = byteArrayOf(1, 2, 3)
        val result = org.androidbible.data.sword.io.CipherUtils.applyCipher(data, config)
        assertTrue(data.contentEquals(result))
    }

    @Test
    fun xorCipherRoundTrip() {
        val config = SwordModuleConfig("test", rawEntries = mapOf("cipherkey" to "secret"))
        val data = "Hello World".encodeToByteArray()
        val encrypted = org.androidbible.data.sword.io.CipherUtils.applyCipher(data, config)
        val decrypted = org.androidbible.data.sword.io.CipherUtils.applyCipher(encrypted, config)
        assertEquals("Hello World", decrypted.decodeToString())
    }

    @Test
    fun isLockedWithEmptyKey() {
        val config = SwordModuleConfig("test", rawEntries = mapOf("cipherkey" to ""))
        assertTrue(org.androidbible.data.sword.io.CipherUtils.isLocked(config))
    }

    @Test
    fun requiresCipherKeyTrue() {
        val config = SwordModuleConfig("test", rawEntries = mapOf("cipherkey" to "abc"))
        assertTrue(org.androidbible.data.sword.io.CipherUtils.requiresCipherKey(config))
    }
}
