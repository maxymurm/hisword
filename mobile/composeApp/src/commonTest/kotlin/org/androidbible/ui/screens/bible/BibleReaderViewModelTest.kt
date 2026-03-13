package org.androidbible.ui.screens.bible

import org.androidbible.domain.model.Verse
import org.androidbible.util.Ari
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue
import kotlin.time.measureTime

class BibleReaderViewModelTest {

    @Test
    fun extractPericopeTitle_yes2_ts_tag() {
        val text = "<TS1>The Creation<Ts>In the beginning God created..."
        val title = BibleReaderViewModel.extractPericopeTitle(text)
        assertEquals("The Creation", title)
    }

    @Test
    fun extractPericopeTitle_osis_title_tag() {
        val text = "<title type=\"main\">The Flood</title>And it cam to pass..."
        val title = BibleReaderViewModel.extractPericopeTitle(text)
        assertEquals("The Flood", title)
    }

    @Test
    fun extractPericopeTitle_no_pericope() {
        val text = "In the beginning God created the heavens and the earth."
        assertNull(BibleReaderViewModel.extractPericopeTitle(text))
    }

    @Test
    fun getBookName_valid() {
        assertEquals("Genesis", BibleReaderViewModel.getBookName(1))
        assertEquals("Revelation of John", BibleReaderViewModel.getBookName(66))
        assertEquals("Psalms", BibleReaderViewModel.getBookName(19))
    }

    @Test
    fun getBookName_invalid() {
        assertEquals("Book 0", BibleReaderViewModel.getBookName(0))
        assertEquals("Book 99", BibleReaderViewModel.getBookName(99))
    }

    @Test
    fun getBookChapterCount() {
        assertEquals(50, BibleReaderViewModel.getBookChapterCount(1))  // Genesis
        assertEquals(22, BibleReaderViewModel.getBookChapterCount(66)) // Revelation
        assertEquals(150, BibleReaderViewModel.getBookChapterCount(19)) // Psalms
        assertEquals(0, BibleReaderViewModel.getBookChapterCount(0))   // Invalid
    }

    @Test
    fun readerItem_types() {
        val pericope = ReaderItem.PericopeItem("Title")
        val verse = ReaderItem.VerseItemData(
            verse = Verse(
                bibleVersionId = 0,
                ari = Ari.encode(1, 1, 1),
                bookId = 1,
                chapter = 1,
                verse = 1,
                text = "In the beginning...",
            ),
        )

        assertTrue(pericope is ReaderItem)
        assertTrue(verse is ReaderItem)
        assertEquals("Title", pericope.title)
        assertEquals(false, verse.isSelected)
        assertEquals(false, verse.hasBookmark)
        assertNull(verse.highlightColor)
    }

    @Test
    fun readerState_defaults() {
        val state = ReaderState()
        assertEquals("", state.moduleKey)
        assertEquals("sword", state.moduleEngine)
        assertEquals(1, state.bookId)
        assertEquals(1, state.chapter)
        assertEquals(16, state.fontSize)
        assertEquals(1.5f, state.lineSpacing)
        assertTrue(state.items.isEmpty())
        assertTrue(state.selectedAris.isEmpty())
    }

    @Test
    fun performance_buildVerseList_1000_items() {
        // Simulate building 1000 verse items — should complete in well under 16ms
        val verses = (1..1000).map { i ->
            Verse(
                bibleVersionId = 0,
                ari = Ari.encode(1, 1, i),
                bookId = 1,
                chapter = 1,
                verse = i,
                text = "This is verse $i of the test chapter with some typical text content.",
                textWithoutFormatting = "This is verse $i of the test chapter with some typical text content.",
            )
        }

        val elapsed = measureTime {
            val items = verses.map { verse ->
                ReaderItem.VerseItemData(
                    verse = verse,
                    isSelected = false,
                    hasBookmark = (verse.verse % 10 == 0),
                    hasNote = (verse.verse % 20 == 0),
                    highlightColor = if (verse.verse % 5 == 0) 1 else null,
                )
            }
            assertEquals(1000, items.size)
        }

        // 16ms render budget — list building should be < 16ms
        assertTrue(
            elapsed.inWholeMilliseconds < 100, // generous budget for slow CI
            "List building took ${elapsed.inWholeMilliseconds}ms, expected < 100ms",
        )
    }

    @Test
    fun performance_ariEncoding_10000_ops() {
        val elapsed = measureTime {
            for (bookId in 1..66) {
                for (chapter in 1..5) {
                    for (verse in 1..30) {
                        val ari = Ari.encode(bookId, chapter, verse)
                        assertEquals(bookId, Ari.decodeBook(ari))
                        assertEquals(chapter, Ari.decodeChapter(ari))
                        assertEquals(verse, Ari.decodeVerse(ari))
                    }
                }
            }
        }

        assertTrue(
            elapsed.inWholeMilliseconds < 100,
            "ARI encoding of 9900 ops took ${elapsed.inWholeMilliseconds}ms",
        )
    }
}
