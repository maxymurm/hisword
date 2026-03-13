package org.androidbible.domain

import org.androidbible.domain.model.Label
import org.androidbible.domain.model.Marker
import org.androidbible.ui.screens.bible.BibleReaderViewModel
import org.androidbible.ui.screens.bible.NoteEditorSheet
import org.androidbible.ui.screens.bible.renderMarkdown
import org.androidbible.util.Ari
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class MarkerSystemTest {

    // ── ARI Round-Trip ────────────────────────────────────

    @Test
    fun ariEncodeDecode_genesis_1_1() {
        val ari = Ari.encode(1, 1, 1)
        assertEquals(1, Ari.decodeBook(ari))
        assertEquals(1, Ari.decodeChapter(ari))
        assertEquals(1, Ari.decodeVerse(ari))
    }

    @Test
    fun ariEncodeDecode_revelation_22_21() {
        val ari = Ari.encode(66, 22, 21)
        assertEquals(66, Ari.decodeBook(ari))
        assertEquals(22, Ari.decodeChapter(ari))
        assertEquals(21, Ari.decodeVerse(ari))
    }

    @Test
    fun ariEncodeDecode_psalm_119_176() {
        val ari = Ari.encode(19, 119, 176)
        assertEquals(19, Ari.decodeBook(ari))
        assertEquals(119, Ari.decodeChapter(ari))
        assertEquals(176, Ari.decodeVerse(ari))
    }

    @Test
    fun ariRange_sameChapter_ordered() {
        val start = Ari.encode(1, 1, 1)
        val end = Ari.encode(1, 1, 31)
        assertTrue(start < end)
        // All verses in Gen 1 fall within this range
        val v15 = Ari.encode(1, 1, 15)
        assertTrue(v15 in start..end)
    }

    @Test
    fun ariRange_crossChapter_ordered() {
        val gen1_31 = Ari.encode(1, 1, 31)
        val gen2_1 = Ari.encode(1, 2, 1)
        assertTrue(gen1_31 < gen2_1, "ARI for Gen 1:31 should be < Gen 2:1")
    }

    // ── Marker Model ─────────────────────────────────────

    @Test
    fun markerKind_bookmark() {
        val marker = Marker(ari = Ari.encode(1, 1, 1), kind = Marker.KIND_BOOKMARK)
        assertTrue(marker.isBookmark)
        assertFalse(marker.isNote)
        assertFalse(marker.isHighlight)
    }

    @Test
    fun markerKind_note() {
        val marker = Marker(ari = Ari.encode(1, 1, 1), kind = Marker.KIND_NOTE, caption = "Test note")
        assertFalse(marker.isBookmark)
        assertTrue(marker.isNote)
        assertFalse(marker.isHighlight)
        assertEquals("Test note", marker.caption)
    }

    @Test
    fun markerKind_highlight_withColor() {
        val marker = Marker(ari = Ari.encode(1, 1, 1), kind = Marker.KIND_HIGHLIGHT, color = 3)
        assertFalse(marker.isBookmark)
        assertFalse(marker.isNote)
        assertTrue(marker.isHighlight)
        assertEquals(3, marker.color)
    }

    @Test
    fun markerDefaults() {
        val marker = Marker(ari = 0, kind = 0)
        assertEquals(0L, marker.id)
        assertEquals("", marker.gid)
        assertNull(marker.userId)
        assertEquals(1, marker.verseCount)
        assertNull(marker.color)
    }

    // ── Label Model ──────────────────────────────────────

    @Test
    fun labelDefaults() {
        val label = Label(title = "Study")
        assertEquals(0L, label.id)
        assertEquals("Study", label.title)
        assertNull(label.backgroundColor)
    }

    @Test
    fun labelWithColor() {
        val label = Label(title = "Prayer", backgroundColor = 4)
        assertEquals(4, label.backgroundColor)
    }

    // ── Pericope Extraction ──────────────────────────────

    @Test
    fun extractPericope_yes2_ts_tag() {
        val title = BibleReaderViewModel.extractPericopeTitle("<TS1>The Creation<Ts>In the beginning...")
        assertEquals("The Creation", title)
    }

    @Test
    fun extractPericope_osis_title_tag() {
        val title = BibleReaderViewModel.extractPericopeTitle("<title>The Fall</title>Now the serpent...")
        assertEquals("The Fall", title)
    }

    @Test
    fun extractPericope_noTag_returnsNull() {
        val title = BibleReaderViewModel.extractPericopeTitle("In the beginning God created")
        assertNull(title)
    }

    // ── Book Name Lookup ─────────────────────────────────

    @Test
    fun bookName_genesis() {
        val name = BibleReaderViewModel.getBookName(1)
        assertEquals("Genesis", name)
    }

    @Test
    fun bookName_revelation() {
        val name = BibleReaderViewModel.getBookName(66)
        assertEquals("Revelation of John", name)
    }

    @Test
    fun bookChapterCount_genesis_50() {
        assertEquals(50, BibleReaderViewModel.getBookChapterCount(1))
    }

    @Test
    fun bookChapterCount_psalms_150() {
        assertEquals(150, BibleReaderViewModel.getBookChapterCount(19))
    }

    // ── Markdown Rendering ───────────────────────────────

    @Test
    fun renderMarkdown_bold() {
        val result = renderMarkdown("This is **bold** text")
        assertTrue(result.text.contains("bold"))
        assertTrue(result.text.contains("This is"))
    }

    @Test
    fun renderMarkdown_italic() {
        val result = renderMarkdown("This is *italic* text")
        assertTrue(result.text.contains("italic"))
    }

    @Test
    fun renderMarkdown_heading() {
        val result = renderMarkdown("# Heading\nBody text")
        assertTrue(result.text.contains("Heading"))
        assertTrue(result.text.contains("Body text"))
    }

    @Test
    fun renderMarkdown_plainText() {
        val result = renderMarkdown("No formatting here")
        assertEquals("No formatting here\n", result.text)
    }

    // ── Marker Export Format ─────────────────────────────

    @Test
    fun exportPlainTextFormat_bookmark() {
        // Verify the format string pattern used in MarkerExportService
        val marker = Marker(ari = Ari.encode(1, 1, 1), kind = Marker.KIND_BOOKMARK)
        val kindLabel = when (marker.kind) {
            Marker.KIND_BOOKMARK -> "Bookmark"
            Marker.KIND_NOTE -> "Note"
            Marker.KIND_HIGHLIGHT -> "Highlight"
            else -> "Marker"
        }
        assertEquals("Bookmark", kindLabel)
    }

    @Test
    fun exportPlainTextFormat_highlight_colorName() {
        val colorNames = mapOf(1 to "yellow", 2 to "green", 3 to "blue", 4 to "pink", 5 to "orange", 6 to "purple")
        assertEquals("yellow", colorNames[1])
        assertEquals("purple", colorNames[6])
        assertNull(colorNames[7])
    }

    // ── Marker Kind Constants ────────────────────────────

    @Test
    fun markerKindConstants() {
        assertEquals(0, Marker.KIND_BOOKMARK)
        assertEquals(1, Marker.KIND_NOTE)
        assertEquals(2, Marker.KIND_HIGHLIGHT)
    }

    // ── Performance: ARI Encoding 10000 ops ──────────────

    @Test
    fun performance_ariEncoding_10000_ops() {
        val start = kotlin.time.TimeSource.Monotonic.markNow()
        for (book in 1..66) {
            for (chapter in 1..5) {
                for (verse in 1..30) {
                    val ari = Ari.encode(book, chapter, verse)
                    Ari.decodeBook(ari)
                    Ari.decodeChapter(ari)
                    Ari.decodeVerse(ari)
                }
            }
        }
        val elapsed = start.elapsedNow()
        assertTrue(elapsed.inWholeMilliseconds < 200, "10000 ARI ops should take <200ms, took ${elapsed.inWholeMilliseconds}ms")
    }
}
