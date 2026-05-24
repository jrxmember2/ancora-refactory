package br.com.serratech.ancora.hub.core.network

import br.com.serratech.ancora.hub.data.local.SecureTokenStore
import okhttp3.Interceptor
import okhttp3.Response

class AuthTokenInterceptor(
    private val tokenStore: SecureTokenStore,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = tokenStore.currentToken()
        val request = chain.request().newBuilder().apply {
            header("Accept", "application/json")
            if (!token.isNullOrBlank()) {
                header("Authorization", "Bearer $token")
            }
        }.build()

        return chain.proceed(request)
    }
}
