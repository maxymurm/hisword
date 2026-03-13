package org.androidbible.app

import androidx.compose.runtime.Composable
import cafe.adriel.voyager.navigator.Navigator
import cafe.adriel.voyager.transitions.SlideTransition
import org.androidbible.ui.screens.home.HomeScreen
import org.androidbible.ui.theme.AndroidBibleTheme

@Composable
fun App() {
    AndroidBibleTheme {
        Navigator(HomeScreen()) { navigator ->
            SlideTransition(navigator)
        }
    }
}
