package org.androidbible.domain.model

import kotlinx.serialization.json.Json
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

class ModelSerializationTest {

    private val json = Json {
        ignoreUnknownKeys = true
        encodeDefaults = true
    }

    // === BibleModels ===

    @Test
    fun testBibleVersionSerialization() {
        val version = BibleVersion(
            id = 1,
            shortName = "KJV",
            longName = "King James Version",
            languageCode = "en",
            locale = "en_US",
            description = "Classic English Bible",
            sortOrder = 1,
            hasOldTestament = true,
            hasNewTestament = true,
            isActive = true,
        )
        val jsonStr = json.encodeToString(BibleVersion.serializer(), version)
        val decoded = json.decodeFromString(BibleVersion.serializer(), jsonStr)
        assertEquals(version, decoded)
    }

    @Test
    fun testBookSerialization() {
        val book = Book(
            id = 1,
            bibleVersionId = 1,
            bookId = 43,
            shortName = "Jhn",
            longName = "John",
            abbreviation = "Jn",
            chapterCount = 21,
            sortOrder = 43,
        )
        val jsonStr = json.encodeToString(Book.serializer(), book)
        val decoded = json.decodeFromString(Book.serializer(), jsonStr)
        assertEquals(book, decoded)
    }

    @Test
    fun testVerseSerialization() {
        val verse = Verse(
            id = 100,
            bibleVersionId = 1,
            ari = (43 shl 16) or (3 shl 8) or 16,
            bookId = 43,
            chapter = 3,
            verse = 16,
            text = "For God so loved the world...",
            textWithoutFormatting = "For God so loved the world...",
        )
        val jsonStr = json.encodeToString(Verse.serializer(), verse)
        val decoded = json.decodeFromString(Verse.serializer(), jsonStr)
        assertEquals(verse, decoded)
    }

    @Test
    fun testChapterSerialization() {
        val chapter = Chapter(
            bookId = 1,
            chapter = 1,
            verses = listOf(
                Verse(id = 1, bibleVersionId = 1, ari = (1 shl 16) or (1 shl 8) or 1, bookId = 1, chapter = 1, verse = 1, text = "In the beginning..."),
                Verse(id = 2, bibleVersionId = 1, ari = (1 shl 16) or (1 shl 8) or 2, bookId = 1, chapter = 1, verse = 2, text = "And the earth was..."),
            ),
        )
        val jsonStr = json.encodeToString(Chapter.serializer(), chapter)
        val decoded = json.decodeFromString(Chapter.serializer(), jsonStr)
        assertEquals(chapter, decoded)
    }

    @Test
    fun testSearchResultSerialization() {
        val result = SearchResult(
            verse = Verse(id = 1, bibleVersionId = 1, ari = 0, bookId = 1, chapter = 1, verse = 1, text = "test"),
            bookName = "Genesis",
        )
        val jsonStr = json.encodeToString(SearchResult.serializer(), result)
        val decoded = json.decodeFromString(SearchResult.serializer(), jsonStr)
        assertEquals(result.bookName, decoded.bookName)
        assertEquals(result.verse.text, decoded.verse.text)
    }

    // === MarkerModels ===

    @Test
    fun testMarkerKindConstants() {
        assertEquals(0, Marker.KIND_BOOKMARK)
        assertEquals(1, Marker.KIND_NOTE)
        assertEquals(2, Marker.KIND_HIGHLIGHT)
    }

    @Test
    fun testMarkerBookmark() {
        val marker = Marker(
            id = 1,
            gid = "uuid-1",
            ari = (43 shl 16) or (3 shl 8) or 16,
            kind = Marker.KIND_BOOKMARK,
            caption = "Favorite verse",
        )
        assertTrue(marker.isBookmark)
        assertTrue(!marker.isNote)
        assertTrue(!marker.isHighlight)
    }

    @Test
    fun testMarkerNote() {
        val marker = Marker(
            id = 2,
            gid = "uuid-2",
            ari = (1 shl 16) or (1 shl 8) or 1,
            kind = Marker.KIND_NOTE,
            caption = "Study note",
        )
        assertTrue(marker.isNote)
    }

    @Test
    fun testMarkerHighlight() {
        val marker = Marker(
            id = 3,
            gid = "uuid-3",
            ari = (19 shl 16) or (23 shl 8) or 1,
            kind = Marker.KIND_HIGHLIGHT,
            color = 0xFFFF00,
        )
        assertTrue(marker.isHighlight)
        assertEquals(0xFFFF00, marker.color)
    }

    @Test
    fun testMarkerSerialization() {
        val marker = Marker(
            id = 1,
            gid = "test-gid",
            userId = 42,
            bibleVersionId = 1,
            ari = (43 shl 16) or (3 shl 8) or 16,
            kind = Marker.KIND_BOOKMARK,
            caption = "John 3:16",
            verseCount = 1,
            color = null,
        )
        val jsonStr = json.encodeToString(Marker.serializer(), marker)
        val decoded = json.decodeFromString(Marker.serializer(), jsonStr)
        assertEquals(marker, decoded)
    }

    @Test
    fun testLabelSerialization() {
        val label = Label(
            id = 1,
            gid = "label-gid",
            title = "Important",
            backgroundColor = 0xFF0000,
        )
        val jsonStr = json.encodeToString(Label.serializer(), label)
        val decoded = json.decodeFromString(Label.serializer(), jsonStr)
        assertEquals(label, decoded)
    }

    @Test
    fun testProgressMarkSerialization() {
        val pm = ProgressMark(
            id = 1,
            gid = "pm-gid",
            preset = 0,
            ari = (1 shl 16) or (1 shl 8) or 1,
            caption = "Reading Progress",
        )
        val jsonStr = json.encodeToString(ProgressMark.serializer(), pm)
        val decoded = json.decodeFromString(ProgressMark.serializer(), jsonStr)
        assertEquals(pm, decoded)
    }

    // === SyncModels ===

    @Test
    fun testSyncEventPayloadSerialization() {
        val payload = SyncEventPayload(
            entityType = "marker",
            entityId = "uuid-123",
            action = "create",
            payload = """{"ari":12345,"kind":0}""",
        )
        val jsonStr = json.encodeToString(SyncEventPayload.serializer(), payload)
        val decoded = json.decodeFromString(SyncEventPayload.serializer(), jsonStr)
        assertEquals(payload, decoded)
    }

    @Test
    fun testSyncPushRequestSerialization() {
        val request = SyncPushRequest(
            events = listOf(
                SyncEventPayload("marker", "id1", "create", "{}"),
                SyncEventPayload("label", "id2", "update", """{"title":"New"}"""),
            ),
            deviceId = "device-123",
        )
        val jsonStr = json.encodeToString(SyncPushRequest.serializer(), request)
        val decoded = json.decodeFromString(SyncPushRequest.serializer(), jsonStr)
        assertEquals(2, decoded.events.size)
        assertEquals("device-123", decoded.deviceId)
    }

    @Test
    fun testSyncPullRequestSerialization() {
        val request = SyncPullRequest(lastVersion = 42, deviceId = "dev-1")
        val jsonStr = json.encodeToString(SyncPullRequest.serializer(), request)
        val decoded = json.decodeFromString(SyncPullRequest.serializer(), jsonStr)
        assertEquals(42, decoded.lastVersion)
    }

    // === AuthModels ===

    @Test
    fun testLoginRequestSerialization() {
        val request = LoginRequest(email = "test@example.com", password = "secret")
        val jsonStr = json.encodeToString(LoginRequest.serializer(), request)
        val decoded = json.decodeFromString(LoginRequest.serializer(), jsonStr)
        assertEquals("test@example.com", decoded.email)
    }

    @Test
    fun testRegisterRequestSerialization() {
        val request = RegisterRequest(
            name = "John",
            email = "john@example.com",
            password = "password123",
            passwordConfirmation = "password123",
        )
        val jsonStr = json.encodeToString(RegisterRequest.serializer(), request)
        val decoded = json.decodeFromString(RegisterRequest.serializer(), jsonStr)
        assertEquals("John", decoded.name)
    }

    @Test
    fun testSocialAuthRequestSerialization() {
        val request = SocialAuthRequest(provider = "google", token = "oauth-token-123")
        val jsonStr = json.encodeToString(SocialAuthRequest.serializer(), request)
        val decoded = json.decodeFromString(SocialAuthRequest.serializer(), jsonStr)
        assertEquals("google", decoded.provider)
        assertEquals("oauth-token-123", decoded.token)
    }

    @Test
    fun testAuthTokenSerialization() {
        val token = AuthToken(
            token = "abc123",
            user = User(id = 1, name = "Test", email = "test@example.com"),
        )
        val jsonStr = json.encodeToString(AuthToken.serializer(), token)
        val decoded = json.decodeFromString(AuthToken.serializer(), jsonStr)
        assertEquals("abc123", decoded.token)
        assertEquals("Test", decoded.user.name)
    }

    @Test
    fun testUserSerialization() {
        val user = User(
            id = 1,
            name = "John Doe",
            email = "john@example.com",
        )
        val jsonStr = json.encodeToString(User.serializer(), user)
        val decoded = json.decodeFromString(User.serializer(), jsonStr)
        assertEquals(1, decoded.id)
        assertEquals("John Doe", decoded.name)
    }
}
