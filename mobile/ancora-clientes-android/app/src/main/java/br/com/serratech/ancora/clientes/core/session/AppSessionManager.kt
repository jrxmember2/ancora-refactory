package br.com.serratech.ancora.clientes.core.session

import br.com.serratech.ancora.clientes.data.local.AppPreferencesDataSource
import br.com.serratech.ancora.clientes.data.local.SecureTokenStore

enum class LaunchDestination {
    Setup,
    Login,
    Biometric,
    Home,
}

class AppSessionManager(
    private val preferences: AppPreferencesDataSource,
    private val secureTokenStore: SecureTokenStore,
) {
    suspend fun resolveLaunchDestination(): LaunchDestination {
        val baseUrl = preferences.instanceBaseUrl()
        if (baseUrl.isBlank()) {
            return LaunchDestination.Setup
        }

        val token = secureTokenStore.currentToken()
        if (token.isNullOrBlank()) {
            return LaunchDestination.Login
        }

        return if (preferences.isBiometricEnabled()) {
            LaunchDestination.Biometric
        } else {
            LaunchDestination.Home
        }
    }

    suspend fun saveBaseUrl(baseUrl: String) {
        preferences.setInstanceBaseUrl(baseUrl)
    }

    suspend fun saveSession(accessToken: String) {
        secureTokenStore.saveToken(accessToken)
    }

    suspend fun enableBiometric(enabled: Boolean, markPrompted: Boolean = true) {
        preferences.setBiometricEnabled(enabled)
        if (markPrompted) {
            preferences.setBiometricPrompted(true)
        }
    }

    suspend fun clearSession(clearInstance: Boolean = false) {
        secureTokenStore.clear()
        preferences.setBiometricEnabled(false)
        preferences.setBiometricPrompted(false)
        preferences.clearFcmToken()
        if (clearInstance) {
            preferences.clearInstanceBaseUrl()
        }
    }
}
