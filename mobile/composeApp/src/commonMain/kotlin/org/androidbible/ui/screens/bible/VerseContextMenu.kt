package org.androidbible.ui.screens.bible

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import org.androidbible.domain.model.Label
import org.androidbible.domain.model.Marker
import org.androidbible.ui.theme.*
import org.androidbible.util.Ari

/**
 * Bottom sheet context menu for a single verse long-press.
 * Shows: bookmark toggle, highlight colour picker, note editor, label chips, copy/share.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun VerseContextMenu(
    ari: Int,
    existingMarkers: List<Marker>,
    labels: List<Label>,
    onBookmark: () -> Unit,
    onHighlight: (Int) -> Unit,
    onRemoveHighlight: () -> Unit,
    onNote: (String) -> Unit,
    onCopy: () -> Unit,
    onShare: () -> Unit,
    onAttachLabel: (Long) -> Unit,
    onDismiss: () -> Unit,
) {
    val (bookId, chapter, verse) = Ari.decode(ari)
    val bookName = BibleReaderViewModel.getBookName(bookId)
    val hasBookmark = existingMarkers.any { it.isBookmark }
    val existingHighlight = existingMarkers.firstOrNull { it.isHighlight }
    val existingNote = existingMarkers.firstOrNull { it.isNote }

    var showNoteEditor by remember { mutableStateOf(false) }
    var noteText by remember { mutableStateOf(existingNote?.caption ?: "") }

    val sheetState = rememberModalBottomSheetState()

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp, vertical = 16.dp),
        ) {
            // Reference header
            Text(
                text = "$bookName $chapter:$verse",
                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                color = MaterialTheme.colorScheme.primary,
            )

            Spacer(Modifier.height(16.dp))

            // ── Bookmark ──
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(8.dp))
                    .clickable { onBookmark() }
                    .padding(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(
                    Modifier
                        .size(8.dp)
                        .clip(CircleShape)
                        .background(
                            if (hasBookmark) MaterialTheme.colorScheme.primary
                            else MaterialTheme.colorScheme.outlineVariant
                        )
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = if (hasBookmark) "Remove Bookmark" else "Bookmark",
                    style = MaterialTheme.typography.bodyLarge,
                )
            }

            // ── Highlight Colours ──
            Text(
                "Highlight",
                style = MaterialTheme.typography.labelMedium,
                modifier = Modifier.padding(top = 8.dp, bottom = 4.dp),
            )
            Row(
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.padding(horizontal = 12.dp),
            ) {
                val colors = listOf(
                    1 to HighlightYellow,
                    2 to HighlightGreen,
                    3 to HighlightBlue,
                    4 to HighlightPink,
                    5 to HighlightOrange,
                    6 to HighlightPurple,
                )
                colors.forEach { (idx, color) ->
                    Box(
                        Modifier
                            .size(32.dp)
                            .clip(CircleShape)
                            .background(color)
                            .clickable { onHighlight(idx) },
                        contentAlignment = Alignment.Center,
                    ) {
                        if (existingHighlight?.color == idx) {
                            Box(
                                Modifier
                                    .size(12.dp)
                                    .clip(CircleShape)
                                    .background(MaterialTheme.colorScheme.onPrimary)
                            )
                        }
                    }
                }
                if (existingHighlight != null) {
                    TextButton(onClick = onRemoveHighlight) { Text("Clear") }
                }
            }

            // ── Note ──
            Spacer(Modifier.height(12.dp))
            if (showNoteEditor) {
                OutlinedTextField(
                    value = noteText,
                    onValueChange = { noteText = it },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Note") },
                    minLines = 3,
                    maxLines = 6,
                )
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End,
                ) {
                    TextButton(onClick = { showNoteEditor = false }) { Text("Cancel") }
                    Button(
                        onClick = { onNote(noteText); showNoteEditor = false },
                        enabled = noteText.isNotBlank(),
                    ) { Text("Save Note") }
                }
            } else {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(8.dp))
                        .clickable { showNoteEditor = true }
                        .padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(
                        Modifier
                            .size(8.dp)
                            .clip(CircleShape)
                            .background(
                                if (existingNote != null) MaterialTheme.colorScheme.tertiary
                                else MaterialTheme.colorScheme.outlineVariant
                            )
                    )
                    Spacer(Modifier.width(12.dp))
                    Text(
                        text = if (existingNote != null) "Edit Note" else "Add Note",
                        style = MaterialTheme.typography.bodyLarge,
                    )
                }
            }

            // ── Labels ──
            if (labels.isNotEmpty()) {
                Spacer(Modifier.height(8.dp))
                Text(
                    "Labels",
                    style = MaterialTheme.typography.labelMedium,
                    modifier = Modifier.padding(bottom = 4.dp),
                )
                LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(labels) { label ->
                        AssistChip(
                            onClick = { onAttachLabel(label.id) },
                            label = { Text(label.title) },
                        )
                    }
                }
            }

            // ── Copy / Share ──
            Spacer(Modifier.height(12.dp))
            HorizontalDivider()
            Spacer(Modifier.height(8.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                OutlinedButton(
                    onClick = onCopy,
                    modifier = Modifier.weight(1f),
                ) { Text("Copy") }
                OutlinedButton(
                    onClick = onShare,
                    modifier = Modifier.weight(1f),
                ) { Text("Share") }
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}
