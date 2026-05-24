package br.com.serratech.ancora.hub.data.local

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

data class SecureTokenSnapshot(
    val token: String?,
    val invalidated: Boolean = false,
)

class SecureTokenStore(
    context: Context,
) {
    private val appContext = context.applicationContext

    fun currentToken(): String? = readTokenSnapshot().token

    fun readTokenSnapshot(): SecureTokenSnapshot = runCatching {
        SecureTokenSnapshot(prefs().getString(KEY_ACCESS_TOKEN, null))
    }.getOrElse {
        purgeSecureState()
        SecureTokenSnapshot(token = null, invalidated = true)
    }

    fun saveToken(token: String) {
        runCatching {
            prefs().edit().putString(KEY_ACCESS_TOKEN, token).apply()
        }.getOrElse {
            purgeSecureState()
            throw SecureStorageUnavailableException(it)
        }
    }

    fun clear() {
        runCatching {
            prefs().edit().remove(KEY_ACCESS_TOKEN).apply()
        }.onFailure {
            purgeSecureState()
        }
    }

    private fun prefs() = EncryptedSharedPreferences.create(
        appContext,
        PREFS_NAME,
        MasterKey.Builder(appContext)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
    )

    private fun purgeSecureState() {
        appContext.deleteSharedPreferences(PREFS_NAME)
    }

    class SecureStorageUnavailableException(
        cause: Throwable,
    ) : RuntimeException("Não foi possível proteger sua sessão neste aparelho.", cause)

    private companion object {
        const val PREFS_NAME = "ancora_hub.secure_prefs"
        const val KEY_ACCESS_TOKEN = "access_token"
    }
}
