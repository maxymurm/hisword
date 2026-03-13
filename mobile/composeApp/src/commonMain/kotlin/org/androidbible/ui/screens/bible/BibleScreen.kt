package org.androidbible.ui.screens.bible

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.screen.Screen
import org.androidbible.domain.model.Book
import org.androidbible.domain.model.Verse
import org.androidbible.ui.theme.*

class BibleScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val screenModel = rememberScreenModel { BibleScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            // Book + Chapter clickable
                            TextButton(onClick = { screenModel.toggleBookPicker() }) {
                                Text(
                                    text = state.currentBookName.ifEmpty { "Select Book" } +
                                        if (state.currentChapter > 0) " ${state.currentChapter}" else "",
                                    style = MaterialTheme.typography.titleMedium,
                                )
                            }
                            // Version badge
                            val versionName = state.versions
                                .find { it.id == state.currentVersionId }?.shortName ?: ""
                            if (versionName.isNotEmpty()) {
                                AssistChip(
                                    onClick = { screenModel.toggleVersionPicker() },
                                    label = { Text(versionName, style = MaterialTheme.typography.labelSmall) },
                                )
                            }
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                )
            },
            bottomBar = {
                if (state.verses.isNotEmpty()) {
                    ChapterNavigationBar(
                        currentChapter = state.currentChapter,
                        totalChapters = state.totalChapters,
                        onPrevious = { screenModel.previousChapter() },
                        onNext = { screenModel.nextChapter() },
                    )
                }
            },
        ) { padding ->
            Box(modifier = Modifier.padding(padding)) {
                when {
                    state.isLoading -> {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = Alignment.Center,
                        ) {
                            CircularProgressIndicator()
                        }
                    }
                    state.showBookPicker -> {
                        BookPickerContent(
                            books = state.books,
                            currentBookId = state.currentBookId,
                            totalChapters = state.totalChapters,
                            onBookSelected = { screenModel.selectBook(it) },
                            onChapterSelected = { screenModel.loadChapter(it) },
                        )
                    }
                    state.showVersionPicker -> {
                        VersionPickerContent(
                            versions = state.versions,
                            currentVersionId = state.currentVersionId,
                            onVersionSelected = { screenModel.selectVersion(it) },
                        )
                    }
                    state.verses.isNotEmpty() -> {
                        VerseList(
                            verses = state.verses,
                            highlightedVerses = state.highlightedVerses,
                            onVerseLongClick = { screenModel.selectVerse(it.ari) },
                        )
                    }
                    else -> {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = Alignment.Center,
                        ) {
                            Text(
                                "Select a Bible version to start reading",
                                style = MaterialTheme.typography.bodyLarge,
                            )
                        }
                    }
                }

                // Verse action bottom sheet
                if (state.showVerseActions) {
                    VerseActionSheet(
                        onDismiss = { screenModel.dismissVerseActions() },
                        onBookmark = { screenModel.bookmarkVerse() },
                        onHighlight = { screenModel.highlightVerse(it) },
                        onNote = { screenModel.addNote(it) },
                    )
                }
            }
        }
    }
}

@Composable
fun ChapterNavigationBar(
    currentChapter: Int,
    totalChapters: Int,
    onPrevious: () -> Unit,
    onNext: () -> Unit,
) {
    BottomAppBar(
        containerColor = MaterialTheme.colorScheme.surfaceContainer,
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            TextButton(
                onClick = onPrevious,
                enabled = currentChapter > 1,
            ) {
                Text("< Previous")
            }
            Text(
                "$currentChapter / $totalChapters",
                style = MaterialTheme.typography.bodyMedium,
            )
            TextButton(onClick = onNext) {
                Text("Next >")
            }
        }
    }
}

@Composable
fun BookPickerContent(
    books: List<Book>,
    currentBookId: Int,
    totalChapters: Int,
    onBookSelected: (Int) -> Unit,
    onChapterSelected: (Int) -> Unit,
) {
    var selectedBook by remember { mutableStateOf<Book?>(null) }

    Column(modifier = Modifier.fillMaxSize()) {
        if (selectedBook != null) {
            // Chapter grid
            Text(
                "${selectedBook?.longName}",
                style = MaterialTheme.typography.titleMedium,
                modifier = Modifier.padding(16.dp),
            )
            TextButton(
                onClick = { selectedBook = null },
                modifier = Modifier.padding(horizontal = 16.dp),
            ) {
                Text("< Back to Books")
            }
            LazyVerticalGrid(
                columns = GridCells.Fixed(5),
                contentPadding = PaddingValues(16.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                items((1..(selectedBook?.chapterCount ?: 1)).toList()) { ch ->
                    FilledTonalButton(
                        onClick = { onChapterSelected(ch) },
                        contentPadding = PaddingValues(4.dp),
                    ) {
                        Text("$ch")
                    }
                }
            }
        } else {
            // Book list
            Text(
                "Select Book",
                style = MaterialTheme.typography.titleMedium,
                modifier = Modifier.padding(16.dp),
            )
            // Old Testament
            Text(
                "Old Testament",
                style = MaterialTheme.typography.labelLarge,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            )
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(horizontal = 16.dp),
            ) {
                items(books) { book ->
                    ListItem(
                        headlineContent = { Text(book.longName) },
                        supportingContent = { Text("${book.chapterCount} chapters") },
                        modifier = Modifier.clickable {
                            if (book.chapterCount == 1) {
                                onBookSelected(book.bookId)
                            } else {
                                selectedBook = book
                            }
                        },
                        colors = if (book.bookId == currentBookId)
                            ListItemDefaults.colors(containerColor = MaterialTheme.colorScheme.primaryContainer)
                        else ListItemDefaults.colors(),
                    )
                }
            }
        }
    }
}

@Composable
fun VersionPickerContent(
    versions: List<org.androidbible.domain.model.BibleVersion>,
    currentVersionId: Long,
    onVersionSelected: (Long) -> Unit,
) {
    Column(modifier = Modifier.fillMaxSize()) {
        Text(
            "Select Version",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(16.dp),
        )
        LazyColumn(
            contentPadding = PaddingValues(horizontal = 16.dp),
        ) {
            items(versions) { version ->
                ListItem(
                    headlineContent = { Text(version.shortName) },
                    supportingContent = { Text(version.longName) },
                    modifier = Modifier.clickable { onVersionSelected(version.id) },
                    colors = if (version.id == currentVersionId)
                        ListItemDefaults.colors(containerColor = MaterialTheme.colorScheme.primaryContainer)
                    else ListItemDefaults.colors(),
                )
            }
        }
    }
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun VerseList(
    verses: List<Verse>,
    highlightedVerses: Map<Int, Int> = emptyMap(),
    modifier: Modifier = Modifier,
    onVerseLongClick: (Verse) -> Unit = {},
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
    ) {
        items(verses, key = { it.ari }) { verse ->
            val highlightColor = highlightedVerses[verse.ari]
            val bgColor = when (highlightColor) {
                1 -> HighlightYellow
                2 -> HighlightGreen
                3 -> HighlightBlue
                4 -> HighlightPink
                5 -> HighlightOrange
                6 -> HighlightPurple
                else -> Color.Transparent
            }

            VerseItem(
                verse = verse,
                backgroundColor = bgColor,
                onLongClick = { onVerseLongClick(verse) },
            )
        }
    }
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun VerseItem(
    verse: Verse,
    backgroundColor: Color = Color.Transparent,
    onLongClick: () -> Unit = {},
) {
    val annotatedText = buildAnnotatedString {
        withStyle(
            SpanStyle(
                fontWeight = FontWeight.Bold,
                fontSize = 12.sp,
                color = MaterialTheme.colorScheme.primary,
            )
        ) {
            append("${verse.verse} ")
        }
        append(verse.text)
    }

    Text(
        text = annotatedText,
        style = MaterialTheme.typography.bodyLarge.copy(
            lineHeight = 28.sp,
        ),
        modifier = Modifier
            .fillMaxWidth()
            .background(backgroundColor)
            .combinedClickable(
                onClick = {},
                onLongClick = onLongClick,
            )
            .padding(vertical = 2.dp, horizontal = 4.dp),
    )
}

@Composable
fun VerseActionSheet(
    onDismiss: () -> Unit,
    onBookmark: () -> Unit,
    onHighlight: (Int) -> Unit,
    onNote: (String) -> Unit,
) {
    var noteText by remember { mutableStateOf("") }
    var showNoteInput by remember { mutableStateOf(false) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Verse Actions") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                // Bookmark
                FilledTonalButton(
                    onClick = onBookmark,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text("Bookmark")
                }

                // Highlight colors
                Text("Highlight", style = MaterialTheme.typography.labelMedium)
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    val colors = listOf(
                        1 to HighlightYellow,
                        2 to HighlightGreen,
                        3 to HighlightBlue,
                        4 to HighlightPink,
                        5 to HighlightOrange,
                        6 to HighlightPurple,
                    )
                    colors.forEach { (index, color) ->
                        Box(
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .background(color)
                                .clickable { onHighlight(index) },
                        )
                    }
                }

                // Note
                if (showNoteInput) {
                    OutlinedTextField(
                        value = noteText,
                        onValueChange = { noteText = it },
                        label = { Text("Note") },
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 2,
                    )
                    Button(
                        onClick = { onNote(noteText) },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = noteText.isNotBlank(),
                    ) {
                        Text("Save Note")
                    }
                } else {
                    OutlinedButton(
                        onClick = { showNoteInput = true },
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Text("Add Note")
                    }
                }
            }
        },
        confirmButton = {},
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel") }
        },
    )
}
