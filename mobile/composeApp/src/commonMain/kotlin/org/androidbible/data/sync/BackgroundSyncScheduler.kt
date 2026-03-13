package org.androidbible.data.sync

/**
 * Platform abstraction for scheduling background sync.
 * Implementations:
 * - Android: WorkManager periodic work request
 * - iOS: BGTaskScheduler with BGAppRefreshTaskRequest
 */
expect class BackgroundSyncScheduler {
    /**
     * Schedule periodic background sync (typically every 15-60 minutes).
     */
    fun schedulePeriodicSync()

    /**
     * Cancel all scheduled background sync tasks.
     */
    fun cancelAll()
}
