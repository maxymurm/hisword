package org.androidbible.domain.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ReadingPlan(
    val id: Long = 0,
    val title: String,
    val description: String? = null,
    val totalDays: Int,
    @SerialName("is_active")
    val isActive: Boolean = true,
    val createdAt: String? = null,
)

@Serializable
data class ReadingPlanDay(
    val id: Long = 0,
    val readingPlanId: Long,
    val dayNumber: Int,
    val title: String? = null,
    val description: String? = null,
    val ariRanges: String, // JSON array of ARI ranges
)

@Serializable
data class ReadingPlanProgress(
    val id: Long = 0,
    val userId: Long,
    val readingPlanId: Long,
    val readingPlanDayId: Long,
    val completedAt: String? = null,
    val createdAt: String? = null,
)

@Serializable
data class Devotional(
    val id: Long = 0,
    val title: String,
    val body: String,
    val publishDate: String,
    val ariReference: Int? = null,
    val author: String? = null,
    @SerialName("is_published")
    val isPublished: Boolean = true,
    val createdAt: String? = null,
)

@Serializable
data class SongBook(
    val id: Long = 0,
    val title: String,
    val description: String? = null,
    @SerialName("is_active")
    val isActive: Boolean = true,
)

@Serializable
data class Song(
    val id: Long = 0,
    val songBookId: Long,
    val number: Int,
    val title: String,
    val lyrics: String,
    val author: String? = null,
    val tune: String? = null,
    val key: String? = null,
    val createdAt: String? = null,
)
