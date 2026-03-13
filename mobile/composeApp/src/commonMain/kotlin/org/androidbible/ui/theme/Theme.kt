package org.androidbible.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

// Colors
val Primary = Color(0xFF1B5E20)
val OnPrimary = Color(0xFFFFFFFF)
val PrimaryContainer = Color(0xFFA5D6A7)
val OnPrimaryContainer = Color(0xFF002204)
val Secondary = Color(0xFF4E6352)
val OnSecondary = Color(0xFFFFFFFF)
val SecondaryContainer = Color(0xFFD0E8D2)
val OnSecondaryContainer = Color(0xFF0B1F12)
val Tertiary = Color(0xFF3B6470)
val OnTertiary = Color(0xFFFFFFFF)
val Background = Color(0xFFF8FAF0)
val OnBackground = Color(0xFF1A1C19)
val Surface = Color(0xFFF8FAF0)
val OnSurface = Color(0xFF1A1C19)
val Error = Color(0xFFBA1A1A)

// Highlight colors for verses
val HighlightYellow = Color(0xFFFFF9C4)
val HighlightGreen = Color(0xFFC8E6C9)
val HighlightBlue = Color(0xFFBBDEFB)
val HighlightPink = Color(0xFFF8BBD0)
val HighlightOrange = Color(0xFFFFE0B2)
val HighlightPurple = Color(0xFFE1BEE7)

private val LightColorScheme = lightColorScheme(
    primary = Primary,
    onPrimary = OnPrimary,
    primaryContainer = PrimaryContainer,
    onPrimaryContainer = OnPrimaryContainer,
    secondary = Secondary,
    onSecondary = OnSecondary,
    secondaryContainer = SecondaryContainer,
    onSecondaryContainer = OnSecondaryContainer,
    tertiary = Tertiary,
    onTertiary = OnTertiary,
    background = Background,
    onBackground = OnBackground,
    surface = Surface,
    onSurface = OnSurface,
    error = Error,
)

private val DarkColorScheme = darkColorScheme(
    primary = Color(0xFF81C784),
    onPrimary = Color(0xFF00390A),
    primaryContainer = Color(0xFF005313),
    onPrimaryContainer = Color(0xFFA5D6A7),
    secondary = Color(0xFFB5CCB7),
    onSecondary = Color(0xFF213526),
    secondaryContainer = Color(0xFF374B3B),
    onSecondaryContainer = Color(0xFFD0E8D2),
    tertiary = Color(0xFFA2CED9),
    onTertiary = Color(0xFF01363F),
    background = Color(0xFF1A1C19),
    onBackground = Color(0xFFE2E3DD),
    surface = Color(0xFF1A1C19),
    onSurface = Color(0xFFE2E3DD),
    error = Color(0xFFFFB4AB),
)

val AppTypography = Typography(
    displayLarge = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.Normal,
        fontSize = 57.sp,
        lineHeight = 64.sp,
    ),
    headlineLarge = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.SemiBold,
        fontSize = 32.sp,
        lineHeight = 40.sp,
    ),
    headlineMedium = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.SemiBold,
        fontSize = 28.sp,
        lineHeight = 36.sp,
    ),
    titleLarge = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.SemiBold,
        fontSize = 22.sp,
        lineHeight = 28.sp,
    ),
    titleMedium = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.Medium,
        fontSize = 16.sp,
        lineHeight = 24.sp,
    ),
    bodyLarge = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.Normal,
        fontSize = 16.sp,
        lineHeight = 24.sp,
    ),
    bodyMedium = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.Normal,
        fontSize = 14.sp,
        lineHeight = 20.sp,
    ),
    labelLarge = TextStyle(
        fontFamily = FontFamily.Default,
        fontWeight = FontWeight.Medium,
        fontSize = 14.sp,
        lineHeight = 20.sp,
    ),
)

// Night mode colors (AMOLED-friendly)
private val NightColorScheme = darkColorScheme(
    primary = Color(0xFF81C784),
    onPrimary = Color(0xFF00390A),
    primaryContainer = Color(0xFF003910),
    onPrimaryContainer = Color(0xFFA5D6A7),
    secondary = Color(0xFFB5CCB7),
    onSecondary = Color(0xFF213526),
    secondaryContainer = Color(0xFF2A3B2E),
    onSecondaryContainer = Color(0xFFD0E8D2),
    tertiary = Color(0xFFA2CED9),
    onTertiary = Color(0xFF01363F),
    background = Color(0xFF000000),
    onBackground = Color(0xFFD8D8D2),
    surface = Color(0xFF000000),
    onSurface = Color(0xFFD8D8D2),
    error = Color(0xFFFFB4AB),
)

// Sepia reading mode
private val SepiaColorScheme = lightColorScheme(
    primary = Color(0xFF5D4037),
    onPrimary = Color(0xFFFFFFFF),
    primaryContainer = Color(0xFFD7CCC8),
    onPrimaryContainer = Color(0xFF3E2723),
    secondary = Color(0xFF6D4C41),
    onSecondary = Color(0xFFFFFFFF),
    background = Color(0xFFF5ECD7),
    onBackground = Color(0xFF3E2723),
    surface = Color(0xFFF5ECD7),
    onSurface = Color(0xFF3E2723),
    error = Color(0xFFBA1A1A),
)

enum class ThemeMode { SYSTEM, LIGHT, DARK, NIGHT, SEPIA }

@Composable
fun AndroidBibleTheme(
    themeMode: ThemeMode = ThemeMode.SYSTEM,
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = when (themeMode) {
        ThemeMode.LIGHT -> LightColorScheme
        ThemeMode.DARK -> DarkColorScheme
        ThemeMode.NIGHT -> NightColorScheme
        ThemeMode.SEPIA -> SepiaColorScheme
        ThemeMode.SYSTEM -> if (darkTheme) DarkColorScheme else LightColorScheme
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = AppTypography,
        content = content,
    )
}
