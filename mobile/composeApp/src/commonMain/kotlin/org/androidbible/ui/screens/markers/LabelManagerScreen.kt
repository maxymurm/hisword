package org.androidbible.ui.screens.markers

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
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
import org.androidbible.domain.model.Label
import org.androidbible.domain.repository.MarkerRepository
import org.androidbible.ui.theme.*
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

/**
 * Full label management screen: list, create, edit (title + color), delete.
 */
class LabelManagerScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val vm = rememberScreenModel { LabelManagerViewModel() }
        val state by vm.state.collectAsState()

        var editLabel by remember { mutableStateOf<Label?>(null) }
        var showCreate by remember { mutableStateOf(false) }

        Scaffold(
            topBar = {
                TopAppBar(
                    navigationIcon = {
                        TextButton(onClick = { navigator.pop() }) { Text("Back") }
                    },
                    title = { Text("Labels") },
                    actions = {
                        IconButton(onClick = { showCreate = true }) {
                            Text("+", style = MaterialTheme.typography.titleLarge)
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                )
            }
        ) { padding ->
            if (state.labels.isEmpty()) {
                Box(
                    Modifier.fillMaxSize().padding(padding),
                    contentAlignment = Alignment.Center,
                ) {
                    Text("No labels yet. Tap + to create one.")
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize().padding(padding),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(state.labels, key = { it.id }) { label ->
                        LabelCard(
                            label = label,
                            onEdit = { editLabel = label },
                            onDelete = { vm.deleteLabel(label.id) },
                        )
                    }
                }
            }
        }

        if (showCreate) {
            LabelEditDialog(
                title = "Create Label",
                initialTitle = "",
                initialColor = null,
                onSave = { title, color ->
                    vm.createLabel(title, color)
                    showCreate = false
                },
                onDismiss = { showCreate = false },
            )
        }

        editLabel?.let { label ->
            LabelEditDialog(
                title = "Edit Label",
                initialTitle = label.title,
                initialColor = label.backgroundColor,
                onSave = { title, color ->
                    vm.updateLabel(label.id, title, color)
                    editLabel = null
                },
                onDismiss = { editLabel = null },
            )
        }
    }
}

@Composable
private fun LabelCard(
    label: Label,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
) {
    val bgColor = labelColorFromIndex(label.backgroundColor)

    Card(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onEdit),
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(24.dp)
                    .clip(CircleShape)
                    .background(bgColor)
            )
            Spacer(Modifier.width(16.dp))
            Text(
                text = label.title,
                style = MaterialTheme.typography.titleSmall,
                modifier = Modifier.weight(1f),
            )
            TextButton(onClick = onDelete) {
                Text("Delete", color = MaterialTheme.colorScheme.error)
            }
        }
    }
}

@Composable
private fun LabelEditDialog(
    title: String,
    initialTitle: String,
    initialColor: Int?,
    onSave: (String, Int?) -> Unit,
    onDismiss: () -> Unit,
) {
    var labelTitle by remember { mutableStateOf(initialTitle) }
    var selectedColor by remember { mutableStateOf(initialColor) }

    val colorOptions = listOf(
        null to Color.Gray,
        1 to HighlightYellow,
        2 to HighlightGreen,
        3 to HighlightBlue,
        4 to HighlightPink,
        5 to HighlightOrange,
        6 to HighlightPurple,
    )

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                OutlinedTextField(
                    value = labelTitle,
                    onValueChange = { labelTitle = it },
                    label = { Text("Label Name") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                )

                Text("Color", style = MaterialTheme.typography.labelMedium)
                LazyRow(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    items(colorOptions) { (idx, color) ->
                        Box(
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .background(color)
                                .clickable { selectedColor = idx },
                            contentAlignment = Alignment.Center,
                        ) {
                            if (selectedColor == idx) {
                                Box(
                                    Modifier
                                        .size(14.dp)
                                        .clip(CircleShape)
                                        .background(MaterialTheme.colorScheme.onPrimary)
                                )
                            }
                        }
                    }
                }
            }
        },
        confirmButton = {
            Button(
                onClick = { onSave(labelTitle, selectedColor) },
                enabled = labelTitle.isNotBlank(),
            ) { Text("Save") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel") }
        },
    )
}

fun labelColorFromIndex(index: Int?): Color = when (index) {
    1 -> HighlightYellow
    2 -> HighlightGreen
    3 -> HighlightBlue
    4 -> HighlightPink
    5 -> HighlightOrange
    6 -> HighlightPurple
    else -> Color.Gray
}

data class LabelManagerState(
    val labels: List<Label> = emptyList(),
)

class LabelManagerViewModel : ScreenModel, KoinComponent {
    private val markerRepo: MarkerRepository by inject()
    private val _state = MutableStateFlow(LabelManagerState())
    val state: StateFlow<LabelManagerState> = _state.asStateFlow()

    init {
        screenModelScope.launch {
            markerRepo.getLabels().collect { _state.value = LabelManagerState(labels = it) }
        }
    }

    fun createLabel(title: String, color: Int?) {
        screenModelScope.launch {
            markerRepo.createLabel(Label(title = title, backgroundColor = color))
        }
    }

    fun updateLabel(id: Long, title: String, color: Int?) {
        screenModelScope.launch {
            markerRepo.updateLabel(Label(id = id, title = title, backgroundColor = color))
        }
    }

    fun deleteLabel(id: Long) {
        screenModelScope.launch { markerRepo.deleteLabel(id) }
    }
}
