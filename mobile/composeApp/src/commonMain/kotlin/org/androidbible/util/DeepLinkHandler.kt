package org.androidbible.util

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

/**
 * Global deep link handler that bridges platform-specific URI receivers
 * to the Compose navigation layer.
 *
 * Platform code calls [emit] when a deep link is received.
 * The App composable collects [deepLinks] and navigates accordingly.
 */
object DeepLinkHandler {

    private val _deepLinks = MutableSharedFlow<String>(extraBufferCapacity = 1)
    val deepLinks: SharedFlow<String> = _deepLinks.asSharedFlow()

    /**
     * Called from platform code (Android onNewIntent / iOS scene URL handler).
     */
    fun emit(uri: String) {
        _deepLinks.tryEmit(uri)
    }
}
