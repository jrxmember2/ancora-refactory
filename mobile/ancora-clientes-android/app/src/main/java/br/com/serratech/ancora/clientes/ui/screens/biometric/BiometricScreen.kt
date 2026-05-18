package br.com.serratech.ancora.clientes.ui.screens.biometric

import android.content.Context
import android.content.ContextWrapper
import androidx.biometric.BiometricPrompt
import androidx.compose.runtime.DisposableEffect
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
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import br.com.serratech.ancora.clientes.R
import androidx.biometric.BiometricManager
import br.com.serratech.ancora.clientes.ui.components.BiometricUnlockCard
import kotlinx.coroutines.delay

@Composable
fun BiometricScreen(
    modifier: Modifier = Modifier,
    onUnlocked: () -> Unit,
    onUsePassword: () -> Unit,
    onBiometricUnavailable: () -> Unit,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    var promptRequested by remember { mutableStateOf(false) }
    var promptError by remember { mutableStateOf<String?>(null) }
    var isResumed by remember { mutableStateOf(false) }

    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            isResumed = event == Lifecycle.Event.ON_RESUME
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
        }
    }

    fun requestBiometricPrompt() {
        promptError = null
        showBiometricPrompt(
            context = context,
            onUnlocked = onUnlocked,
            onUsePassword = onUsePassword,
            onBiometricUnavailable = onBiometricUnavailable,
            onError = { message -> promptError = message },
        )
    }

    LaunchedEffect(isResumed) {
        if (isResumed && !promptRequested) {
            promptRequested = true
            delay(350)
            if (isResumed) {
                requestBiometricPrompt()
            }
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
                requestBiometricPrompt()
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
    onUsePassword: () -> Unit,
    onBiometricUnavailable: () -> Unit,
    onError: (String) -> Unit,
) {
    val activity = context.findFragmentActivity()
        ?: return onBiometricUnavailable()
    val biometricStatus = BiometricManager.from(context)
        .canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK)
    if (biometricStatus != BiometricManager.BIOMETRIC_SUCCESS) {
        onBiometricUnavailable()
        return
    }

    runCatching {
        val prompt = BiometricPrompt(
            activity,
            ContextCompat.getMainExecutor(context),
            object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    onUnlocked()
                }

                override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                    if (errorCode == BiometricPrompt.ERROR_NEGATIVE_BUTTON) {
                        onUsePassword()
                        return
                    }

                    if (
                        errorCode == BiometricPrompt.ERROR_HW_NOT_PRESENT ||
                        errorCode == BiometricPrompt.ERROR_HW_UNAVAILABLE ||
                        errorCode == BiometricPrompt.ERROR_NO_BIOMETRICS ||
                        errorCode == BiometricPrompt.ERROR_SECURITY_UPDATE_REQUIRED
                    ) {
                        onBiometricUnavailable()
                        return
                    }

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
                .setNegativeButtonText(context.getString(R.string.biometric_negative))
                .setConfirmationRequired(false)
                .build()
        )
    }.onFailure {
        onBiometricUnavailable()
    }
}

private tailrec fun Context.findFragmentActivity(): FragmentActivity? = when (this) {
    is FragmentActivity -> this
    is ContextWrapper -> baseContext.findFragmentActivity()
    else -> null
}
