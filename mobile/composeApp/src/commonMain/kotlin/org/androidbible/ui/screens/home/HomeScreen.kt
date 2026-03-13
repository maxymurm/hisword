package org.androidbible.ui.screens.home

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.style.TextAlign
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
import org.androidbible.data.remote.ApiService
import org.androidbible.data.remote.VerseOfTheDay
import org.androidbible.data.sync.SyncManager
import org.androidbible.domain.repository.AuthRepository
import org.androidbible.ui.screens.bible.BibleScreen
import org.androidbible.ui.screens.markers.BookmarksScreen
import org.androidbible.ui.screens.readingplan.ReadingPlanScreen
import org.androidbible.ui.screens.search.SearchScreen
import org.androidbible.ui.screens.settings.SettingsScreen
import org.androidbible.ui.screens.songs.SongsScreen
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class HomeScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { HomeScreenModel() }
        val state by screenModel.state.collectAsState()
        var selectedTab by remember { mutableStateOf(0) }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Android Bible") },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                        titleContentColor = MaterialTheme.colorScheme.onPrimaryContainer,
                    ),
                )
            },
            bottomBar = {
                NavigationBar {
                    NavigationBarItem(
                        icon = { Text("\uD83D\uDCD6") },
                        label = { Text("Home") },
                        selected = selectedTab == 0,
                        onClick = { selectedTab = 0 },
                    )
                    NavigationBarItem(
                        icon = { Text("\uD83D\uDCD6") },
                        label = { Text("Bible") },
                        selected = selectedTab == 1,
                        onClick = { selectedTab = 1; navigator.push(BibleScreen()) },
                    )
                    NavigationBarItem(
                        icon = { Text("\uD83D\uDD16") },
                        label = { Text("Bookmarks") },
                        selected = selectedTab == 2,
                        onClick = { selectedTab = 2; navigator.push(BookmarksScreen()) },
                    )
                    NavigationBarItem(
                        icon = { Text("\uD83D\uDD0D") },
                        label = { Text("Search") },
                        selected = selectedTab == 3,
                        onClick = { selectedTab = 3; navigator.push(SearchScreen()) },
                    )
                    NavigationBarItem(
                        icon = { Text("\u2699") },
                        label = { Text("Settings") },
                        selected = selectedTab == 4,
                        onClick = { selectedTab = 4; navigator.push(SettingsScreen()) },
                    )
                }
            },
        ) { innerPadding ->
            Column(
                modifier = Modifier
                    .padding(innerPadding)
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                // Verse of the Day Card
                VerseOfTheDayCard(
                    votd = state.verseOfTheDay,
                    isLoading = state.isLoadingVotd,
                )

                // Quick Action Cards
                Text(
                    "Quick Access",
                    style = MaterialTheme.typography.titleMedium,
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    QuickActionCard(
                        title = "Continue\nReading",
                        subtitle = "Pick up where\nyou left off",
                        modifier = Modifier.weight(1f),
                        onClick = { navigator.push(BibleScreen()) },
                    )
                    QuickActionCard(
                        title = "Reading\nPlans",
                        subtitle = "Stay on track",
                        modifier = Modifier.weight(1f),
                        onClick = { navigator.push(ReadingPlanScreen()) },
                    )
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    QuickActionCard(
                        title = "Songs &\nHymns",
                        subtitle = "Browse hymnal",
                        modifier = Modifier.weight(1f),
                        onClick = { navigator.push(SongsScreen()) },
                    )
                    QuickActionCard(
                        title = "My\nBookmarks",
                        subtitle = "${state.bookmarkCount} saved",
                        modifier = Modifier.weight(1f),
                        onClick = { navigator.push(BookmarksScreen()) },
                    )
                }

                // Sync status
                if (state.pendingSyncItems > 0) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.tertiaryContainer,
                        ),
                    ) {
                        Row(
                            modifier = Modifier.padding(16.dp).fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Column {
                                Text(
                                    "Sync Pending",
                                    style = MaterialTheme.typography.labelLarge,
                                )
                                Text(
                                    "${state.pendingSyncItems} items to sync",
                                    style = MaterialTheme.typography.bodySmall,
                                )
                            }
                            FilledTonalButton(onClick = { screenModel.syncNow() }) {
                                Text("Sync Now")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun VerseOfTheDayCard(
    votd: VerseOfTheDay?,
    isLoading: Boolean,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer,
        ),
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                "Verse of the Day",
                style = MaterialTheme.typography.labelLarge,
                color = MaterialTheme.colorScheme.onPrimaryContainer,
            )
            Spacer(modifier = Modifier.height(12.dp))

            if (isLoading) {
                CircularProgressIndicator(modifier = Modifier.size(24.dp))
            } else if (votd != null) {
                Text(
                    text = "\u201C${votd.text}\u201D",
                    style = MaterialTheme.typography.bodyLarge,
                    fontStyle = FontStyle.Italic,
                    textAlign = TextAlign.Center,
                    color = MaterialTheme.colorScheme.onPrimaryContainer,
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "\u2014 ${votd.reference}",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.8f),
                )
            } else {
                Text(
                    "\u201CFor God so loved the world that he gave his one and only Son, " +
                        "that whoever believes in him shall not perish but have eternal life.\u201D",
                    style = MaterialTheme.typography.bodyLarge,
                    fontStyle = FontStyle.Italic,
                    textAlign = TextAlign.Center,
                    color = MaterialTheme.colorScheme.onPrimaryContainer,
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    "\u2014 John 3:16",
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.8f),
                )
            }
        }
    }
}

@Composable
fun QuickActionCard(
    title: String,
    subtitle: String,
    modifier: Modifier = Modifier,
    onClick: () -> Unit = {},
) {
    Card(
        modifier = modifier.clickable(onClick = onClick),
    ) {
        Column(
            modifier = Modifier.padding(16.dp).fillMaxWidth(),
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleSmall,
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = subtitle,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

data class HomeState(
    val verseOfTheDay: VerseOfTheDay? = null,
    val isLoadingVotd: Boolean = true,
    val bookmarkCount: Int = 0,
    val pendingSyncItems: Int = 0,
    val isLoggedIn: Boolean = false,
)

class HomeScreenModel : ScreenModel, KoinComponent {

    private val api: ApiService by inject()
    private val authRepo: AuthRepository by inject()
    private val syncManager: SyncManager by inject()

    private val _state = MutableStateFlow(HomeState())
    val state: StateFlow<HomeState> = _state.asStateFlow()

    init {
        loadVerseOfTheDay()
        observeSync()
        observeAuth()
    }

    private fun loadVerseOfTheDay() {
        screenModelScope.launch {
            try {
                val votd = api.getVerseOfTheDay()
                _state.value = _state.value.copy(
                    verseOfTheDay = votd,
                    isLoadingVotd = false,
                )
            } catch (_: Exception) {
                _state.value = _state.value.copy(isLoadingVotd = false)
            }
        }
    }

    private fun observeSync() {
        screenModelScope.launch {
            syncManager.pendingCount.collect { count ->
                _state.value = _state.value.copy(pendingSyncItems = count)
            }
        }
    }

    private fun observeAuth() {
        screenModelScope.launch {
            authRepo.isLoggedIn().collect { loggedIn ->
                _state.value = _state.value.copy(isLoggedIn = loggedIn)
            }
        }
    }

    fun syncNow() {
        screenModelScope.launch {
            syncManager.fullSync()
        }
    }
}
