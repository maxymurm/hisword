package org.androidbible.ui.screens.onboarding

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import cafe.adriel.voyager.core.model.ScreenModel
import cafe.adriel.voyager.core.model.rememberScreenModel
import cafe.adriel.voyager.core.screen.Screen
import cafe.adriel.voyager.navigator.LocalNavigator
import cafe.adriel.voyager.navigator.currentOrThrow
import com.russhwolf.settings.Settings
import kotlinx.coroutines.launch
import org.androidbible.ui.screens.home.HomeScreen
import org.androidbible.ui.screens.versions.VersionsScreen
import org.koin.core.component.KoinComponent
import org.koin.core.component.inject

private const val KEY_ONBOARDING_COMPLETE = "onboarding_complete"

/**
 * First-launch onboarding screen with welcome pages and Bible version selection.
 *
 * Checks [Settings] for completion flag. Once finished, navigates to HomeScreen
 * and marks onboarding complete so it never shows again.
 */
class OnboardingScreen : Screen {

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    override fun Content() {
        val navigator = LocalNavigator.currentOrThrow
        val screenModel = rememberScreenModel { OnboardingScreenModel() }
        val pagerState = rememberPagerState(pageCount = { 4 })
        val coroutineScope = rememberCoroutineScope()

        Scaffold { padding ->
            Column(
                modifier = Modifier
                    .padding(padding)
                    .fillMaxSize(),
            ) {
                HorizontalPager(
                    state = pagerState,
                    modifier = Modifier.weight(1f),
                ) { page ->
                    when (page) {
                        0 -> OnboardingPage(
                            title = "Welcome to Android Bible",
                            description = "Read, study, and meditate on God\u2019s Word\u2014anytime, anywhere.",
                            emoji = "\uD83D\uDCD6",
                        )
                        1 -> OnboardingPage(
                            title = "Multiple Translations",
                            description = "Access hundreds of Bible versions and languages. " +
                                "Read offline with downloaded modules.",
                            emoji = "\uD83C\uDF0D",
                        )
                        2 -> OnboardingPage(
                            title = "Study Tools",
                            description = "Bookmarks, highlights, notes, cross-references, " +
                                "Strong\u2019s numbers, and word studies\u2014all at your fingertips.",
                            emoji = "\uD83D\uDD0D",
                        )
                        3 -> OnboardingPage(
                            title = "Choose Your Bible",
                            description = "Select a Bible version to get started. " +
                                "You can always add more later.",
                            emoji = "\u2B50",
                        )
                    }
                }

                // Page indicators
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 8.dp),
                    horizontalArrangement = Arrangement.Center,
                ) {
                    repeat(4) { index ->
                        val color = if (index == pagerState.currentPage) {
                            MaterialTheme.colorScheme.primary
                        } else {
                            MaterialTheme.colorScheme.outlineVariant
                        }
                        Surface(
                            modifier = Modifier
                                .padding(horizontal = 4.dp)
                                .size(8.dp)
                                .clip(CircleShape),
                            color = color,
                            content = {},
                        )
                    }
                }

                // Bottom buttons
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 24.dp, vertical = 16.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    if (pagerState.currentPage > 0) {
                        TextButton(
                            onClick = {
                                coroutineScope.launch {
                                    pagerState.animateScrollToPage(pagerState.currentPage - 1)
                                }
                            },
                        ) {
                            Text("Back")
                        }
                    } else {
                        Spacer(modifier = Modifier.width(1.dp))
                    }

                    if (pagerState.currentPage < 3) {
                        Button(
                            onClick = {
                                coroutineScope.launch {
                                    pagerState.animateScrollToPage(pagerState.currentPage + 1)
                                }
                            },
                        ) {
                            Text("Next")
                        }
                    } else {
                        Column(horizontalAlignment = Alignment.End) {
                            Button(
                                onClick = {
                                    screenModel.completeOnboarding()
                                    navigator.replaceAll(HomeScreen())
                                    navigator.push(VersionsScreen())
                                },
                            ) {
                                Text("Choose Bible Version")
                            }
                            Spacer(modifier = Modifier.height(4.dp))
                            TextButton(
                                onClick = {
                                    screenModel.completeOnboarding()
                                    navigator.replaceAll(HomeScreen())
                                },
                            ) {
                                Text("Skip for now")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun OnboardingPage(
    title: String,
    description: String,
    emoji: String,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            text = emoji,
            style = MaterialTheme.typography.displayLarge,
        )
        Spacer(modifier = Modifier.height(32.dp))
        Text(
            text = title,
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center,
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text(
            text = description,
            style = MaterialTheme.typography.bodyLarge,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

class OnboardingScreenModel : ScreenModel, KoinComponent {

    private val settings: Settings by inject()

    fun completeOnboarding() {
        settings.putBoolean(KEY_ONBOARDING_COMPLETE, true)
    }

    companion object {
        fun isOnboardingComplete(settings: Settings): Boolean {
            return settings.getBoolean(KEY_ONBOARDING_COMPLETE, false)
        }
    }
}
