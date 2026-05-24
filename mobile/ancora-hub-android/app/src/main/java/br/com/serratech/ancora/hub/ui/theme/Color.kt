package br.com.serratech.ancora.hub.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

val WineRed = Color(0xFF8E1C1C)
val WineRedSoft = Color(0xFFF7DDD6)
val Ink = Color(0xFF1F1A17)
val Sand = Color(0xFFF5F1EA)
val Paper = Color(0xFFFFFCF7)
val Mist = Color(0xFFEDE6DD)
val Ocean = Color(0xFF275F7A)
val OceanSoft = Color(0xFFDCECF4)
val Moss = Color(0xFF206A4A)
val MossSoft = Color(0xFFDBF0E5)
val Amber = Color(0xFFA05A1A)
val AmberSoft = Color(0xFFF6E4CE)
val Slate = Color(0xFF5D5752)
val SlateSoft = Color(0xFFE8E0DA)
val Danger = Color(0xFFBA1A1A)
val DangerSoft = Color(0xFFFFE0DE)

enum class AncoraTone {
    Brand,
    Info,
    Success,
    Warning,
    Error,
    Neutral,
}

data class AncoraTonePalette(
    val container: Color,
    val content: Color,
    val border: Color,
)

@Composable
fun ancoraTonePalette(tone: AncoraTone): AncoraTonePalette = when (tone) {
    AncoraTone.Brand -> AncoraTonePalette(
        container = MaterialTheme.colorScheme.primaryContainer,
        content = MaterialTheme.colorScheme.primary,
        border = MaterialTheme.colorScheme.primary.copy(alpha = 0.18f),
    )

    AncoraTone.Info -> AncoraTonePalette(
        container = OceanSoft,
        content = Ocean,
        border = Ocean.copy(alpha = 0.18f),
    )

    AncoraTone.Success -> AncoraTonePalette(
        container = MossSoft,
        content = Moss,
        border = Moss.copy(alpha = 0.18f),
    )

    AncoraTone.Warning -> AncoraTonePalette(
        container = AmberSoft,
        content = Amber,
        border = Amber.copy(alpha = 0.18f),
    )

    AncoraTone.Error -> AncoraTonePalette(
        container = DangerSoft,
        content = Danger,
        border = Danger.copy(alpha = 0.18f),
    )

    AncoraTone.Neutral -> AncoraTonePalette(
        container = SlateSoft,
        content = Slate,
        border = Slate.copy(alpha = 0.16f),
    )
}

fun ancoraToneFromAccent(accent: String?): AncoraTone = when (accent?.lowercase()) {
    "blue", "info" -> AncoraTone.Info
    "success", "green" -> AncoraTone.Success
    "warning", "amber" -> AncoraTone.Warning
    "error", "danger" -> AncoraTone.Error
    "gray", "neutral" -> AncoraTone.Neutral
    else -> AncoraTone.Brand
}
