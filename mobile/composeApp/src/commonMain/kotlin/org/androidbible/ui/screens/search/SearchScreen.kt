package org.androidbible.ui.screens.search

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import cafe.adriel.voyager.core.screen.Screen
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.androidbible.domain.model.SearchResult
import org.androidbible.domain.repository.BibleRepository
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class SearchScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val screenModel = rememberScreenModel { SearchScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Search") },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                )
            }
        ) { padding ->
            Column(modifier = Modifier.padding(padding).fillMaxSize()) {
                // Search input
                OutlinedTextField(
                    value = state.query,
                    onValueChange = { screenModel.updateQuery(it) },
                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                    placeholder = { Text("Search the Bible...") },
                    singleLine = true,
                )

                // Results
                when {
                    state.isSearching -> {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = androidx.compose.ui.Alignment.Center,
                        ) {
                            CircularProgressIndicator()
                        }
                    }
                    state.results.isNotEmpty() -> {
                        Text(
                            "${state.results.size} results",
                            modifier = Modifier.padding(horizontal = 16.dp),
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        LazyColumn(
                            modifier = Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                        ) {
                            items(state.results) { result ->
                                SearchResultCard(result)
                            }
                        }
                    }
                    state.query.isNotEmpty() && !state.isSearching -> {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = androidx.compose.ui.Alignment.Center,
                        ) {
                            Text("No results found")
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun SearchResultCard(result: SearchResult) {
    val chapter = Ari.decodeChapter(result.verse.ari)
    val verse = Ari.decodeVerse(result.verse.ari)

    Card(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(12.dp)) {
            Text(
                text = "${result.bookName} $chapter:$verse",
                style = MaterialTheme.typography.titleSmall,
                color = MaterialTheme.colorScheme.primary,
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = result.verse.text,
                style = MaterialTheme.typography.bodyMedium,
                maxLines = 3,
            )
        }
    }
}

data class SearchState(
    val query: String = "",
    val results: List<SearchResult> = emptyList(),
    val isSearching: Boolean = false,
    val versionId: Long = 1,
)

class SearchScreenModel : ScreenModel, KoinComponent {

    private val bibleRepo: BibleRepository by inject()

    private val _state = MutableStateFlow(SearchState())
    val state: StateFlow<SearchState> = _state.asStateFlow()

    fun updateQuery(query: String) {
        _state.value = _state.value.copy(query = query)
        if (query.length >= 3) {
            search(query)
        }
    }

    private fun search(query: String) {
        _state.value = _state.value.copy(isSearching = true)
        screenModelScope.launch {
            try {
                val results = bibleRepo.searchVerses(_state.value.versionId, query)
                _state.value = _state.value.copy(results = results, isSearching = false)
            } catch (e: Exception) {
                _state.value = _state.value.copy(isSearching = false)
            }
        }
    }
}
