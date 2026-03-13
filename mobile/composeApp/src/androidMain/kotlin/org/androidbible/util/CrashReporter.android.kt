package org.androidbible.util

import io.github.aakira.napier.Napier

/**
 * Android CrashReporter implementation.
 *
 * Production: integrate Sentry Android SDK:
 *   SentryAndroid.init(context) { options -> options.dsn = dsn }
 *
 * For now, logs via Napier. Replace with actual Sentry calls when SDK is added.
 */
actual class CrashReporter actual constructor() {

    actual fun initialize(dsn: String) {
        Napier.i("CrashReporter initialized (Android)", tag = "CrashReporter")
        // SentryAndroid.init(applicationContext) { options ->
        //     options.dsn = dsn
        //     options.tracesSampleRate = 1.0
        //     options.isEnableAutoSessionTracking = true
        // }
    }

    actual fun reportException(throwable: Throwable) {
        Napier.e("Non-fatal: ${throwable.message}", throwable, tag = "CrashReporter")
        // Sentry.captureException(throwable)
    }

    actual fun setUserId(userId: String?) {
        Napier.d("Set user: $userId", tag = "CrashReporter")
        // Sentry.setUser(User().apply { id = userId })
    }

    actual fun addBreadcrumb(message: String, category: String) {
        Napier.d("Breadcrumb [$category]: $message", tag = "CrashReporter")
        // Sentry.addBreadcrumb(Breadcrumb().apply {
        //     this.message = message; this.category = category
        // })
    }

    actual fun setTag(key: String, value: String) {
        Napier.d("Tag $key=$value", tag = "CrashReporter")
        // Sentry.setTag(key, value)
    }
}
