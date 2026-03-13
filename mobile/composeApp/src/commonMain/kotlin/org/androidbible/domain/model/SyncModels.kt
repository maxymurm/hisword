package org.androidbible.domain.model

import kotlinx.serialization.Serializable

@Serializable
data class SyncEvent(
    val id: Long = 0,
    val userId: Long,
    val entityType: String,
    val entityId: String,
    val action: String,
    val payload: String, // JSON payload
    val version: Long,
    val createdAt: String? = null,
)

@Serializable
data class SyncPullRequest(
    val lastVersion: Long = 0,
    val deviceId: String,
)

@Serializable
data class SyncPullResponse(
    val events: List<SyncEvent>,
    val currentVersion: Long,
)

@Serializable
data class SyncPushRequest(
    val events: List<SyncEventPayload>,
    val deviceId: String,
)

@Serializable
data class SyncEventPayload(
    val entityType: String,
    val entityId: String,
    val action: String,
    val payload: String,
)

@Serializable
data class SyncPushResponse(
    val processed: Int,
    val currentVersion: Long,
)

@Serializable
data class SyncStatus(
    val currentVersion: Long,
    val deviceVersion: Long,
    val pendingEvents: Int,
)
