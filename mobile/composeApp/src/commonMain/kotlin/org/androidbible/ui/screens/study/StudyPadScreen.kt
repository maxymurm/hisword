package org.androidbible.ui.screens.study

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.*
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import cafe.adriel.voyager.core.screen.Screen
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import com.russhwolf.settings.Settings
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.serialization.Serializable
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import org.androidbible.ui.screens.bible.BibleReaderScreen
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

/**
 * StudyPad — a personal research notebook with Markdown editing
 * and linked Bible verse references.
 *
 * Features:
 * - Create/edit/delete study pads
 * - Markdown formatting (bold, italic, headers, lists)
 * - Insert verse references as [[Gen 1:1]] links
 * - Preview mode with rendered Markdown and clickable verse links
 * - Persisted locally via multiplatform-settings
 */
class StudyPadScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { StudyPadScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text(state.editingPad?.title ?: "Study Pads") },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                    navigationIcon = {
                        if (state.editingPad != null) {
                            TextButton(onClick = { screenModel.saveAndClose() }) {
                                Text("< Back")
                            }
                        }
                    },
                    actions = {
                        if (state.editingPad != null) {
                            TextButton(onClick = { screenModel.togglePreview() }) {
                                Text(if (state.isPreview) "Edit" else "Preview")
                            }
                        }
                    },
                )
            },
            floatingActionButton = {
                if (state.editingPad == null) {
                    FloatingActionButton(onClick = { screenModel.createNew() }) {
                        Text("+", style = MaterialTheme.typography.headlineSmall)
                    }
                }
            },
        ) { padding ->
            if (state.editingPad != null) {
                val pad = state.editingPad!!
                if (state.isPreview) {
                    // Preview mode — rendered Markdown with clickable verse links
                    StudyPadPreview(
                        content = pad.content,
                        onVerseClick = { ari -> navigator.push(BibleReaderScreen(initialAri = ari)) },
                        modifier = Modifier.padding(padding),
                    )
                } else {
                    // Edit mode
                    StudyPadEditor(
                        pad = pad,
                        onTitleChange = { screenModel.updateTitle(it) },
                        onContentChange = { screenModel.updateContent(it) },
                        onInsertVerseRef = { screenModel.insertVerseRef(it) },
                        modifier = Modifier.padding(padding),
                    )
                }
            } else {
                // Pads list
                if (state.pads.isEmpty()) {
                    Box(
                        modifier = Modifier.fillMaxSize().padding(padding),
                        contentAlignment = Alignment.Center,
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("No study pads yet")
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                "Start a new study pad to organize your research",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.padding(padding).fillMaxSize(),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        items(state.pads) { pad ->
                            StudyPadCard(
                                pad = pad,
                                onClick = { screenModel.open(pad) },
                                onDelete = { screenModel.delete(pad.id) },
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun StudyPadCard(
    pad: StudyPad,
    onClick: () -> Unit,
    onDelete: () -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    pad.title,
                    style = MaterialTheme.typography.titleMedium,
                    modifier = Modifier.weight(1f),
                )
                TextButton(onClick = onDelete) {
                    Text("Delete", color = MaterialTheme.colorScheme.error)
                }
            }
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                pad.content.take(100).ifBlank { "Empty pad" },
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 2,
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                "Last updated: ${pad.updatedAt}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
fun StudyPadEditor(
    pad: StudyPad,
    onTitleChange: (String) -> Unit,
    onContentChange: (String) -> Unit,
    onInsertVerseRef: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    var verseRefInput by remember { mutableStateOf("") }
    var showVerseDialog by remember { mutableStateOf(false) }

    Column(
        modifier = modifier.fillMaxSize().padding(16.dp),
    ) {
        // Title field
        OutlinedTextField(
            value = pad.title,
            onValueChange = onTitleChange,
            label = { Text("Title") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        Spacer(modifier = Modifier.height(8.dp))

        // Formatting toolbar
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(4.dp),
        ) {
            FilledTonalButton(
                onClick = { onContentChange(pad.content + "**bold**") },
                contentPadding = PaddingValues(8.dp),
            ) { Text("B", fontWeight = FontWeight.Bold) }

            FilledTonalButton(
                onClick = { onContentChange(pad.content + "*italic*") },
                contentPadding = PaddingValues(8.dp),
            ) { Text("I", fontStyle = FontStyle.Italic) }

            FilledTonalButton(
                onClick = { onContentChange(pad.content + "\n# ") },
                contentPadding = PaddingValues(8.dp),
            ) { Text("H") }

            FilledTonalButton(
                onClick = { onContentChange(pad.content + "\n- ") },
                contentPadding = PaddingValues(8.dp),
            ) { Text("•") }

            FilledTonalButton(
                onClick = { showVerseDialog = true },
                contentPadding = PaddingValues(8.dp),
            ) { Text("\uD83D\uDCD6") }
        }

        Spacer(modifier = Modifier.height(8.dp))

        // Content editor
        OutlinedTextField(
            value = pad.content,
            onValueChange = onContentChange,
            modifier = Modifier.fillMaxWidth().weight(1f),
            label = { Text("Notes (Markdown)") },
            placeholder = { Text("Write your study notes...\nUse [[Gen 1:1]] for verse links") },
        )
    }

    // Verse reference insert dialog
    if (showVerseDialog) {
        AlertDialog(
            onDismissRequest = { showVerseDialog = false },
            title = { Text("Insert Verse Reference") },
            text = {
                OutlinedTextField(
                    value = verseRefInput,
                    onValueChange = { verseRefInput = it },
                    label = { Text("e.g., Gen 1:1") },
                    singleLine = true,
                )
            },
            confirmButton = {
                TextButton(onClick = {
                    if (verseRefInput.isNotBlank()) {
                        onInsertVerseRef(verseRefInput)
                        verseRefInput = ""
                        showVerseDialog = false
                    }
                }) { Text("Insert") }
            },
            dismissButton = {
                TextButton(onClick = { showVerseDialog = false }) { Text("Cancel") }
            },
        )
    }
}

@Composable
fun StudyPadPreview(
    content: String,
    onVerseClick: (Int) -> Unit,
    modifier: Modifier = Modifier,
) {
    SelectionContainer {
        Column(
            modifier = modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
        ) {
            val lines = content.lines()
            for (line in lines) {
                when {
                    line.startsWith("# ") -> Text(
                        line.removePrefix("# "),
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                    )
                    line.startsWith("## ") -> Text(
                        line.removePrefix("## "),
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.SemiBold,
                    )
                    line.startsWith("- ") || line.startsWith("* ") -> {
                        Row {
                            Text("\u2022 ", modifier = Modifier.padding(end = 4.dp))
                            RenderInlineMarkdown(
                                line.removePrefix("- ").removePrefix("* "),
                                onVerseClick,
                            )
                        }
                    }
                    line.isBlank() -> Spacer(modifier = Modifier.height(8.dp))
                    else -> RenderInlineMarkdown(line, onVerseClick)
                }
            }
        }
    }
}

@Composable
fun RenderInlineMarkdown(text: String, onVerseClick: (Int) -> Unit) {
    val annotated = buildAnnotatedString {
        var i = 0
        while (i < text.length) {
            // Check for verse reference [[...]]
            if (i + 1 < text.length && text[i] == '[' && text[i + 1] == '[') {
                val end = text.indexOf("]]", i + 2)
                if (end != -1) {
                    val ref = text.substring(i + 2, end)
                    val ari = parseVerseReference(ref)
                    pushStringAnnotation("verse", ari.toString())
                    withStyle(SpanStyle(
                        color = androidx.compose.ui.graphics.Color(0xFF1E88E5),
                        textDecoration = TextDecoration.Underline,
                    )) {
                        append(ref)
                    }
                    pop()
                    i = end + 2
                    continue
                }
            }
            // Bold **...**
            if (i + 1 < text.length && text[i] == '*' && text[i + 1] == '*') {
                val end = text.indexOf("**", i + 2)
                if (end != -1) {
                    withStyle(SpanStyle(fontWeight = FontWeight.Bold)) {
                        append(text.substring(i + 2, end))
                    }
                    i = end + 2
                    continue
                }
            }
            // Italic *...*
            if (text[i] == '*' && (i + 1 >= text.length || text[i + 1] != '*')) {
                val end = text.indexOf('*', i + 1)
                if (end != -1) {
                    withStyle(SpanStyle(fontStyle = FontStyle.Italic)) {
                        append(text.substring(i + 1, end))
                    }
                    i = end + 1
                    continue
                }
            }
            append(text[i])
            i++
        }
    }

    ClickableText(
        text = annotated,
        style = MaterialTheme.typography.bodyLarge,
        onClick = { offset ->
            annotated.getStringAnnotations("verse", offset, offset).firstOrNull()?.let {
                val ari = it.item.toIntOrNull() ?: 0
                if (ari != 0) onVerseClick(ari)
            }
        },
    )
}

/**
 * Parse a verse reference like "Gen 1:1" into an ARI.
 * Returns 0 if parsing fails.
 */
fun parseVerseReference(ref: String): Int {
    val abbrevToBookId = mapOf(
        "gen" to 1, "exo" to 2, "lev" to 3, "num" to 4, "deu" to 5,
        "jos" to 6, "jdg" to 7, "rth" to 8, "rut" to 8,
        "1sa" to 9, "2sa" to 10, "1ki" to 11, "2ki" to 12,
        "1ch" to 13, "2ch" to 14, "ezr" to 15, "neh" to 16, "est" to 17,
        "job" to 18, "psa" to 19, "pro" to 20, "ecc" to 21, "sol" to 22,
        "isa" to 23, "jer" to 24, "lam" to 25, "eze" to 26, "dan" to 27,
        "hos" to 28, "joe" to 29, "amo" to 30, "oba" to 31, "jon" to 32,
        "mic" to 33, "nah" to 34, "hab" to 35, "zep" to 36, "hag" to 37,
        "zec" to 38, "mal" to 39,
        "mat" to 40, "mar" to 41, "luk" to 42, "joh" to 43, "act" to 44,
        "rom" to 45, "1co" to 46, "2co" to 47, "gal" to 48, "eph" to 49,
        "php" to 50, "col" to 51, "1th" to 52, "2th" to 53, "1ti" to 54,
        "2ti" to 55, "tit" to 56, "phm" to 57, "heb" to 58, "jam" to 59,
        "1pe" to 60, "2pe" to 61, "1jn" to 62, "2jn" to 63, "3jn" to 64,
        "jud" to 65, "rev" to 66,
    )
    val parts = ref.trim().split(" ", limit = 2)
    if (parts.size != 2) return 0
    val bookId = abbrevToBookId[parts[0].lowercase().take(3)] ?: return 0
    val cv = parts[1].split(":")
    val chapter = cv.getOrNull(0)?.toIntOrNull() ?: return 0
    val verse = cv.getOrNull(1)?.toIntOrNull() ?: 0
    return Ari.encode(bookId, chapter, verse)
}

// ── Data model ─────────────────────────────────────────────

@Serializable
data class StudyPad(
    val id: String,
    val title: String,
    val content: String,
    val createdAt: String,
    val updatedAt: String,
)

// ── ScreenModel ────────────────────────────────────────────

data class StudyPadState(
    val pads: List<StudyPad> = emptyList(),
    val editingPad: StudyPad? = null,
    val isPreview: Boolean = false,
)

class StudyPadScreenModel : ScreenModel, KoinComponent {

    private val settings: Settings by inject()
    private val json = Json { prettyPrint = true; ignoreUnknownKeys = true }

    private val _state = MutableStateFlow(StudyPadState())
    val state: StateFlow<StudyPadState> = _state.asStateFlow()

    init {
        loadPads()
    }

    private fun loadPads() {
        val raw = settings.getStringOrNull("study_pads") ?: return
        try {
            val pads = json.decodeFromString<List<StudyPad>>(raw)
            _state.value = _state.value.copy(pads = pads.sortedByDescending { it.updatedAt })
        } catch (_: Exception) {
            // Ignore corrupt data
        }
    }

    private fun savePads(pads: List<StudyPad>) {
        settings.putString("study_pads", json.encodeToString(pads))
    }

    fun createNew() {
        val now = kotlinx.datetime.Clock.System.now().toString()
        val pad = StudyPad(
            id = kotlinx.datetime.Clock.System.now().toEpochMilliseconds().toString(),
            title = "New Study Pad",
            content = "",
            createdAt = now,
            updatedAt = now,
        )
        _state.value = _state.value.copy(editingPad = pad, isPreview = false)
    }

    fun open(pad: StudyPad) {
        _state.value = _state.value.copy(editingPad = pad, isPreview = false)
    }

    fun updateTitle(title: String) {
        val pad = _state.value.editingPad ?: return
        _state.value = _state.value.copy(
            editingPad = pad.copy(
                title = title,
                updatedAt = kotlinx.datetime.Clock.System.now().toString(),
            ),
        )
    }

    fun updateContent(content: String) {
        val pad = _state.value.editingPad ?: return
        _state.value = _state.value.copy(
            editingPad = pad.copy(
                content = content,
                updatedAt = kotlinx.datetime.Clock.System.now().toString(),
            ),
        )
    }

    fun insertVerseRef(ref: String) {
        val pad = _state.value.editingPad ?: return
        val newContent = pad.content + "[[${ref}]]"
        updateContent(newContent)
    }

    fun togglePreview() {
        _state.value = _state.value.copy(isPreview = !_state.value.isPreview)
    }

    fun saveAndClose() {
        val pad = _state.value.editingPad ?: return
        val pads = _state.value.pads.toMutableList()
        val idx = pads.indexOfFirst { it.id == pad.id }
        if (idx >= 0) {
            pads[idx] = pad
        } else {
            pads.add(pad)
        }
        savePads(pads)
        _state.value = _state.value.copy(
            editingPad = null,
            isPreview = false,
            pads = pads.sortedByDescending { it.updatedAt },
        )
    }

    fun delete(id: String) {
        val pads = _state.value.pads.filter { it.id != id }
        savePads(pads)
        _state.value = _state.value.copy(pads = pads)
    }
}
