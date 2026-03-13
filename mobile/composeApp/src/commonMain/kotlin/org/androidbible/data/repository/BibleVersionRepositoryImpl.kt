package org.androidbible.data.repository

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import org.androidbible.data.bintex.ModuleFileLoader
import org.androidbible.data.sword.SwordManager
import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.domain.repository.BibleVersionRepository
import org.androidbible.domain.repository.ModuleInfo

/**
 * Scans both SWORD and Bintex module directories,
 * merges them into a unified list of installed modules.
 */
class BibleVersionRepositoryImpl(
    private val swordManager: SwordManager,
    private val moduleFileLoader: ModuleFileLoader,
) : BibleVersionRepository {

    private val _modules = MutableStateFlow<List<ModuleInfo>>(emptyList())

    override fun getInstalledModules(): Flow<List<ModuleInfo>> = _modules.asStateFlow()

    override suspend fun refreshModules() {
        val all = mutableListOf<ModuleInfo>()

        // SWORD modules
        swordManager.loadModules()
        for ((key, config) in swordManager.getModules()) {
            if (config.modDrv != SwordModuleConfig.ModDrv.Z_TEXT) continue
            all.add(
                ModuleInfo(
                    key = key,
                    name = config.description.ifBlank { config.moduleName },
                    description = config.rawEntries["about"] ?: "",
                    language = config.language,
                    engine = "sword",
                    hasOT = true,
                    hasNT = true,
                )
            )
        }

        // Bintex modules
        val bintexKeys = moduleFileLoader.listAvailableModules()
        for (key in bintexKeys) {
            val data = moduleFileLoader.loadModuleData(key) ?: continue
            val reader = BintexRepositoryImpl.detectAndCreate(data) ?: continue
            val info = when (reader) {
                is org.androidbible.data.bintex.Yes2Reader -> {
                    val vi = reader.getVersionInfo()
                    val books = reader.getBooksInfo()
                    ModuleInfo(
                        key = key,
                        name = vi.longName ?: vi.shortName ?: key,
                        description = vi.description ?: "",
                        language = vi.locale ?: "en",
                        engine = "bintex",
                        hasOT = books.any { it.bookId in 1..39 },
                        hasNT = books.any { it.bookId in 40..66 },
                    )
                }
                is org.androidbible.data.bintex.Yes1Reader -> {
                    val booksMap = reader.getBooksInfo()
                    ModuleInfo(
                        key = key,
                        name = key,
                        description = "",
                        language = "en",
                        engine = "bintex",
                        hasOT = booksMap.keys.any { it in 1..39 },
                        hasNT = booksMap.keys.any { it in 40..66 },
                    )
                }
                else -> continue
            }
            all.add(info)
        }

        _modules.value = all.sortedBy { it.name }
    }

    override suspend fun getModule(key: String): ModuleInfo? {
        return _modules.value.find { it.key.equals(key, ignoreCase = true) }
    }
}
