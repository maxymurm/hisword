package org.androidbible.data.sync

import io.github.aakira.napier.Napier
import kotlinx.datetime.Instant
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.domain.model.SyncEvent

/**
 * Conflict resolver using last-write-wins (LWW) with echo prevention.
 *
 * Rules:
 * 1. Echo prevention: events from this device are skipped (handled in SyncEngine).
 * 2. GID match: if a local entity has the same GID, compare updated_at timestamps.
 * 3. LWW: the event with the later updated_at wins.
 * 4. Create conflict: if GID already exists locally and server event is "create",
 *    treat as "update" (idempotent upsert via INSERT OR REPLACE).
 * 5. Delete wins: if server says "delete", always apply (tombstone).
 */
class SyncConflictResolver {

    /**
     * Determine whether the remote event should be applied.
     * Returns true if the event should be applied, false if it should be skipped.
     */
    fun resolve(event: SyncEvent, db: AndroidBibleDatabase): Boolean {
        // Deletes always win — server is authoritative for deletes
        if (event.action == "delete") return true

        // Check for local version of this entity
        val localUpdatedAt = findLocalUpdatedAt(event, db)
            ?: return true // No local version → always apply

        val remoteUpdatedAt = event.updatedAt
            ?: return true // No timestamp on remote → apply (can't compare)

        return try {
            val localTime = Instant.parse(localUpdatedAt)
            val remoteTime = Instant.parse(remoteUpdatedAt)
            // LWW: remote wins if it's newer or equal (server is tiebreaker)
            remoteTime >= localTime
        } catch (e: Exception) {
            Napier.w("Failed to parse timestamps for conflict resolution: ${e.message}", tag = "Sync")
            true // Apply on parse failure (safe default)
        }
    }

    private fun findLocalUpdatedAt(event: SyncEvent, db: AndroidBibleDatabase): String? {
        return when (event.entityType) {
            "marker" -> {
                db.markerQueries.getMarkerByGid(event.entityId)
                    .executeAsOneOrNull()?.updated_at
            }
            "label" -> {
                db.markerQueries.getLabelByGid(event.entityId)
                    .executeAsOneOrNull()?.updated_at
            }
            else -> null
        }
    }
}
