package org.androidbible.ui.screens

import org.androidbible.domain.model.*
import org.androidbible.ui.screens.study.StudyPad
import org.androidbible.ui.screens.study.parseVerseReference
import org.androidbible.util.Ari
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotEquals
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

/**
 * Tests for Phase 9 content features:
 * - ARI reference string formatting
 * - Verse reference parsing (StudyPad links)
 * - ReadingPlan day ARI ranges
 * - Devotional model
 * - Song/SongBook model
 * - StudyPad serialization
 * - Cross-reference entry model
 * - Strong's number validation
 */
class ContentFeaturesTest {

    // ── ARI reference string tests ─────────────────────────

    @Test
    fun ariReferenceStringGenesis() {
        val ari = Ari.encode(1, 1, 1)
        val ref = Ari.referenceString(ari)
        assertEquals("Gen 1:1", ref)
    }

    @Test
    fun ariReferenceStringRevelation() {
        val ari = Ari.encode(66, 22, 21)
        val ref = Ari.referenceString(ari)
        assertEquals("Rev 22:21", ref)
    }

    @Test
    fun ariReferenceStringChapterOnly() {
        val ari = Ari.encode(19, 23, 0)
        val ref = Ari.referenceString(ari)
        assertEquals("Psa 23", ref)
    }

    @Test
    fun ariReferenceStringPsalm119() {
        val ari = Ari.encode(19, 119, 105)
        val ref = Ari.referenceString(ari)
        assertEquals("Psa 119:105", ref)
    }

    @Test
    fun ariReferenceStringUnknownBook() {
        val ari = Ari.encode(99, 1, 1)
        val ref = Ari.referenceString(ari)
        assertEquals("Book 99 1:1", ref)
    }

    // ── Verse reference parsing (StudyPad) ─────────────────

    @Test
    fun parseVerseRefGenesis() {
        val ari = parseVerseReference("Gen 1:1")
        assertEquals(1, Ari.decodeBook(ari))
        assertEquals(1, Ari.decodeChapter(ari))
        assertEquals(1, Ari.decodeVerse(ari))
    }

    @Test
    fun parseVerseRefRevelation() {
        val ari = parseVerseReference("Rev 22:21")
        assertEquals(66, Ari.decodeBook(ari))
        assertEquals(22, Ari.decodeChapter(ari))
        assertEquals(21, Ari.decodeVerse(ari))
    }

    @Test
    fun parseVerseRefMatthew() {
        val ari = parseVerseReference("Mat 5:9")
        assertEquals(40, Ari.decodeBook(ari))
        assertEquals(5, Ari.decodeChapter(ari))
        assertEquals(9, Ari.decodeVerse(ari))
    }

    @Test
    fun parseVerseRefChapterOnly() {
        val ari = parseVerseReference("Psa 23")
        assertEquals(19, Ari.decodeBook(ari))
        assertEquals(23, Ari.decodeChapter(ari))
        assertEquals(0, Ari.decodeVerse(ari))
    }

    @Test
    fun parseVerseRefInvalid() {
        assertEquals(0, parseVerseReference(""))
        assertEquals(0, parseVerseReference("InvalidBook 1:1"))
        assertEquals(0, parseVerseReference("Gen"))
    }

    // ── Content model tests ────────────────────────────────

    @Test
    fun readingPlanModelDefaults() {
        val plan = ReadingPlan(
            title = "Bible in a Year",
            totalDays = 365,
        )
        assertEquals(0L, plan.id)
        assertEquals("Bible in a Year", plan.title)
        assertEquals(365, plan.totalDays)
        assertTrue(plan.isActive)
    }

    @Test
    fun readingPlanDayAriRanges() {
        val day = ReadingPlanDay(
            readingPlanId = 1,
            dayNumber = 1,
            title = "Day 1",
            ariRanges = "[65537, 65538, 65539]",
        )
        assertEquals("Day 1", day.title)
        assertEquals(1, day.dayNumber)
    }

    @Test
    fun readingPlanProgressTracking() {
        val progress = ReadingPlanProgress(
            userId = 1,
            readingPlanId = 1,
            readingPlanDayId = 1,
            completedAt = "2024-01-15T00:00:00Z",
        )
        assertEquals(1L, progress.readingPlanDayId)
        assertNotNull(progress.completedAt)
    }

    @Test
    fun devotionalModel() {
        val devo = Devotional(
            title = "Morning Grace",
            body = "Today's devotional text...",
            publishDate = "2024-06-15",
            ariReference = Ari.encode(43, 3, 16),
            author = "John Author",
        )
        assertEquals("Morning Grace", devo.title)
        assertEquals(43, Ari.decodeBook(devo.ariReference!!))
        assertEquals("John Author", devo.author)
    }

    @Test
    fun devotionalNoReference() {
        val devo = Devotional(
            title = "Reflection",
            body = "A general reflection...",
            publishDate = "2024-06-16",
        )
        assertTrue(devo.ariReference == null || devo.ariReference == 0)
    }

    @Test
    fun songBookModel() {
        val book = SongBook(
            id = 1,
            title = "Hymns of the Faith",
            description = "Classic hymns for worship",
        )
        assertEquals("Hymns of the Faith", book.title)
        assertTrue(book.isActive)
    }

    @Test
    fun songModel() {
        val song = Song(
            songBookId = 1,
            number = 1,
            title = "Amazing Grace",
            lyrics = "Amazing grace, how sweet the sound...",
            author = "John Newton",
            key = "G",
        )
        assertEquals("Amazing Grace", song.title)
        assertEquals("John Newton", song.author)
        assertEquals("G", song.key)
    }

    // ── StudyPad tests ─────────────────────────────────────

    @Test
    fun studyPadCreation() {
        val pad = StudyPad(
            id = "123456",
            title = "John 3 Study",
            content = "# Key Themes\n\n- New birth\n- God's love [[Joh 3:16]]\n- Light vs darkness",
            createdAt = "2024-01-01T00:00:00Z",
            updatedAt = "2024-01-02T12:00:00Z",
        )
        assertEquals("John 3 Study", pad.title)
        assertTrue(pad.content.contains("[[Joh 3:16]]"))
    }

    @Test
    fun studyPadVerseRefInContent() {
        val content = "Study of creation [[Gen 1:1]] and salvation [[Joh 3:16]]"
        val refs = Regex("\\[\\[(.+?)]]").findAll(content).map { it.groupValues[1] }.toList()
        assertEquals(2, refs.size)
        assertEquals("Gen 1:1", refs[0])
        assertEquals("Joh 3:16", refs[1])
    }

    // ── Strong's number validation ─────────────────────────

    @Test
    fun strongsNumberHebrewValid() {
        val num = "H1254"
        assertTrue(num.matches(Regex("^[HG]\\d+$")))
    }

    @Test
    fun strongsNumberGreekValid() {
        val num = "G26"
        assertTrue(num.matches(Regex("^[HG]\\d+$")))
    }

    @Test
    fun strongsNumberInvalid() {
        val invalid = listOf("1254", "X123", "H", "G", "hello", "")
        invalid.forEach { num ->
            assertTrue(!num.matches(Regex("^[HG]\\d+$")), "Expected invalid: $num")
        }
    }

    // ── Cross-reference model tests ────────────────────────

    @Test
    fun crossReferenceModel() {
        val ref = CrossReference(
            bibleVersionId = 1,
            fromAri = Ari.encode(43, 3, 16),
            toAri = Ari.encode(45, 5, 8),
        )
        assertEquals(43, Ari.decodeBook(ref.fromAri))
        assertEquals(45, Ari.decodeBook(ref.toAri))
    }

    @Test
    fun crossReferenceAriSymmetry() {
        val from = Ari.encode(1, 1, 1)
        val to = Ari.encode(66, 22, 21)
        val ref = CrossReference(bibleVersionId = 1, fromAri = from, toAri = to)
        assertNotEquals(ref.fromAri, ref.toAri)
        assertEquals("Gen 1:1", Ari.referenceString(ref.fromAri))
        assertEquals("Rev 22:21", Ari.referenceString(ref.toAri))
    }

    // ── ARI encode/decode round-trip ───────────────────────

    @Test
    fun ariRoundTripAllBooks() {
        for (bookId in 1..66) {
            val ari = Ari.encode(bookId, 1, 1)
            assertEquals(bookId, Ari.decodeBook(ari), "Book $bookId round-trip failed")
            assertEquals(1, Ari.decodeChapter(ari))
            assertEquals(1, Ari.decodeVerse(ari))
        }
    }

    @Test
    fun ariNavigateToDecoding() {
        val ari = Ari.encode(43, 3, 16)
        assertEquals(43, Ari.decodeBook(ari))
        assertEquals(3, Ari.decodeChapter(ari))
        assertEquals(16, Ari.decodeVerse(ari))
    }
}
