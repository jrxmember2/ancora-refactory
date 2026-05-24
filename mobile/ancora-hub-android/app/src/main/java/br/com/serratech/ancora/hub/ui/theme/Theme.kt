package br.com.serratech.ancora.hub.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.graphics.Color

private val LightScheme = lightColorScheme(
    primary = WineRed,
    onPrimary = Color.White,
    primaryContainer = WineRedSoft,
    onPrimaryContainer = Ink,
    secondary = Ocean,
    onSecondary = Color.White,
    secondaryContainer = OceanSoft,
    onSecondaryContainer = Ink,
    tertiary = Amber,
    onTertiary = Color.White,
    tertiaryContainer = AmberSoft,
    onTertiaryContainer = Ink,
    background = Sand,
    onBackground = Ink,
    surface = Paper,
    onSurface = Ink,
    surfaceVariant = Mist,
    onSurfaceVariant = Slate,
    surfaceContainer = Color(0xFFF7F2EB),
    outline = Color(0xFFD4C8BE),
    error = Danger,
    onError = Color.White,
    errorContainer = DangerSoft,
    onErrorContainer = Ink,
)

private val DarkScheme = darkColorScheme(
    primary = Color(0xFFF2B8B0),
    onPrimary = Color(0xFF551111),
    primaryContainer = Color(0xFF712020),
    onPrimaryContainer = Color(0xFFFFDAD4),
    secondary = Color(0xFFB8D9DF),
    onSecondary = Color(0xFF213A40),
    secondaryContainer = Color(0xFF2E4C54),
    onSecondaryContainer = Color(0xFFD4EEF4),
    tertiary = Color(0xFFF3C289),
    onTertiary = Color(0xFF4F2500),
    tertiaryContainer = Color(0xFF6A3A0B),
    onTertiaryContainer = Color(0xFFFFDDBA),
    background = Color(0xFF171412),
    onBackground = Color(0xFFECE0D8),
    surface = Color(0xFF221E1B),
    onSurface = Color(0xFFECE0D8),
    surfaceVariant = Color(0xFF3A342F),
    onSurfaceVariant = Color(0xFFD2C4BB),
    surfaceContainer = Color(0xFF2A2521),
    outline = Color(0xFF726A63),
    error = Color(0xFFFFB4AB),
    onError = Color(0xFF690005),
    errorContainer = Color(0xFF93000A),
    onErrorContainer = Color(0xFFFFDAD6),
)

@Composable
fun AncoraHubTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    CompositionLocalProvider(
        LocalAncoraSpacing provides AncoraSpacing(),
    ) {
        MaterialTheme(
            colorScheme = if (darkTheme) DarkScheme else LightScheme,
            typography = AncoraHubTypography,
            content = content,
        )
    }
}
