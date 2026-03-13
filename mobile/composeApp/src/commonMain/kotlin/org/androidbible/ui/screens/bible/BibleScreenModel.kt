package org.androidbible.ui.screens.bible

import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.androidbible.domain.model.*
import org.androidbible.domain.repository.BibleRepository
import org.androidbible.domain.repository.MarkerRepository
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject
import org.androidbible.util.Ari

data class BibleState(
    val versions: List<BibleVersion> = emptyList(),
    val books: List<Book> = emptyList(),
    val verses: List<Verse> = emptyList(),
    val currentVersionId: Long = 0,
    val currentBookId: Int = 0,
    val currentBookName: String = "",
    val currentChapter: Int = 0,
    val totalChapters: Int = 0,
    val isLoading: Boolean = false,
    val error: String? = null,
    val showBookPicker: Boolean = false,
    val showVersionPicker: Boolean = false,
    val selectedVerseAri: Int? = null,
    val showVerseActions: Boolean = false,
    val highlightedVerses: Map<Int, Int> = emptyMap(), // ari -> color index
)

class BibleScreenModel : ScreenModel, KoinComponent {

    private val bibleRepo: BibleRepository by inject()
    private val markerRepo: MarkerRepository by inject()

    private val _state = MutableStateFlow(BibleState())
    val state: StateFlow<BibleState> = _state.asStateFlow()

    init {
        loadVersions()
        loadHighlights()
    }

    private fun loadVersions() {
        screenModelScope.launch {
            bibleRepo.getVersions().collect { versions ->
                _state.value = _state.value.copy(versions = versions)
                if (versions.isNotEmpty() && _state.value.currentVersionId == 0L) {
                    selectVersion(versions.first().id)
                }
            }
        }
    }

    private fun loadHighlights() {
        screenModelScope.launch {
            markerRepo.getMarkers(Marker.KIND_HIGHLIGHT).collect { markers ->
                val highlightMap = markers.associate { it.ari to (it.color ?: 1) }
                _state.value = _state.value.copy(highlightedVerses = highlightMap)
            }
        }
    }

    fun selectVersion(versionId: Long) {
        _state.value = _state.value.copy(
            currentVersionId = versionId,
            isLoading = true,
            showVersionPicker = false,
        )
        screenModelScope.launch {
            bibleRepo.getBooks(versionId).collect { books ->
                _state.value = _state.value.copy(books = books, isLoading = false)
                if (books.isNotEmpty() && _state.value.currentBookId == 0) {
                    selectBook(books.first().bookId)
                }
            }
        }
    }

    fun selectBook(bookId: Int) {
        val book = _state.value.books.find { it.bookId == bookId }
        _state.value = _state.value.copy(
            currentBookId = bookId,
            currentBookName = book?.shortName ?: "",
            totalChapters = book?.chapterCount ?: 0,
            currentChapter = 1,
            showBookPicker = false,
        )
        loadChapter(1)
    }

    fun loadChapter(chapter: Int) {
        _state.value = _state.value.copy(currentChapter = chapter, isLoading = true)
        screenModelScope.launch {
            bibleRepo.getChapter(
                _state.value.currentVersionId,
                _state.value.currentBookId,
                chapter,
            ).collect { chapterData ->
                _state.value = _state.value.copy(
                    verses = chapterData.verses,
                    isLoading = false,
                )
            }
        }
    }

    fun nextChapter() {
        val s = _state.value
        if (s.currentChapter < s.totalChapters) {
            loadChapter(s.currentChapter + 1)
        } else {
            // Move to next book
            val currentIndex = s.books.indexOfFirst { it.bookId == s.currentBookId }
            if (currentIndex >= 0 && currentIndex < s.books.size - 1) {
                selectBook(s.books[currentIndex + 1].bookId)
            }
        }
    }

    fun previousChapter() {
        val s = _state.value
        if (s.currentChapter > 1) {
            loadChapter(s.currentChapter - 1)
        } else {
            // Move to previous book's last chapter
            val currentIndex = s.books.indexOfFirst { it.bookId == s.currentBookId }
            if (currentIndex > 0) {
                val prevBook = s.books[currentIndex - 1]
                _state.value = _state.value.copy(
                    currentBookId = prevBook.bookId,
                    currentBookName = prevBook.shortName,
                    totalChapters = prevBook.chapterCount,
                )
                loadChapter(prevBook.chapterCount)
            }
        }
    }

    fun toggleBookPicker() {
        _state.value = _state.value.copy(showBookPicker = !_state.value.showBookPicker)
    }

    fun toggleVersionPicker() {
        _state.value = _state.value.copy(showVersionPicker = !_state.value.showVersionPicker)
    }

    fun selectVerse(ari: Int) {
        _state.value = _state.value.copy(
            selectedVerseAri = ari,
            showVerseActions = true,
        )
    }

    fun dismissVerseActions() {
        _state.value = _state.value.copy(showVerseActions = false, selectedVerseAri = null)
    }

    fun bookmarkVerse() {
        val ari = _state.value.selectedVerseAri ?: return
        screenModelScope.launch {
            markerRepo.createMarker(
                Marker(
                    ari = ari,
                    kind = Marker.KIND_BOOKMARK,
                    caption = "${_state.value.currentBookName} ${Ari.decodeChapter(ari)}:${Ari.decodeVerse(ari)}",
                )
            )
            dismissVerseActions()
        }
    }

    fun highlightVerse(colorIndex: Int) {
        val ari = _state.value.selectedVerseAri ?: return
        screenModelScope.launch {
            markerRepo.createMarker(
                Marker(
                    ari = ari,
                    kind = Marker.KIND_HIGHLIGHT,
                    color = colorIndex,
                )
            )
            val updated = _state.value.highlightedVerses.toMutableMap()
            updated[ari] = colorIndex
            _state.value = _state.value.copy(highlightedVerses = updated)
            dismissVerseActions()
        }
    }

    fun addNote(text: String) {
        val ari = _state.value.selectedVerseAri ?: return
        screenModelScope.launch {
            markerRepo.createMarker(
                Marker(
                    ari = ari,
                    kind = Marker.KIND_NOTE,
                    caption = text,
                )
            )
            dismissVerseActions()
        }
    }
}
