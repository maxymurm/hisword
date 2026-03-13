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
import org.androidbible.domain.model.ProgressMark
import org.androidbible.domain.model.ProgressMarkHistory
import org.androidbible.domain.repository.ProgressRepository
import com.benasher44.uuid.uuid4

class ProgressRepositoryImpl(
    private val db: AndroidBibleDatabase,
    private val api: ApiService,
) : ProgressRepository {

    override fun getProgressMarks(): Flow<List<ProgressMark>> {
        return db.markerQueries.getAllProgressMarks().asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toProgressMark() }
        }
    }

    override suspend fun getProgressMark(preset: Int): ProgressMark? {
        return db.markerQueries.getProgressMarkByPreset(preset.toLong()).executeAsOneOrNull()?.toProgressMark()
    }

    override suspend fun createOrUpdate(progressMark: ProgressMark): ProgressMark {
        val now = Clock.System.now().toString()
        val existing = getProgressMark(progressMark.preset)

        if (existing != null) {
            // Record history
            db.markerQueries.insertProgressMarkHistory(
                progress_mark_id = existing.id,
                ari = existing.ari.toLong(),
                created_at = now,
            )
            // Update
            db.markerQueries.updateProgressMark(
                ari = progressMark.ari.toLong(),
                caption = progressMark.caption,
                modify_time = now,
                updated_at = now,
                id = existing.id,
            )
            return existing.copy(ari = progressMark.ari, caption = progressMark.caption, modifyTime = now)
        } else {
            val gid = uuid4().toString()
            db.markerQueries.insertProgressMark(
                id = null,
                gid = gid,
                server_id = null,
                user_id = null,
                preset = progressMark.preset.toLong(),
                ari = progressMark.ari.toLong(),
                caption = progressMark.caption,
                modify_time = now,
                is_synced = 0,
                created_at = now,
                updated_at = now,
            )
            return progressMark.copy(gid = gid, modifyTime = now, createdAt = now)
        }
    }

    override suspend fun deleteProgressMark(id: Long) {
        db.markerQueries.deleteProgressMark(id)
    }

    override fun getHistory(progressMarkId: Long): Flow<List<ProgressMarkHistory>> {
        return db.markerQueries.getProgressMarkHistory(progressMarkId).asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map {
                ProgressMarkHistory(
                    id = it.id,
                    progressMarkId = it.progress_mark_id,
                    ari = it.ari.toInt(),
                    createdAt = it.created_at,
                )
            }
        }
    }
}

private fun org.androidbible.data.local.Progress_marks.toProgressMark() = ProgressMark(
    id = id,
    gid = gid,
    userId = user_id,
    preset = preset.toInt(),
    ari = ari.toInt(),
    caption = caption,
    modifyTime = modify_time,
    createdAt = created_at,
    updatedAt = updated_at,
)
