package org.androidbible.app

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import cafe.adriel.voyager.navigator.Navigator
import cafe.adriel.voyager.transitions.SlideTransition
import org.androidbible.ui.screens.bible.BibleReaderScreen
import org.androidbible.ui.screens.splash.SplashScreen
import org.androidbible.ui.theme.AndroidBibleTheme
import org.androidbible.util.Ari
import org.androidbible.util.DeepLink
import org.androidbible.util.DeepLinkHandler

@Composable
fun App() {
    AndroidBibleTheme {
        Navigator(SplashScreen()) { navigator ->
            LaunchedEffect(Unit) {
                DeepLinkHandler.deepLinks.collect { uri ->
                    val ref = DeepLink.parse(uri) ?: return@collect
                    val ari = Ari.encode(ref.bookId, ref.chapter, ref.verse)
                    navigator.push(BibleReaderScreen(initialAri = ari))
                }
            }

            SlideTransition(navigator)
        }
    }
}
