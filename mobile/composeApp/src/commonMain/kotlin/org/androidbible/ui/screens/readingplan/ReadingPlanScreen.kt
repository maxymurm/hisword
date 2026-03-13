package org.androidbible.ui.screens.readingplan

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
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
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonPrimitive
import org.androidbible.domain.model.ReadingPlan
import org.androidbible.domain.model.ReadingPlanDay
import org.androidbible.domain.model.ReadingPlanProgress
import org.androidbible.domain.repository.ReadingPlanRepository
import org.androidbible.ui.screens.bible.BibleReaderScreen
import org.androidbible.util.Ari
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

class ReadingPlanScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { ReadingPlanScreenModel() }
        val state by screenModel.state.collectAsState()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        Text(state.selectedPlan?.title ?: "Reading Plans")
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                    navigationIcon = {
                        if (state.selectedPlan != null) {
                            TextButton(onClick = { screenModel.goBack() }) {
                                Text("<")
                            }
                        }
                    },
                    actions = {
                        if (state.selectedPlan != null) {
                            val plan = state.selectedPlan!!
                            val completedCount = state.progress.size
                            val pct = if (plan.totalDays > 0) (completedCount * 100 / plan.totalDays) else 0
                            Text(
                                "$pct%",
                                style = MaterialTheme.typography.labelLarge,
                                modifier = Modifier.padding(end = 16.dp),
                            )
                        }
                    },
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
            } else if (state.selectedPlan != null) {
                PlanDetailContent(
                    plan = state.selectedPlan!!,
                    days = state.days,
                    progress = state.progress,
                    onMarkComplete = { screenModel.markDayComplete(it) },
                    onNavigateToReading = { ari ->
                        navigator.push(BibleReaderScreen(initialAri = ari))
                    },
                    modifier = Modifier.padding(padding),
                )
            } else if (state.plans.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxSize().padding(padding),
                    contentAlignment = Alignment.Center,
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("No reading plans available")
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "Plans will appear here once synced",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            } else {
                LazyColumn(
                    modifier = Modifier.padding(padding).fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(state.plans) { plan ->
                        ReadingPlanCard(
                            plan = plan,
                            completedDays = state.planProgress[plan.id] ?: 0,
                            onClick = { screenModel.selectPlan(plan) },
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun ReadingPlanCard(
    plan: ReadingPlan,
    completedDays: Int = 0,
    onClick: () -> Unit = {},
) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text(
                text = plan.title,
                style = MaterialTheme.typography.titleMedium,
            )
            if (!plan.description.isNullOrEmpty()) {
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = plan.description,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(modifier = Modifier.height(12.dp))

            // Progress bar
            val progress = if (plan.totalDays > 0) completedDays.toFloat() / plan.totalDays else 0f
            LinearProgressIndicator(
                progress = { progress },
                modifier = Modifier.fillMaxWidth(),
                color = MaterialTheme.colorScheme.primary,
                trackColor = MaterialTheme.colorScheme.primaryContainer,
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = "$completedDays / ${plan.totalDays} days",
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.primary,
            )
        }
    }
}

@Composable
fun PlanDetailContent(
    plan: ReadingPlan,
    days: List<ReadingPlanDay>,
    progress: List<ReadingPlanProgress>,
    onMarkComplete: (Long) -> Unit,
    onNavigateToReading: (Int) -> Unit,
    modifier: Modifier = Modifier,
) {
    val completedDayIds = progress.map { it.readingPlanDayId }.toSet()

    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        // Plan description + overall progress
        item {
            if (!plan.description.isNullOrEmpty()) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer,
                    ),
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = plan.description,
                            style = MaterialTheme.typography.bodyMedium,
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        val overallProgress = if (plan.totalDays > 0)
                            completedDayIds.size.toFloat() / plan.totalDays else 0f
                        LinearProgressIndicator(
                            progress = { overallProgress },
                            modifier = Modifier.fillMaxWidth(),
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            "${completedDayIds.size} / ${plan.totalDays} days completed",
                            style = MaterialTheme.typography.labelSmall,
                        )
                    }
                }
                Spacer(modifier = Modifier.height(8.dp))
            }
        }

        // Days list with ARI reference chips
        items(days) { day ->
            val isCompleted = completedDayIds.contains(day.id)
            val ariRanges = parseAriRanges(day.ariRanges)

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = if (isCompleted)
                    CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant,
                    )
                else CardDefaults.cardColors(),
            ) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.fillMaxWidth(),
                    ) {
                        Checkbox(
                            checked = isCompleted,
                            onCheckedChange = { if (!isCompleted) onMarkComplete(day.id) },
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                day.title ?: "Day ${day.dayNumber}",
                                style = MaterialTheme.typography.bodyLarge,
                                fontWeight = if (!isCompleted) FontWeight.Medium else FontWeight.Normal,
                            )
                            day.description?.let {
                                Text(
                                    it,
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                        Text(
                            "Day ${day.dayNumber}",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }

                    // ARI reference chips — clickable to navigate
                    if (ariRanges.isNotEmpty()) {
                        Spacer(modifier = Modifier.height(8.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(start = 48.dp),
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                        ) {
                            for (ari in ariRanges.take(4)) {
                                val ref = Ari.referenceString(ari)
                                AssistChip(
                                    onClick = { onNavigateToReading(ari) },
                                    label = { Text(ref, style = MaterialTheme.typography.labelSmall) },
                                )
                            }
                            if (ariRanges.size > 4) {
                                Text(
                                    "+${ariRanges.size - 4}",
                                    style = MaterialTheme.typography.labelSmall,
                                    modifier = Modifier.align(Alignment.CenterVertically),
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

/** Parse ariRanges JSON string (e.g., "[65536, 65537]") into list of ARI ints. */
private fun parseAriRanges(json: String): List<Int> {
    return try {
        val arr = Json.parseToJsonElement(json).jsonArray
        arr.map { it.jsonPrimitive.content.toInt() }
    } catch (_: Exception) {
        emptyList()
    }
}

data class ReadingPlanState(
    val plans: List<ReadingPlan> = emptyList(),
    val selectedPlan: ReadingPlan? = null,
    val days: List<ReadingPlanDay> = emptyList(),
    val progress: List<ReadingPlanProgress> = emptyList(),
    val planProgress: Map<Long, Int> = emptyMap(), // planId -> completed count
    val isLoading: Boolean = false,
)

class ReadingPlanScreenModel : ScreenModel, KoinComponent {

    private val planRepo: ReadingPlanRepository by inject()

    private val _state = MutableStateFlow(ReadingPlanState())
    val state: StateFlow<ReadingPlanState> = _state.asStateFlow()

    init {
        _state.value = _state.value.copy(isLoading = true)
        screenModelScope.launch {
            planRepo.getReadingPlans().collect { plans ->
                _state.value = _state.value.copy(plans = plans, isLoading = false)
                // Load progress counts for all plans
                plans.forEach { plan ->
                    launch {
                        planRepo.getProgress(plan.id).collect { progress ->
                            val current = _state.value.planProgress.toMutableMap()
                            current[plan.id] = progress.size
                            _state.value = _state.value.copy(planProgress = current)
                        }
                    }
                }
            }
        }
    }

    fun selectPlan(plan: ReadingPlan) {
        _state.value = _state.value.copy(selectedPlan = plan, isLoading = true)
        screenModelScope.launch {
            planRepo.getDays(plan.id).collect { days ->
                _state.value = _state.value.copy(days = days, isLoading = false)
            }
        }
        screenModelScope.launch {
            planRepo.getProgress(plan.id).collect { progress ->
                _state.value = _state.value.copy(progress = progress)
            }
        }
    }

    fun goBack() {
        _state.value = _state.value.copy(
            selectedPlan = null,
            days = emptyList(),
            progress = emptyList(),
        )
    }

    fun markDayComplete(dayId: Long) {
        val plan = _state.value.selectedPlan ?: return
        screenModelScope.launch {
            planRepo.markDayComplete(plan.id, dayId)
        }
    }
}
