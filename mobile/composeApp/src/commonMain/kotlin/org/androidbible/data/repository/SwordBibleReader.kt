package org.androidbible.data.repository

import org.androidbible.data.sword.SwordManager
import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.data.sword.SwordVersification
import org.androidbible.domain.model.SearchResult
import org.androidbible.domain.model.Verse
import org.androidbible.domain.repository.BibleReader
import org.androidbible.domain.repository.ModuleInfo
import org.androidbible.util.Ari

/**
 * BibleReader implementation backed by the SWORD engine.
 *
 * Translates between integer bookId (1-based: Gen=1, Rev=66)
 * and OSIS IDs used by the SWORD API.
 */
class SwordBibleReader(
    private val swordManager: SwordManager,
) : BibleReader {

    override suspend fun readChapter(moduleKey: String, bookId: Int, chapter: Int): List<Verse> {
        val bookDef = bookDefForId(bookId) ?: return emptyList()
        val verses = swordManager.readChapter(moduleKey, bookDef.osisId, chapter)
        return verses.map { (verseNum, text) ->
            val ari = Ari.encode(bookId, chapter, verseNum)
            Verse(
                bibleVersionId = 0,
                ari = ari,
                bookId = bookId,
                chapter = chapter,
                verse = verseNum,
                text = text,
                textWithoutFormatting = text,
            )
        }
    }

    override suspend fun readVerse(moduleKey: String, ari: Int): Verse? {
        val bookId = Ari.decodeBook(ari)
        val chapter = Ari.decodeChapter(ari)
        val verse = Ari.decodeVerse(ari)
        val bookDef = bookDefForId(bookId) ?: return null

        val text = swordManager.readVerse(moduleKey, bookDef.osisId, chapter, verse)
        if (text.isBlank()) return null

        return Verse(
            bibleVersionId = 0,
            ari = ari,
            bookId = bookId,
            chapter = chapter,
            verse = verse,
            text = text,
            textWithoutFormatting = text,
        )
    }

    override suspend fun hasDataFiles(moduleKey: String): Boolean {
        return swordManager.getModules().containsKey(moduleKey.lowercase())
    }

    override suspend fun search(moduleKey: String, query: String, maxResults: Int): List<SearchResult> {
        val results = mutableListOf<SearchResult>()
        val lowerQuery = query.lowercase()

        for ((index, bookDef) in SwordVersification.allBooks.withIndex()) {
            val bookId = index + 1
            for (chapter in 1..bookDef.chapterCount) {
                val verses = swordManager.readChapter(moduleKey, bookDef.osisId, chapter)
                for ((verseNum, text) in verses) {
                    if (text.lowercase().contains(lowerQuery)) {
                        val ari = Ari.encode(bookId, chapter, verseNum)
                        results.add(
                            SearchResult(
                                verse = Verse(
                                    bibleVersionId = 0,
                                    ari = ari,
                                    bookId = bookId,
                                    chapter = chapter,
                                    verse = verseNum,
                                    text = text,
                                    textWithoutFormatting = text,
                                ),
                                bookName = bookDef.name,
                            )
                        )
                        if (results.size >= maxResults) return results
                    }
                }
            }
        }
        return results
    }

    override suspend fun getModuleInfo(moduleKey: String): ModuleInfo? {
        val config = swordManager.getModules()[moduleKey.lowercase()] ?: return null
        return ModuleInfo(
            key = moduleKey,
            name = config.description.ifBlank { config.moduleName },
            description = config.rawEntries["about"] ?: "",
            language = config.language,
            engine = "sword",
            hasOT = config.modDrv == SwordModuleConfig.ModDrv.Z_TEXT,
            hasNT = config.modDrv == SwordModuleConfig.ModDrv.Z_TEXT,
        )
    }

    companion object {
        /**
         * Maps 1-based bookId to SwordVersification BookDef.
         * bookId 1 = Genesis (index 0), bookId 66 = Revelation (index 65).
         */
        fun bookDefForId(bookId: Int): SwordVersification.BookDef? {
            val index = bookId - 1
            if (index < 0 || index >= SwordVersification.allBooks.size) return null
            return SwordVersification.allBooks[index]
        }

        /**
         * Maps OSIS ID to 1-based bookId.
         */
        fun bookIdForOsis(osisId: String): Int? {
            val (index, _) = SwordVersification.findBookByOsisId(osisId) ?: return null
            return index + 1
        }
    }
}
