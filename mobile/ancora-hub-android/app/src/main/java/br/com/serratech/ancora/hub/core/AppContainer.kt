package br.com.serratech.ancora.hub.core

import android.app.Application
import br.com.serratech.ancora.hub.BuildConfig
import br.com.serratech.ancora.hub.core.network.AuthTokenInterceptor
import br.com.serratech.ancora.hub.core.network.DynamicBaseUrlInterceptor
import br.com.serratech.ancora.hub.core.push.PushNotifier
import br.com.serratech.ancora.hub.core.security.BiometricAuthenticator
import br.com.serratech.ancora.hub.core.session.AppSessionManager
import br.com.serratech.ancora.hub.core.utils.UrlNormalizer
import br.com.serratech.ancora.hub.data.api.HubApiService
import br.com.serratech.ancora.hub.data.local.AppPreferencesDataSource
import br.com.serratech.ancora.hub.data.local.SecureTokenStore
import br.com.serratech.ancora.hub.data.repository.AuthRepository
import br.com.serratech.ancora.hub.data.repository.CollectionRepository
import br.com.serratech.ancora.hub.data.repository.DashboardRepository
import br.com.serratech.ancora.hub.data.repository.DemandRepository
import br.com.serratech.ancora.hub.data.repository.InstanceRepository
import br.com.serratech.ancora.hub.data.repository.NotificationRepository
import br.com.serratech.ancora.hub.data.repository.ProcessRepository
import br.com.serratech.ancora.hub.domain.usecase.BootstrapAppUseCase
import br.com.serratech.ancora.hub.domain.usecase.ValidateInstanceUseCase
import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit

class AppContainer(application: Application) {
    val json = Json {
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

    private val loggingInterceptor = HttpLoggingInterceptor().apply {
        level = if (BuildConfig.DEBUG) {
            HttpLoggingInterceptor.Level.BASIC
        } else {
            HttpLoggingInterceptor.Level.NONE
        }
    }

    private val okHttpClient: OkHttpClient = OkHttpClient.Builder()
        .addInterceptor(DynamicBaseUrlInterceptor(preferences))
        .addInterceptor(AuthTokenInterceptor(secureTokenStore))
        .addInterceptor(loggingInterceptor)
        .build()

    val apiService: HubApiService = Retrofit.Builder()
        .baseUrl("https://placeholder.invalid/")
        .client(okHttpClient)
        .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
        .build()
        .create(HubApiService::class.java)

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
        secureTokenStore = secureTokenStore,
        pushNotifier = pushNotifier,
        json = json,
    )
    val dashboardRepository = DashboardRepository(apiService)
    val notificationRepository = NotificationRepository(apiService)
    val demandRepository = DemandRepository(apiService, json)
    val processRepository = ProcessRepository(apiService, json)
    val collectionRepository = CollectionRepository(apiService, json)

    val bootstrapAppUseCase = BootstrapAppUseCase(sessionManager)
    val validateInstanceUseCase = ValidateInstanceUseCase(instanceRepository)
}
