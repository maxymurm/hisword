package org.androidbible.ui.widget

import org.androidbible.data.remote.VerseOfTheDay

/**
 * Platform-specific Verse of the Day widget.
 *
 * Android: Glance AppWidget
 * iOS: WidgetKit (SwiftUI via Kotlin/Native bridge)
 */
expect class VotdWidgetManager {
    /**
     * Update the widget with new VOTD data.
     * Called after fetching the verse of the day from the API.
     */
    fun updateWidget(votd: VerseOfTheDay)

    /**
     * Check if the platform supports home screen widgets.
     */
    fun isWidgetSupported(): Boolean
}
