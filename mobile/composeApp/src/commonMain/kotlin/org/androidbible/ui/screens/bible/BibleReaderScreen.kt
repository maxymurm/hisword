package org.androidbible.ui.screens.bible

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.screen.Screen
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import kotlinx.coroutines.launch
import org.androidbible.data.bintex.Yes2TextDecoder
import org.androidbible.data.sword.osis.OsisTextFilter
import org.androidbible.domain.model.Verse
import org.androidbible.ui.theme.*

/**
 * Main Bible reader screen: engine-agnostic, supports SWORD + Bintex modules.
 * Features: chapter pager, verse selection, bookmark/highlight/note indicators,
 * pericope headers, text appearance settings, commentary panel.
 */
class BibleReaderScreen(
    private val initialAri: Int = 0,
) : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val viewModel = rememberScreenModel { BibleReaderViewModel() }
        val state by viewModel.state.collectAsState()
        var showAppearance by remember { mutableStateOf(false) }
        var showCommentary by remember { mutableStateOf(false) }

        LaunchedEffect(initialAri) {
            if (initialAri != 0) {
                viewModel.navigateToAri(initialAri)
            }
        }

        Scaffold(
            topBar = {
                ReaderTopBar(
                    bookName = state.bookName,
                    chapter = state.chapter,
                    moduleName = state.moduleName,
                    onBack = { navigator.pop() },
                    onBookPicker = { /* TODO: navigate to GotoScreen */ },
                    onVersionPicker = { /* TODO: show version picker */ },
                    onAppearance = { showAppearance = !showAppearance },
                    onCommentary = { showCommentary = !showCommentary },
                )
            },
            bottomBar = {
                if (state.showVerseActions) {
                    VerseActionsBar(
                        selectedCount = state.selectedAris.size,
                        onBookmark = { viewModel.bookmarkSelected() },
                        onHighlight = { viewModel.highlightSelected(it) },
                        onNote = { viewModel.noteSelected(it) },
                        onCancel = { viewModel.clearSelection() },
                    )
                } else {
                    ChapterNavBar(
                        chapter = state.chapter,
                        totalChapters = state.totalChapters,
                        onPrevious = { viewModel.previousChapter() },
                        onNext = { viewModel.nextChapter() },
                    )
                }
            },
        ) { padding ->
            Box(modifier = Modifier.padding(padding)) {
                if (state.isLoading) {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
                } else {
                    ChapterContent(
                        items = state.items,
                        fontSize = state.fontSize,
                        lineSpacing = state.lineSpacing,
                        engine = state.moduleEngine,
                        onVerseLongClick = { viewModel.toggleVerseSelection(it) },
                    )
                }

                // Text appearance panel
                if (showAppearance) {
                    TextAppearancePanel(
                        fontSize = state.fontSize,
                        lineSpacing = state.lineSpacing,
                        onFontSizeChange = { viewModel.setFontSize(it) },
                        onLineSpacingChange = { viewModel.setLineSpacing(it) },
                        onDismiss = { showAppearance = false },
                    )
                }

                // Commentary side panel
                if (showCommentary) {
                    CommentaryPanel(
                        viewModel = viewModel,
                        bookId = state.bookId,
                        chapter = state.chapter,
                        onDismiss = { showCommentary = false },
                    )
                }
            }
        }
    }
}

// ── Top Bar ──────────────────────────────────────────────

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ReaderTopBar(
    bookName: String,
    chapter: Int,
    moduleName: String,
    onBack: () -> Unit,
    onBookPicker: () -> Unit,
    onVersionPicker: () -> Unit,
    onAppearance: () -> Unit,
    onCommentary: () -> Unit,
) {
    TopAppBar(
        navigationIcon = {
            TextButton(onClick = onBack) { Text("Back") }
        },
        title = {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                TextButton(onClick = onBookPicker) {
                    Text(
                        "$bookName $chapter",
                        style = MaterialTheme.typography.titleMedium,
                    )
                }
                if (moduleName.isNotEmpty()) {
                    AssistChip(
                        onClick = onVersionPicker,
                        label = {
                            Text(
                                moduleName.take(12),
                                style = MaterialTheme.typography.labelSmall,
                            )
                        },
                    )
                }
            }
        },
        actions = {
            TextButton(onClick = onAppearance) { Text("Aa") }
            TextButton(onClick = onCommentary) { Text("Cm") }
        },
        colors = TopAppBarDefaults.topAppBarColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer,
        ),
    )
}

// ── Chapter Content (LazyColumn with pericopes + verses) ─

@Composable
private fun ChapterContent(
    items: List<ReaderItem>,
    fontSize: Int,
    lineSpacing: Float,
    engine: String,
    onVerseLongClick: (Int) -> Unit,
) {
    val listState = rememberLazyListState()

    LazyColumn(
        state = listState,
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
    ) {
        items(items, key = { item ->
            when (item) {
                is ReaderItem.PericopeItem -> "pericope_${item.title.hashCode()}"
                is ReaderItem.VerseItemData -> "verse_${item.verse.ari}"
            }
        }) { item ->
            when (item) {
                is ReaderItem.PericopeItem -> PericopeHeader(title = item.title)
                is ReaderItem.VerseItemData -> EnhancedVerseItem(
                    data = item,
                    fontSize = fontSize,
                    lineSpacing = lineSpacing,
                    engine = engine,
                    onLongClick = { onVerseLongClick(item.verse.ari) },
                )
            }
        }
    }
}

// ── Pericope Header (#79) ────────────────────────────────

@Composable
fun PericopeHeader(title: String) {
    Text(
        text = title,
        style = MaterialTheme.typography.titleSmall.copy(
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.primary,
        ),
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 16.dp, bottom = 4.dp),
    )
}

// ── Enhanced Verse Item (#78) ────────────────────────────

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun EnhancedVerseItem(
    data: ReaderItem.VerseItemData,
    fontSize: Int,
    lineSpacing: Float,
    engine: String,
    onLongClick: () -> Unit,
) {
    val verse = data.verse
    val bgColor = when {
        data.isSelected -> MaterialTheme.colorScheme.secondaryContainer
        data.highlightColor != null -> when (data.highlightColor) {
            1 -> HighlightYellow
            2 -> HighlightGreen
            3 -> HighlightBlue
            4 -> HighlightPink
            5 -> HighlightOrange
            6 -> HighlightPurple
            else -> Color.Transparent
        }
        else -> Color.Transparent
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(bgColor, RoundedCornerShape(4.dp))
            .combinedClickable(onClick = {}, onLongClick = onLongClick)
            .padding(vertical = 2.dp, horizontal = 4.dp),
        verticalAlignment = Alignment.Top,
    ) {
        // Indicators column
        if (data.hasBookmark || data.hasNote) {
            Column(
                modifier = Modifier.padding(end = 4.dp, top = 2.dp),
                verticalArrangement = Arrangement.spacedBy(2.dp),
            ) {
                if (data.hasBookmark) {
                    Box(
                        Modifier
                            .size(6.dp)
                            .clip(CircleShape)
                            .background(MaterialTheme.colorScheme.primary)
                    )
                }
                if (data.hasNote) {
                    Box(
                        Modifier
                            .size(6.dp)
                            .clip(CircleShape)
                            .background(MaterialTheme.colorScheme.tertiary)
                    )
                }
            }
        }

        // Formatted text (#80)
        Text(
            text = formatVerseText(verse, engine),
            style = MaterialTheme.typography.bodyLarge.copy(
                fontSize = fontSize.sp,
                lineHeight = (fontSize * lineSpacing).sp,
            ),
            modifier = Modifier.weight(1f),
        )
    }
}

// ── Formatted Verse Text (#80) ───────────────────────────

/**
 * Dispatch text formatting based on engine type.
 * YES2/Bintex: use Yes2TextDecoder
 * SWORD: use OsisTextFilter (already stripped by SwordManager, but handle remnants)
 */
@Composable
fun formatVerseText(verse: Verse, engine: String): AnnotatedString {
    return buildAnnotatedString {
        // Verse number
        withStyle(
            SpanStyle(
                fontWeight = FontWeight.Bold,
                fontSize = 11.sp,
                color = MaterialTheme.colorScheme.primary,
            )
        ) {
            append("${verse.verse} ")
        }

        // Plain text (already decoded/filtered by the reader layer)
        val displayText = verse.textWithoutFormatting ?: verse.text
        append(displayText)
    }
}

// ── Chapter Navigation Bar ───────────────────────────────

@Composable
private fun ChapterNavBar(
    chapter: Int,
    totalChapters: Int,
    onPrevious: () -> Unit,
    onNext: () -> Unit,
) {
    BottomAppBar(containerColor = MaterialTheme.colorScheme.surfaceContainer) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TextButton(onClick = onPrevious, enabled = chapter > 1) {
                Text("\u25C0 Previous")
            }
            Text("$chapter / $totalChapters", style = MaterialTheme.typography.bodyMedium)
            TextButton(onClick = onNext, enabled = chapter < totalChapters) {
                Text("Next \u25B6")
            }
        }
    }
}

// ── Verse Actions Bar (multi-select) ─────────────────────

@Composable
private fun VerseActionsBar(
    selectedCount: Int,
    onBookmark: () -> Unit,
    onHighlight: (Int) -> Unit,
    onNote: (String) -> Unit,
    onCancel: () -> Unit,
) {
    var showNoteInput by remember { mutableStateOf(false) }
    var noteText by remember { mutableStateOf("") }

    BottomAppBar(containerColor = MaterialTheme.colorScheme.secondaryContainer) {
        Column(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("$selectedCount selected", style = MaterialTheme.typography.labelMedium)
                TextButton(onClick = onCancel) { Text("Cancel") }
            }

            if (showNoteInput) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    OutlinedTextField(
                        value = noteText,
                        onValueChange = { noteText = it },
                        modifier = Modifier.weight(1f),
                        placeholder = { Text("Note...") },
                        singleLine = true,
                    )
                    TextButton(
                        onClick = { onNote(noteText); showNoteInput = false; noteText = "" },
                        enabled = noteText.isNotBlank(),
                    ) { Text("Save") }
                }
            } else {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    FilledTonalButton(onClick = onBookmark) { Text("Bookmark") }

                    // Highlight color circles
                    val highlightColors = listOf(
                        1 to HighlightYellow,
                        2 to HighlightGreen,
                        3 to HighlightBlue,
                        4 to HighlightPink,
                    )
                    highlightColors.forEach { (idx, color) ->
                        Box(
                            Modifier
                                .size(28.dp)
                                .clip(CircleShape)
                                .background(color)
                                .combinedClickable(onClick = { onHighlight(idx) }),
                        )
                    }

                    Spacer(Modifier.weight(1f))
                    OutlinedButton(onClick = { showNoteInput = true }) { Text("Note") }
                }
            }
        }
    }
}

// ── Text Appearance Panel (#83) ──────────────────────────

@Composable
fun TextAppearancePanel(
    fontSize: Int,
    lineSpacing: Float,
    onFontSizeChange: (Int) -> Unit,
    onLineSpacingChange: (Float) -> Unit,
    onDismiss: () -> Unit,
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        shape = RoundedCornerShape(16.dp),
        shadowElevation = 8.dp,
        color = MaterialTheme.colorScheme.surface,
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text("Text Appearance", style = MaterialTheme.typography.titleSmall)
                TextButton(onClick = onDismiss) { Text("Done") }
            }

            Spacer(Modifier.height(12.dp))

            // Font size
            Text("Font Size: ${fontSize}sp", style = MaterialTheme.typography.labelMedium)
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                TextButton(onClick = { onFontSizeChange(fontSize - 1) }) { Text("A-") }
                Slider(
                    value = fontSize.toFloat(),
                    onValueChange = { onFontSizeChange(it.toInt()) },
                    valueRange = 10f..32f,
                    modifier = Modifier.weight(1f),
                )
                TextButton(onClick = { onFontSizeChange(fontSize + 1) }) { Text("A+") }
            }

            Spacer(Modifier.height(8.dp))

            // Line spacing
            Text("Line Spacing: ${"%.1f".format(lineSpacing)}", style = MaterialTheme.typography.labelMedium)
            Slider(
                value = lineSpacing,
                onValueChange = onLineSpacingChange,
                valueRange = 1.0f..3.0f,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

// ── Commentary + Dictionary Panel (#84) ──────────────────

@Composable
fun CommentaryPanel(
    viewModel: BibleReaderViewModel,
    bookId: Int,
    chapter: Int,
    onDismiss: () -> Unit,
) {
    val state by viewModel.state.collectAsState()

    // Get SWORD commentary modules
    val commentaryModules = state.modules.filter {
        it.engine == "sword" && it.key != state.moduleKey
    }

    var selectedComKey by remember { mutableStateOf("") }
    var commentaryText by remember { mutableStateOf("") }
    var lookupTerm by remember { mutableStateOf("") }
    var lookupResult by remember { mutableStateOf("") }

    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .fillMaxHeight(0.4f),
        shape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp),
        shadowElevation = 8.dp,
        color = MaterialTheme.colorScheme.surfaceContainerHigh,
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text("Commentary & Dictionary", style = MaterialTheme.typography.titleSmall)
                TextButton(onClick = onDismiss) { Text("Close") }
            }

            if (commentaryModules.isEmpty()) {
                Text(
                    "No commentary modules installed",
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(top = 16.dp),
                )
            } else {
                // Commentary
                LazyColumn(modifier = Modifier.weight(1f)) {
                    item {
                        Text(
                            text = commentaryText.ifEmpty { "Select a commentary module above" },
                            style = MaterialTheme.typography.bodyMedium,
                        )
                    }
                }
            }

            HorizontalDivider(Modifier.padding(vertical = 8.dp))

            // Dictionary lookup
            Row(verticalAlignment = Alignment.CenterVertically) {
                OutlinedTextField(
                    value = lookupTerm,
                    onValueChange = { lookupTerm = it },
                    modifier = Modifier.weight(1f),
                    placeholder = { Text("Strong's / Dictionary...") },
                    singleLine = true,
                )
                TextButton(
                    onClick = {
                        if (lookupTerm.isNotBlank() && commentaryModules.isNotEmpty()) {
                            lookupResult = viewModel.lookupDictionary(
                                commentaryModules.first().key,
                                lookupTerm,
                            )
                        }
                    },
                ) { Text("Lookup") }
            }

            if (lookupResult.isNotBlank()) {
                Text(
                    text = lookupResult.take(500),
                    style = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.padding(top = 8.dp),
                )
            }
        }
    }
}
