package org.androidbible.data.sync

import io.github.aakira.napier.Napier

/**
 * iOS BackgroundSyncScheduler using BGTaskScheduler.
 *
 * Full integration requires:
 *   Add BGTaskSchedulerPermittedIdentifiers to Info.plist
 *   Register task in application:didFinishLaunchingWithOptions:
 *
 * Task identifier: "org.androidbible.sync.refresh"
 */
actual class BackgroundSyncScheduler {

    actual fun schedulePeriodicSync() {
        // TODO: full BGTaskScheduler integration
        //   BGTaskScheduler.shared.register(
        //       forTaskWithIdentifier: "org.androidbible.sync.refresh",
        //       using: nil
        //   ) { task in
        //       self.handleAppRefresh(task as! BGAppRefreshTask)
        //   }
        //
        //   let request = BGAppRefreshTaskRequest(identifier: "org.androidbible.sync.refresh")
        //   request.earliestBeginDate = Date(timeIntervalSinceNow: 15 * 60)
        //   try BGTaskScheduler.shared.submit(request)
        Napier.i("BackgroundSync: iOS stub — add BGTaskScheduler integration", tag = "Sync")
    }

    actual fun cancelAll() {
        // BGTaskScheduler.shared.cancelAllTaskRequests()
        Napier.d("BackgroundSync: iOS cancelAll stub", tag = "Sync")
    }
}
