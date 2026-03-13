package org.androidbible

import androidx.compose.ui.window.ComposeUIViewController
import org.androidbible.app.App
import org.androidbible.util.DeepLinkHandler

fun MainViewController() = ComposeUIViewController { App() }

/**
 * Called from Swift AppDelegate / SceneDelegate when a deep link URL is received.
 * Usage in Swift:
 *   MainViewControllerKt.handleDeepLink(url: url.absoluteString)
 */
fun handleDeepLink(url: String) {
    if (url.startsWith("bible://")) {
        DeepLinkHandler.emit(url)
    }
}
