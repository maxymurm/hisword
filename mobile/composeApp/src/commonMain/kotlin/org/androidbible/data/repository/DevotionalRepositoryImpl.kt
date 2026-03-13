package org.androidbible.data.repository

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flowOf
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.model.Devotional
import org.androidbible.domain.repository.DevotionalRepository

class DevotionalRepositoryImpl(
    private val api: ApiService,
) : DevotionalRepository {

    // In-memory cache for now; could add SQLDelight caching later
    private var cache: List<Devotional> = emptyList()

    override fun getDevotionals(): Flow<List<Devotional>> = flowOf(cache)

    override suspend fun getDevotional(id: Long): Devotional? {
        return cache.find { it.id == id } ?: try {
            api.getDevotional(id)
        } catch (_: Exception) { null }
    }

    override suspend fun getDevotionalByDate(date: String): Devotional? {
        return cache.find { it.publishDate == date }
    }

    override suspend fun syncDevotionals() {
        cache = api.getDevotionals()
    }
}
