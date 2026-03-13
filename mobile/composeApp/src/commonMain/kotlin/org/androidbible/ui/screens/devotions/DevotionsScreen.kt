package org.androidbible.ui.screens.devotions

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
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
import org.androidbible.domain.model.Devotional
import org.androidbible.domain.repository.DevotionalRepository
import org.androidbible.ui.screens.bible.BibleReaderScreen
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class DevotionsScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { DevotionsScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Daily Devotion") },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
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
            } else if (state.selectedDevotional != null) {
                DevotionalDetailView(
                    devotional = state.selectedDevotional!!,
                    onNavigateToVerse = { ari ->
                        navigator.push(BibleReaderScreen(initialAri = ari))
                    },
                    onBack = { screenModel.clearSelection() },
                    modifier = Modifier.padding(padding),
                )
            } else if (state.todayDevotional != null) {
                DevotionalDetailView(
                    devotional = state.todayDevotional!!,
                    onNavigateToVerse = { ari ->
                        navigator.push(BibleReaderScreen(initialAri = ari))
                    },
                    onBack = null,
                    showDateNav = true,
                    onPrevious = { screenModel.previousDay() },
                    onNext = { screenModel.nextDay() },
                    modifier = Modifier.padding(padding),
                )
            } else {
                // No devotionals available — show list if we have archived ones
                if (state.allDevotionals.isNotEmpty()) {
                    DevotionalListView(
                        devotionals = state.allDevotionals,
                        onSelect = { screenModel.select(it) },
                        modifier = Modifier.padding(padding),
                    )
                } else {
                    Box(
                        modifier = Modifier.fillMaxSize().padding(padding),
                        contentAlignment = Alignment.Center,
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                "No devotionals available",
                                style = MaterialTheme.typography.titleMedium,
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                "Devotionals will appear when synced from the server",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            FilledTonalButton(onClick = { screenModel.refresh() }) {
                                Text("Refresh")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun DevotionalDetailView(
    devotional: Devotional,
    onNavigateToVerse: (Int) -> Unit,
    onBack: (() -> Unit)?,
    showDateNav: Boolean = false,
    onPrevious: (() -> Unit)? = null,
    onNext: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
    ) {
        // Date navigation bar
        if (showDateNav) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                TextButton(
                    onClick = { onPrevious?.invoke() },
                    enabled = onPrevious != null,
                ) {
                    Text("< Previous")
                }
                Text(
                    devotional.publishDate,
                    style = MaterialTheme.typography.labelLarge,
                    color = MaterialTheme.colorScheme.primary,
                )
                TextButton(
                    onClick = { onNext?.invoke() },
                    enabled = onNext != null,
                ) {
                    Text("Next >")
                }
            }
            Spacer(modifier = Modifier.height(8.dp))
        }

        if (onBack != null) {
            TextButton(onClick = onBack) {
                Text("< Back to list")
            }
            Spacer(modifier = Modifier.height(8.dp))
        }

        // Title
        Text(
            text = devotional.title,
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
        )
        Spacer(modifier = Modifier.height(8.dp))

        // Author and date
        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            devotional.author?.let {
                Text(
                    "By $it",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Text(
                devotional.publishDate,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        // Scripture reference chip
        devotional.ariReference?.let { ari ->
            if (ari != 0) {
                Spacer(modifier = Modifier.height(12.dp))
                AssistChip(
                    onClick = { onNavigateToVerse(ari) },
                    label = { Text(Ari.referenceString(ari)) },
                )
            }
        }

        Spacer(modifier = Modifier.height(16.dp))
        HorizontalDivider()
        Spacer(modifier = Modifier.height(16.dp))

        // Body
        Text(
            text = devotional.body,
            style = MaterialTheme.typography.bodyLarge.copy(
                lineHeight = MaterialTheme.typography.bodyLarge.lineHeight * 1.4f,
            ),
        )
    }
}

@Composable
fun DevotionalListView(
    devotionals: List<Devotional>,
    onSelect: (Devotional) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(modifier = modifier.fillMaxSize()) {
        Text(
            "All Devotionals",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(16.dp),
        )

        devotionals.forEach { devo ->
            ListItem(
                headlineContent = { Text(devo.title) },
                supportingContent = {
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text(devo.publishDate)
                        devo.author?.let { Text("by $it") }
                    }
                },
                modifier = Modifier.padding(horizontal = 8.dp),
                colors = ListItemDefaults.colors(),
            )
            HorizontalDivider(modifier = Modifier.padding(horizontal = 16.dp))
        }
    }
}

data class DevotionsState(
    val todayDevotional: Devotional? = null,
    val selectedDevotional: Devotional? = null,
    val allDevotionals: List<Devotional> = emptyList(),
    val currentDateIndex: Int = -1,
    val isLoading: Boolean = false,
)

class DevotionsScreenModel : ScreenModel, KoinComponent {

    private val devotionalRepo: DevotionalRepository by inject()

    private val _state = MutableStateFlow(DevotionsState())
    val state: StateFlow<DevotionsState> = _state.asStateFlow()

    init {
        _state.value = _state.value.copy(isLoading = true)
        screenModelScope.launch {
            devotionalRepo.getDevotionals().collect { devotionals ->
                val sorted = devotionals.sortedByDescending { it.publishDate }
                val today = sorted.firstOrNull() // Most recent as "today"
                _state.value = _state.value.copy(
                    allDevotionals = sorted,
                    todayDevotional = today,
                    currentDateIndex = 0,
                    isLoading = false,
                )
            }
        }
    }

    fun select(devotional: Devotional) {
        _state.value = _state.value.copy(selectedDevotional = devotional)
    }

    fun clearSelection() {
        _state.value = _state.value.copy(selectedDevotional = null)
    }

    fun previousDay() {
        val s = _state.value
        val newIndex = s.currentDateIndex + 1
        if (newIndex < s.allDevotionals.size) {
            _state.value = s.copy(
                currentDateIndex = newIndex,
                todayDevotional = s.allDevotionals[newIndex],
            )
        }
    }

    fun nextDay() {
        val s = _state.value
        val newIndex = s.currentDateIndex - 1
        if (newIndex >= 0) {
            _state.value = s.copy(
                currentDateIndex = newIndex,
                todayDevotional = s.allDevotionals[newIndex],
            )
        }
    }

    fun refresh() {
        screenModelScope.launch {
            _state.value = _state.value.copy(isLoading = true)
            try {
                devotionalRepo.syncDevotionals()
            } catch (_: Exception) {
                // Ignore sync errors
            }
            _state.value = _state.value.copy(isLoading = false)
        }
    }
}
