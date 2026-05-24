package br.com.serratech.ancora.hub.core.security

import android.content.Context
import android.content.ContextWrapper
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity

fun requestBiometricPrompt(
    context: Context,
    title: String,
    subtitle: String,
    negativeButtonText: String,
    onAuthenticated: () -> Unit,
    onNegativeAction: () -> Unit,
    onUnavailable: () -> Unit,
    onError: (String) -> Unit,
) {
    val activity = context.findFragmentActivity() ?: run {
        onUnavailable()
        return
    }

    val status = BiometricManager.from(context)
        .canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK)
    if (status != BiometricManager.BIOMETRIC_SUCCESS) {
        onUnavailable()
        return
    }

    runCatching {
        val prompt = BiometricPrompt(
            activity,
            ContextCompat.getMainExecutor(context),
            object : BiometricPrompt.AuthenticationCallback() {
                override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                    onAuthenticated()
                }

                override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                    if (
                        errorCode == BiometricPrompt.ERROR_NEGATIVE_BUTTON ||
                        errorCode == BiometricPrompt.ERROR_USER_CANCELED ||
                        errorCode == BiometricPrompt.ERROR_CANCELED
                    ) {
                        onNegativeAction()
                        return
                    }

                    if (
                        errorCode == BiometricPrompt.ERROR_HW_NOT_PRESENT ||
                        errorCode == BiometricPrompt.ERROR_HW_UNAVAILABLE ||
                        errorCode == BiometricPrompt.ERROR_NO_BIOMETRICS ||
                        errorCode == BiometricPrompt.ERROR_SECURITY_UPDATE_REQUIRED
                    ) {
                        onUnavailable()
                        return
                    }

                    onError("Não foi possível validar sua biometria agora.")
                }

                override fun onAuthenticationFailed() {
                    onError("Biometria não reconhecida. Tente novamente ou entre com e-mail e senha.")
                }
            },
        )

        prompt.authenticate(
            BiometricPrompt.PromptInfo.Builder()
                .setTitle(title)
                .setSubtitle(subtitle)
                .setNegativeButtonText(negativeButtonText)
                .setConfirmationRequired(false)
                .build(),
        )
    }.onFailure {
        onUnavailable()
    }
}

private tailrec fun Context.findFragmentActivity(): FragmentActivity? = when (this) {
    is FragmentActivity -> this
    is ContextWrapper -> baseContext.findFragmentActivity()
    else -> null
}
