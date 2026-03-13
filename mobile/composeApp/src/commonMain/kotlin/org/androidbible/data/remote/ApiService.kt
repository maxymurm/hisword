package org.androidbible.data.remote

import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.request.*
import io.ktor.http.*
import kotlinx.serialization.Serializable
import org.androidbible.domain.model.*

/**
 * API service for all backend REST endpoints.
 */
class ApiService(private val client: HttpClient) {

    // ========== Auth ==========

    suspend fun login(request: LoginRequest): AuthResponse {
        return client.post("api/v1/auth/login") {
            setBody(request)
        }.body()
    }

    suspend fun register(request: RegisterRequest): AuthResponse {
        return client.post("api/v1/auth/register") {
            setBody(request)
        }.body()
    }

    suspend fun socialAuth(request: SocialAuthRequest): AuthResponse {
        return client.post("api/v1/auth/social") {
            setBody(request)
        }.body()
    }

    suspend fun forgotPassword(request: ForgotPasswordRequest): MessageResponse {
        return client.post("api/v1/auth/forgot-password") {
            setBody(request)
        }.body()
    }

    suspend fun resetPassword(request: ResetPasswordRequest): MessageResponse {
        return client.post("api/v1/auth/reset-password") {
            setBody(request)
        }.body()
    }

    suspend fun changePassword(request: ChangePasswordRequest): MessageResponse {
        return client.post("api/v1/auth/change-password") {
            setBody(request)
        }.body()
    }

    suspend fun updateProfile(request: UpdateProfileRequest): User {
        return client.put("api/v1/auth/profile") {
            setBody(request)
        }.body<DataResponse<User>>().data
    }

    suspend fun deleteAccount(): MessageResponse {
        return client.delete("api/v1/auth/account").body()
    }

    suspend fun logout() {
        client.post("api/v1/auth/logout")
    }

    suspend fun getProfile(): User {
        return client.get("api/v1/auth/profile").body<UserResponse>().data
    }

    // ========== Bible Versions ==========

    suspend fun getVersions(): List<BibleVersion> {
        return client.get("api/v1/bible/versions").body<ListResponse<BibleVersion>>().data
    }

    suspend fun getVersion(id: Long): BibleVersion {
        return client.get("api/v1/bible/versions/$id").body<DataResponse<BibleVersion>>().data
    }

    // ========== Books ==========

    suspend fun getBooks(versionId: Long): List<Book> {
        return client.get("api/v1/bible/versions/$versionId/books").body<ListResponse<Book>>().data
    }

    // ========== Chapters & Verses ==========

    suspend fun getChapter(versionId: Long, bookId: Int, chapter: Int): ChapterResponse {
        return client.get("api/v1/bible/versions/$versionId/books/$bookId/chapters/$chapter").body()
    }

    suspend fun getVerse(versionId: Long, bookId: Int, chapter: Int, verse: Int): Verse {
        return client.get("api/v1/bible/versions/$versionId/books/$bookId/chapters/$chapter/verses/$verse")
            .body<DataResponse<Verse>>().data
    }

    suspend fun search(versionId: Long, query: String): List<Verse> {
        return client.get("api/v1/bible/search") {
            parameter("version_id", versionId)
            parameter("q", query)
        }.body<ListResponse<Verse>>().data
    }

    // ========== Cross References & Footnotes ==========

    suspend fun getCrossReferences(versionId: Long, ari: Int): List<CrossReference> {
        return client.get("api/v1/bible/cross-references") {
            parameter("version_id", versionId)
            parameter("ari", ari)
        }.body<ListResponse<CrossReference>>().data
    }

    suspend fun getFootnotes(versionId: Long, ari: Int): List<Footnote> {
        return client.get("api/v1/bible/footnotes") {
            parameter("version_id", versionId)
            parameter("ari", ari)
        }.body<ListResponse<Footnote>>().data
    }

    // ========== Compare Versions ==========

    suspend fun compareVerses(ari: Int, versionIds: List<Long>): CompareResponse {
        return client.get("api/v1/bible/compare") {
            parameter("ari", ari)
            versionIds.forEach { parameter("version_ids[]", it) }
        }.body()
    }

    // ========== Reading History ==========

    suspend fun getReadingHistory(): List<ReadingHistoryEntry> {
        return client.get("api/v1/bible/reading-history").body<ListResponse<ReadingHistoryEntry>>().data
    }

    // ========== Verse of the Day ==========

    suspend fun getVerseOfTheDay(): VerseOfTheDay {
        return client.get("api/v1/verse-of-the-day").body<DataResponse<VerseOfTheDay>>().data
    }

    // ========== Sharing ==========

    suspend fun getShareUrl(ari: Int, versionId: Long): ShareData {
        return client.get("api/v1/share/verse") {
            parameter("ari", ari)
            parameter("version_id", versionId)
        }.body<DataResponse<ShareData>>().data
    }

    // ========== Markers ==========

    suspend fun getMarkers(kind: Int? = null): List<Marker> {
        return client.get("api/v1/markers") {
            kind?.let { parameter("kind", it) }
        }.body<ListResponse<Marker>>().data
    }

    suspend fun createMarker(marker: Marker): Marker {
        return client.post("api/v1/markers") {
            setBody(marker)
        }.body<DataResponse<Marker>>().data
    }

    suspend fun updateMarker(id: Long, marker: Marker): Marker {
        return client.put("api/v1/markers/$id") {
            setBody(marker)
        }.body<DataResponse<Marker>>().data
    }

    suspend fun deleteMarker(id: Long) {
        client.delete("api/v1/markers/$id")
    }

    suspend fun batchCreateMarkers(markers: List<Marker>): List<Marker> {
        return client.post("api/v1/markers/batch") {
            setBody(mapOf("markers" to markers))
        }.body<ListResponse<Marker>>().data
    }

    suspend fun exportMarkers(format: String = "json"): String {
        return client.get("api/v1/markers/export") {
            parameter("format", format)
        }.body()
    }

    // ========== Labels ==========

    suspend fun getLabels(): List<Label> {
        return client.get("api/v1/labels").body<ListResponse<Label>>().data
    }

    suspend fun createLabel(label: Label): Label {
        return client.post("api/v1/labels") {
            setBody(label)
        }.body<DataResponse<Label>>().data
    }

    suspend fun updateLabel(id: Long, label: Label): Label {
        return client.put("api/v1/labels/$id") {
            setBody(label)
        }.body<DataResponse<Label>>().data
    }

    suspend fun deleteLabel(id: Long) {
        client.delete("api/v1/labels/$id")
    }

    suspend fun attachLabel(markerId: Long, labelId: Long) {
        client.post("api/v1/markers/$markerId/labels/$labelId")
    }

    suspend fun detachLabel(markerId: Long, labelId: Long) {
        client.delete("api/v1/markers/$markerId/labels/$labelId")
    }

    // ========== Progress Marks ==========

    suspend fun getProgressMarks(): List<ProgressMark> {
        return client.get("api/v1/progress-marks").body<ListResponse<ProgressMark>>().data
    }

    suspend fun createOrUpdateProgressMark(progressMark: ProgressMark): ProgressMark {
        return client.post("api/v1/progress-marks") {
            setBody(progressMark)
        }.body<DataResponse<ProgressMark>>().data
    }

    suspend fun deleteProgressMark(id: Long) {
        client.delete("api/v1/progress-marks/$id")
    }

    // ========== Reading Plans ==========

    suspend fun getReadingPlans(): List<ReadingPlan> {
        return client.get("api/v1/reading-plans").body<ListResponse<ReadingPlan>>().data
    }

    suspend fun getReadingPlanDays(planId: Long): List<ReadingPlanDay> {
        return client.get("api/v1/reading-plans/$planId/days").body<ListResponse<ReadingPlanDay>>().data
    }

    suspend fun getReadingPlanProgress(planId: Long): List<ReadingPlanProgress> {
        return client.get("api/v1/reading-plans/$planId/progress").body<ListResponse<ReadingPlanProgress>>().data
    }

    suspend fun markDayComplete(planId: Long, dayId: Long) {
        client.post("api/v1/reading-plans/$planId/progress") {
            setBody(mapOf("reading_plan_day_id" to dayId))
        }
    }

    // ========== Devotionals ==========

    suspend fun getDevotionals(): List<Devotional> {
        return client.get("api/v1/devotionals").body<ListResponse<Devotional>>().data
    }

    suspend fun getDevotional(id: Long): Devotional {
        return client.get("api/v1/devotionals/$id").body<DataResponse<Devotional>>().data
    }

    // ========== Songs ==========

    suspend fun getSongBooks(): List<SongBook> {
        return client.get("api/v1/song-books").body<ListResponse<SongBook>>().data
    }

    suspend fun getSongs(bookId: Long): List<Song> {
        return client.get("api/v1/song-books/$bookId/songs").body<ListResponse<Song>>().data
    }

    suspend fun searchSongs(query: String): List<Song> {
        return client.get("api/v1/songs/search") {
            parameter("q", query)
        }.body<ListResponse<Song>>().data
    }

    // ========== Sync ==========

    suspend fun syncPull(request: SyncPullRequest): SyncPullResponse {
        return client.post("api/v1/sync/pull") {
            setBody(request)
        }.body()
    }

    suspend fun syncPush(request: SyncPushRequest): SyncPushResponse {
        return client.post("api/v1/sync/push") {
            setBody(request)
        }.body()
    }

    suspend fun syncStatus(deviceId: String): SyncStatus {
        return client.get("api/v1/sync/status") {
            parameter("device_id", deviceId)
        }.body()
    }

    suspend fun registerDevice(token: String, platform: String): MessageResponse {
        return client.post("api/v1/push/register") {
            setBody(mapOf("token" to token, "platform" to platform))
        }.body()
    }

    // ========== User Preferences ==========

    suspend fun getPreferences(): List<UserPreference> {
        return client.get("api/v1/preferences").body<ListResponse<UserPreference>>().data
    }

    suspend fun setPreference(key: String, value: String): UserPreference {
        return client.post("api/v1/preferences") {
            setBody(mapOf("key" to key, "value" to value))
        }.body<DataResponse<UserPreference>>().data
    }

    suspend fun deletePreference(key: String) {
        client.delete("api/v1/preferences/$key")
    }
}

// ========== Response Wrappers ==========

@Serializable
data class AuthResponse(
    val token: String,
    val user: User,
)

@Serializable
data class UserResponse(
    val data: User,
)

@Serializable
data class DataResponse<T>(
    val data: T,
)

@Serializable
data class ListResponse<T>(
    val data: List<T>,
)

@Serializable
data class ChapterResponse(
    val verses: List<Verse>,
    val pericopes: List<Pericope> = emptyList(),
)

@Serializable
data class CompareResponse(
    val ari: Int,
    val versions: List<CompareVersionData>,
)

@Serializable
data class CompareVersionData(
    val versionId: Long,
    val versionName: String,
    val text: String,
)

@Serializable
data class VerseOfTheDay(
    val ari: Int,
    val text: String,
    val reference: String,
    val versionName: String,
)

@Serializable
data class ShareData(
    val url: String,
    val text: String,
)

@Serializable
data class ReadingHistoryEntry(
    val id: Long,
    val ari: Int,
    val versionId: Long,
    val readAt: String,
)
