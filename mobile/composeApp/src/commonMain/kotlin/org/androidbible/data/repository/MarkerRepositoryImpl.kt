package org.androidbible.data.repository

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToList
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.IO
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.datetime.Clock
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.model.Label
import org.androidbible.domain.model.Marker
import org.androidbible.domain.repository.MarkerRepository
import com.benasher44.uuid.uuid4

class MarkerRepositoryImpl(
    private val db: AndroidBibleDatabase,
    private val api: ApiService,
) : MarkerRepository {

    override fun getMarkers(kind: Int?): Flow<List<Marker>> {
        val query = if (kind != null) {
            db.markerQueries.getMarkersByKind(kind.toLong())
        } else {
            db.markerQueries.getAllMarkers()
        }
        return query.asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toMarker() }
        }
    }

    override fun getMarkersByAri(ari: Int): Flow<List<Marker>> {
        return db.markerQueries.getMarkersByAri(ari.toLong()).asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toMarker() }
        }
    }

    override fun getMarkersByAriRange(startAri: Int, endAri: Int): Flow<List<Marker>> {
        return db.markerQueries.getMarkersByAriRange(startAri.toLong(), endAri.toLong())
            .asFlow().mapToList(Dispatchers.IO).map { rows -> rows.map { it.toMarker() } }
    }

    override fun getMarkersForLabel(labelId: Long): Flow<List<Marker>> {
        return db.markerQueries.getMarkersForLabel(labelId)
            .asFlow().mapToList(Dispatchers.IO).map { rows -> rows.map { it.toMarker() } }
    }

    override fun searchMarkers(query: String): Flow<List<Marker>> {
        return db.markerQueries.searchMarkersByCaption("%$query%")
            .asFlow().mapToList(Dispatchers.IO).map { rows -> rows.map { it.toMarker() } }
    }

    override suspend fun getMarker(id: Long): Marker? {
        return db.markerQueries.getMarkerById(id).executeAsOneOrNull()?.toMarker()
    }

    override suspend fun createMarker(marker: Marker): Marker {
        val now = Clock.System.now().toString()
        val gid = uuid4().toString()
        db.markerQueries.insertMarker(
            id = null,
            gid = gid,
            server_id = null,
            user_id = null,
            bible_version_id = marker.bibleVersionId,
            ari = marker.ari.toLong(),
            kind = marker.kind.toLong(),
            caption = marker.caption,
            verse_count = marker.verseCount.toLong(),
            color = marker.color?.toLong(),
            is_synced = 0,
            created_at = now,
            updated_at = now,
            deleted_at = null,
        )

        // Enqueue sync
        val insertedId = db.markerQueries.lastInsertRowId().executeAsOne()
        enqueueSyncAction("marker", gid, "create", marker)
        return marker.copy(id = insertedId, gid = gid, createdAt = now, updatedAt = now)
    }

    override suspend fun updateMarker(marker: Marker): Marker {
        val now = Clock.System.now().toString()
        db.markerQueries.updateMarker(
            ari = marker.ari.toLong(),
            kind = marker.kind.toLong(),
            caption = marker.caption,
            verse_count = marker.verseCount.toLong(),
            color = marker.color?.toLong(),
            is_synced = 0,
            updated_at = now,
            id = marker.id,
        )
        enqueueSyncAction("marker", marker.gid, "update", marker)
        return marker.copy(updatedAt = now)
    }

    override suspend fun deleteMarker(id: Long) {
        val marker = getMarker(id) ?: return
        val now = Clock.System.now().toString()
        db.markerQueries.softDeleteMarker(deleted_at = now, updated_at = now, id = id)
        enqueueSyncAction("marker", marker.gid, "delete", marker)
    }

    override suspend fun deleteMarkers(ids: List<Long>) {
        val now = Clock.System.now().toString()
        db.markerQueries.softDeleteMarkers(deleted_at = now, updated_at = now, ids)
    }

    override suspend fun getAllMarkersSnapshot(): List<Marker> {
        return db.markerQueries.getAllMarkersSnapshot().executeAsList().map { it.toMarker() }
    }

    override fun getLabels(): Flow<List<Label>> {
        return db.markerQueries.getAllLabels().asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toLabel() }
        }
    }

    override suspend fun createLabel(label: Label): Label {
        val now = Clock.System.now().toString()
        val gid = uuid4().toString()
        db.markerQueries.insertLabel(
            id = null,
            gid = gid,
            server_id = null,
            user_id = null,
            title = label.title,
            background_color = label.backgroundColor?.toLong(),
            is_synced = 0,
            created_at = now,
            updated_at = now,
            deleted_at = null,
        )
        val insertedId = db.markerQueries.lastInsertRowId().executeAsOne()
        return label.copy(id = insertedId, gid = gid, createdAt = now, updatedAt = now)
    }

    override suspend fun updateLabel(label: Label): Label {
        val now = Clock.System.now().toString()
        db.markerQueries.updateLabel(
            title = label.title,
            background_color = label.backgroundColor?.toLong(),
            updated_at = now,
            id = label.id,
        )
        return label.copy(updatedAt = now)
    }

    override suspend fun deleteLabel(id: Long) {
        val now = Clock.System.now().toString()
        db.markerQueries.softDeleteLabel(deleted_at = now, updated_at = now, id = id)
    }

    override suspend fun attachLabel(markerId: Long, labelId: Long) {
        db.markerQueries.attachLabel(markerId, labelId)
    }

    override suspend fun detachLabel(markerId: Long, labelId: Long) {
        db.markerQueries.detachLabel(markerId, labelId)
    }

    override suspend fun attachLabelBulk(markerIds: List<Long>, labelId: Long) {
        db.transaction {
            for (markerId in markerIds) {
                db.markerQueries.attachLabel(markerId, labelId)
            }
        }
    }

    override fun getLabelsForMarker(markerId: Long): Flow<List<Label>> {
        return db.markerQueries.getLabelsForMarker(markerId).asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toLabel() }
        }
    }

    private fun enqueueSyncAction(entityType: String, entityId: String, action: String, data: Any) {
        val now = Clock.System.now().toString()
        val payload = kotlinx.serialization.json.Json.encodeToString(
            kotlinx.serialization.serializer(),
            data.toString()
        )
        db.syncQueries.insertSyncQueueItem(
            entity_type = entityType,
            entity_id = entityId,
            action = action,
            payload = payload,
            created_at = now,
        )
    }
}

// ========== Mappers ==========

private fun org.androidbible.data.local.Markers.toMarker() = Marker(
    id = id,
    gid = gid,
    userId = user_id,
    bibleVersionId = bible_version_id,
    ari = ari.toInt(),
    kind = kind.toInt(),
    caption = caption,
    verseCount = verse_count.toInt(),
    color = color?.toInt(),
    createdAt = created_at,
    updatedAt = updated_at,
)

private fun org.androidbible.data.local.Labels.toLabel() = Label(
    id = id,
    gid = gid,
    userId = user_id,
    title = title,
    backgroundColor = background_color?.toInt(),
    createdAt = created_at,
    updatedAt = updated_at,
)
