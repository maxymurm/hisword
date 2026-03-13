package org.androidbible.util

/**
 * Android share/clipboard implementation.
 *
 * Uses Intent.ACTION_SEND for sharing and ClipboardManager for copying.
 * Requires Android Context injection via Koin.
 *
 * Registration in PlatformModule:
 *   single { PlatformShareManager(androidContext()) }
 */
actual class PlatformShareManager {

    actual fun shareText(text: String, title: String) {
        // Implementation:
        //   val context: Context = ... // injected via Koin
        //   val intent = Intent(Intent.ACTION_SEND).apply {
        //       type = "text/plain"
        //       putExtra(Intent.EXTRA_TEXT, text)
        //       if (title.isNotBlank()) putExtra(Intent.EXTRA_SUBJECT, title)
        //   }
        //   val chooser = Intent.createChooser(intent, title.ifBlank { "Share" })
        //   chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        //   context.startActivity(chooser)
    }

    actual fun copyToClipboard(text: String) {
        // Implementation:
        //   val context: Context = ... // injected via Koin
        //   val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        //   val clip = ClipData.newPlainText("Bible verse", text)
        //   clipboard.setPrimaryClip(clip)
    }
}
