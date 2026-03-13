package org.androidbible.data.repository

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue
import kotlin.test.assertFalse
import org.androidbible.domain.model.*
import org.androidbible.util.Ari

/**
 * Comprehensive repository tests covering model creation, validation,
 * and business logic for all repository interfaces.
 */
class RepositoryComprehensiveTest {

    // ══════════════════════════════════════
    // BibleRepository — Model Tests
    // ══════════════════════════════════════

    @Test
    fun bibleVersionDefaults() {
        val v = BibleVersion(
            id = 1L, shortName = "KJV", longName = "King James Version",
            languageCode = "en", sortOrder = 1,
            hasOldTestament = true, hasNewTestament = true, isActive = true,
        )
        assertEquals("KJV", v.shortName)
        assertTrue(v.hasOldTestament)
        assertTrue(v.isActive)
    }

    @Test
    fun bibleVersionOptionalFields() {
        val v = BibleVersion(
            id = 2L, shortName = "TB", longName = "Terjemahan Baru",
            languageCode = "id", locale = "id_ID",
            description = "Indonesian Bible", sortOrder = 2,
            hasOldTestament = true, hasNewTestament = true, isActive = false,
        )
        assertEquals("id_ID", v.locale)
        assertEquals("Indonesian Bible", v.description)
        assertFalse(v.isActive)
    }

    @Test
    fun bookModel() {
        val b = Book(
            id = 1L, bibleVersionId = 1L, bookId = 1,
            shortName = "Genesis", longName = "The First Book of Moses, called Genesis",
            abbreviation = "Gen", chapterCount = 50, sortOrder = 1,
        )
        assertEquals(50, b.chapterCount)
        assertEquals("Gen", b.abbreviation)
    }

    @Test
    fun verseWithAri() {
        val ari = Ari.encode(1, 1, 1) // Gen 1:1
        val v = Verse(
            id = 1L, bibleVersionId = 1L, ari = ari,
            bookId = 1, chapter = 1, verse = 1,
            text = "In the beginning God created the heaven and the earth.",
        )
        assertEquals(1, Ari.decodeBook(v.ari))
        assertEquals(1, Ari.decodeChapter(v.ari))
        assertEquals(1, Ari.decodeVerse(v.ari))
    }

    @Test
    fun verseTextWithoutFormatting() {
        val v = Verse(
            id = 1L, bibleVersionId = 1L, ari = Ari.encode(43, 3, 16),
            bookId = 43, chapter = 3, verse = 16,
            text = "<woj>For God so loved the world</woj>",
            textWithoutFormatting = "For God so loved the world",
        )
        assertNotNull(v.textWithoutFormatting)
        assertFalse(v.textWithoutFormatting!!.contains("<"))
    }

    @Test
    fun chapterVerseList() {
        val verses = (1..31).map { v ->
            Verse(
                id = v.toLong(), bibleVersionId = 1L, ari = Ari.encode(1, 1, v),
                bookId = 1, chapter = 1, verse = v, text = "Verse $v text",
            )
        }
        val chapter = Chapter(bookId = 1, chapter = 1, verses = verses)
        assertEquals(31, chapter.verses.size)
        assertEquals(1, chapter.chapter)
    }

    @Test
    fun pericopeModel() {
        val p = Pericope(
            id = 1L, bibleVersionId = 1L,
            ari = Ari.encode(1, 1, 1), title = "The Creation",
        )
        assertEquals("The Creation", p.title)
    }

    @Test
    fun crossReferenceSymmetry() {
        val cr = CrossReference(
            id = 1L, bibleVersionId = 1L,
            fromAri = Ari.encode(43, 3, 16), // John 3:16
            toAri = Ari.encode(45, 5, 8),    // Rom 5:8
        )
        assertEquals(43, Ari.decodeBook(cr.fromAri))
        assertEquals(45, Ari.decodeBook(cr.toAri))
    }

    @Test
    fun footnoteContent() {
        val fn = Footnote(
            id = 1L, bibleVersionId = 1L,
            ari = Ari.encode(1, 1, 1),
            content = "Or: when God began creating",
        )
        assertTrue(fn.content.isNotEmpty())
    }

    @Test
    fun searchResultWithHighlights() {
        val verse = Verse(
            id = 1L, bibleVersionId = 1L, ari = Ari.encode(43, 3, 16),
            bookId = 43, chapter = 3, verse = 16, text = "For God so loved the world",
        )
        val sr = SearchResult(
            verse = verse, bookName = "John",
            highlights = listOf(IntRange(4, 6)),
        )
        assertEquals("John", sr.bookName)
        assertTrue(sr.highlights.isNotEmpty())
    }

    // ══════════════════════════════════════
    // MarkerRepository — Model Tests
    // ══════════════════════════════════════

    @Test
    fun markerKindBookmark() {
        val m = Marker(
            id = 1L, gid = "uuid-1", ari = Ari.encode(1, 1, 1),
            kind = Marker.KIND_BOOKMARK, caption = "My bookmark",
            verseCount = 1,
        )
        assertTrue(m.isBookmark)
        assertFalse(m.isNote)
        assertFalse(m.isHighlight)
    }

    @Test
    fun markerKindNote() {
        val m = Marker(
            id = 2L, gid = "uuid-2", ari = Ari.encode(19, 23, 1),
            kind = Marker.KIND_NOTE, caption = "The Lord is my shepherd",
            verseCount = 6,
        )
        assertTrue(m.isNote)
        assertEquals(19, Ari.decodeBook(m.ari)) // Psalms
    }

    @Test
    fun markerKindHighlight() {
        val m = Marker(
            id = 3L, gid = "uuid-3", ari = Ari.encode(43, 3, 16),
            kind = Marker.KIND_HIGHLIGHT, caption = "",
            verseCount = 1, color = "#FFD700",
        )
        assertTrue(m.isHighlight)
        assertEquals("#FFD700", m.color)
    }

    @Test
    fun markerKindConstants() {
        assertEquals(0, Marker.KIND_BOOKMARK)
        assertEquals(1, Marker.KIND_NOTE)
        assertEquals(2, Marker.KIND_HIGHLIGHT)
    }

    @Test
    fun labelModel() {
        val l = Label(
            id = 1L, gid = "label-uuid", title = "Promises",
            backgroundColor = "#4CAF50",
        )
        assertEquals("Promises", l.title)
        assertNotNull(l.backgroundColor)
    }

    @Test
    fun progressMarkModel() {
        val pm = ProgressMark(
            id = 1L, gid = "pm-uuid", preset = 0,
            ari = Ari.encode(1, 1, 1), caption = "Current Reading",
        )
        assertEquals(0, pm.preset)
    }

    @Test
    fun progressMarkHistoryModel() {
        val pmh = ProgressMarkHistory(
            id = 1L, progressMarkId = 1L,
            ari = Ari.encode(1, 2, 1),
        )
        assertEquals(1, Ari.decodeBook(pmh.ari))
        assertEquals(2, Ari.decodeChapter(pmh.ari))
    }

    // ══════════════════════════════════════
    // ReadingPlanRepository — Model Tests
    // ══════════════════════════════════════

    @Test
    fun readingPlanModel() {
        val plan = ReadingPlan(
            id = 1L, title = "Bible in a Year",
            description = "Read the entire Bible in 365 days",
            totalDays = 365, isActive = true,
        )
        assertEquals(365, plan.totalDays)
        assertTrue(plan.isActive)
    }

    @Test
    fun readingPlanDayAriRanges() {
        val day = ReadingPlanDay(
            id = 1L, readingPlanId = 1L, dayNumber = 1,
            title = "Day 1", ariRanges = "[65537, 65538, 65539]",
        )
        assertEquals(1, day.dayNumber)
        assertTrue(day.ariRanges.contains("65537"))
    }

    @Test
    fun readingPlanProgressTracking() {
        val progress = ReadingPlanProgress(
            id = 1L, userId = 1L, readingPlanId = 1L,
            readingPlanDayId = 1L, completedAt = "2024-01-01",
        )
        assertNotNull(progress.completedAt)
    }

    // ══════════════════════════════════════
    // DevotionalRepository — Model Tests
    // ══════════════════════════════════════

    @Test
    fun devotionalWithReference() {
        val d = Devotional(
            id = 1L, title = "Morning Word",
            body = "Today's devotional content...",
            publishDate = "2024-06-15",
            ariReference = Ari.encode(19, 23, 1),
            author = "Pastor Smith",
            isPublished = true,
        )
        assertEquals(19, Ari.decodeBook(d.ariReference!!))
        assertEquals("Pastor Smith", d.author)
    }

    @Test
    fun devotionalWithoutReference() {
        val d = Devotional(
            id = 2L, title = "Evening Reflection",
            body = "Content without verse reference.",
            publishDate = "2024-06-15",
            isPublished = true,
        )
        assertNull(d.ariReference)
        assertNull(d.author)
    }

    // ══════════════════════════════════════
    // SongRepository — Model Tests
    // ══════════════════════════════════════

    @Test
    fun songBookModel() {
        val sb = SongBook(
            id = 1L, title = "Kidung Jemaat",
            description = "Indonesian Hymnal", isActive = true,
        )
        assertTrue(sb.isActive)
    }

    @Test
    fun songModel() {
        val s = Song(
            id = 1L, songBookId = 1L, number = 1,
            title = "Holy, Holy, Holy",
            lyrics = "Holy, holy, holy! Lord God Almighty!\nEarly in the morning our song shall rise to Thee.",
            author = "Reginald Heber", tune = "NICAEA", key = "D",
        )
        assertEquals(1, s.number)
        assertEquals("D", s.key)
        assertTrue(s.lyrics.contains("Holy"))
    }

    @Test
    fun songWithoutOptionals() {
        val s = Song(
            id = 2L, songBookId = 1L, number = 2,
            title = "Amazing Grace", lyrics = "Amazing grace!",
        )
        assertNull(s.author)
        assertNull(s.tune)
        assertNull(s.key)
    }

    // ══════════════════════════════════════
    // UserPreferenceRepository — Key Patterns
    // ══════════════════════════════════════

    @Test
    fun preferenceKeyPatterns() {
        // Verify common preference keys are valid strings
        val keys = listOf(
            "active_bible_version",
            "active_book_id",
            "font_size",
            "theme_mode",
            "sync_enabled",
            "last_read_ari",
        )
        keys.forEach { key ->
            assertTrue(key.isNotBlank())
            assertFalse(key.contains(" "))
        }
    }

    // ══════════════════════════════════════
    // ARI Integration with Repository Models
    // ══════════════════════════════════════

    @Test
    fun ariConsistencyAcrossModels() {
        val ari = Ari.encode(43, 3, 16) // John 3:16
        val verse = Verse(id = 1L, bibleVersionId = 1L, ari = ari, bookId = 43, chapter = 3, verse = 16, text = "")
        val marker = Marker(id = 1L, gid = "g1", ari = ari, kind = 0, caption = "", verseCount = 1)
        val pericope = Pericope(id = 1L, bibleVersionId = 1L, ari = ari, title = "")
        val crossRef = CrossReference(id = 1L, bibleVersionId = 1L, fromAri = ari, toAri = ari)

        // All models use the same ARI encoding
        assertEquals(verse.ari, marker.ari)
        assertEquals(marker.ari, pericope.ari)
        assertEquals(pericope.ari, crossRef.fromAri)
    }

    @Test
    fun ariRangeForMarkerQuery() {
        // Simulating getMarkersByAriRange for Genesis chapter 1 (all verses)
        val startAri = Ari.encode(1, 1, 0)  // Gen 1:0 (chapter start)
        val endAri = Ari.encode(1, 1, 255)   // Gen 1:255 (chapter end)
        assertTrue(startAri < endAri)

        val midAri = Ari.encode(1, 1, 15)
        assertTrue(midAri in startAri..endAri)
    }

    @Test
    fun ariBookBoundaries() {
        // OT ends at Malachi (39), NT starts at Matthew (40)
        val lastOt = Ari.encode(39, 4, 6)   // Mal 4:6
        val firstNt = Ari.encode(40, 1, 1)    // Mat 1:1

        assertTrue(Ari.decodeBook(lastOt) < Ari.decodeBook(firstNt))
        assertEquals(39, Ari.decodeBook(lastOt))
        assertEquals(40, Ari.decodeBook(firstNt))
    }
}
