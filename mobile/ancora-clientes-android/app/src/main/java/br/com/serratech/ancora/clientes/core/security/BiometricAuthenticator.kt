package br.com.serratech.ancora.clientes.core.security

import android.content.Context
import androidx.biometric.BiometricManager

class BiometricAuthenticator {
    fun isAvailable(context: Context): Boolean {
        val manager = BiometricManager.from(context)
        val result = manager.canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_STRONG or BiometricManager.Authenticators.DEVICE_CREDENTIAL)
        return result == BiometricManager.BIOMETRIC_SUCCESS
    }
}
