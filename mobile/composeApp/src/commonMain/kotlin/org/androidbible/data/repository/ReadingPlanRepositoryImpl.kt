package org.androidbible.data.repository

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToList
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.IO
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.model.ReadingPlan
import org.androidbible.domain.model.ReadingPlanDay
import org.androidbible.domain.model.ReadingPlanProgress
import org.androidbible.domain.repository.ReadingPlanRepository
import kotlinx.datetime.Clock

class ReadingPlanRepositoryImpl(
    private val db: AndroidBibleDatabase,
    private val api: ApiService,
) : ReadingPlanRepository {

    override fun getReadingPlans(): Flow<List<ReadingPlan>> {
        return db.syncQueries.getAllReadingPlans().asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toReadingPlan() }
        }
    }

    override suspend fun getReadingPlan(id: Long): ReadingPlan? {
        return db.syncQueries.getReadingPlanById(id).executeAsOneOrNull()?.toReadingPlan()
    }

    override fun getDays(planId: Long): Flow<List<ReadingPlanDay>> {
        return db.syncQueries.getReadingPlanDays(planId).asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toReadingPlanDay() }
        }
    }

    override fun getProgress(planId: Long): Flow<List<ReadingPlanProgress>> {
        return db.syncQueries.getReadingPlanProgress(planId).asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map {
                ReadingPlanProgress(
                    id = it.id,
                    userId = it.user_id ?: 0,
                    readingPlanId = it.reading_plan_id,
                    readingPlanDayId = it.reading_plan_day_id,
                    completedAt = it.completed_at,
                    createdAt = it.created_at,
                )
            }
        }
    }

    override suspend fun markDayComplete(planId: Long, dayId: Long) {
        val now = Clock.System.now().toString()
        db.syncQueries.insertReadingPlanProgress(
            server_id = null,
            user_id = null,
            reading_plan_id = planId,
            reading_plan_day_id = dayId,
            completed_at = now,
            is_synced = 0,
            created_at = now,
        )
        // Also attempt API call
        try {
            api.markDayComplete(planId, dayId)
        } catch (_: Exception) {
            // Offline - will sync later
        }
    }

    override suspend fun syncPlans() {
        val plans = api.getReadingPlans()
        db.transaction {
            plans.forEach { p ->
                db.syncQueries.insertReadingPlan(
                    id = p.id,
                    title = p.title,
                    description = p.description,
                    total_days = p.totalDays.toLong(),
                    is_active = if (p.isActive) 1L else 0L,
                    created_at = p.createdAt,
                )
            }
        }
    }
}

private fun org.androidbible.data.local.Reading_plans.toReadingPlan() = ReadingPlan(
    id = id,
    title = title,
    description = description,
    totalDays = total_days.toInt(),
    isActive = is_active == 1L,
    createdAt = created_at,
)

private fun org.androidbible.data.local.Reading_plan_days.toReadingPlanDay() = ReadingPlanDay(
    id = id,
    readingPlanId = reading_plan_id,
    dayNumber = day_number.toInt(),
    title = title,
    description = description,
    ariRanges = ari_ranges,
)
