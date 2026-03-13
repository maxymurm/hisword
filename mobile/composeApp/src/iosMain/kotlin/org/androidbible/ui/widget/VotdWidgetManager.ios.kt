package org.androidbible.ui.widget

import org.androidbible.data.remote.VerseOfTheDay

/**
 * iOS implementation of VotdWidgetManager.
 *
 * Uses WidgetKit via a separate Swift WidgetExtension target.
 * The Kotlin side writes VOTD data to a shared App Group container
 * (UserDefaults with app group suite) so the WidgetKit extension
 * can read and display it.
 *
 * Setup requires:
 * 1. WidgetExtension target in Xcode with SwiftUI widget
 * 2. Shared App Group between main app and widget extension
 * 3. NSUserDefaults(suiteName: "group.org.androidbible") data sharing
 * 4. WidgetCenter.shared.reloadAllTimelines() after data update
 */
actual class VotdWidgetManager {

    actual fun updateWidget(votd: VerseOfTheDay) {
        // Write to shared UserDefaults for WidgetKit access
        //
        // Implementation (via platform bridge):
        //   val defaults = NSUserDefaults(suiteName = "group.org.androidbible")
        //   defaults.setObject(votd.text, forKey = "votd_text")
        //   defaults.setObject(votd.reference, forKey = "votd_reference")
        //   defaults.setInteger(votd.ari.toLong(), forKey = "votd_ari")
        //   defaults.setObject(votd.versionName, forKey = "votd_version")
        //   defaults.synchronize()
        //
        //   WidgetCenter.shared.reloadAllTimelines()
    }

    actual fun isWidgetSupported(): Boolean = true
}

// The SwiftUI widget is defined in the iOS WidgetExtension target:
//
// @main
// struct VotdWidget: Widget {
//     let kind = "VotdWidget"
//     var body: some WidgetConfiguration {
//         StaticConfiguration(kind: kind, provider: VotdProvider()) { entry in
//             VotdWidgetView(entry: entry)
//         }
//         .configurationDisplayName("Verse of the Day")
//         .description("Daily Bible verse")
//         .supportedFamilies([.systemSmall, .systemMedium])
//     }
// }
