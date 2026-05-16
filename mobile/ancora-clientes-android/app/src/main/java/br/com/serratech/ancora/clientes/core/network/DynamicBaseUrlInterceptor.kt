package br.com.serratech.ancora.clientes.core.network

import br.com.serratech.ancora.clientes.data.local.AppPreferencesDataSource
import kotlinx.coroutines.runBlocking
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.Interceptor
import okhttp3.Response

class DynamicBaseUrlInterceptor(
    private val preferences: AppPreferencesDataSource,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val baseUrl = runBlocking { preferences.instanceBaseUrl() }.orEmpty()
        if (baseUrl.isBlank()) {
            return chain.proceed(chain.request())
        }

        val dynamicBase = baseUrl.toHttpUrlOrNull() ?: return chain.proceed(chain.request())
        val originalUrl = chain.request().url
        val dynamicPath = dynamicBase.encodedPath.trimEnd('/')
        val originalPath = originalUrl.encodedPath.trimStart('/')
        val combinedPath = buildString {
            append(if (dynamicPath.isBlank()) "/" else "$dynamicPath/")
            append(originalPath)
        }.replace("//", "/")

        val newUrl = originalUrl.newBuilder()
            .scheme(dynamicBase.scheme)
            .host(dynamicBase.host)
            .port(dynamicBase.port)
            .encodedPath(combinedPath)
            .build()

        return chain.proceed(
            chain.request().newBuilder()
                .url(newUrl)
                .build()
        )
    }
}
