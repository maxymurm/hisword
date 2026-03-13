package org.androidbible.ui.screens.settings

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
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
import org.androidbible.data.sync.SyncManager
import org.androidbible.data.sync.SyncState
import org.androidbible.domain.repository.AuthRepository
import org.androidbible.domain.repository.UserPreferenceRepository
import org.androidbible.ui.screens.auth.LoginScreen
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class SettingsScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { SettingsScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Settings") },
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) {
                            Text("\u2190 Back")
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                )
            },
        ) { padding ->
            LazyColumn(
                modifier = Modifier.padding(padding).fillMaxSize(),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                // Account section
                item {
                    Text(
                        "Account",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.padding(vertical = 8.dp),
                    )
                }

                item {
                    if (state.isLoggedIn) {
                        ListItem(
                            headlineContent = { Text(state.userName) },
                            supportingContent = { Text(state.userEmail) },
                            trailingContent = {
                                TextButton(onClick = { screenModel.logout() }) {
                                    Text("Logout")
                                }
                            },
                        )
                    } else {
                        ListItem(
                            headlineContent = { Text("Sign In") },
                            supportingContent = { Text("Sync your data across devices") },
                            modifier = Modifier.clickable {
                                navigator.push(LoginScreen())
                            },
                        )
                    }
                }

                item { HorizontalDivider() }

                // Display section
                item {
                    Text(
                        "Display",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.padding(vertical = 8.dp),
                    )
                }

                item {
                    Column(modifier = Modifier.padding(horizontal = 16.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text("Font Size", style = MaterialTheme.typography.bodyLarge)
                            Text("${state.fontSize}sp", style = MaterialTheme.typography.bodyMedium)
                        }
                        Slider(
                            value = state.fontSize.toFloat(),
                            onValueChange = { screenModel.setFontSize(it.toInt()) },
                            valueRange = 12f..32f,
                            steps = 9,
                        )
                    }
                }

                item {
                    ListItem(
                        headlineContent = { Text("Dark Mode") },
                        trailingContent = {
                            Switch(
                                checked = state.isDarkMode,
                                onCheckedChange = { screenModel.setDarkMode(it) },
                            )
                        },
                    )
                }

                item { HorizontalDivider() }

                // Sync section
                item {
                    Text(
                        "Sync",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.padding(vertical = 8.dp),
                    )
                }

                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(
                            containerColor = when (state.syncState) {
                                SyncState.PUSHING, SyncState.PULLING -> MaterialTheme.colorScheme.tertiaryContainer
                                SyncState.ERROR -> MaterialTheme.colorScheme.errorContainer
                                else -> MaterialTheme.colorScheme.surfaceVariant
                            },
                        ),
                    ) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Column {
                                    Text(
                                        when (state.syncState) {
                                            SyncState.PUSHING -> "Syncing (uploading)..."
                                            SyncState.PULLING -> "Syncing (downloading)..."
                                            SyncState.ERROR -> "Sync Error"
                                            else -> "Sync Status"
                                        },
                                        style = MaterialTheme.typography.titleSmall,
                                    )
                                    Text(
                                        "${state.pendingSyncItems} pending items",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                    if (state.lastSyncTime != null) {
                                        Text(
                                            "Last sync: ${state.lastSyncTime}",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                                        )
                                    }
                                }

                                val isSyncing = state.syncState == SyncState.PUSHING ||
                                    state.syncState == SyncState.PULLING
                                FilledTonalButton(
                                    onClick = { screenModel.syncNow() },
                                    enabled = !isSyncing,
                                ) {
                                    if (isSyncing) {
                                        CircularProgressIndicator(
                                            modifier = Modifier.size(16.dp),
                                            strokeWidth = 2.dp,
                                        )
                                    } else {
                                        Text("Sync Now")
                                    }
                                }
                            }
                        }
                    }
                }

                item { HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp)) }

                // Data Management section
                item {
                    Text(
                        "Data",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.padding(vertical = 8.dp),
                    )
                }

                item {
                    ListItem(
                        headlineContent = { Text("Clear Cache") },
                        supportingContent = { Text("Free up storage space") },
                        modifier = Modifier.clickable { screenModel.clearCache() },
                    )
                }

                if (state.isLoggedIn) {
                    item {
                        ListItem(
                            headlineContent = {
                                Text(
                                    "Delete Account",
                                    color = MaterialTheme.colorScheme.error,
                                )
                            },
                            supportingContent = {
                                Text(
                                    "Permanently delete your account and data",
                                    color = MaterialTheme.colorScheme.error.copy(alpha = 0.7f),
                                )
                            },
                            modifier = Modifier.clickable {
                                screenModel.showDeleteConfirmation()
                            },
                        )
                    }
                }

                item { HorizontalDivider() }

                // About section
                item {
                    Text(
                        "About",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.padding(vertical = 8.dp),
                    )
                }

                item {
                    ListItem(
                        headlineContent = { Text("Version") },
                        supportingContent = { Text("1.0.0") },
                    )
                }

                item {
                    ListItem(
                        headlineContent = { Text("Open Source Licenses") },
                        modifier = Modifier.clickable { /* TODO: show licenses */ },
                    )
                }
            }
        }

        // Delete account confirmation dialog
        if (state.showDeleteDialog) {
            AlertDialog(
                onDismissRequest = { screenModel.dismissDeleteConfirmation() },
                title = { Text("Delete Account") },
                text = { Text("Are you sure? This action cannot be undone. All your data will be permanently deleted.") },
                confirmButton = {
                    TextButton(
                        onClick = { screenModel.deleteAccount(onComplete = { navigator.pop() }) },
                        colors = ButtonDefaults.textButtonColors(
                            contentColor = MaterialTheme.colorScheme.error,
                        ),
                    ) {
                        Text("Delete")
                    }
                },
                dismissButton = {
                    TextButton(onClick = { screenModel.dismissDeleteConfirmation() }) {
                        Text("Cancel")
                    }
                },
            )
        }
    }
}

data class SettingsState(
    val isLoggedIn: Boolean = false,
    val userName: String = "",
    val userEmail: String = "",
    val fontSize: Int = 16,
    val isDarkMode: Boolean = false,
    val pendingSyncItems: Int = 0,
    val syncState: SyncState = SyncState.IDLE,
    val lastSyncTime: String? = null,
    val showDeleteDialog: Boolean = false,
)

class SettingsScreenModel : ScreenModel, KoinComponent {

    private val authRepo: AuthRepository by inject()
    private val syncManager: SyncManager by inject()
    private val prefRepo: UserPreferenceRepository by inject()

    private val _state = MutableStateFlow(SettingsState())
    val state: StateFlow<SettingsState> = _state.asStateFlow()

    init {
        screenModelScope.launch {
            authRepo.isLoggedIn().collect { isLoggedIn ->
                _state.value = _state.value.copy(isLoggedIn = isLoggedIn)
            }
        }
        screenModelScope.launch {
            authRepo.getCurrentUser().collect { user ->
                if (user != null) {
                    _state.value = _state.value.copy(
                        userName = user.name,
                        userEmail = user.email,
                    )
                }
            }
        }
        screenModelScope.launch {
            syncManager.syncState.collect { syncState ->
                _state.value = _state.value.copy(syncState = syncState)
            }
        }
        screenModelScope.launch {
            syncManager.pendingCount.collect { count ->
                _state.value = _state.value.copy(pendingSyncItems = count)
            }
        }
        screenModelScope.launch {
            syncManager.lastSyncTime.collect { time ->
                _state.value = _state.value.copy(lastSyncTime = time)
            }
        }
        loadPreferences()
    }

    private fun loadPreferences() {
        screenModelScope.launch {
            val fontSize = prefRepo.get("font_size")?.toIntOrNull() ?: 16
            val isDarkMode = prefRepo.get("dark_mode")?.toBooleanStrictOrNull() ?: false
            _state.value = _state.value.copy(fontSize = fontSize, isDarkMode = isDarkMode)
        }
    }

    fun logout() {
        screenModelScope.launch {
            authRepo.logout()
        }
    }

    fun setFontSize(size: Int) {
        _state.value = _state.value.copy(fontSize = size)
        screenModelScope.launch {
            prefRepo.set("font_size", size.toString())
        }
    }

    fun setDarkMode(enabled: Boolean) {
        _state.value = _state.value.copy(isDarkMode = enabled)
        screenModelScope.launch {
            prefRepo.set("dark_mode", enabled.toString())
        }
    }

    fun syncNow() {
        screenModelScope.launch {
            syncManager.fullSync()
        }
    }

    fun clearCache() {
        // Clear local cache / offline data as needed
    }

    fun showDeleteConfirmation() {
        _state.value = _state.value.copy(showDeleteDialog = true)
    }

    fun dismissDeleteConfirmation() {
        _state.value = _state.value.copy(showDeleteDialog = false)
    }

    fun deleteAccount(onComplete: () -> Unit) {
        screenModelScope.launch {
            try {
                authRepo.deleteAccount()
                onComplete()
            } catch (_: Exception) {
                _state.value = _state.value.copy(showDeleteDialog = false)
            }
        }
    }
}
