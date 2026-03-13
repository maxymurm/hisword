package org.androidbible.domain.repository

import kotlinx.coroutines.flow.Flow

/**
 * Repository that scans both SWORD and Bintex engine module directories
 * and provides a unified list of installed Bible modules.
 */
interface BibleVersionRepository {
    fun getInstalledModules(): Flow<List<ModuleInfo>>
    suspend fun refreshModules()
    suspend fun getModule(key: String): ModuleInfo?
}
