package org.androidbible.ui.screens.bible

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import org.androidbible.util.Ari

/**
 * Full-screen bottom sheet Markdown note editor.
 * Supports bold (**text**), italic (*text*), and heading (# text) preview.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NoteEditorSheet(
    ari: Int,
    initialText: String = "",
    onSave: (String) -> Unit,
    onDelete: (() -> Unit)? = null,
    onDismiss: () -> Unit,
) {
    val (bookId, chapter, verse) = Ari.decode(ari)
    val bookName = BibleReaderViewModel.getBookName(bookId)

    var text by remember { mutableStateOf(initialText) }
    var showPreview by remember { mutableStateOf(false) }

    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp, vertical = 16.dp)
                .imePadding(),
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = "$bookName $chapter:$verse",
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.primary,
                )
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (onDelete != null) {
                        TextButton(onClick = onDelete) {
                            Text("Delete", color = MaterialTheme.colorScheme.error)
                        }
                    }
                    TextButton(onClick = onDismiss) { Text("Cancel") }
                    Button(
                        onClick = { onSave(text) },
                        enabled = text.isNotBlank(),
                    ) { Text("Save") }
                }
            }

            Spacer(Modifier.height(8.dp))

            // Toolbar
            Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                MarkdownToolbarButton("B") {
                    text = wrapSelection(text, "**")
                }
                MarkdownToolbarButton("I") {
                    text = wrapSelection(text, "*")
                }
                MarkdownToolbarButton("H") {
                    text = if (text.startsWith("# ")) text else "# $text"
                }
                Spacer(Modifier.weight(1f))
                FilterChip(
                    selected = showPreview,
                    onClick = { showPreview = !showPreview },
                    label = { Text("Preview") },
                )
            }

            Spacer(Modifier.height(8.dp))

            // Editor or Preview
            if (showPreview) {
                Surface(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f),
                    shape = MaterialTheme.shapes.medium,
                    color = MaterialTheme.colorScheme.surfaceContainerLow,
                ) {
                    Column(
                        modifier = Modifier
                            .padding(16.dp)
                            .verticalScroll(rememberScrollState()),
                    ) {
                        Text(
                            text = renderMarkdown(text),
                            style = MaterialTheme.typography.bodyLarge,
                        )
                    }
                }
            } else {
                OutlinedTextField(
                    value = text,
                    onValueChange = { text = it },
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f),
                    label = { Text("Note (Markdown supported)") },
                    minLines = 8,
                )
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}

@Composable
private fun MarkdownToolbarButton(label: String, onClick: () -> Unit) {
    FilledTonalButton(
        onClick = onClick,
        modifier = Modifier.size(40.dp),
        contentPadding = PaddingValues(0.dp),
    ) {
        Text(label, style = MaterialTheme.typography.labelLarge)
    }
}

private fun wrapSelection(text: String, wrapper: String): String {
    return "${text}${wrapper}text${wrapper}"
}

/**
 * Simple Markdown renderer for preview.
 * Handles: **bold**, *italic*, # heading, ## heading.
 */
internal fun renderMarkdown(input: String): AnnotatedString {
    return buildAnnotatedString {
        for (line in input.lines()) {
            when {
                line.startsWith("## ") -> {
                    withStyle(SpanStyle(fontWeight = FontWeight.Bold, fontSize = 18.sp)) {
                        append(line.removePrefix("## "))
                    }
                }
                line.startsWith("# ") -> {
                    withStyle(SpanStyle(fontWeight = FontWeight.Bold, fontSize = 22.sp)) {
                        append(line.removePrefix("# "))
                    }
                }
                else -> appendInlineMarkdown(line)
            }
            append("\n")
        }
    }
}

private fun AnnotatedString.Builder.appendInlineMarkdown(line: String) {
    var i = 0
    while (i < line.length) {
        when {
            line.startsWith("**", i) -> {
                val end = line.indexOf("**", i + 2)
                if (end > 0) {
                    withStyle(SpanStyle(fontWeight = FontWeight.Bold)) {
                        append(line.substring(i + 2, end))
                    }
                    i = end + 2
                } else {
                    append(line[i])
                    i++
                }
            }
            line[i] == '*' && (i + 1 < line.length && line[i + 1] != '*') -> {
                val end = line.indexOf('*', i + 1)
                if (end > 0) {
                    withStyle(SpanStyle(fontStyle = FontStyle.Italic)) {
                        append(line.substring(i + 1, end))
                    }
                    i = end + 1
                } else {
                    append(line[i])
                    i++
                }
            }
            else -> {
                append(line[i])
                i++
            }
        }
    }
}
