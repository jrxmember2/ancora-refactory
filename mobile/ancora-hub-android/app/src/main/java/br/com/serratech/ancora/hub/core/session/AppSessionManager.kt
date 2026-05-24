package br.com.serratech.ancora.hub.core.session

import br.com.serratech.ancora.hub.data.local.AppPreferencesDataSource
import br.com.serratech.ancora.hub.data.local.SecureTokenStore
import java.time.Instant

enum class LaunchDestination {
    Setup,
    Login,
    Biometric,
    Home,
}

data class SessionLaunchState(
    val hasSavedBaseUrl: Boolean,
    val hasSavedToken: Boolean,
    val biometricEnabled: Boolean,
    val localSessionExpired: Boolean,
    val secureStorageInvalidated: Boolean,
    val launchDestination: LaunchDestination,
)

class AppSessionManager(
    private val preferences: AppPreferencesDataSource,
    private val secureTokenStore: SecureTokenStore,
) {
    suspend fun resolveLaunchState(): SessionLaunchState {
        val baseUrl = preferences.instanceBaseUrl()
        if (baseUrl.isBlank()) {
            return SessionLaunchState(
                hasSavedBaseUrl = false,
                hasSavedToken = false,
                biometricEnabled = false,
                localSessionExpired = false,
                secureStorageInvalidated = false,
                launchDestination = LaunchDestination.Setup,
            )
        }

        val biometricEnabled = preferences.isBiometricEnabled()
        val tokenSnapshot = secureTokenStore.readTokenSnapshot()
        if (tokenSnapshot.invalidated) {
            clearSession(clearBiometricState = true)

            return SessionLaunchState(
                hasSavedBaseUrl = true,
                hasSavedToken = false,
                biometricEnabled = false,
                localSessionExpired = false,
                secureStorageInvalidated = true,
                launchDestination = LaunchDestination.Login,
            )
        }

        val hasSavedToken = !tokenSnapshot.token.isNullOrBlank()
        if (!hasSavedToken) {
            return SessionLaunchState(
                hasSavedBaseUrl = true,
                hasSavedToken = false,
                biometricEnabled = biometricEnabled,
                localSessionExpired = false,
                secureStorageInvalidated = false,
                launchDestination = LaunchDestination.Login,
            )
        }

        val expiresAt = preferences.sessionExpiresAt()
        val localSessionExpired = expiresAt.isNotBlank() && expiresAt.toInstantOrNull()?.isBefore(Instant.now()) == true
        if (localSessionExpired) {
            clearSession(clearBiometricState = false)

            return SessionLaunchState(
                hasSavedBaseUrl = true,
                hasSavedToken = true,
                biometricEnabled = biometricEnabled,
                localSessionExpired = true,
                secureStorageInvalidated = false,
                launchDestination = LaunchDestination.Login,
            )
        }

        return SessionLaunchState(
            hasSavedBaseUrl = true,
            hasSavedToken = true,
            biometricEnabled = biometricEnabled,
            localSessionExpired = false,
            secureStorageInvalidated = false,
            launchDestination = if (biometricEnabled) {
                LaunchDestination.Biometric
            } else {
                LaunchDestination.Home
            },
        )
    }

    suspend fun resolveLaunchDestination(): LaunchDestination =
        resolveLaunchState().launchDestination

    suspend fun saveBaseUrl(baseUrl: String) {
        preferences.setInstanceBaseUrl(baseUrl)
    }

    suspend fun saveAuthenticatedSession(
        accessToken: String,
        sessionSnapshotJson: String,
        expiresAt: String?,
    ) {
        secureTokenStore.saveToken(accessToken)
        preferences.setSessionSnapshotJson(sessionSnapshotJson)
        preferences.setLastValidatedAt(Instant.now().toString())
        if (expiresAt.isNullOrBlank()) {
            preferences.clearSessionExpiresAt()
        } else {
            preferences.setSessionExpiresAt(expiresAt)
        }
    }

    suspend fun updateSessionSnapshot(
        sessionSnapshotJson: String,
        expiresAt: String?,
    ) {
        preferences.setSessionSnapshotJson(sessionSnapshotJson)
        preferences.setLastValidatedAt(Instant.now().toString())
        if (expiresAt.isNullOrBlank()) {
            preferences.clearSessionExpiresAt()
        } else {
            preferences.setSessionExpiresAt(expiresAt)
        }
    }

    suspend fun cachedSessionSnapshotJson(): String =
        preferences.sessionSnapshotJson()

    suspend fun cachedSessionExpiresAt(): String =
        preferences.sessionExpiresAt()

    suspend fun cachedLastValidatedAt(): String =
        preferences.lastValidatedAt()

    suspend fun enableBiometric(enabled: Boolean, markPrompted: Boolean = true) {
        preferences.setBiometricEnabled(enabled)
        if (markPrompted) {
            preferences.setBiometricPrompted(true)
        }
    }

    suspend fun disableBiometric() {
        preferences.setBiometricEnabled(false)
        preferences.setBiometricPrompted(true)
    }

    suspend fun clearSession(
        clearInstance: Boolean = false,
        clearBiometricState: Boolean = false,
    ) {
        secureTokenStore.clear()
        preferences.clearSessionSnapshot()
        preferences.clearSessionExpiresAt()
        preferences.clearLastValidatedAt()
        preferences.clearFcmToken()
        if (clearInstance) {
            preferences.clearInstanceBaseUrl()
        }
        if (clearBiometricState) {
            preferences.setBiometricEnabled(false)
            preferences.setBiometricPrompted(false)
        }
    }
}

private fun String.toInstantOrNull(): Instant? = runCatching {
    Instant.parse(this)
}.getOrNull()
