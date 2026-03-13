package org.androidbible.ui.screens.songs

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
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
import org.androidbible.data.sword.SwordManager
import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.domain.model.Song
import org.androidbible.domain.model.SongBook
import org.androidbible.domain.repository.SongRepository
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class SongsScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { SongsScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        Text(
                            state.currentSong?.title
                                ?: state.currentBookTitle
                                ?: "Song Books",
                        )
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                    navigationIcon = {
                        if (state.currentSong != null || state.currentBookId != null) {
                            TextButton(onClick = { screenModel.goBack() }) {
                                Text("<")
                            }
                        }
                    },
                )
            }
        ) { padding ->
            Column(modifier = Modifier.padding(padding)) {
                // Search bar (always visible)
                OutlinedTextField(
                    value = state.searchQuery,
                    onValueChange = { screenModel.search(it) },
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                    placeholder = { Text("Search songs...") },
                    singleLine = true,
                )

                if (state.isLoading) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center,
                    ) { CircularProgressIndicator() }
                } else if (state.currentSong != null) {
                    // Song detail with lyrics
                    SongDetailContent(song = state.currentSong!!)
                } else if (state.currentSwordModuleKey != null && state.swordContent != null) {
                    // SWORD module content viewer
                    Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .verticalScroll(rememberScrollState())
                            .padding(16.dp),
                    ) {
                        Text(
                            text = state.swordContent!!,
                            style = MaterialTheme.typography.bodyLarge,
                        )
                    }
                } else if (state.searchResults.isNotEmpty()) {
                    // Search results
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        items(state.searchResults) { song ->
                            ListItem(
                                headlineContent = { Text("${song.number}. ${song.title}") },
                                supportingContent = { song.author?.let { Text(it) } },
                                modifier = Modifier.clickable {
                                    screenModel.selectSong(song)
                                },
                            )
                        }
                    }
                } else if (state.currentBookId != null) {
                    // Songs in selected book
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(horizontal = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                    ) {
                        items(state.songs) { song ->
                            ListItem(
                                headlineContent = { Text("${song.number}. ${song.title}") },
                                supportingContent = {
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        song.author?.let {
                                            Text(it, style = MaterialTheme.typography.bodySmall)
                                        }
                                        song.key?.let {
                                            Text(
                                                "Key: $it",
                                                style = MaterialTheme.typography.bodySmall,
                                                color = MaterialTheme.colorScheme.primary,
                                            )
                                        }
                                    }
                                },
                                modifier = Modifier.clickable {
                                    screenModel.selectSong(song)
                                },
                            )
                        }
                    }
                } else {
                    // Song books + SWORD modules
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        // API-backed song books
                        if (state.songBooks.isNotEmpty()) {
                            item {
                                Text(
                                    "Song Books",
                                    style = MaterialTheme.typography.titleSmall,
                                    modifier = Modifier.padding(bottom = 4.dp),
                                )
                            }
                        }
                        items(state.songBooks) { book ->
                            Card(
                                modifier = Modifier.fillMaxWidth().clickable {
                                    screenModel.selectBook(book)
                                },
                            ) {
                                Column(modifier = Modifier.padding(16.dp)) {
                                    Text(book.title, style = MaterialTheme.typography.titleMedium)
                                    book.description?.let {
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            it,
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                }
                            }
                        }
                        // SWORD-based GenBook modules (hymns, etc.)
                        if (state.swordSongModules.isNotEmpty()) {
                            item {
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    "Local SWORD Modules",
                                    style = MaterialTheme.typography.titleSmall,
                                    modifier = Modifier.padding(bottom = 4.dp),
                                )
                            }
                            items(state.swordSongModules) { mod ->
                                Card(
                                    modifier = Modifier.fillMaxWidth().clickable {
                                        screenModel.selectSwordModule(mod)
                                    },
                                    colors = CardDefaults.cardColors(
                                        containerColor = MaterialTheme.colorScheme.secondaryContainer,
                                    ),
                                ) {
                                    Column(modifier = Modifier.padding(16.dp)) {
                                        Text(mod.description, style = MaterialTheme.typography.titleMedium)
                                        Text(
                                            "SWORD \u2022 ${mod.language}",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSecondaryContainer,
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun SongDetailContent(song: Song) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
    ) {
        // Song header
        Text(
            text = "${song.number}. ${song.title}",
            style = MaterialTheme.typography.headlineSmall,
        )
        Spacer(modifier = Modifier.height(8.dp))

        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            song.author?.let {
                Text(
                    "By $it",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            song.tune?.let {
                Text(
                    "Tune: $it",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            song.key?.let {
                AssistChip(
                    onClick = {},
                    label = { Text("Key: $it") },
                )
            }
        }

        Spacer(modifier = Modifier.height(16.dp))
        HorizontalDivider()
        Spacer(modifier = Modifier.height(16.dp))

        // Lyrics
        Text(
            text = song.lyrics,
            style = MaterialTheme.typography.bodyLarge.copy(
                lineHeight = MaterialTheme.typography.bodyLarge.lineHeight * 1.3f,
            ),
        )
    }
}

data class SongsState(
    val songBooks: List<SongBook> = emptyList(),
    val songs: List<Song> = emptyList(),
    val currentBookId: Long? = null,
    val currentBookTitle: String? = null,
    val currentSong: Song? = null,
    val searchQuery: String = "",
    val searchResults: List<Song> = emptyList(),
    val isLoading: Boolean = false,
    // SWORD-based hymn/song modules
    val swordSongModules: List<SwordSongModule> = emptyList(),
    val currentSwordModuleKey: String? = null,
    val swordContent: String? = null,
)

data class SwordSongModule(
    val key: String,
    val description: String,
    val language: String,
)

class SongsScreenModel : ScreenModel, KoinComponent {

    private val songRepo: SongRepository by inject()
    private val swordManager: SwordManager by inject()

    private val _state = MutableStateFlow(SongsState())
    val state: StateFlow<SongsState> = _state.asStateFlow()

    init {
        _state.value = _state.value.copy(isLoading = true)
        screenModelScope.launch {
            songRepo.getSongBooks().collect { books ->
                _state.value = _state.value.copy(songBooks = books, isLoading = false)
            }
        }
        // Load SWORD GenBook modules that could be hymn/song books
        loadSwordSongModules()
    }

    private fun loadSwordSongModules() {
        val genBooks = swordManager.getGenBookModules()
        val songModules = genBooks.map { (key, config) ->
            SwordSongModule(
                key = key,
                description = config.description.ifBlank { config.moduleName },
                language = config.language,
            )
        }
        _state.value = _state.value.copy(swordSongModules = songModules)
    }

    fun selectBook(book: SongBook) {
        _state.value = _state.value.copy(
            currentBookId = book.id,
            currentBookTitle = book.title,
            currentSong = null,
            currentSwordModuleKey = null,
            swordContent = null,
            isLoading = true,
        )
        screenModelScope.launch {
            songRepo.getSongs(book.id).collect { songs ->
                _state.value = _state.value.copy(songs = songs, isLoading = false)
            }
        }
    }

    fun selectSwordModule(module: SwordSongModule) {
        val content = swordManager.lookupDictionary(module.key, "")
        _state.value = _state.value.copy(
            currentSwordModuleKey = module.key,
            currentBookTitle = module.description,
            swordContent = content,
            currentBookId = null,
            currentSong = null,
        )
    }

    fun selectSong(song: Song) {
        _state.value = _state.value.copy(currentSong = song)
    }

    fun goBack() {
        val s = _state.value
        when {
            s.currentSong != null -> _state.value = s.copy(currentSong = null)
            s.currentBookId != null -> _state.value = s.copy(
                currentBookId = null,
                currentBookTitle = null,
                songs = emptyList(),
            )
            s.currentSwordModuleKey != null -> _state.value = s.copy(
                currentSwordModuleKey = null,
                currentBookTitle = null,
                swordContent = null,
            )
        }
    }

    fun search(query: String) {
        _state.value = _state.value.copy(searchQuery = query)
        if (query.length >= 2) {
            screenModelScope.launch {
                try {
                    val results = songRepo.searchSongs(query)
                    _state.value = _state.value.copy(searchResults = results)
                } catch (_: Exception) {
                    // Ignore search errors
                }
            }
        } else {
            _state.value = _state.value.copy(searchResults = emptyList())
        }
    }
}
