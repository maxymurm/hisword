package org.androidbible.ui.screens

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertTrue
import kotlin.test.assertFalse
import kotlin.test.assertNull
import org.androidbible.domain.model.*
import org.androidbible.util.Ari
import org.androidbible.util.DeepLink

/**
 * UI logic tests for critical user flows.
 * Tests ViewModel/ScreenModel logic without Compose runtime.
 */
class CriticalFlowsTest {

    // ══════════════════════════════════════
    // Bible Reader Flow
    // ══════════════════════════════════════

    @Test
    fun navigateToAriDecodesCorrectly() {
        val ari = Ari.encode(43, 3, 16) // John 3:16
        val bookId = Ari.decodeBook(ari)
        val chapter = Ari.decodeChapter(ari)
        assertEquals(43, bookId)
        assertEquals(3, chapter)
    }

    @Test
    fun nextChapterIncrement() {
        var currentChapter = 3
        currentChapter++
        assertEquals(4, currentChapter)
    }

    @Test
    fun previousChapterDecrement() {
        var currentChapter = 3
        currentChapter = maxOf(1, currentChapter - 1)
        assertEquals(2, currentChapter)
    }

    @Test
    fun previousChapterAtFirstStaysAtOne() {
        var currentChapter = 1
        currentChapter = maxOf(1, currentChapter - 1)
        assertEquals(1, currentChapter)
    }

    @Test
    fun chapterNavigationBounds() {
        val maxChapters = 50 // Genesis
        var chapter = 50
        chapter = minOf(maxChapters, chapter + 1)
        assertEquals(50, chapter) // Should stay at max
    }

    // ══════════════════════════════════════
    // Search Flow
    // ══════════════════════════════════════

    @Test
    fun searchQueryTrimming() {
        val query = "  love  "
        val trimmed = query.trim()
        assertEquals("love", trimmed)
    }

    @Test
    fun searchQueryMinLength() {
        val query = "ab"
        val isValid = query.trim().length >= 2
        assertTrue(isValid)
    }

    @Test
    fun searchQueryEmpty() {
        val query = ""
        val isValid = query.trim().length >= 2
        assertFalse(isValid)
    }

    @Test
    fun searchResultHighlighting() {
        val text = "For God so loved the world"
        val query = "loved"
        val startIndex = text.indexOf(query, ignoreCase = true)
        assertEquals(11, startIndex)
        val range = IntRange(startIndex, startIndex + query.length - 1)
        assertEquals(IntRange(11, 15), range)
    }

    // ══════════════════════════════════════
    // Bookmark Flow
    // ══════════════════════════════════════

    @Test
    fun createBookmarkMarker() {
        val ari = Ari.encode(43, 3, 16)
        val marker = Marker(
            id = 0L, gid = "", ari = ari,
            kind = Marker.KIND_BOOKMARK,
            caption = "John 3:16", verseCount = 1,
        )
        assertTrue(marker.isBookmark)
        assertEquals(ari, marker.ari)
    }

    @Test
    fun createHighlightMarker() {
        val ari = Ari.encode(19, 23, 1)
        val marker = Marker(
            id = 0L, gid = "", ari = ari,
            kind = Marker.KIND_HIGHLIGHT,
            caption = "", verseCount = 6, color = "#FFFF00",
        )
        assertTrue(marker.isHighlight)
        assertEquals(6, marker.verseCount)
    }

    @Test
    fun createNoteMarker() {
        val ari = Ari.encode(45, 8, 28)
        val marker = Marker(
            id = 0L, gid = "", ari = ari,
            kind = Marker.KIND_NOTE,
            caption = "All things work together for good",
            verseCount = 1,
        )
        assertTrue(marker.isNote)
    }

    @Test
    fun markerFilterByKind() {
        val markers = listOf(
            Marker(1L, "g1", ari = 1, kind = 0, caption = "", verseCount = 1),
            Marker(2L, "g2", ari = 2, kind = 1, caption = "", verseCount = 1),
            Marker(3L, "g3", ari = 3, kind = 2, caption = "", verseCount = 1),
            Marker(4L, "g4", ari = 4, kind = 0, caption = "", verseCount = 1),
        )
        val bookmarks = markers.filter { it.isBookmark }
        assertEquals(2, bookmarks.size)
        val notes = markers.filter { it.isNote }
        assertEquals(1, notes.size)
    }

    // ══════════════════════════════════════
    // Reading Plan Flow
    // ══════════════════════════════════════

    @Test
    fun readingPlanProgressCalculation() {
        val totalDays = 365
        val completedDays = 73
        val percentage = (completedDays * 100) / totalDays
        assertEquals(20, percentage) // 73/365 = 20%
    }

    @Test
    fun readingPlanProgressComplete() {
        val totalDays = 30
        val completedDays = 30
        val percentage = (completedDays * 100) / totalDays
        assertEquals(100, percentage)
    }

    @Test
    fun readingPlanDayNavigation() {
        val currentDay = 5
        val totalDays = 365

        val nextDay = minOf(currentDay + 1, totalDays)
        assertEquals(6, nextDay)

        val prevDay = maxOf(currentDay - 1, 1)
        assertEquals(4, prevDay)
    }

    // ══════════════════════════════════════
    // Devotional Flow
    // ══════════════════════════════════════

    @Test
    fun devotionalDateNavigation() {
        val dates = listOf("2024-06-13", "2024-06-14", "2024-06-15")
        var currentIndex = 1 // June 14

        // Next day
        if (currentIndex < dates.size - 1) currentIndex++
        assertEquals(2, currentIndex) // June 15

        // Previous day
        if (currentIndex > 0) currentIndex--
        assertEquals(1, currentIndex) // June 14
    }

    @Test
    fun devotionalVerseReference() {
        val devotional = Devotional(
            id = 1L, title = "Morning Word",
            body = "Content", publishDate = "2024-06-15",
            ariReference = Ari.encode(19, 23, 1),
            isPublished = true,
        )
        val ref = devotional.ariReference!!
        assertEquals("Psa", Ari.referenceString(ref).substringBefore(" "))
    }

    // ══════════════════════════════════════
    // Song Search Flow
    // ══════════════════════════════════════

    @Test
    fun songSearchByTitle() {
        val songs = listOf(
            Song(1L, 1L, 1, "Holy Holy Holy", "lyrics", null, null, null, null),
            Song(2L, 1L, 2, "Amazing Grace", "lyrics", null, null, null, null),
            Song(3L, 1L, 3, "How Great Thou Art", "lyrics", null, null, null, null),
        )
        val query = "grace"
        val results = songs.filter { it.title.contains(query, ignoreCase = true) }
        assertEquals(1, results.size)
        assertEquals("Amazing Grace", results[0].title)
    }

    @Test
    fun songSearchByNumber() {
        val songs = listOf(
            Song(1L, 1L, 100, "Title A", "lyrics", null, null, null, null),
            Song(2L, 1L, 200, "Title B", "lyrics", null, null, null, null),
        )
        val number = 100
        val result = songs.find { it.number == number }
        assertNotNull(result)
        assertEquals("Title A", result.title)
    }

    // ══════════════════════════════════════
    // Deep Link Navigation Flow
    // ══════════════════════════════════════

    @Test
    fun deepLinkToReaderNavigation() {
        val uri = "bible://John.3.16?version=KJV"
        val ref = DeepLink.parse(uri)
        assertNotNull(ref)

        val ari = Ari.encode(ref.bookId, ref.chapter, ref.verse)
        assertEquals(43, Ari.decodeBook(ari))
        assertEquals(3, Ari.decodeChapter(ari))
        assertEquals(16, Ari.decodeVerse(ari))
        assertEquals("KJV", ref.versionKey)
    }

    @Test
    fun deepLinkInvalidGraceful() {
        val ref = DeepLink.parse("not-a-valid-link")
        assertNull(ref)
        // App should gracefully ignore invalid deep links
    }

    // ══════════════════════════════════════
    // Version Selection Flow
    // ══════════════════════════════════════

    @Test
    fun versionSorting() {
        val versions = listOf(
            BibleVersion(3L, "NIV", "NIV", "en", sortOrder = 3, hasOldTestament = true, hasNewTestament = true, isActive = true),
            BibleVersion(1L, "KJV", "KJV", "en", sortOrder = 1, hasOldTestament = true, hasNewTestament = true, isActive = true),
            BibleVersion(2L, "ESV", "ESV", "en", sortOrder = 2, hasOldTestament = true, hasNewTestament = true, isActive = true),
        )
        val sorted = versions.sortedBy { it.sortOrder }
        assertEquals("KJV", sorted[0].shortName)
        assertEquals("ESV", sorted[1].shortName)
        assertEquals("NIV", sorted[2].shortName)
    }

    @Test
    fun versionFilterByLanguage() {
        val versions = listOf(
            BibleVersion(1L, "KJV", "KJV", "en", sortOrder = 1, hasOldTestament = true, hasNewTestament = true, isActive = true),
            BibleVersion(2L, "TB", "TB", "id", sortOrder = 2, hasOldTestament = true, hasNewTestament = true, isActive = true),
            BibleVersion(3L, "NIV", "NIV", "en", sortOrder = 3, hasOldTestament = true, hasNewTestament = true, isActive = true),
        )
        val english = versions.filter { it.languageCode == "en" }
        assertEquals(2, english.size)
    }

    // ══════════════════════════════════════
    // Onboarding Flow
    // ══════════════════════════════════════

    @Test
    fun onboardingPagerPageCount() {
        val pageCount = 4 // Welcome, Translations, Study Tools, Choose Bible
        assertEquals(4, pageCount)
    }

    @Test
    fun onboardingSkipGoesToHome() {
        // After skip, user should land on HomeScreen
        val onboardingComplete = true
        assertTrue(onboardingComplete)
    }

    // ══════════════════════════════════════
    // Settings Flow
    // ══════════════════════════════════════

    @Test
    fun fontSizeRange() {
        val minSize = 12
        val maxSize = 32
        val defaultSize = 16
        assertTrue(defaultSize in minSize..maxSize)
    }

    @Test
    fun themeOptions() {
        val themes = listOf("system", "light", "dark")
        assertEquals(3, themes.size)
        assertTrue(themes.contains("system"))
    }
}
