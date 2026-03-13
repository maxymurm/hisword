package org.androidbible.ui.screens.bible

import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import com.russhwolf.settings.Settings
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.androidbible.domain.model.Marker
import org.androidbible.domain.model.Verse
import org.androidbible.domain.repository.BibleReader
import org.androidbible.domain.repository.BibleReaderFactory
import org.androidbible.domain.repository.BibleVersionRepository
import org.androidbible.domain.repository.MarkerRepository
import org.androidbible.domain.repository.ModuleInfo
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

/**
 * UI item in the verse list — either a pericope header or a verse.
 */
sealed class ReaderItem {
    data class PericopeItem(val title: String) : ReaderItem()
    data class VerseItemData(
        val verse: Verse,
        val isSelected: Boolean = false,
        val hasBookmark: Boolean = false,
        val hasNote: Boolean = false,
        val highlightColor: Int? = null,
    ) : ReaderItem()
}

data class ReaderState(
    val moduleKey: String = "",
    val moduleEngine: String = "sword",
    val moduleName: String = "",
    val bookId: Int = 1,
    val bookName: String = "Genesis",
    val chapter: Int = 1,
    val totalChapters: Int = 50,
    val items: List<ReaderItem> = emptyList(),
    val selectedAris: Set<Int> = emptySet(),
    val isLoading: Boolean = false,
    val showVerseActions: Boolean = false,
    val modules: List<ModuleInfo> = emptyList(),
    // Text appearance
    val fontSize: Int = 16,
    val fontFamily: String = "default",
    val lineSpacing: Float = 1.5f,
)

/**
 * Engine-agnostic Bible reader ViewModel.
 * Uses BibleReaderFactory to dispatch reads to SWORD or Bintex engines.
 */
class BibleReaderViewModel : ScreenModel, KoinComponent {
    private val readerFactory: BibleReaderFactory by inject()
    private val versionRepo: BibleVersionRepository by inject()
    private val markerRepo: MarkerRepository by inject()
    private val settings: Settings by inject()

    private val _state = MutableStateFlow(ReaderState())
    val state: StateFlow<ReaderState> = _state.asStateFlow()

    private var bookmarkCache = mapOf<Int, Marker>()
    private var highlightCache = mapOf<Int, Int>()
    private var noteCache = mapOf<Int, Marker>()

    init {
        loadModules()
        loadMarkerCaches()
        restoreLastPosition()
    }

    private fun loadModules() {
        screenModelScope.launch {
            versionRepo.refreshModules()
            versionRepo.getInstalledModules().collect { modules ->
                _state.value = _state.value.copy(modules = modules)
                if (modules.isNotEmpty() && _state.value.moduleKey.isEmpty()) {
                    selectModule(modules.first())
                }
            }
        }
    }

    private fun loadMarkerCaches() {
        screenModelScope.launch {
            markerRepo.getMarkers(Marker.KIND_BOOKMARK).collect { markers ->
                bookmarkCache = markers.associateBy { it.ari }
                rebuildItems()
            }
        }
        screenModelScope.launch {
            markerRepo.getMarkers(Marker.KIND_HIGHLIGHT).collect { markers ->
                highlightCache = markers.associate { it.ari to (it.color ?: 1) }
                rebuildItems()
            }
        }
        screenModelScope.launch {
            markerRepo.getMarkers(Marker.KIND_NOTE).collect { markers ->
                noteCache = markers.associateBy { it.ari }
                rebuildItems()
            }
        }
    }

    fun selectModule(module: ModuleInfo) {
        _state.value = _state.value.copy(
            moduleKey = module.key,
            moduleEngine = module.engine,
            moduleName = module.name,
        )
        loadChapter(_state.value.bookId, _state.value.chapter)
    }

    fun navigateTo(bookId: Int, chapter: Int) {
        val totalChapters = getBookChapterCount(bookId)
        val bookName = getBookName(bookId)
        _state.value = _state.value.copy(
            bookId = bookId,
            bookName = bookName,
            chapter = chapter,
            totalChapters = totalChapters,
            selectedAris = emptySet(),
        )
        loadChapter(bookId, chapter)
        saveLastPosition()
    }

    fun nextChapter() {
        val s = _state.value
        if (s.chapter < s.totalChapters) {
            navigateTo(s.bookId, s.chapter + 1)
        } else if (s.bookId < 66) {
            navigateTo(s.bookId + 1, 1)
        }
    }

    fun previousChapter() {
        val s = _state.value
        if (s.chapter > 1) {
            navigateTo(s.bookId, s.chapter - 1)
        } else if (s.bookId > 1) {
            val prevBook = s.bookId - 1
            navigateTo(prevBook, getBookChapterCount(prevBook))
        }
    }

    fun toggleVerseSelection(ari: Int) {
        val current = _state.value.selectedAris.toMutableSet()
        if (ari in current) current.remove(ari) else current.add(ari)
        _state.value = _state.value.copy(
            selectedAris = current,
            showVerseActions = current.isNotEmpty(),
        )
        rebuildItems()
    }

    fun clearSelection() {
        _state.value = _state.value.copy(selectedAris = emptySet(), showVerseActions = false)
        rebuildItems()
    }

    fun bookmarkSelected() {
        screenModelScope.launch {
            for (ari in _state.value.selectedAris) {
                markerRepo.createMarker(
                    Marker(
                        ari = ari,
                        kind = Marker.KIND_BOOKMARK,
                        caption = formatReference(ari),
                    )
                )
            }
            clearSelection()
        }
    }

    fun highlightSelected(colorIndex: Int) {
        screenModelScope.launch {
            for (ari in _state.value.selectedAris) {
                markerRepo.createMarker(
                    Marker(
                        ari = ari,
                        kind = Marker.KIND_HIGHLIGHT,
                        color = colorIndex,
                    )
                )
            }
            clearSelection()
        }
    }

    fun noteSelected(text: String) {
        screenModelScope.launch {
            for (ari in _state.value.selectedAris) {
                markerRepo.createMarker(
                    Marker(
                        ari = ari,
                        kind = Marker.KIND_NOTE,
                        caption = text,
                    )
                )
            }
            clearSelection()
        }
    }

    fun setFontSize(size: Int) {
        _state.value = _state.value.copy(fontSize = size.coerceIn(10, 32))
        settings.putInt(KEY_FONT_SIZE, _state.value.fontSize)
    }

    fun setLineSpacing(spacing: Float) {
        _state.value = _state.value.copy(lineSpacing = spacing.coerceIn(1.0f, 3.0f))
        settings.putFloat(KEY_LINE_SPACING, _state.value.lineSpacing)
    }

    // ── Commentary / Dictionary (SWORD only) ─────────────

    fun readCommentary(moduleKey: String, bookId: Int, chapter: Int, verse: Int): String {
        val def = org.androidbible.data.repository.SwordBibleReader.bookDefForId(bookId) ?: return ""
        val swordManager: org.androidbible.data.sword.SwordManager by inject()
        return swordManager.readCommentary(moduleKey, def.osisId, chapter, verse)
    }

    fun lookupDictionary(moduleKey: String, term: String): String {
        val swordManager: org.androidbible.data.sword.SwordManager by inject()
        return swordManager.lookupDictionary(moduleKey, term)
    }

    // ── Private ──────────────────────────────────────────

    private fun loadChapter(bookId: Int, chapter: Int) {
        val key = _state.value.moduleKey
        if (key.isEmpty()) return
        val reader = readerFactory.readerFor(_state.value.moduleEngine)

        _state.value = _state.value.copy(isLoading = true)
        screenModelScope.launch {
            val verses = reader.readChapter(key, bookId, chapter)
            _state.value = _state.value.copy(
                items = buildReaderItems(verses),
                isLoading = false,
            )
        }
    }

    private fun buildReaderItems(verses: List<Verse>): List<ReaderItem> {
        val items = mutableListOf<ReaderItem>()
        val selected = _state.value.selectedAris

        for (verse in verses) {
            // Check for pericope before this verse (YES2 `<TS>` tags embedded in text)
            val pericopeTitle = extractPericopeTitle(verse.text)
            if (pericopeTitle != null) {
                items.add(ReaderItem.PericopeItem(pericopeTitle))
            }

            items.add(
                ReaderItem.VerseItemData(
                    verse = verse,
                    isSelected = verse.ari in selected,
                    hasBookmark = verse.ari in bookmarkCache,
                    hasNote = verse.ari in noteCache,
                    highlightColor = highlightCache[verse.ari],
                )
            )
        }
        return items
    }

    private fun rebuildItems() {
        val currentVerses = _state.value.items.mapNotNull {
            (it as? ReaderItem.VerseItemData)?.verse
        }
        if (currentVerses.isNotEmpty()) {
            _state.value = _state.value.copy(items = buildReaderItems(currentVerses))
        }
    }

    private fun restoreLastPosition() {
        val bookId = settings.getInt(KEY_BOOK_ID, 1)
        val chapter = settings.getInt(KEY_CHAPTER, 1)
        val totalChapters = getBookChapterCount(bookId)
        _state.value = _state.value.copy(
            bookId = bookId,
            bookName = getBookName(bookId),
            chapter = chapter,
            totalChapters = totalChapters,
            fontSize = settings.getInt(KEY_FONT_SIZE, 16),
            lineSpacing = settings.getFloat(KEY_LINE_SPACING, 1.5f),
        )
    }

    private fun saveLastPosition() {
        settings.putInt(KEY_BOOK_ID, _state.value.bookId)
        settings.putInt(KEY_CHAPTER, _state.value.chapter)
    }

    private fun formatReference(ari: Int): String {
        val book = Ari.decodeBook(ari)
        val ch = Ari.decodeChapter(ari)
        val v = Ari.decodeVerse(ari)
        return "${getBookName(book)} $ch:$v"
    }

    companion object {
        private const val KEY_BOOK_ID = "reader_book_id"
        private const val KEY_CHAPTER = "reader_chapter"
        private const val KEY_FONT_SIZE = "reader_font_size"
        private const val KEY_LINE_SPACING = "reader_line_spacing"

        /** Book name lookup using SwordVersification (1-based bookId). */
        fun getBookName(bookId: Int): String {
            val def = org.androidbible.data.repository.SwordBibleReader.bookDefForId(bookId)
            return def?.name ?: "Book $bookId"
        }

        /** Chapter count using SwordVersification (1-based bookId). */
        fun getBookChapterCount(bookId: Int): Int {
            val def = org.androidbible.data.repository.SwordBibleReader.bookDefForId(bookId)
            return def?.chapterCount ?: 0
        }

        /**
         * Extract pericope title from YES2 format `<TS>title<Ts>` tags.
         * Also handles OSIS `<title>` tags.
         */
        fun extractPericopeTitle(text: String): String? {
            // YES2 pericope tags: <TS1>Title<Ts> or <TS2>Title<Ts> etc.
            val tsMatch = Regex("<TS\\d*>(.*?)<Ts>", RegexOption.DOT_MATCHES_ALL).find(text)
            if (tsMatch != null) return tsMatch.groupValues[1].trim()

            // OSIS title tags
            val titleMatch = Regex("<title[^>]*>(.*?)</title>", RegexOption.DOT_MATCHES_ALL).find(text)
            if (titleMatch != null) return titleMatch.groupValues[1].trim()

            return null
        }
    }
}
