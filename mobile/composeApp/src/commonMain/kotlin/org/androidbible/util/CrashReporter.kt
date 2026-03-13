package org.androidbible.util

/**
 * Multiplatform crash reporting abstraction.
 *
 * Platform implementations can integrate Sentry, Firebase Crashlytics,
 * or other crash reporting SDKs.
 *
 * Registration: single { CrashReporter() }
 */
expect class CrashReporter() {

    /**
     * Initialize the crash reporter with the DSN/project key.
     * Should be called once at app startup.
     */
    fun initialize(dsn: String)

    /**
     * Report a non-fatal exception.
     */
    fun reportException(throwable: Throwable)

    /**
     * Set a user identifier for crash reports.
     */
    fun setUserId(userId: String?)

    /**
     * Add a breadcrumb for debugging context.
     */
    fun addBreadcrumb(message: String, category: String = "app")

    /**
     * Record a custom key-value pair for crash context.
     */
    fun setTag(key: String, value: String)
}
