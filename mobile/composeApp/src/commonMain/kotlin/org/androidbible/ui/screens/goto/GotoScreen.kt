package org.androidbible.ui.screens.goto

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.screen.Screen
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import org.androidbible.data.sword.SwordVersification
import org.androidbible.util.Ari

/**
 * Book/chapter/verse picker using ARI-based navigation.
 * Navigates back with a result ARI encoded as (bookId, chapter, verse).
 */
class GotoScreen(
    private val onNavigate: (bookId: Int, chapter: Int) -> Unit,
) : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        var selectedBookIndex by remember { mutableStateOf(-1) }
        var showOt by remember { mutableStateOf(true) }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Go to...") },
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) { Text("Back") }
                    },
                )
            },
        ) { padding ->
            Column(modifier = Modifier.padding(padding)) {
                if (selectedBookIndex >= 0) {
                    // Chapter picker
                    val book = SwordVersification.allBooks[selectedBookIndex]
                    val bookId = selectedBookIndex + 1

                    Row(
                        modifier = Modifier.fillMaxWidth().padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        TextButton(onClick = { selectedBookIndex = -1 }) {
                            Text("\u25C0 Books")
                        }
                        Text(
                            book.name,
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                        )
                    }

                    LazyVerticalGrid(
                        columns = GridCells.Fixed(6),
                        contentPadding = PaddingValues(16.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        items((1..book.chapterCount).toList()) { chapter ->
                            FilledTonalButton(
                                onClick = {
                                    onNavigate(bookId, chapter)
                                    navigator.pop()
                                },
                                contentPadding = PaddingValues(4.dp),
                            ) {
                                Text("$chapter")
                            }
                        }
                    }
                } else {
                    // Book picker with OT/NT tabs
                    TabRow(selectedTabIndex = if (showOt) 0 else 1) {
                        Tab(selected = showOt, onClick = { showOt = true }) {
                            Text("Old Testament", modifier = Modifier.padding(12.dp))
                        }
                        Tab(selected = !showOt, onClick = { showOt = false }) {
                            Text("New Testament", modifier = Modifier.padding(12.dp))
                        }
                    }

                    val books = if (showOt) SwordVersification.otBooks else SwordVersification.ntBooks
                    val indexOffset = if (showOt) 0 else SwordVersification.otBooks.size

                    LazyColumn(
                        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                    ) {
                        items(books.indices.toList()) { i ->
                            val book = books[i]
                            ListItem(
                                headlineContent = { Text(book.name) },
                                supportingContent = { Text("${book.chapterCount} chapters") },
                                trailingContent = { Text(book.abbrev, style = MaterialTheme.typography.labelSmall) },
                                modifier = Modifier.clickable {
                                    if (book.chapterCount == 1) {
                                        onNavigate(indexOffset + i + 1, 1)
                                        navigator.pop()
                                    } else {
                                        selectedBookIndex = indexOffset + i
                                    }
                                },
                            )
                        }
                    }
                }
            }
        }
    }
}
