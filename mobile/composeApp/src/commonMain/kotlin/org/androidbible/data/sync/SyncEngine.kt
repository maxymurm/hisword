package org.androidbible.data.sync

import io.github.aakira.napier.Napier
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.*
import kotlinx.datetime.Clock
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.model.*

/**
 * goldenBowl Sync Engine — revision-vector delta sync.
 *
 * Protocol:
 * 1. Each entity type (marker, label, progress_mark, preference) has its own
 *    revision counter stored in sync_revisions table.
 * 2. Push: local offline queue items -> server, server returns new global version.
 * 3. Pull: send per-entity revision map -> server returns only events newer than
 *    the client's known revision for each entity type.
 * 4. Conflict resolution delegated to SyncConflictResolver.
 * 5. Real-time: ReverbClient notifies of new events -> triggers pull.
 */
class SyncEngine(
    private val db: AndroidBibleDatabase,
    private val api: ApiService,
    private val reverb: ReverbClient,
    private val conflictResolver: SyncConflictResolver,
) {
    private val _state = MutableStateFlow(SyncState.IDLE)
    val state: StateFlow<SyncState> = _state.asStateFlow()

    private val _pendingCount = MutableStateFlow(0)
    val pendingCount: StateFlow<Int> = _pendingCount.asStateFlow()

    private val _lastSyncTime = MutableStateFlow<String?>(null)
    val lastSyncTime: StateFlow<String?> = _lastSyncTime.asStateFlow()

    private val _errors = MutableSharedFlow<String>(extraBufferCapacity = 8)
    val errors: SharedFlow<String> = _errors.asSharedFlow()

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var periodicJob: Job? = null

    private val deviceId: String by lazy {
        val existing = db.syncQueries.getSyncState("default").executeAsOneOrNull()
        existing?.device_id ?: run {
            val id = com.benasher44.uuid.uuid4().toString()
            db.syncQueries.upsertSyncState(id, 0, null)
            id
        }
    }

    // ── Lifecycle ────────────────────────────────────────

    fun start() {
        scope.launch {
            pushPending()
            pullDelta()
            startRealtime()
            startPeriodicSync(intervalMs = 5 * 60 * 1000L)
            updatePendingCount()
        }
    }

    fun stop() {
        periodicJob?.cancel()
        reverb.disconnect()
        scope.cancel()
    }

    suspend fun fullSync() {
        pushPending()
        pullDelta()
        retryFailed()
    }

    // ── Queue ────────────────────────────────────────────

    fun queueChange(entityType: String, entityId: String, action: String, payload: String) {
        val now = Clock.System.now().toString()
        db.syncQueries.insertSyncQueueItem(entityType, entityId, action, payload, now)
        updatePendingCount()
        // Debounced push
        scope.launch {
            delay(1000)
            pushPending()
        }
    }

    // ── Push ─────────────────────────────────────────────

    suspend fun pushPending() {
        _state.value = SyncState.PUSHING
        val items = db.syncQueries.getPendingSyncItems().executeAsList()
        if (items.isEmpty()) {
            _state.value = SyncState.IDLE
            return
        }

        try {
            val events = items.map {
                SyncEventPayload(
                    entityType = it.entity_type,
                    entityId = it.entity_id,
                    action = it.action,
                    payload = it.payload,
                )
            }
            val response = api.syncPush(SyncPushRequest(events = events, deviceId = deviceId))
            val now = Clock.System.now().toString()

            db.transaction {
                items.forEach { db.syncQueries.markSyncItemProcessed(now, it.id) }
            }
            db.syncQueries.upsertSyncState(deviceId, response.currentVersion, now)
            db.syncQueries.deleteProcessedItems()
            _lastSyncTime.value = now
            Napier.i("Pushed ${response.processed} events", tag = "Sync")
        } catch (e: Exception) {
            Napier.e("Push failed: ${e.message}", tag = "Sync")
            _errors.emit("Push failed: ${e.message}")
            items.forEach { db.syncQueries.markSyncItemFailed(it.id) }
        }

        _state.value = SyncState.IDLE
        updatePendingCount()
    }

    // ── Pull (revision-vector delta) ─────────────────────

    suspend fun pullDelta() {
        _state.value = SyncState.PULLING
        try {
            // Build per-entity revision map
            val revisions = db.syncQueries.getAllSyncRevisions().executeAsList()
            val revisionMap = revisions.associate { it.entity_type to it.last_version }
            val globalVersion = revisionMap.values.maxOrNull() ?: 0L

            val response = api.syncPull(
                SyncPullRequest(lastVersion = globalVersion, deviceId = deviceId)
            )

            if (response.events.isNotEmpty()) {
                applyEvents(response.events)
                val now = Clock.System.now().toString()

                // Update per-entity revisions
                response.events.groupBy { it.entityType }.forEach { (type, events) ->
                    val maxVersion = events.maxOf { it.version }
                    db.syncQueries.upsertSyncRevision(type, maxVersion)
                }

                db.syncQueries.upsertSyncState(deviceId, response.currentVersion, now)
                _lastSyncTime.value = now
                Napier.i("Pulled ${response.events.size} events", tag = "Sync")
            }
        } catch (e: Exception) {
            Napier.e("Pull failed: ${e.message}", tag = "Sync")
            _errors.emit("Pull failed: ${e.message}")
        }
        _state.value = SyncState.IDLE
    }

    // ── Event Application ────────────────────────────────

    private fun applyEvents(events: List<SyncEvent>) {
        db.transaction {
            events.forEach { event ->
                try {
                    // Echo prevention: skip events from this device
                    if (event.deviceId == deviceId) return@forEach

                    val resolved = conflictResolver.resolve(event, db)
                    if (resolved) {
                        when (event.entityType) {
                            "marker" -> applyMarkerEvent(event)
                            "label" -> applyLabelEvent(event)
                            "progress_mark" -> applyProgressMarkEvent(event)
                            "preference" -> applyPreferenceEvent(event)
                            else -> Napier.w("Unknown entity type: ${event.entityType}", tag = "Sync")
                        }
                    }
                } catch (e: Exception) {
                    Napier.e("Failed to apply event ${event.id}", e, tag = "Sync")
                }
            }
        }
    }

    private fun applyMarkerEvent(event: SyncEvent) {
        val now = Clock.System.now().toString()
        val json = kotlinx.serialization.json.Json { ignoreUnknownKeys = true }
        when (event.action) {
            "create", "update" -> {
                val marker = json.decodeFromString<org.androidbible.domain.model.Marker>(event.payload)
                db.markerQueries.insertMarker(
                    id = null, gid = marker.gid, server_id = event.entityId.toLongOrNull(),
                    user_id = marker.userId, bible_version_id = marker.bibleVersionId,
                    ari = marker.ari.toLong(), kind = marker.kind.toLong(),
                    caption = marker.caption, verse_count = marker.verseCount.toLong(),
                    color = marker.color?.toLong(), is_synced = 1,
                    created_at = marker.createdAt ?: now, updated_at = now, deleted_at = null,
                )
            }
            "delete" -> {
                val existing = db.markerQueries.getMarkerByGid(event.entityId).executeAsOneOrNull()
                if (existing != null) db.markerQueries.softDeleteMarker(now, now, existing.id)
            }
        }
    }

    private fun applyLabelEvent(event: SyncEvent) {
        val now = Clock.System.now().toString()
        val json = kotlinx.serialization.json.Json { ignoreUnknownKeys = true }
        when (event.action) {
            "create", "update" -> {
                val label = json.decodeFromString<org.androidbible.domain.model.Label>(event.payload)
                db.markerQueries.insertLabel(
                    id = null, gid = label.gid, server_id = event.entityId.toLongOrNull(),
                    user_id = label.userId, title = label.title,
                    background_color = label.backgroundColor?.toLong(),
                    is_synced = 1, created_at = label.createdAt ?: now,
                    updated_at = now, deleted_at = null,
                )
            }
            "delete" -> {
                val existing = db.markerQueries.getLabelByGid(event.entityId).executeAsOneOrNull()
                if (existing != null) db.markerQueries.softDeleteLabel(now, now, existing.id)
            }
        }
    }

    private fun applyProgressMarkEvent(event: SyncEvent) {
        val now = Clock.System.now().toString()
        val json = kotlinx.serialization.json.Json { ignoreUnknownKeys = true }
        when (event.action) {
            "create", "update" -> {
                val pm = json.decodeFromString<ProgressMark>(event.payload)
                db.markerQueries.insertProgressMark(
                    id = null, gid = pm.gid, server_id = event.entityId.toLongOrNull(),
                    user_id = pm.userId, preset = pm.preset.toLong(), ari = pm.ari.toLong(),
                    caption = pm.caption, modify_time = pm.modifyTime,
                    is_synced = 1, created_at = pm.createdAt ?: now, updated_at = now,
                )
            }
            "delete" -> Napier.d("Progress mark delete: ${event.entityId}", tag = "Sync")
        }
    }

    private fun applyPreferenceEvent(event: SyncEvent) {
        val now = Clock.System.now().toString()
        val json = kotlinx.serialization.json.Json { ignoreUnknownKeys = true }
        when (event.action) {
            "create", "update" -> {
                val pref = json.decodeFromString<UserPreference>(event.payload)
                db.syncQueries.upsertPreference(pref.key, pref.value, 1, now)
            }
            "delete" -> db.syncQueries.deletePreference(event.entityId)
        }
    }

    // ── Retry ────────────────────────────────────────────

    private suspend fun retryFailed() {
        val failed = db.syncQueries.getFailedSyncItems().executeAsList()
        if (failed.isEmpty()) return
        try {
            val events = failed.map {
                SyncEventPayload(it.entity_type, it.entity_id, it.action, it.payload)
            }
            val response = api.syncPush(SyncPushRequest(events = events, deviceId = deviceId))
            val now = Clock.System.now().toString()
            db.transaction { failed.forEach { db.syncQueries.markSyncItemProcessed(now, it.id) } }
            db.syncQueries.upsertSyncState(deviceId, response.currentVersion, now)
        } catch (e: Exception) {
            Napier.e("Retry failed: ${e.message}", tag = "Sync")
        }
    }

    // ── Real-time ────────────────────────────────────────

    private fun startRealtime() {
        reverb.connect()
        scope.launch {
            reverb.incomingEvents.collect { event ->
                if (event is ReverbClient.ReverbEvent.SyncEventReceived) {
                    pullDelta()
                }
            }
        }
    }

    // ── Periodic ─────────────────────────────────────────

    private fun startPeriodicSync(intervalMs: Long) {
        periodicJob?.cancel()
        periodicJob = scope.launch {
            while (isActive) {
                delay(intervalMs)
                try { pushPending(); pullDelta() } catch (_: Exception) {}
            }
        }
    }

    private fun updatePendingCount() {
        _pendingCount.value = db.syncQueries.getPendingCount().executeAsOne().toInt()
    }

    enum class SyncState { IDLE, PUSHING, PULLING }
}
