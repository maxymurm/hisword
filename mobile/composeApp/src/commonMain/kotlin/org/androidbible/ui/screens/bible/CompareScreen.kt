package org.androidbible.ui.screens.bible

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
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
import org.androidbible.data.remote.ApiService
import org.androidbible.data.remote.CompareVersionData
import org.androidbible.domain.model.BibleVersion
import org.androidbible.domain.repository.BibleRepository
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

data class CompareScreenData(val ari: Int)

class CompareScreen(private val data: CompareScreenData) : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val screenModel = rememberScreenModel { CompareScreenModel(data.ari) }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        val (book, ch, vs) = Ari.decode(data.ari)
                        Text("Compare - Book $book $ch:$vs")
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                )
            }
        ) { padding ->
            if (state.isLoading) {
                Box(
                    modifier = Modifier.fillMaxSize().padding(padding),
                    contentAlignment = Alignment.Center,
                ) {
                    CircularProgressIndicator()
                }
            } else {
                LazyColumn(
                    modifier = Modifier.padding(padding).fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    items(state.comparisons) { comparison ->
                        Card(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text(
                                    text = comparison.versionName,
                                    style = MaterialTheme.typography.titleSmall,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = comparison.text,
                                    style = MaterialTheme.typography.bodyLarge,
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

data class CompareState(
    val comparisons: List<CompareVersionData> = emptyList(),
    val isLoading: Boolean = true,
)

class CompareScreenModel(private val ari: Int) : ScreenModel, KoinComponent {

    private val api: ApiService by inject()
    private val bibleRepo: BibleRepository by inject()

    private val _state = MutableStateFlow(CompareState())
    val state: StateFlow<CompareState> = _state.asStateFlow()

    init {
        loadComparisons()
    }

    private fun loadComparisons() {
        screenModelScope.launch {
            try {
                bibleRepo.getVersions().collect { versions ->
                    if (versions.isNotEmpty()) {
                        val versionIds = versions.take(5).map { it.id }
                        val response = api.compareVerses(ari, versionIds)
                        _state.value = CompareState(
                            comparisons = response.versions,
                            isLoading = false,
                        )
                    }
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(isLoading = false)
            }
        }
    }
}
