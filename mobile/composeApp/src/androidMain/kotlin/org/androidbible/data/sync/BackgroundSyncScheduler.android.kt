package org.androidbible.data.sync

import io.github.aakira.napier.Napier

/**
 * Android BackgroundSyncScheduler using WorkManager.
 *
 * Full integration requires:
 *   implementation("androidx.work:work-runtime-ktx:2.9.1")
 *
 * Usage in Application.onCreate():
 *   BackgroundSyncScheduler().schedulePeriodicSync()
 */
actual class BackgroundSyncScheduler {

    actual fun schedulePeriodicSync() {
        // TODO: full WorkManager integration when dependency is added
        //   val constraints = Constraints.Builder()
        //       .setRequiredNetworkType(NetworkType.CONNECTED)
        //       .build()
        //   val work = PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES)
        //       .setConstraints(constraints)
        //       .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES)
        //       .build()
        //   WorkManager.getInstance(context)
        //       .enqueueUniquePeriodicWork("hisword_sync", ExistingPeriodicWorkPolicy.KEEP, work)
        Napier.i("BackgroundSync: Android stub — add WorkManager dependency", tag = "Sync")
    }

    actual fun cancelAll() {
        // WorkManager.getInstance(context).cancelUniqueWork("hisword_sync")
        Napier.d("BackgroundSync: cancelAll stub", tag = "Sync")
    }
}
