package org.androidbible.util

import io.github.aakira.napier.Napier

/**
 * iOS CrashReporter implementation.
 *
 * Production: integrate Sentry Cocoa SDK via CocoaPods or SPM:
 *   SentrySDK.start { options in options.dsn = dsn }
 *
 * For now, logs via Napier. Replace with actual Sentry calls when SDK is added.
 */
actual class CrashReporter actual constructor() {

    actual fun initialize(dsn: String) {
        Napier.i("CrashReporter initialized (iOS)", tag = "CrashReporter")
        // SentrySDK.start { options in
        //     options.dsn = dsn
        //     options.tracesSampleRate = 1.0
        //     options.enableAutoSessionTracking = true
        // }
    }

    actual fun reportException(throwable: Throwable) {
        Napier.e("Non-fatal: ${throwable.message}", throwable, tag = "CrashReporter")
        // SentrySDK.capture(error: throwable.asNSError())
    }

    actual fun setUserId(userId: String?) {
        Napier.d("Set user: $userId", tag = "CrashReporter")
        // let user = User(); user.userId = userId
        // SentrySDK.setUser(user)
    }

    actual fun addBreadcrumb(message: String, category: String) {
        Napier.d("Breadcrumb [$category]: $message", tag = "CrashReporter")
        // let crumb = Breadcrumb()
        // crumb.message = message; crumb.category = category
        // SentrySDK.addBreadcrumb(crumb)
    }

    actual fun setTag(key: String, value: String) {
        Napier.d("Tag $key=$value", tag = "CrashReporter")
        // SentrySDK.configureScope { scope in scope.setTag(value: value, key: key) }
    }
}
