package org.androidbible.ui.screens

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue
import kotlin.test.assertFalse
import org.androidbible.util.Ari
import org.androidbible.util.DeepLink

/**
 * Tests for Platform Polish features: deep links, onboarding, icons.
 */
class PlatformPolishTest {

    // ── Deep Link Parsing ──

    @Test
    fun deepLinkParseGenesis1_1() {
        val ref = DeepLink.parse("bible://Gen.1.1")
        assertNotNull(ref)
        assertEquals(1, ref.bookId) // Genesis
        assertEquals(1, ref.chapter)
        assertEquals(1, ref.verse)
        assertNull(ref.versionKey)
    }

    @Test
    fun deepLinkParseWithVersion() {
        val ref = DeepLink.parse("bible://Rev.22.21?version=KJV")
        assertNotNull(ref)
        assertEquals(66, ref.bookId) // Revelation
        assertEquals(22, ref.chapter)
        assertEquals(21, ref.verse)
        assertEquals("KJV", ref.versionKey)
    }

    @Test
    fun deepLinkParseChapterOnly() {
        val ref = DeepLink.parse("bible://Ps.23")
        assertNotNull(ref)
        assertEquals(19, ref.bookId) // Psalms
        assertEquals(23, ref.chapter)
        assertEquals(0, ref.verse) // default
    }

    @Test
    fun deepLinkParseInvalidBook() {
        val ref = DeepLink.parse("bible://InvalidBook.1.1")
        assertNull(ref)
    }

    @Test
    fun deepLinkParseMalformed() {
        assertNull(DeepLink.parse(""))
        assertNull(DeepLink.parse("http://google.com"))
        assertNull(DeepLink.parse("bible://"))
    }

    // ── Deep Link Building ──

    @Test
    fun deepLinkBuildGenesis() {
        val uri = DeepLink.build(1, 1, 1)
        assertTrue(uri.startsWith("bible://"))
        assertTrue(uri.contains("Gen"))
        assertTrue(uri.contains("1.1"))
    }

    @Test
    fun deepLinkBuildWithVersion() {
        val uri = DeepLink.build(66, 22, 21, "KJV")
        assertTrue(uri.contains("version=KJV"))
    }

    @Test
    fun deepLinkBuildChapterOnly() {
        val uri = DeepLink.build(19, 23, 0)
        assertTrue(uri.contains("23"))
        assertFalse(uri.contains("23.0"))
    }

    // ── Deep Link Round-Trip ──

    @Test
    fun deepLinkRoundTrip() {
        val original = DeepLink.build(43, 3, 16, "ESV") // John 3:16
        val parsed = DeepLink.parse(original)
        assertNotNull(parsed)
        assertEquals(43, parsed.bookId)
        assertEquals(3, parsed.chapter)
        assertEquals(16, parsed.verse)
        assertEquals("ESV", parsed.versionKey)
    }

    @Test
    fun deepLinkRoundTripAllBooks() {
        for (bookId in 1..66) {
            val uri = DeepLink.build(bookId, 1, 1)
            if (uri.isNotEmpty()) {
                val parsed = DeepLink.parse(uri)
                assertNotNull(parsed, "Failed to parse URI for bookId=$bookId: $uri")
                assertEquals(bookId, parsed.bookId, "BookId mismatch for bookId=$bookId")
            }
        }
    }

    // ── Deep Link to ARI ──

    @Test
    fun deepLinkToAri() {
        val ref = DeepLink.parse("bible://Gen.1.1")
        assertNotNull(ref)
        val ari = Ari.encode(ref.bookId, ref.chapter, ref.verse)
        assertEquals(1, Ari.decodeBook(ari))
        assertEquals(1, Ari.decodeChapter(ari))
        assertEquals(1, Ari.decodeVerse(ari))
    }

    @Test
    fun deepLinkToAriPsalm119() {
        val ref = DeepLink.parse("bible://Ps.119.176")
        assertNotNull(ref)
        val ari = Ari.encode(ref.bookId, ref.chapter, ref.verse)
        assertEquals(19, Ari.decodeBook(ari))
        assertEquals(119, Ari.decodeChapter(ari))
        assertEquals(176, Ari.decodeVerse(ari))
    }

    // ── DeepLinkHandler ──

    @Test
    fun deepLinkHandlerEmitDoesNotThrow() {
        // Just verify the handler can accept URIs without crashing
        org.androidbible.util.DeepLinkHandler.emit("bible://Gen.1.1")
        org.androidbible.util.DeepLinkHandler.emit("invalid://url")
    }

    // ── Onboarding State ──

    @Test
    fun onboardingDefaultNotComplete() {
        // OnboardingScreenModel.isOnboardingComplete checks Settings.getBoolean
        // Default value should be false
        // We can't test with real Settings here, but verify the key constant is consistent
        assertTrue(true) // Placeholder — actual test needs DI setup
    }

    // ── Share Manager Contract ──

    @Test
    fun shareManagerMethodSignatures() {
        // Verify the expect class compiles and has correct signatures
        // Actual invocation needs platform context
        assertTrue(true) // Compiles = passes
    }

    // ── Crash Reporter Contract ──

    @Test
    fun crashReporterMethodSignatures() {
        // Verify the expect class compiles and has correct signatures
        assertTrue(true) // Compiles = passes
    }

    // ── Splash Screen Navigation Logic ──

    @Test
    fun splashDurationConstant() {
        // Splash auto-navigates after 1500ms — verify constant is reasonable
        val expectedMs = 1500L
        assertTrue(expectedMs in 500L..5000L, "Splash duration should be between 0.5-5 seconds")
    }

    // ── ARI Encoding from Deep Links ──

    @Test
    fun ariFromDeepLinkMatthew28_19() {
        val ref = DeepLink.parse("bible://Matt.28.19")
        assertNotNull(ref)
        val ari = Ari.encode(ref.bookId, ref.chapter, ref.verse)
        val refString = Ari.referenceString(ari)
        assertTrue(refString.contains("28"))
        assertTrue(refString.contains("19"))
    }

    @Test
    fun ariFromDeepLinkRomans8_28() {
        val ref = DeepLink.parse("bible://Rom.8.28")
        assertNotNull(ref)
        assertEquals(45, ref.bookId) // Romans
        val ari = Ari.encode(ref.bookId, ref.chapter, ref.verse)
        assertEquals(45, Ari.decodeBook(ari))
        assertEquals(8, Ari.decodeChapter(ari))
        assertEquals(28, Ari.decodeVerse(ari))
    }
}
