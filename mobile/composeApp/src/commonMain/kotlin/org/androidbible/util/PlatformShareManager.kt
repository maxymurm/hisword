package org.androidbible.util

/**
 * Platform-specific share and clipboard operations.
 *
 * Android: Intent.ACTION_SEND + ClipboardManager
 * iOS: UIActivityViewController + UIPasteboard
 */
expect class PlatformShareManager {
    fun shareText(text: String, title: String = "")
    fun copyToClipboard(text: String)
}
