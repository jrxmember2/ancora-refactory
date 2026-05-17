package br.com.serratech.ancora.clientes.ui.screens.biometric

import android.content.Context
import android.content.ContextWrapper
import androidx.biometric.BiometricPrompt
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import br.com.serratech.ancora.clientes.R
import androidx.biometric.BiometricManager
import br.com.serratech.ancora.clientes.ui.components.BiometricUnlockCard

@Composable
fun BiometricScreen(
    modifier: Modifier = Modifier,
    onUnlocked: () -> Unit,
    onUsePassword: () -> Unit,
) {
    val context = LocalContext.current
    var promptRequested by remember { mutableStateOf(false) }
    var promptError by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        if (!promptRequested) {
            promptRequested = true
            showBiometricPrompt(
                context = context,
                onUnlocked = onUnlocked,
                onError = { message -> promptError = message },
            )
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        BiometricUnlockCard(
            onUnlock = {
                promptError = null
                showBiometricPrompt(
                    context = context,
                    onUnlocked = onUnlocked,
                    onError = { message -> promptError = message },
                )
            },
            onUsePassword = onUsePassword,
        )
        promptError?.let {
            Text(
                text = it,
                modifier = Modifier.padding(top = 12.dp),
                color = MaterialTheme.colorScheme.error,
                style = MaterialTheme.typography.bodyMedium,
            )
        }
    }
}

private fun showBiometricPrompt(
    context: Context,
    onUnlocked: () -> Unit,
    onError: (String) -> Unit,
) {
    val activity = context.findFragmentActivity()
        ?: return onError("Nao foi possivel abrir a biometria neste aparelho.")
    val prompt = BiometricPrompt(
        activity,
        ContextCompat.getMainExecutor(context),
        object : BiometricPrompt.AuthenticationCallback() {
            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                onUnlocked()
            }

            override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                onError(errString.toString())
            }

            override fun onAuthenticationFailed() {
                onError("Biometria nao reconhecida. Tente novamente ou entre com senha.")
            }
        },
    )

    prompt.authenticate(
        BiometricPrompt.PromptInfo.Builder()
            .setTitle(context.getString(R.string.biometric_title))
            .setSubtitle(context.getString(R.string.biometric_subtitle))
            .setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_WEAK)
            .build()
    )
}

private tailrec fun Context.findFragmentActivity(): FragmentActivity? = when (this) {
    is FragmentActivity -> this
    is ContextWrapper -> baseContext.findFragmentActivity()
    else -> null
}
