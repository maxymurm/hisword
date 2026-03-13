package org.androidbible.ui.screens.splash

import androidx.compose.animation.core.*
import androidx.compose.foundation.layout.*
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.screen.Screen
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import com.russhwolf.settings.Settings
import kotlinx.coroutines.delay
import org.androidbible.ui.screens.home.HomeScreen
import org.androidbible.ui.screens.onboarding.OnboardingScreen
import org.androidbible.ui.screens.onboarding.OnboardingScreenModel
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

private const val SPLASH_DURATION_MS = 1500L

/**
 * Splash screen shown on app launch with a fade-in animation.
 * After the splash duration, navigates to either Onboarding or Home.
 */
class SplashScreen : Screen {

    private object Deps : KoinComponent {
        val settings: Settings by inject()
    }

    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val alpha by rememberInfiniteTransition(label = "splash").animateFloat(
            initialValue = 0.3f,
            targetValue = 1f,
            animationSpec = infiniteRepeatable(
                animation = tween(800, easing = EaseInOut),
                repeatMode = RepeatMode.Reverse,
            ),
            label = "pulse",
        )

        LaunchedEffect(Unit) {
            delay(SPLASH_DURATION_MS)
            val next = if (OnboardingScreenModel.isOnboardingComplete(Deps.settings)) {
                HomeScreen()
            } else {
                OnboardingScreen()
            }
            navigator.replaceAll(next)
        }

        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center,
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
                modifier = Modifier.alpha(alpha),
            ) {
                Text(
                    text = "\u271E",
                    style = MaterialTheme.typography.displayLarge,
                    color = MaterialTheme.colorScheme.primary,
                )
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Android Bible",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center,
                    color = MaterialTheme.colorScheme.onBackground,
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "His Word in your hands",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
