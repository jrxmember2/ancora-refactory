package br.com.serratech.ancora.clientes.data.local

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

class SecureTokenStore(context: Context) {
    private val prefs = EncryptedSharedPreferences.create(
        context,
        "ancora_clientes.secure_prefs",
        MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
    )

    fun currentToken(): String? = prefs.getString(KEY_ACCESS_TOKEN, null)

    fun saveToken(token: String) {
        prefs.edit().putString(KEY_ACCESS_TOKEN, token).apply()
    }

    fun clear() {
        prefs.edit().remove(KEY_ACCESS_TOKEN).apply()
    }

    private companion object {
        const val KEY_ACCESS_TOKEN = "access_token"
    }
}
