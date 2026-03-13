package org.androidbible.domain.repository

import kotlinx.coroutines.flow.Flow
import org.androidbible.domain.model.*

interface BibleRepository {
    fun getVersions(): Flow<List<BibleVersion>>
    suspend fun getVersion(id: Long): BibleVersion?
    fun getBooks(versionId: Long): Flow<List<Book>>
    suspend fun getBook(versionId: Long, bookId: Int): Book?
    fun getChapter(versionId: Long, bookId: Int, chapter: Int): Flow<Chapter>
    suspend fun getVerse(versionId: Long, ari: Int): Verse?
    suspend fun searchVerses(versionId: Long, query: String): List<SearchResult>
    suspend fun syncVersions()
    suspend fun syncBooks(versionId: Long)
    suspend fun syncChapter(versionId: Long, bookId: Int, chapter: Int)
}

interface MarkerRepository {
    fun getMarkers(kind: Int? = null): Flow<List<Marker>>
    fun getMarkersByAri(ari: Int): Flow<List<Marker>>
    fun getMarkersByAriRange(startAri: Int, endAri: Int): Flow<List<Marker>>
    fun getMarkersForLabel(labelId: Long): Flow<List<Marker>>
    fun searchMarkers(query: String): Flow<List<Marker>>
    suspend fun getMarker(id: Long): Marker?
    suspend fun createMarker(marker: Marker): Marker
    suspend fun updateMarker(marker: Marker): Marker
    suspend fun deleteMarker(id: Long)
    suspend fun deleteMarkers(ids: List<Long>)
    suspend fun getAllMarkersSnapshot(): List<Marker>
    fun getLabels(): Flow<List<Label>>
    suspend fun createLabel(label: Label): Label
    suspend fun updateLabel(label: Label): Label
    suspend fun deleteLabel(id: Long)
    suspend fun attachLabel(markerId: Long, labelId: Long)
    suspend fun detachLabel(markerId: Long, labelId: Long)
    suspend fun attachLabelBulk(markerIds: List<Long>, labelId: Long)
    fun getLabelsForMarker(markerId: Long): Flow<List<Label>>
}

interface ProgressRepository {
    fun getProgressMarks(): Flow<List<ProgressMark>>
    suspend fun getProgressMark(preset: Int): ProgressMark?
    suspend fun createOrUpdate(progressMark: ProgressMark): ProgressMark
    suspend fun deleteProgressMark(id: Long)
    fun getHistory(progressMarkId: Long): Flow<List<ProgressMarkHistory>>
}

interface AuthRepository {
    suspend fun login(request: LoginRequest): AuthToken
    suspend fun register(request: RegisterRequest): AuthToken
    suspend fun socialAuth(request: SocialAuthRequest): AuthToken
    suspend fun forgotPassword(email: String)
    suspend fun changePassword(request: ChangePasswordRequest)
    suspend fun updateProfile(request: UpdateProfileRequest): User
    suspend fun deleteAccount()
    suspend fun logout()
    suspend fun getProfile(): User
    fun isLoggedIn(): Flow<Boolean>
    fun getCurrentUser(): Flow<User?>
    fun getToken(): String?
}

interface ReadingPlanRepository {
    fun getReadingPlans(): Flow<List<ReadingPlan>>
    suspend fun getReadingPlan(id: Long): ReadingPlan?
    fun getDays(planId: Long): Flow<List<ReadingPlanDay>>
    fun getProgress(planId: Long): Flow<List<ReadingPlanProgress>>
    suspend fun markDayComplete(planId: Long, dayId: Long)
    suspend fun syncPlans()
}

interface DevotionalRepository {
    fun getDevotionals(): Flow<List<Devotional>>
    suspend fun getDevotional(id: Long): Devotional?
    suspend fun getDevotionalByDate(date: String): Devotional?
    suspend fun syncDevotionals()
}

interface SongRepository {
    fun getSongBooks(): Flow<List<SongBook>>
    fun getSongs(bookId: Long): Flow<List<Song>>
    suspend fun getSong(id: Long): Song?
    suspend fun searchSongs(query: String): List<Song>
    suspend fun syncSongBooks()
}

interface UserPreferenceRepository {
    suspend fun get(key: String): String?
    suspend fun set(key: String, value: String)
    suspend fun remove(key: String)
    fun observe(key: String): Flow<String?>
    suspend fun syncPreferences()
}
