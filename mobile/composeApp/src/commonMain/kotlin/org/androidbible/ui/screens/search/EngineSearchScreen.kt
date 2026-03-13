package org.androidbible.ui.screens.search

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
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
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.androidbible.domain.repository.BibleReaderFactory
import org.androidbible.domain.repository.BibleVersionRepository
import org.androidbible.domain.repository.ModuleInfo
import org.androidbible.domain.usecase.SearchUseCase
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

/**
 * Engine-aware search screen: searches across SWORD + Bintex modules in parallel.
 * Results tagged with engine badge.
 */
class EngineSearchScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val viewModel = rememberScreenModel { EngineSearchViewModel() }
        val state by viewModel.state.collectAsState()

        LaunchedEffect(Unit) { viewModel.loadModules() }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Search Bible") },
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) { Text("Back") }
                    },
                )
            },
        ) { padding ->
            Column(modifier = Modifier.padding(padding).fillMaxSize()) {
                OutlinedTextField(
                    value = state.query,
                    onValueChange = { viewModel.updateQuery(it) },
                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                    placeholder = { Text("Search across all modules...") },
                    singleLine = true,
                )

                when {
                    state.isSearching -> {
                        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator()
                        }
                    }
                    state.results.isNotEmpty() -> {
                        Text(
                            "${state.results.size} results",
                            modifier = Modifier.padding(horizontal = 16.dp),
                            style = MaterialTheme.typography.labelMedium,
                        )
                        LazyColumn(
                            modifier = Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            items(state.results) { item ->
                                EngineSearchResultCard(
                                    bookName = item.result.bookName,
                                    chapter = Ari.decodeChapter(item.result.verse.ari),
                                    verse = Ari.decodeVerse(item.result.verse.ari),
                                    text = item.result.verse.textWithoutFormatting ?: item.result.verse.text,
                                    engine = item.engine,
                                    moduleKey = item.moduleKey,
                                )
                            }
                        }
                    }
                    state.query.length >= 3 && !state.isSearching -> {
                        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            Text("No results found")
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun EngineSearchResultCard(
    bookName: String,
    chapter: Int,
    verse: Int,
    text: String,
    engine: String,
    moduleKey: String,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    "$bookName $chapter:$verse",
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.primary,
                )
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    EngineBadge(engine)
                    Text(
                        moduleKey.uppercase(),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            Spacer(Modifier.height(4.dp))
            Text(text, style = MaterialTheme.typography.bodyMedium, maxLines = 3)
        }
    }
}

@Composable
private fun EngineBadge(engine: String) {
    val (label, color) = when (engine) {
        "sword" -> "SWORD" to MaterialTheme.colorScheme.primary
        "bintex" -> "YES2" to MaterialTheme.colorScheme.tertiary
        else -> engine.uppercase() to MaterialTheme.colorScheme.secondary
    }
    Surface(
        shape = RoundedCornerShape(4.dp),
        color = color.copy(alpha = 0.15f),
    ) {
        Text(
            label,
            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
            color = color,
        )
    }
}

data class EngineSearchState(
    val query: String = "",
    val results: List<SearchUseCase.EngineSearchResult> = emptyList(),
    val isSearching: Boolean = false,
    val modules: List<ModuleInfo> = emptyList(),
)

class EngineSearchViewModel : ScreenModel, KoinComponent {
    private val readerFactory: BibleReaderFactory by inject()
    private val versionRepo: BibleVersionRepository by inject()

    private val searchUseCase = SearchUseCase(readerFactory)
    private val _state = MutableStateFlow(EngineSearchState())
    val state: StateFlow<EngineSearchState> = _state.asStateFlow()

    fun loadModules() {
        screenModelScope.launch {
            versionRepo.refreshModules()
            versionRepo.getInstalledModules().collect { modules ->
                _state.value = _state.value.copy(modules = modules)
            }
        }
    }

    fun updateQuery(query: String) {
        _state.value = _state.value.copy(query = query)
        if (query.length >= 3) search(query)
    }

    private fun search(query: String) {
        _state.value = _state.value.copy(isSearching = true)
        screenModelScope.launch {
            val moduleArgs = _state.value.modules.map { it.key to it.engine }
            val results = searchUseCase.search(moduleArgs, query, maxPerModule = 50)
            _state.value = _state.value.copy(results = results, isSearching = false)
        }
    }
}
