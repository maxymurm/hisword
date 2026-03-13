package org.androidbible.ui.screens.study

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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import cafe.adriel.voyager.core.screen.Screen
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.IO
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.androidbible.data.sword.SwordManager
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

/**
 * WordStudyScreen — Strong's Hebrew/Greek word study panel.
 *
 * Looks up Strong's numbers (e.g., H1254 for Hebrew "bara", G26 for Greek "agape")
 * in installed SWORD lexicon/dictionary modules (RawLD4, ZLD).
 *
 * Features:
 * - Input a Strong's number (H or G prefix)
 * - Auto-detect available Strong's dictionary modules
 * - Display definition, etymology, and usage
 * - Show all Bible occurrences via extractStrongsNumbers scan
 * - Tap occurrence to navigate to that verse
 */
class WordStudyScreen(
    private val initialStrongsNumber: String = "",
) : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val screenModel = rememberScreenModel { WordStudyScreenModel() }
        val state by screenModel.state.collectAsState()

        LaunchedEffect(initialStrongsNumber) {
            if (initialStrongsNumber.isNotBlank()) {
                screenModel.lookup(initialStrongsNumber)
            }
        }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Word Study") },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                )
            },
        ) { padding ->
            Column(
                modifier = Modifier.padding(padding).fillMaxSize().padding(16.dp),
            ) {
                // Strong's number input
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    OutlinedTextField(
                        value = state.query,
                        onValueChange = { screenModel.setQuery(it) },
                        modifier = Modifier.weight(1f),
                        label = { Text("Strong's # (e.g., H1254 or G26)") },
                        singleLine = true,
                    )
                    FilledTonalButton(
                        onClick = { screenModel.lookup(state.query) },
                        enabled = state.query.isNotBlank() && !state.isLoading,
                    ) {
                        Text("Look up")
                    }
                }

                Spacer(modifier = Modifier.height(8.dp))

                // Available dictionary modules
                if (state.availableDictModules.isNotEmpty()) {
                    Text(
                        "Dictionaries: ${state.availableDictModules.joinToString()}",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                }

                if (state.isLoading) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center,
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            CircularProgressIndicator()
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                "Searching ${state.searchProgress}...",
                                style = MaterialTheme.typography.bodySmall,
                            )
                        }
                    }
                } else if (state.definition != null) {
                    // Definition result
                    SelectionContainer {
                        LazyColumn(
                            modifier = Modifier.fillMaxSize(),
                            verticalArrangement = Arrangement.spacedBy(12.dp),
                        ) {
                            // Strong's number header
                            item {
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    colors = CardDefaults.cardColors(
                                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                                    ),
                                ) {
                                    Column(modifier = Modifier.padding(16.dp)) {
                                        Text(
                                            state.query.uppercase(),
                                            style = MaterialTheme.typography.headlineSmall,
                                            fontWeight = FontWeight.Bold,
                                        )
                                        val lang = if (state.query.uppercase().startsWith("H"))
                                            "Hebrew" else "Greek"
                                        Text(
                                            lang,
                                            style = MaterialTheme.typography.labelMedium,
                                            color = MaterialTheme.colorScheme.onPrimaryContainer,
                                        )
                                    }
                                }
                            }

                            // Definition
                            item {
                                Text(
                                    "Definition",
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold,
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    state.definition!!,
                                    style = MaterialTheme.typography.bodyLarge,
                                )
                            }

                            // Occurrences
                            if (state.occurrences.isNotEmpty()) {
                                item {
                                    HorizontalDivider()
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(
                                        "Occurrences (${state.occurrences.size})",
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.Bold,
                                    )
                                }

                                items(state.occurrences.take(100)) { occ ->
                                    Card(
                                        modifier = Modifier.fillMaxWidth(),
                                    ) {
                                        Column(modifier = Modifier.padding(12.dp)) {
                                            Text(
                                                "${occ.bookName} ${occ.chapter}:${occ.verse}",
                                                style = MaterialTheme.typography.labelMedium,
                                                fontWeight = FontWeight.SemiBold,
                                                color = MaterialTheme.colorScheme.primary,
                                            )
                                            Spacer(modifier = Modifier.height(4.dp))
                                            Text(
                                                occ.text,
                                                style = MaterialTheme.typography.bodyMedium,
                                                maxLines = 3,
                                            )
                                        }
                                    }
                                }

                                if (state.occurrences.size > 100) {
                                    item {
                                        Text(
                                            "Showing first 100 of ${state.occurrences.size} occurrences",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                }
                            }
                        }
                    }
                } else if (state.error != null) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            state.error!!,
                            color = MaterialTheme.colorScheme.error,
                        )
                    }
                } else {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            "Enter a Strong's number to look up its definition",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }
    }
}

data class StrongsOccurrenceItem(
    val bookName: String,
    val chapter: Int,
    val verse: Int,
    val text: String,
    val ari: Int,
)

data class WordStudyState(
    val query: String = "",
    val definition: String? = null,
    val occurrences: List<StrongsOccurrenceItem> = emptyList(),
    val availableDictModules: List<String> = emptyList(),
    val isLoading: Boolean = false,
    val searchProgress: String = "",
    val error: String? = null,
)

class WordStudyScreenModel : ScreenModel, KoinComponent {

    private val swordManager: SwordManager by inject()

    private val _state = MutableStateFlow(WordStudyState())
    val state: StateFlow<WordStudyState> = _state.asStateFlow()

    init {
        val dictModules = swordManager.getDictionaryModules().keys.toList()
        _state.value = _state.value.copy(availableDictModules = dictModules)
    }

    fun setQuery(query: String) {
        _state.value = _state.value.copy(query = query.trim())
    }

    fun lookup(strongsNumber: String) {
        val num = strongsNumber.trim().uppercase()
        if (num.isBlank()) return
        if (!num.matches(Regex("^[HG]\\d+$"))) {
            _state.value = _state.value.copy(
                error = "Invalid format. Use H#### for Hebrew or G#### for Greek.",
            )
            return
        }

        _state.value = _state.value.copy(
            query = num,
            isLoading = true,
            error = null,
            definition = null,
            occurrences = emptyList(),
        )

        screenModelScope.launch {
            withContext(Dispatchers.IO) {
                // Look up definition in available dictionary modules
                var definition: String? = null
                for (moduleKey in _state.value.availableDictModules) {
                    val result = swordManager.lookupDictionary(moduleKey, num)
                    if (result.isNotBlank()) {
                        definition = swordManager.filterMarkup(moduleKey, result)
                        break
                    }
                    // Try without prefix
                    val numOnly = num.removePrefix("H").removePrefix("G")
                    val alt = swordManager.lookupDictionary(moduleKey, numOnly)
                    if (alt.isNotBlank()) {
                        definition = swordManager.filterMarkup(moduleKey, alt)
                        break
                    }
                }

                if (definition == null) {
                    _state.value = _state.value.copy(
                        isLoading = false,
                        error = "No definition found for $num. " +
                            "Install a Strong's dictionary module (e.g., StrongsRealGreek, StrongsRealHebrew).",
                    )
                    return@withContext
                }

                _state.value = _state.value.copy(definition = definition, searchProgress = "definitions")

                // Search for occurrences in Bible text modules
                val bibleModules = swordManager.getBibleModules()
                val firstBibleKey = bibleModules.keys.firstOrNull()
                if (firstBibleKey != null) {
                    _state.value = _state.value.copy(searchProgress = "occurrences")
                    val occurrences = swordManager.searchStrongsOccurrences(firstBibleKey, num)
                    val items = occurrences.map { occ ->
                        val bookDef = swordManager.getBooks().firstOrNull { it.osisId == occ.bookOsisId }
                        val bookId = bookDef?.let { swordManager.getBooks().indexOf(it) + 1 } ?: 0
                        StrongsOccurrenceItem(
                            bookName = occ.bookName,
                            chapter = occ.chapter,
                            verse = occ.verse,
                            text = occ.text,
                            ari = Ari.encode(bookId, occ.chapter, occ.verse),
                        )
                    }
                    _state.value = _state.value.copy(occurrences = items)
                }

                _state.value = _state.value.copy(isLoading = false)
            }
        }
    }
}
