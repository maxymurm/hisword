package org.androidbible.domain.repository

import org.androidbible.domain.model.SearchResult
import org.androidbible.domain.model.Verse

/**
 * Engine-agnostic interface for reading Bible text.
 * Implementations: SwordBibleReader (OSIS/SWORD modules), BintexRepositoryImpl (YES2/YES1 binary format).
 *
 * bookId is 0-based: Genesis=0, Exodus=1, ... Revelation=65
 */
interface BibleReader {
    suspend fun readChapter(moduleKey: String, bookId: Int, chapter: Int): List<Verse>
    suspend fun readVerse(moduleKey: String, ari: Int): Verse?
    suspend fun hasDataFiles(moduleKey: String): Boolean
    suspend fun search(moduleKey: String, query: String, maxResults: Int = 200): List<SearchResult>
    suspend fun getModuleInfo(moduleKey: String): ModuleInfo?
}

data class ModuleInfo(
    val key: String,
    val name: String,
    val description: String,
    val language: String,
    val engine: String,
    val hasOT: Boolean,
    val hasNT: Boolean,
)

/**
 * Dispatches to the correct engine reader based on module engine type.
 */
class BibleReaderFactory(
    private val swordReader: BibleReader,
    private val bintexReader: BibleReader,
) {
    fun readerFor(engine: String): BibleReader = when (engine) {
        "bintex" -> bintexReader
        else -> swordReader
    }

    fun allReaders(): Map<String, BibleReader> = mapOf(
        "sword" to swordReader,
        "bintex" to bintexReader,
    )
}

/**
 * No-op placeholder reader. Replaced by real implementations in later phases.
 */
object NoOpBibleReader : BibleReader {
    override suspend fun readChapter(moduleKey: String, bookId: Int, chapter: Int): List<Verse> = emptyList()
    override suspend fun readVerse(moduleKey: String, ari: Int): Verse? = null
    override suspend fun hasDataFiles(moduleKey: String): Boolean = false
    override suspend fun search(moduleKey: String, query: String, maxResults: Int): List<SearchResult> = emptyList()
    override suspend fun getModuleInfo(moduleKey: String): ModuleInfo? = null
}
