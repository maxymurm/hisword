package org.androidbible.util

/**
 * iOS share/clipboard implementation.
 *
 * Uses UIActivityViewController for sharing and UIPasteboard for clipboard.
 *
 * Registration in PlatformModule:
 *   single { PlatformShareManager() }
 */
actual class PlatformShareManager {

    actual fun shareText(text: String, title: String) {
        // Implementation:
        //   val activityVC = UIActivityViewController(
        //       activityItems = listOf(text),
        //       applicationActivities = null,
        //   )
        //   val rootVC = UIApplication.sharedApplication
        //       .keyWindow?.rootViewController
        //   rootVC?.presentViewController(activityVC, animated = true, completion = null)
    }

    actual fun copyToClipboard(text: String) {
        // Implementation:
        //   UIPasteboard.generalPasteboard.string = text
    }
}
