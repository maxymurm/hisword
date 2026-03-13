package org.androidbible.ui.screens.bible

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import cafe.adriel.voyager.core.screen.Screen
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.IO
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.androidbible.data.remote.ApiService
import org.androidbible.data.sword.SwordManager
import org.androidbible.data.sword.SwordVersification
import org.androidbible.domain.model.CrossReference
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

/**
 * CrossReferencePanel — displays cross-references for a selected verse.
 *
 * Data sources:
 * 1. Server API: CrossReference model from API (versionId + ARI)
 * 2. SWORD TSK: Treasury of Scripture Knowledge module (commentary-type)
 *
 * Shows all related verses with their text, allowing navigation.
 */
class CrossReferenceScreen(
    private val ari: Int,
    private val versionId: Long = 0,
) : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { CrossRefScreenModel() }
        val state by screenModel.state.collectAsState()

        LaunchedEffect(ari) {
            screenModel.load(ari, versionId)
        }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        Text("Cross References — ${Ari.referenceString(ari)}")
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) {
                            Text("<")
                        }
                    },
                )
            },
        ) { padding ->
            if (state.isLoading) {
                Box(
                    modifier = Modifier.fillMaxSize().padding(padding),
                    contentAlignment = Alignment.Center,
                ) {
                    CircularProgressIndicator()
                }
            } else if (state.entries.isEmpty() && state.tskContent.isNullOrBlank()) {
                Box(
                    modifier = Modifier.fillMaxSize().padding(padding),
                    contentAlignment = Alignment.Center,
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("No cross-references found")
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "This verse has no linked references",
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
                    // TSK (Treasury of Scripture Knowledge) content from SWORD
                    if (!state.tskContent.isNullOrBlank()) {
                        item {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                colors = CardDefaults.cardColors(
                                    containerColor = MaterialTheme.colorScheme.tertiaryContainer,
                                ),
                            ) {
                                Column(modifier = Modifier.padding(16.dp)) {
                                    Text(
                                        "Treasury of Scripture Knowledge",
                                        style = MaterialTheme.typography.titleSmall,
                                        fontWeight = FontWeight.Bold,
                                    )
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(
                                        state.tskContent!!,
                                        style = MaterialTheme.typography.bodyMedium,
                                    )
                                }
                            }
                        }
                    }

                    // API-based cross references
                    if (state.entries.isNotEmpty()) {
                        item {
                            Text(
                                "Related Verses (${state.entries.size})",
                                style = MaterialTheme.typography.titleSmall,
                                fontWeight = FontWeight.Bold,
                            )
                        }

                        items(state.entries) { entry ->
                            CrossRefCard(
                                entry = entry,
                                onClick = {
                                    navigator.push(BibleReaderScreen(initialAri = entry.toAri))
                                },
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun CrossRefCard(
    entry: CrossRefEntry,
    onClick: () -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Text(
                Ari.referenceString(entry.toAri),
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
            )
            if (entry.verseText.isNotBlank()) {
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    entry.verseText,
                    style = MaterialTheme.typography.bodyMedium,
                    maxLines = 3,
                )
            }
        }
    }
}

data class CrossRefEntry(
    val fromAri: Int,
    val toAri: Int,
    val verseText: String = "",
)

data class CrossRefState(
    val entries: List<CrossRefEntry> = emptyList(),
    val tskContent: String? = null,
    val isLoading: Boolean = false,
)

class CrossRefScreenModel : ScreenModel, KoinComponent {

    private val api: ApiService by inject()
    private val swordManager: SwordManager by inject()

    private val _state = MutableStateFlow(CrossRefState())
    val state: StateFlow<CrossRefState> = _state.asStateFlow()

    fun load(ari: Int, versionId: Long) {
        _state.value = CrossRefState(isLoading = true)
        screenModelScope.launch {
            withContext(Dispatchers.IO) {
                // 1. Load TSK from SWORD commentary modules
                val tskContent = loadTskContent(ari)

                // 2. Load API cross-references
                val apiRefs = loadApiCrossRefs(ari, versionId)

                _state.value = CrossRefState(
                    entries = apiRefs,
                    tskContent = tskContent,
                    isLoading = false,
                )
            }
        }
    }

    private fun loadTskContent(ari: Int): String? {
        // Look for TSK or cross-reference commentary modules
        val commentaryModules = swordManager.getCommentaryModules()
        val tskKeys = commentaryModules.keys.filter { key ->
            val lower = key.lowercase()
            lower.contains("tsk") || lower.contains("cross") || lower.contains("treasury")
        }

        val bookId = Ari.decodeBook(ari)
        val chapter = Ari.decodeChapter(ari)
        val verse = Ari.decodeVerse(ari)

        // Map bookId to OSIS ID
        val allBooks = SwordVersification.allBooks
        val bookIndex = bookId - 1
        if (bookIndex < 0 || bookIndex >= allBooks.size) return null
        val osisId = allBooks[bookIndex].osisId

        // Try TSK-specific modules first, then any commentary
        for (moduleKey in tskKeys) {
            val text = swordManager.readCommentary(moduleKey, osisId, chapter, verse)
            if (text.isNotBlank()) {
                return swordManager.filterMarkup(moduleKey, text)
            }
        }

        // Fallback: try all commentary modules
        for ((moduleKey, _) in commentaryModules) {
            if (moduleKey in tskKeys) continue
            val text = swordManager.readCommentary(moduleKey, osisId, chapter, verse)
            if (text.isNotBlank()) {
                return swordManager.filterMarkup(moduleKey, text)
            }
        }

        return null
    }

    private suspend fun loadApiCrossRefs(ari: Int, versionId: Long): List<CrossRefEntry> {
        if (versionId <= 0) return emptyList()
        return try {
            val refs = api.getCrossReferences(versionId, ari)
            refs.map { ref ->
                CrossRefEntry(
                    fromAri = ref.fromAri,
                    toAri = ref.toAri,
                )
            }
        } catch (_: Exception) {
            emptyList()
        }
    }
}
