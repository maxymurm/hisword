package org.androidbible.domain.repository

import org.androidbible.domain.model.Verse

/**
 * Engine-agnostic interface for reading Bible text.
 * Implementations: SwordReader (OSIS/SWORD modules), BintexReader (YES2/YES1 binary format).
 */
interface BibleReader {
    suspend fun readChapter(moduleKey: String, bookId: Int, chapter: Int): List<Verse>
    suspend fun readVerse(moduleKey: String, ari: Int): Verse?
    suspend fun hasDataFiles(moduleKey: String): Boolean
}

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
}

/**
 * No-op placeholder reader. Replaced by real implementations in later phases.
 */
object NoOpBibleReader : BibleReader {
    override suspend fun readChapter(moduleKey: String, bookId: Int, chapter: Int): List<Verse> = emptyList()
    override suspend fun readVerse(moduleKey: String, ari: Int): Verse? = null
    override suspend fun hasDataFiles(moduleKey: String): Boolean = false
}
