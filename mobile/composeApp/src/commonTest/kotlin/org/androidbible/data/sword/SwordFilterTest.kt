package org.androidbible.data.sword

import org.androidbible.data.sword.gbf.GbfTextFilter
import org.androidbible.data.sword.osis.OsisTextFilter
import org.androidbible.data.sword.thml.ThmlTextFilter
import org.androidbible.data.sword.tei.TeiTextFilter
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class OsisTextFilterTest {

    @Test
    fun stripSimpleOsis() {
        val osis = """<verse osisID="Gen.1.1">In the beginning God created the heaven and the earth.</verse>"""
        val result = OsisTextFilter.stripMarkup(osis)
        assertTrue(result.contains("In the beginning"))
    }

    @Test
    fun stripOsisWithStrongs() {
        val osis = """<w lemma="strong:H07225">beginning</w> <w lemma="strong:H0430">God</w>"""
        val result = OsisTextFilter.stripMarkup(osis)
        assertTrue(result.contains("beginning"))
        assertTrue(result.contains("God"))
    }

    @Test
    fun stripOsisRemovesNotes() {
        val osis = """Text before <note type="study">Some note</note> text after"""
        val result = OsisTextFilter.stripMarkup(osis)
        assertEquals("Text before text after", result)
    }

    @Test
    fun extractStrongsNumbers() {
        val osis = """<w lemma="strong:H07225">beginning</w> <w lemma="strong:H0430">God</w>"""
        val strongs = OsisTextFilter.extractStrongsNumbers(osis)
        assertEquals(listOf("H07225", "H0430"), strongs)
    }

    @Test
    fun plainTextPassthrough() {
        assertEquals("hello", OsisTextFilter.stripMarkup("hello"))
    }

    @Test
    fun blankInput() {
        assertEquals("", OsisTextFilter.stripMarkup(""))
        assertEquals("", OsisTextFilter.stripMarkup("   "))
    }
}

class GbfTextFilterTest {

    @Test
    fun stripGbfFormatting() {
        val gbf = "<FI>In the beginning<Fi> God created"
        val result = GbfTextFilter.stripMarkup(gbf)
        assertEquals("In the beginning God created", result)
    }

    @Test
    fun stripGbfFootnotes() {
        val gbf = "Text <RF>footnote content<Rf> more text"
        val result = GbfTextFilter.stripMarkup(gbf)
        assertEquals("Text more text", result)
    }

    @Test
    fun extractGbfStrongs() {
        val gbf = "word<WH1234>other<WG5678>"
        val strongs = GbfTextFilter.extractStrongsNumbers(gbf)
        assertEquals(listOf("H1234", "G5678"), strongs)
    }

    @Test
    fun stripGbfRedLetter() {
        val gbf = "<FR>Verily I say<Fr>"
        val result = GbfTextFilter.stripMarkup(gbf)
        assertEquals("Verily I say", result)
    }
}

class ThmlTextFilterTest {

    @Test
    fun stripThmlBasic() {
        val thml = "<scripRef passage=\"Gen.1.1\">Gen 1:1</scripRef>"
        val result = ThmlTextFilter.stripMarkup(thml)
        assertEquals("Gen 1:1", result)
    }

    @Test
    fun stripThmlNotes() {
        val thml = "Before <note>note content</note> after"
        val result = ThmlTextFilter.stripMarkup(thml)
        assertEquals("Before after", result)
    }

    @Test
    fun stripThmlEntities() {
        val thml = "Tom &amp; Jerry"
        val result = ThmlTextFilter.stripMarkup(thml)
        assertEquals("Tom & Jerry", result)
    }
}

class TeiTextFilterTest {

    @Test
    fun stripTeiEntry() {
        val tei = "<entry><orth>agape</orth><def>love</def></entry>"
        val result = TeiTextFilter.stripMarkup(tei)
        assertTrue(result.contains("agape"))
        assertTrue(result.contains("love"))
    }

    @Test
    fun stripTeiSense() {
        val tei = """<sense n="1">first meaning</sense>"""
        val result = TeiTextFilter.stripMarkup(tei)
        assertTrue(result.contains("1."))
        assertTrue(result.contains("first meaning"))
    }
}

class ByteUtilsTest {
    @Test
    fun readUInt32LE() {
        val data = byteArrayOf(0x01, 0x02, 0x03, 0x04)
        val result = org.androidbible.data.sword.reader.ByteUtils.readUInt32LE(data, 0)
        assertEquals(0x04030201L, result)
    }

    @Test
    fun readUInt16LE() {
        val data = byteArrayOf(0x01, 0x02)
        val result = org.androidbible.data.sword.reader.ByteUtils.readUInt16LE(data, 0)
        assertEquals(0x0201, result)
    }

    @Test
    fun readInt32LENegative() {
        val data = byteArrayOf(0xFF.toByte(), 0xFF.toByte(), 0xFF.toByte(), 0xFF.toByte())
        val result = org.androidbible.data.sword.reader.ByteUtils.readInt32LE(data, 0)
        assertEquals(-1, result)
    }
}
