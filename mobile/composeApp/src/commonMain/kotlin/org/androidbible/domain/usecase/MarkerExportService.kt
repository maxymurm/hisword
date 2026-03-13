package org.androidbible.domain.usecase

import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import org.androidbible.domain.model.Marker
import org.androidbible.domain.repository.MarkerRepository
import org.androidbible.ui.screens.bible.BibleReaderViewModel
import org.androidbible.util.Ari

/**
 * Export markers as JSON or formatted plain text.
 */
class MarkerExportService(private val markerRepo: MarkerRepository) {

    private val json = Json {
        prettyPrint = true
        encodeDefaults = true
    }

    /**
     * Export all markers (or filtered by kind) as JSON string.
     */
    suspend fun exportAsJson(kind: Int? = null): String {
        val markers = markerRepo.getAllMarkersSnapshot()
            .let { all -> if (kind != null) all.filter { it.kind == kind } else all }
        return json.encodeToString(markers)
    }

    /**
     * Export markers as human-readable plain text.
     * Format per entry:
     *   [Bookmark] Genesis 1:1
     *   [Note] Genesis 1:1 — note text here
     *   [Highlight] Genesis 1:1 (yellow)
     */
    suspend fun exportAsPlainText(kind: Int? = null): String {
        val markers = markerRepo.getAllMarkersSnapshot()
            .let { all -> if (kind != null) all.filter { it.kind == kind } else all }
            .sortedBy { it.ari }

        return buildString {
            appendLine("HisWord Markers Export")
            appendLine("=".repeat(40))
            appendLine()

            for (marker in markers) {
                val ref = formatReference(marker.ari)
                val kindLabel = when (marker.kind) {
                    Marker.KIND_BOOKMARK -> "Bookmark"
                    Marker.KIND_NOTE -> "Note"
                    Marker.KIND_HIGHLIGHT -> "Highlight"
                    else -> "Marker"
                }

                append("[$kindLabel] $ref")

                when (marker.kind) {
                    Marker.KIND_NOTE -> {
                        if (marker.caption.isNotBlank()) {
                            appendLine()
                            appendLine("  ${marker.caption}")
                        } else {
                            appendLine()
                        }
                    }
                    Marker.KIND_HIGHLIGHT -> {
                        val colorName = when (marker.color) {
                            1 -> "yellow"
                            2 -> "green"
                            3 -> "blue"
                            4 -> "pink"
                            5 -> "orange"
                            6 -> "purple"
                            else -> "default"
                        }
                        appendLine(" ($colorName)")
                    }
                    else -> appendLine()
                }
            }

            appendLine()
            appendLine("Total: ${markers.size} markers")
        }
    }

    /**
     * Export selected markers (by IDs) as JSON.
     */
    suspend fun exportSelectedAsJson(ids: List<Long>): String {
        val markers = markerRepo.getAllMarkersSnapshot().filter { it.id in ids.toSet() }
        return json.encodeToString(markers)
    }

    private fun formatReference(ari: Int): String {
        val bookId = Ari.decodeBook(ari)
        val chapter = Ari.decodeChapter(ari)
        val verse = Ari.decodeVerse(ari)
        val bookName = BibleReaderViewModel.getBookName(bookId)
        return "$bookName $chapter:$verse"
    }
}
