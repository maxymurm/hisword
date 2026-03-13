package org.androidbible.ui.screens.bible

import androidx.compose.foundation.ExperimentalFoundationApi
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
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.androidbible.domain.model.Verse
import org.androidbible.domain.repository.BibleReaderFactory
import org.androidbible.domain.repository.BibleVersionRepository
import org.androidbible.domain.repository.ModuleInfo
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

/**
 * Dual-pane split reader: shows same chapter from two different modules side by side.
 */
class SplitReaderScreen(
    private val bookId: Int = 1,
    private val chapter: Int = 1,
) : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val viewModel = rememberScreenModel { SplitReaderViewModel() }
        val state by viewModel.state.collectAsState()

        LaunchedEffect(Unit) {
            viewModel.init(bookId, chapter)
        }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        Text(
                            "${BibleReaderViewModel.getBookName(state.bookId)} ${state.chapter}",
                            style = MaterialTheme.typography.titleMedium,
                        )
                    },
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) { Text("Back") }
                    },
                )
            },
        ) { padding ->
            Row(modifier = Modifier.padding(padding).fillMaxSize()) {
                // Left pane
                SplitPane(
                    modifier = Modifier.weight(1f),
                    verses = state.leftVerses,
                    moduleName = state.leftModuleName,
                    modules = state.modules,
                    onModuleSelect = { viewModel.selectLeftModule(it) },
                )

                VerticalDivider()

                // Right pane
                SplitPane(
                    modifier = Modifier.weight(1f),
                    verses = state.rightVerses,
                    moduleName = state.rightModuleName,
                    modules = state.modules,
                    onModuleSelect = { viewModel.selectRightModule(it) },
                )
            }
        }
    }
}

@Composable
private fun SplitPane(
    modifier: Modifier,
    verses: List<Verse>,
    moduleName: String,
    modules: List<ModuleInfo>,
    onModuleSelect: (ModuleInfo) -> Unit,
) {
    var showPicker by remember { mutableStateOf(false) }

    Column(modifier = modifier) {
        // Module selector
        AssistChip(
            onClick = { showPicker = !showPicker },
            label = { Text(moduleName.ifEmpty { "Select" }) },
            modifier = Modifier.padding(8.dp),
        )

        if (showPicker) {
            LazyColumn(modifier = Modifier.fillMaxWidth().heightIn(max = 200.dp)) {
                items(modules) { mod ->
                    ListItem(
                        headlineContent = { Text(mod.name) },
                        modifier = Modifier.fillMaxWidth().run {
                            androidx.compose.foundation.clickable {
                                onModuleSelect(mod)
                                showPicker = false
                            }
                            this
                        },
                    )
                }
            }
        }

        // Verses
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp),
        ) {
            items(verses, key = { it.ari }) { verse ->
                VerseItem(verse = verse)
            }
        }
    }
}

data class SplitState(
    val bookId: Int = 1,
    val chapter: Int = 1,
    val leftModuleKey: String = "",
    val leftModuleName: String = "",
    val leftEngine: String = "sword",
    val leftVerses: List<Verse> = emptyList(),
    val rightModuleKey: String = "",
    val rightModuleName: String = "",
    val rightEngine: String = "sword",
    val rightVerses: List<Verse> = emptyList(),
    val modules: List<ModuleInfo> = emptyList(),
)

class SplitReaderViewModel : ScreenModel, KoinComponent {
    private val readerFactory: BibleReaderFactory by inject()
    private val versionRepo: BibleVersionRepository by inject()

    private val _state = MutableStateFlow(SplitState())
    val state: StateFlow<SplitState> = _state.asStateFlow()

    fun init(bookId: Int, chapter: Int) {
        _state.value = _state.value.copy(bookId = bookId, chapter = chapter)
        screenModelScope.launch {
            versionRepo.refreshModules()
            versionRepo.getInstalledModules().collect { modules ->
                _state.value = _state.value.copy(modules = modules)
                if (modules.size >= 2) {
                    selectLeftModule(modules[0])
                    selectRightModule(modules[1])
                } else if (modules.size == 1) {
                    selectLeftModule(modules[0])
                }
            }
        }
    }

    fun selectLeftModule(module: ModuleInfo) {
        _state.value = _state.value.copy(
            leftModuleKey = module.key,
            leftModuleName = module.name,
            leftEngine = module.engine,
        )
        loadPane("left", module)
    }

    fun selectRightModule(module: ModuleInfo) {
        _state.value = _state.value.copy(
            rightModuleKey = module.key,
            rightModuleName = module.name,
            rightEngine = module.engine,
        )
        loadPane("right", module)
    }

    private fun loadPane(side: String, module: ModuleInfo) {
        val s = _state.value
        screenModelScope.launch {
            val reader = readerFactory.readerFor(module.engine)
            val verses = reader.readChapter(module.key, s.bookId, s.chapter)
            if (side == "left") {
                _state.value = _state.value.copy(leftVerses = verses)
            } else {
                _state.value = _state.value.copy(rightVerses = verses)
            }
        }
    }
}
