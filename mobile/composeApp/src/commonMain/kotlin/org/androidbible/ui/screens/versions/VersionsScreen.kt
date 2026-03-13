package org.androidbible.ui.screens.versions

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
import org.androidbible.domain.repository.BibleVersionRepository
import org.androidbible.domain.repository.ModuleInfo
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class VersionsScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { VersionsScreenModel() }
        val state by screenModel.state.collectAsState()

        LaunchedEffect(Unit) { screenModel.loadModules() }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Bible Versions") },
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) {
                            Text("Back")
                        }
                    },
                )
            },
        ) { padding ->
            when {
                state.isLoading -> {
                    Box(
                        modifier = Modifier.fillMaxSize().padding(padding),
                        contentAlignment = Alignment.Center,
                    ) {
                        CircularProgressIndicator()
                    }
                }
                state.modules.isEmpty() -> {
                    Box(
                        modifier = Modifier.fillMaxSize().padding(padding),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text("No modules installed", style = MaterialTheme.typography.bodyLarge)
                    }
                }
                else -> {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize().padding(padding),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        items(state.modules, key = { it.key }) { module ->
                            ModuleCard(
                                module = module,
                                onClick = { screenModel.selectModule(module) },
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ModuleCard(module: ModuleInfo, onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = module.name,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                )
                if (module.description.isNotBlank()) {
                    Text(
                        text = module.description.take(80),
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Spacer(Modifier.height(4.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(
                        text = module.language.uppercase(),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    if (module.hasOT) {
                        Text("OT", style = MaterialTheme.typography.labelSmall)
                    }
                    if (module.hasNT) {
                        Text("NT", style = MaterialTheme.typography.labelSmall)
                    }
                }
            }
            Spacer(Modifier.width(12.dp))
            EngineBadge(engine = module.engine)
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
            text = label,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Bold,
            color = color,
        )
    }
}

data class VersionsUiState(
    val modules: List<ModuleInfo> = emptyList(),
    val isLoading: Boolean = false,
    val selectedModule: ModuleInfo? = null,
)

class VersionsScreenModel : ScreenModel, KoinComponent {
    private val repo: BibleVersionRepository by inject()

    private val _state = MutableStateFlow(VersionsUiState())
    val state: StateFlow<VersionsUiState> = _state.asStateFlow()

    fun loadModules() {
        screenModelScope.launch {
            _state.value = _state.value.copy(isLoading = true)
            repo.refreshModules()
            repo.getInstalledModules().collect { modules ->
                _state.value = _state.value.copy(modules = modules, isLoading = false)
            }
        }
    }

    fun selectModule(module: ModuleInfo) {
        _state.value = _state.value.copy(selectedModule = module)
    }
}
