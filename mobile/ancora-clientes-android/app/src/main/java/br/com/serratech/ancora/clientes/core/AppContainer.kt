package br.com.serratech.ancora.clientes.core

import android.app.Application
import br.com.serratech.ancora.clientes.core.network.AuthTokenInterceptor
import br.com.serratech.ancora.clientes.core.network.DynamicBaseUrlInterceptor
import br.com.serratech.ancora.clientes.core.push.PushNotifier
import br.com.serratech.ancora.clientes.core.security.BiometricAuthenticator
import br.com.serratech.ancora.clientes.core.session.AppSessionManager
import br.com.serratech.ancora.clientes.core.utils.UrlNormalizer
import br.com.serratech.ancora.clientes.data.api.MobileApiService
import br.com.serratech.ancora.clientes.data.local.AppPreferencesDataSource
import br.com.serratech.ancora.clientes.data.local.SecureTokenStore
import br.com.serratech.ancora.clientes.data.repository.AuthRepository
import br.com.serratech.ancora.clientes.data.repository.CondominiumRepository
import br.com.serratech.ancora.clientes.data.repository.DashboardRepository
import br.com.serratech.ancora.clientes.data.repository.DemandRepository
import br.com.serratech.ancora.clientes.data.repository.InstanceRepository
import br.com.serratech.ancora.clientes.data.repository.LemeRepository
import br.com.serratech.ancora.clientes.data.repository.NotificationRepository
import br.com.serratech.ancora.clientes.data.repository.ProcessRepository
import br.com.serratech.ancora.clientes.domain.usecase.BootstrapAppUseCase
import br.com.serratech.ancora.clientes.domain.usecase.ValidateInstanceUseCase
import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit

class AppContainer(application: Application) {
    private val json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        isLenient = true
    }

    val preferences = AppPreferencesDataSource(application)
    val secureTokenStore = SecureTokenStore(application)
    val sessionManager = AppSessionManager(preferences, secureTokenStore)
    val biometricAuthenticator = BiometricAuthenticator()
    val urlNormalizer = UrlNormalizer()
    val pushNotifier = PushNotifier(application)

    private val okHttpClient: OkHttpClient = OkHttpClient.Builder()
        .addInterceptor(DynamicBaseUrlInterceptor(preferences))
        .addInterceptor(AuthTokenInterceptor(secureTokenStore))
        .build()

    val apiService: MobileApiService = Retrofit.Builder()
        .baseUrl("https://placeholder.invalid/")
        .client(okHttpClient)
        .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
        .build()
        .create(MobileApiService::class.java)

    private val downloadClient: OkHttpClient = okHttpClient.newBuilder().build()

    val instanceRepository = InstanceRepository(
        preferences = preferences,
        sessionManager = sessionManager,
        urlNormalizer = urlNormalizer,
        json = json,
    )
    val authRepository = AuthRepository(
        api = apiService,
        sessionManager = sessionManager,
        preferences = preferences,
    )
    val condominiumRepository = CondominiumRepository(apiService)
    val dashboardRepository = DashboardRepository(apiService)
    val processRepository = ProcessRepository(apiService)
    val demandRepository = DemandRepository(application, apiService, downloadClient)
    val notificationRepository = NotificationRepository(apiService)
    val lemeRepository = LemeRepository(apiService)

    val bootstrapAppUseCase = BootstrapAppUseCase(sessionManager)
    val validateInstanceUseCase = ValidateInstanceUseCase(instanceRepository)
}
