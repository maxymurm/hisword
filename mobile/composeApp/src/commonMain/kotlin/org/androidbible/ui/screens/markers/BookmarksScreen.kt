package org.androidbible.ui.screens.markers

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.model.screenModelScope
import cafe.adriel.voyager.core.screen.Screen
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.androidbible.domain.model.Label
import org.androidbible.domain.model.Marker
import org.androidbible.domain.repository.MarkerRepository
import org.androidbible.ui.theme.*
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class BookmarksScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val screenModel = rememberScreenModel { BookmarksScreenModel() }
        val state by screenModel.state.collectAsState()

        var selectedTab by remember { mutableStateOf(0) }
        var showCreateLabelDialog by remember { mutableStateOf(false) }
        var showEditMarkerDialog by remember { mutableStateOf<Marker?>(null) }
        var showSortMenu by remember { mutableStateOf(false) }

        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Bookmarks & Notes") },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                    actions = {
                        Box {
                            IconButton(onClick = { showSortMenu = true }) {
                                Text("\u2195", style = MaterialTheme.typography.titleLarge)
                            }
                            DropdownMenu(
                                expanded = showSortMenu,
                                onDismissRequest = { showSortMenu = false },
                            ) {
                                SortOrder.entries.forEach { order ->
                                    DropdownMenuItem(
                                        text = {
                                            Text(
                                                when (order) {
                                                    SortOrder.NEWEST -> "Newest First"
                                                    SortOrder.OLDEST -> "Oldest First"
                                                    SortOrder.BOOK_ORDER -> "Book Order"
                                                }
                                            )
                                        },
                                        onClick = {
                                            screenModel.setSortOrder(order)
                                            showSortMenu = false
                                        },
                                        trailingIcon = {
                                            if (state.sortOrder == order) Text("\u2713")
                                        },
                                    )
                                }
                            }
                        }
                        IconButton(onClick = { showCreateLabelDialog = true }) {
                            Text("+", style = MaterialTheme.typography.titleLarge)
                        }
                    },
                )
            }
        ) { padding ->
            Column(modifier = Modifier.padding(padding)) {
                // Search bar
                OutlinedTextField(
                    value = state.searchQuery,
                    onValueChange = { screenModel.setSearchQuery(it) },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 8.dp),
                    placeholder = { Text("Search notes...") },
                    singleLine = true,
                )

                // Tabs for Bookmarks, Notes, Highlights
                TabRow(selectedTabIndex = selectedTab) {
                    Tab(
                        selected = selectedTab == 0,
                        onClick = {
                            selectedTab = 0
                            screenModel.filterByKind(Marker.KIND_BOOKMARK)
                        },
                        text = { Text("Bookmarks") },
                    )
                    Tab(
                        selected = selectedTab == 1,
                        onClick = {
                            selectedTab = 1
                            screenModel.filterByKind(Marker.KIND_NOTE)
                        },
                        text = { Text("Notes") },
                    )
                    Tab(
                        selected = selectedTab == 2,
                        onClick = {
                            selectedTab = 2
                            screenModel.filterByKind(Marker.KIND_HIGHLIGHT)
                        },
                        text = { Text("Highlights") },
                    )
                }

                // Label filter chips
                if (state.labels.isNotEmpty()) {
                    LazyRow(
                        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        item {
                            FilterChip(
                                selected = state.selectedLabelId == null,
                                onClick = { screenModel.filterByLabel(null) },
                                label = { Text("All") },
                            )
                        }
                        items(state.labels) { label ->
                            FilterChip(
                                selected = state.selectedLabelId == label.id,
                                onClick = { screenModel.filterByLabel(label.id) },
                                label = { Text(label.title) },
                            )
                        }
                    }
                }

                if (state.isLoading) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center,
                    ) {
                        CircularProgressIndicator()
                    }
                } else if (state.markers.isEmpty()) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center,
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = when (selectedTab) {
                                    0 -> "No bookmarks yet"
                                    1 -> "No notes yet"
                                    2 -> "No highlights yet"
                                    else -> "No items yet"
                                },
                                style = MaterialTheme.typography.bodyLarge,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = "Long-press a verse to add one",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        items(state.markers, key = { it.id }) { marker ->
                            MarkerCard(
                                marker = marker,
                                onDelete = { screenModel.deleteMarker(it) },
                                onEdit = { showEditMarkerDialog = it },
                            )
                        }
                    }
                }
            }

            // Create Label Dialog
            if (showCreateLabelDialog) {
                CreateLabelDialog(
                    onDismiss = { showCreateLabelDialog = false },
                    onCreate = { title ->
                        screenModel.createLabel(title)
                        showCreateLabelDialog = false
                    },
                )
            }

            // Edit Marker Dialog
            showEditMarkerDialog?.let { marker ->
                EditMarkerDialog(
                    marker = marker,
                    labels = state.labels,
                    onDismiss = { showEditMarkerDialog = null },
                    onSave = { caption ->
                        screenModel.updateMarkerCaption(marker.id, caption)
                        showEditMarkerDialog = null
                    },
                    onAttachLabel = { labelId ->
                        screenModel.attachLabel(marker.id, labelId)
                    },
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MarkerCard(
    marker: Marker,
    onDelete: (Long) -> Unit = {},
    onEdit: (Marker) -> Unit = {},
) {
    val (book, chapter, verse) = Ari.decode(marker.ari)
    val kindLabel = when (marker.kind) {
        Marker.KIND_BOOKMARK -> "Bookmark"
        Marker.KIND_NOTE -> "Note"
        Marker.KIND_HIGHLIGHT -> "Highlight"
        else -> "Unknown"
    }

    val highlightColor = when (marker.color) {
        1 -> HighlightYellow
        2 -> HighlightGreen
        3 -> HighlightBlue
        4 -> HighlightPink
        5 -> HighlightOrange
        6 -> HighlightPurple
        else -> null
    }

    var showMenu by remember { mutableStateOf(false) }

    Card(
        modifier = Modifier.fillMaxWidth().clickable { onEdit(marker) },
    ) {
        Row(modifier = Modifier.padding(16.dp)) {
            // Color indicator for highlights
            if (highlightColor != null) {
                Box(
                    modifier = Modifier
                        .size(8.dp, 48.dp)
                        .clip(CircleShape)
                        .background(highlightColor)
                )
                Spacer(modifier = Modifier.width(12.dp))
            }

            Column(modifier = Modifier.weight(1f)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(
                        text = "Book $book, $chapter:$verse",
                        style = MaterialTheme.typography.titleSmall,
                        color = MaterialTheme.colorScheme.primary,
                    )
                    Text(
                        text = kindLabel,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                if (marker.caption.isNotBlank()) {
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = marker.caption,
                        style = MaterialTheme.typography.bodyMedium,
                        maxLines = 3,
                    )
                }
                if (marker.createdAt != null) {
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = marker.createdAt.take(10),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }

            Box {
                IconButton(onClick = { showMenu = true }) {
                    Text("...")
                }
                DropdownMenu(
                    expanded = showMenu,
                    onDismissRequest = { showMenu = false },
                ) {
                    DropdownMenuItem(
                        text = { Text("Edit") },
                        onClick = {
                            showMenu = false
                            onEdit(marker)
                        },
                    )
                    DropdownMenuItem(
                        text = { Text("Delete") },
                        onClick = {
                            showMenu = false
                            onDelete(marker.id)
                        },
                    )
                }
            }
        }
    }
}

@Composable
fun CreateLabelDialog(
    onDismiss: () -> Unit,
    onCreate: (String) -> Unit,
) {
    var title by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Create Label") },
        text = {
            OutlinedTextField(
                value = title,
                onValueChange = { title = it },
                label = { Text("Label Name") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
            )
        },
        confirmButton = {
            Button(
                onClick = { onCreate(title) },
                enabled = title.isNotBlank(),
            ) {
                Text("Create")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel") }
        },
    )
}

@Composable
fun EditMarkerDialog(
    marker: Marker,
    labels: List<Label>,
    onDismiss: () -> Unit,
    onSave: (String) -> Unit,
    onAttachLabel: (Long) -> Unit,
) {
    var caption by remember { mutableStateOf(marker.caption) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Edit ${if (marker.isNote) "Note" else "Marker"}") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                OutlinedTextField(
                    value = caption,
                    onValueChange = { caption = it },
                    label = { Text(if (marker.isNote) "Note text" else "Caption") },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = if (marker.isNote) 3 else 1,
                )

                if (labels.isNotEmpty()) {
                    Text("Labels", style = MaterialTheme.typography.labelMedium)
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(labels) { label ->
                            AssistChip(
                                onClick = { onAttachLabel(label.id) },
                                label = { Text(label.title) },
                            )
                        }
                    }
                }
            }
        },
        confirmButton = {
            Button(onClick = { onSave(caption) }) { Text("Save") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel") }
        },
    )
}

data class BookmarksState(
    val markers: List<Marker> = emptyList(),
    val labels: List<Label> = emptyList(),
    val isLoading: Boolean = false,
    val currentKind: Int? = null,
    val selectedLabelId: Long? = null,
    val searchQuery: String = "",
    val sortOrder: SortOrder = SortOrder.NEWEST,
)

enum class SortOrder { NEWEST, OLDEST, BOOK_ORDER }

class BookmarksScreenModel : ScreenModel, KoinComponent {

    private val markerRepo: MarkerRepository by inject()

    private val _state = MutableStateFlow(BookmarksState())
    val state: StateFlow<BookmarksState> = _state.asStateFlow()

    init {
        loadMarkers(null)
        loadLabels()
    }

    private fun loadLabels() {
        screenModelScope.launch {
            markerRepo.getLabels().collect { labels ->
                _state.value = _state.value.copy(labels = labels)
            }
        }
    }

    fun filterByKind(kind: Int) {
        _state.value = _state.value.copy(currentKind = kind)
        loadMarkers(kind)
    }

    fun filterByLabel(labelId: Long?) {
        _state.value = _state.value.copy(selectedLabelId = labelId)
        if (labelId != null) {
            screenModelScope.launch {
                markerRepo.getMarkersForLabel(labelId).collect { markers ->
                    val kind = _state.value.currentKind
                    val filtered = if (kind != null) markers.filter { it.kind == kind } else markers
                    _state.value = _state.value.copy(markers = applySorting(filtered), isLoading = false)
                }
            }
        } else {
            loadMarkers(_state.value.currentKind)
        }
    }

    fun setSearchQuery(query: String) {
        _state.value = _state.value.copy(searchQuery = query)
        if (query.isBlank()) {
            loadMarkers(_state.value.currentKind)
        } else {
            screenModelScope.launch {
                markerRepo.searchMarkers(query).collect { markers ->
                    val kind = _state.value.currentKind
                    val filtered = if (kind != null) markers.filter { it.kind == kind } else markers
                    _state.value = _state.value.copy(markers = applySorting(filtered), isLoading = false)
                }
            }
        }
    }

    fun setSortOrder(order: SortOrder) {
        _state.value = _state.value.copy(
            sortOrder = order,
            markers = applySorting(_state.value.markers, order),
        )
    }

    private fun applySorting(markers: List<Marker>, order: SortOrder? = null): List<Marker> {
        return when (order ?: _state.value.sortOrder) {
            SortOrder.NEWEST -> markers.sortedByDescending { it.createdAt ?: "" }
            SortOrder.OLDEST -> markers.sortedBy { it.createdAt ?: "" }
            SortOrder.BOOK_ORDER -> markers.sortedBy { it.ari }
        }
    }

    private fun loadMarkers(kind: Int?) {
        _state.value = _state.value.copy(isLoading = true)
        screenModelScope.launch {
            markerRepo.getMarkers(kind).collect { markers ->
                _state.value = _state.value.copy(markers = applySorting(markers), isLoading = false)
            }
        }
    }

    fun deleteMarker(id: Long) {
        screenModelScope.launch {
            markerRepo.deleteMarker(id)
        }
    }

    fun createLabel(title: String) {
        screenModelScope.launch {
            markerRepo.createLabel(Label(title = title))
        }
    }

    fun updateMarkerCaption(id: Long, caption: String) {
        screenModelScope.launch {
            val marker = markerRepo.getMarker(id) ?: return@launch
            markerRepo.updateMarker(marker.copy(caption = caption))
        }
    }

    fun attachLabel(markerId: Long, labelId: Long) {
        screenModelScope.launch {
            markerRepo.attachLabel(markerId, labelId)
        }
    }
}
