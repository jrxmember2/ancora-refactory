package br.com.serratech.ancora.clientes.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.ColorScheme
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

private val LightScheme: ColorScheme = lightColorScheme(
    primary = Color(0xFF941415),
    onPrimary = Color.White,
    primaryContainer = Color(0xFFF8E0DB),
    onPrimaryContainer = Color(0xFF3A0909),
    secondary = Color(0xFF7F2F21),
    onSecondary = Color.White,
    background = Color(0xFFF7F2EC),
    onBackground = Color(0xFF1F1F1F),
    surface = Color.White,
    onSurface = Color(0xFF1F1F1F),
    surfaceVariant = Color(0xFFF2F2F2),
    onSurfaceVariant = Color(0xFF565656),
    error = Color(0xFFB3261E),
)

private val DarkScheme: ColorScheme = darkColorScheme(
    primary = Color(0xFFF4BBB0),
    onPrimary = Color(0xFF5E0B0C),
    secondary = Color(0xFFE7BCAF),
    onSecondary = Color(0xFF4A170D),
    background = Color(0xFF191413),
    onBackground = Color(0xFFF5EAE5),
    surface = Color(0xFF231C1B),
    onSurface = Color(0xFFF5EAE5),
    surfaceVariant = Color(0xFF3A312F),
    onSurfaceVariant = Color(0xFFD9C5BF),
    error = Color(0xFFF2B8B5),
)

private val AncoraTypography = Typography(
    headlineLarge = TextStyle(
        fontFamily = FontFamily.Serif,
        fontWeight = FontWeight.Bold,
        fontSize = 30.sp,
        lineHeight = 36.sp,
    ),
    headlineSmall = TextStyle(
        fontFamily = FontFamily.Serif,
        fontWeight = FontWeight.Bold,
        fontSize = 24.sp,
        lineHeight = 30.sp,
    ),
    titleLarge = TextStyle(
        fontWeight = FontWeight.SemiBold,
        fontSize = 22.sp,
        lineHeight = 28.sp,
    ),
    titleMedium = TextStyle(
        fontWeight = FontWeight.SemiBold,
        fontSize = 18.sp,
        lineHeight = 24.sp,
    ),
    bodyLarge = TextStyle(
        fontSize = 16.sp,
        lineHeight = 24.sp,
    ),
    bodyMedium = TextStyle(
        fontSize = 14.sp,
        lineHeight = 22.sp,
    ),
    labelLarge = TextStyle(
        fontWeight = FontWeight.SemiBold,
        fontSize = 14.sp,
    ),
)

@Composable
fun AncoraClientesTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkScheme else LightScheme,
        typography = AncoraTypography,
        content = content,
    )
}
