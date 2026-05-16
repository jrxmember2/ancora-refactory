package br.com.serratech.ancora.clientes.core.network

import br.com.serratech.ancora.clientes.data.local.SecureTokenStore
import okhttp3.Interceptor
import okhttp3.Response

class AuthTokenInterceptor(
    private val tokenStore: SecureTokenStore,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = tokenStore.currentToken()
        val request = chain.request().newBuilder().apply {
            if (!token.isNullOrBlank()) {
                header("Authorization", "Bearer $token")
            }
            header("Accept", "application/json")
        }.build()
        return chain.proceed(request)
    }
}
