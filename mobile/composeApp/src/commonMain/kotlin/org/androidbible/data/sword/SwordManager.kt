package org.androidbible.data.sword

import org.androidbible.data.sword.gbf.GbfTextFilter
import org.androidbible.data.sword.io.CipherUtils
import org.androidbible.data.sword.io.FileSystem
import org.androidbible.data.sword.osis.OsisTextFilter
import org.androidbible.data.sword.reader.RawComReader
import org.androidbible.data.sword.reader.RawGenBookReader
import org.androidbible.data.sword.reader.RawLD4Reader
import org.androidbible.data.sword.reader.ZLDReader
import org.androidbible.data.sword.reader.ZTextReader
import org.androidbible.data.sword.tei.TeiTextFilter
import org.androidbible.data.sword.thml.ThmlTextFilter

/**
 * High-level manager for SWORD Bible modules.
 *
 * This is the main entry point for offline Bible reading.
 * It discovers installed modules, creates appropriate readers,
 * and provides a clean API for the rest of the app.
 *
 * Module directory structure (after extraction from ZIP):
 *   <basePath>/
 *     mods.d/
 *       kjv.conf
 *       mhcc.conf
 *       ...
 *     modules/
 *       texts/ztext/kjv/
 *         ot.bzs, ot.bzv, ot.bzz
 *         nt.bzs, nt.bzv, nt.bzz
 *       comments/rawcom/mhcc/
 *         ot, ot.vss, nt, nt.vss
 *       lexdict/rawld4/strongsrealgreek/
 *         strongsrealgreek.idx, strongsrealgreek.dat
 *       ...
 */
class SwordManager(
    private val basePath: String,
) {
    private val configs = mutableMapOf<String, SwordModuleConfig>()
    private val zTextReaders = mutableMapOf<String, ZTextReader>()
    private val rawComReaders = mutableMapOf<String, RawComReader>()
    private val rawLD4Readers = mutableMapOf<String, RawLD4Reader>()
    private val rawGenBookReaders = mutableMapOf<String, RawGenBookReader>()
    private val zLDReaders = mutableMapOf<String, ZLDReader>()

    fun loadModules() {
        configs.clear()
        clearAllCaches()

        val modsDir = "$basePath/mods.d"
        if (!FileSystem.exists(modsDir)) return

        val confFiles = FileSystem.listDir(modsDir).filter { it.endsWith(".conf") }

        for (confFile in confFiles) {
            try {
                val confText = FileSystem.readText("$modsDir/$confFile")
                val config = SwordModuleConfig.parse(confText)
                val key = confFile.removeSuffix(".conf").lowercase()
                configs[key] = config.copy(moduleName = config.moduleName.ifBlank { key })
            } catch (_: Exception) {
            }
        }
    }

    fun getModules(): Map<String, SwordModuleConfig> = configs.toMap()

    fun getModulesByType(modDrv: SwordModuleConfig.ModDrv): Map<String, SwordModuleConfig> =
        configs.filter { it.value.modDrv == modDrv }

    fun getBibleModules(): Map<String, SwordModuleConfig> =
        getModulesByType(SwordModuleConfig.ModDrv.Z_TEXT)

    fun getCommentaryModules(): Map<String, SwordModuleConfig> =
        configs.filter {
            it.value.modDrv == SwordModuleConfig.ModDrv.RAW_COM ||
            it.value.modDrv == SwordModuleConfig.ModDrv.RAW_COM4
        }

    fun getDictionaryModules(): Map<String, SwordModuleConfig> =
        configs.filter {
            it.value.modDrv == SwordModuleConfig.ModDrv.RAW_LD4 ||
            it.value.modDrv == SwordModuleConfig.ModDrv.RAW_LD ||
            it.value.modDrv == SwordModuleConfig.ModDrv.Z_LD
        }

    // ── Bible (zText) API ────────────────────────────────────

    fun readVerse(
        moduleKey: String,
        bookOsisId: String,
        chapter: Int,
        verse: Int,
        stripMarkup: Boolean = true,
    ): String {
        val reader = getZTextReader(moduleKey) ?: return ""
        val raw = reader.readVerse(bookOsisId, chapter, verse)
        return if (stripMarkup) filterMarkup(moduleKey, raw) else raw
    }

    fun readChapter(
        moduleKey: String,
        bookOsisId: String,
        chapter: Int,
        stripMarkup: Boolean = true,
    ): List<Pair<Int, String>> {
        val reader = getZTextReader(moduleKey) ?: return emptyList()
        val verses = reader.readChapter(bookOsisId, chapter)
        return if (stripMarkup) {
            verses.map { (num, text) -> num to filterMarkup(moduleKey, text) }
        } else {
            verses
        }
    }

    // ── Markup Filter Dispatch ───────────────────────────────

    fun filterMarkup(moduleKey: String, text: String): String {
        val config = configs[moduleKey.lowercase()]
        return when (config?.sourceType) {
            SwordModuleConfig.SourceType.OSIS -> OsisTextFilter.stripMarkup(text)
            SwordModuleConfig.SourceType.GBF -> GbfTextFilter.stripMarkup(text)
            SwordModuleConfig.SourceType.THML -> ThmlTextFilter.stripMarkup(text)
            SwordModuleConfig.SourceType.TEI -> TeiTextFilter.stripMarkup(text)
            SwordModuleConfig.SourceType.PLAIN, null -> text.trim()
        }
    }

    fun extractStrongsNumbers(moduleKey: String, text: String): List<String> {
        val config = configs[moduleKey.lowercase()]
        return when (config?.sourceType) {
            SwordModuleConfig.SourceType.OSIS -> OsisTextFilter.extractStrongsNumbers(text)
            SwordModuleConfig.SourceType.GBF -> GbfTextFilter.extractStrongsNumbers(text)
            SwordModuleConfig.SourceType.THML -> ThmlTextFilter.extractStrongsNumbers(text)
            else -> emptyList()
        }
    }

    fun searchStrongsOccurrences(
        moduleKey: String,
        strongsNumber: String,
    ): List<StrongsOccurrence> {
        val reader = getZTextReader(moduleKey) ?: return emptyList()
        val results = mutableListOf<StrongsOccurrence>()
        val normalizedTarget = strongsNumber.uppercase()

        for (book in SwordVersification.allBooks) {
            for (chapter in 1..book.chapterCount) {
                val verses = reader.readChapter(book.osisId, chapter)
                for ((verseNum, rawText) in verses) {
                    if (rawText.isBlank()) continue
                    val strongs = extractStrongsNumbers(moduleKey, rawText)
                    if (strongs.any { it.uppercase() == normalizedTarget }) {
                        results.add(
                            StrongsOccurrence(
                                bookOsisId = book.osisId,
                                bookName = book.name,
                                chapter = chapter,
                                verse = verseNum,
                                text = filterMarkup(moduleKey, rawText),
                            ),
                        )
                    }
                }
            }
        }
        return results
    }

    data class StrongsOccurrence(
        val bookOsisId: String,
        val bookName: String,
        val chapter: Int,
        val verse: Int,
        val text: String,
    )

    // ── Commentary (RawCom) API ──────────────────────────────

    fun readCommentary(
        moduleKey: String,
        bookOsisId: String,
        chapter: Int,
        verse: Int,
    ): String {
        val reader = getRawComReader(moduleKey) ?: return ""
        return reader.readVerse(bookOsisId, chapter, verse)
    }

    fun readCommentaryChapter(
        moduleKey: String,
        bookOsisId: String,
        chapter: Int,
    ): List<Pair<Int, String>> {
        val reader = getRawComReader(moduleKey) ?: return emptyList()
        return reader.readChapter(bookOsisId, chapter)
    }

    // ── Dictionary (RawLD4 / zLD) API ────────────────────────

    fun lookupDictionary(moduleKey: String, key: String): String {
        val config = configs[moduleKey.lowercase()] ?: return ""
        return when (config.modDrv) {
            SwordModuleConfig.ModDrv.RAW_LD4,
            SwordModuleConfig.ModDrv.RAW_LD -> {
                val reader = getRawLD4Reader(moduleKey) ?: return ""
                reader.lookup(key)
            }
            SwordModuleConfig.ModDrv.Z_LD -> {
                val reader = getZLDReader(moduleKey) ?: return ""
                reader.lookup(key)
            }
            SwordModuleConfig.ModDrv.RAW_GEN_BOOK -> {
                val reader = getRawGenBookReader(moduleKey) ?: return ""
                reader.lookup(key)
            }
            else -> ""
        }
    }

    fun getGenBookModules(): Map<String, SwordModuleConfig> =
        getModulesByType(SwordModuleConfig.ModDrv.RAW_GEN_BOOK)

    // ── Cipher key management ────────────────────────────────

    fun isModuleLocked(moduleKey: String): Boolean {
        val config = configs[moduleKey.lowercase()] ?: return false
        return CipherUtils.isLocked(config)
    }

    fun moduleRequiresCipherKey(moduleKey: String): Boolean {
        val config = configs[moduleKey.lowercase()] ?: return false
        return CipherUtils.requiresCipherKey(config)
    }

    fun setCipherKey(moduleKey: String, cipherKey: String) {
        val key = moduleKey.lowercase()
        val config = configs[key] ?: return
        val updatedEntries = config.rawEntries.toMutableMap()
        updatedEntries["cipherkey"] = cipherKey
        configs[key] = config.copy(rawEntries = updatedEntries)
        zTextReaders.remove(key)?.clearCache()
        rawComReaders.remove(key)
        rawLD4Readers.remove(key)?.clearCache()
        rawGenBookReaders.remove(key)?.clearCache()
        zLDReaders.remove(key)
    }

    // ── Book/Chapter/Verse metadata ──────────────────────────

    fun getBooks(): List<SwordVersification.BookDef> = SwordVersification.allBooks

    fun getOTBooks(): List<SwordVersification.BookDef> = SwordVersification.otBooks

    fun getNTBooks(): List<SwordVersification.BookDef> = SwordVersification.ntBooks

    fun getVerseCount(bookOsisId: String, chapter: Int): Int {
        val (bookIndex, _) = SwordVersification.findBookByOsisId(bookOsisId) ?: return 0
        return SwordVersification.getVerseCount(bookIndex, chapter)
    }

    // ── Reader factory methods ───────────────────────────────

    private fun getZTextReader(moduleKey: String): ZTextReader? {
        val key = moduleKey.lowercase()
        zTextReaders[key]?.let { return it }
        val config = configs[key] ?: return null
        if (config.modDrv != SwordModuleConfig.ModDrv.Z_TEXT) return null
        val reader = ZTextReader(config, basePath)
        zTextReaders[key] = reader
        return reader
    }

    private fun getRawComReader(moduleKey: String): RawComReader? {
        val key = moduleKey.lowercase()
        rawComReaders[key]?.let { return it }
        val config = configs[key] ?: return null
        if (config.modDrv != SwordModuleConfig.ModDrv.RAW_COM &&
            config.modDrv != SwordModuleConfig.ModDrv.RAW_COM4
        ) return null
        val reader = RawComReader(config, basePath)
        rawComReaders[key] = reader
        return reader
    }

    private fun getRawLD4Reader(moduleKey: String): RawLD4Reader? {
        val key = moduleKey.lowercase()
        rawLD4Readers[key]?.let { return it }
        val config = configs[key] ?: return null
        val reader = RawLD4Reader(config, basePath)
        rawLD4Readers[key] = reader
        return reader
    }

    private fun getZLDReader(moduleKey: String): ZLDReader? {
        val key = moduleKey.lowercase()
        zLDReaders[key]?.let { return it }
        val config = configs[key] ?: return null
        val reader = ZLDReader(config, basePath)
        zLDReaders[key] = reader
        return reader
    }

    private fun getRawGenBookReader(moduleKey: String): RawGenBookReader? {
        val key = moduleKey.lowercase()
        rawGenBookReaders[key]?.let { return it }
        val config = configs[key] ?: return null
        if (config.modDrv != SwordModuleConfig.ModDrv.RAW_GEN_BOOK) return null
        val reader = RawGenBookReader(config, basePath)
        rawGenBookReaders[key] = reader
        return reader
    }

    private fun clearAllCaches() {
        zTextReaders.values.forEach { it.clearCache() }
        rawLD4Readers.values.forEach { it.clearCache() }
        rawGenBookReaders.values.forEach { it.clearCache() }
        zTextReaders.clear()
        rawComReaders.clear()
        rawLD4Readers.clear()
        rawGenBookReaders.clear()
        zLDReaders.clear()
    }
}
