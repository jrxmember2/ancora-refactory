package br.com.serratech.ancora.hub.data.local

import android.content.Context
import androidx.datastore.preferences.core.PreferenceDataStoreFactory
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStoreFile
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

class AppPreferencesDataSource(context: Context) {
    private val store = PreferenceDataStoreFactory.create(
        produceFile = { context.preferencesDataStoreFile("ancora_hub.preferences_pb") },
    )

    suspend fun instanceBaseUrl(): String =
        store.data.map { it[Keys.INSTANCE_BASE_URL].orEmpty() }.first()

    suspend fun setInstanceBaseUrl(value: String) {
        store.edit { it[Keys.INSTANCE_BASE_URL] = value }
    }

    suspend fun clearInstanceBaseUrl() {
        store.edit { it.remove(Keys.INSTANCE_BASE_URL) }
    }

    suspend fun isBiometricEnabled(): Boolean =
        store.data.map { it[Keys.BIOMETRIC_ENABLED] ?: false }.first()

    suspend fun setBiometricEnabled(enabled: Boolean) {
        store.edit { it[Keys.BIOMETRIC_ENABLED] = enabled }
    }

    suspend fun wasBiometricPrompted(): Boolean =
        store.data.map { it[Keys.BIOMETRIC_PROMPTED] ?: false }.first()

    suspend fun setBiometricPrompted(prompted: Boolean) {
        store.edit { it[Keys.BIOMETRIC_PROMPTED] = prompted }
    }

    suspend fun fcmToken(): String =
        store.data.map { it[Keys.FCM_TOKEN].orEmpty() }.first()

    suspend fun setFcmToken(token: String) {
        store.edit { it[Keys.FCM_TOKEN] = token }
    }

    suspend fun clearFcmToken() {
        store.edit { it.remove(Keys.FCM_TOKEN) }
    }

    suspend fun sessionSnapshotJson(): String =
        store.data.map { it[Keys.SESSION_SNAPSHOT_JSON].orEmpty() }.first()

    suspend fun setSessionSnapshotJson(value: String) {
        store.edit { it[Keys.SESSION_SNAPSHOT_JSON] = value }
    }

    suspend fun clearSessionSnapshot() {
        store.edit { it.remove(Keys.SESSION_SNAPSHOT_JSON) }
    }

    suspend fun sessionExpiresAt(): String =
        store.data.map { it[Keys.SESSION_EXPIRES_AT].orEmpty() }.first()

    suspend fun setSessionExpiresAt(value: String) {
        store.edit { it[Keys.SESSION_EXPIRES_AT] = value }
    }

    suspend fun clearSessionExpiresAt() {
        store.edit { it.remove(Keys.SESSION_EXPIRES_AT) }
    }

    suspend fun lastValidatedAt(): String =
        store.data.map { it[Keys.LAST_VALIDATED_AT].orEmpty() }.first()

    suspend fun setLastValidatedAt(value: String) {
        store.edit { it[Keys.LAST_VALIDATED_AT] = value }
    }

    suspend fun clearLastValidatedAt() {
        store.edit { it.remove(Keys.LAST_VALIDATED_AT) }
    }

    private object Keys {
        val INSTANCE_BASE_URL = stringPreferencesKey("instance_base_url")
        val BIOMETRIC_ENABLED = booleanPreferencesKey("biometric_enabled")
        val BIOMETRIC_PROMPTED = booleanPreferencesKey("biometric_prompted")
        val FCM_TOKEN = stringPreferencesKey("fcm_token")
        val SESSION_SNAPSHOT_JSON = stringPreferencesKey("session_snapshot_json")
        val SESSION_EXPIRES_AT = stringPreferencesKey("session_expires_at")
        val LAST_VALIDATED_AT = stringPreferencesKey("last_validated_at")
    }
}
