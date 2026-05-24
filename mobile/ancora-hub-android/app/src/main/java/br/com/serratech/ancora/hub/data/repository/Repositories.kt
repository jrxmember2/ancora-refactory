package br.com.serratech.ancora.hub.data.repository

import android.os.Build
import br.com.serratech.ancora.hub.BuildConfig
import br.com.serratech.ancora.hub.core.push.PushNotifier
import br.com.serratech.ancora.hub.core.session.AppSessionManager
import br.com.serratech.ancora.hub.core.utils.UrlNormalizer
import br.com.serratech.ancora.hub.data.api.HubApiService
import br.com.serratech.ancora.hub.data.dto.AuthResponseDto
import br.com.serratech.ancora.hub.data.dto.DashboardAlertDto
import br.com.serratech.ancora.hub.data.dto.DashboardCardDto
import br.com.serratech.ancora.hub.data.dto.DashboardResponseDto
import br.com.serratech.ancora.hub.data.dto.DashboardSummaryDto
import br.com.serratech.ancora.hub.data.dto.DeviceRegistrationRequestDto
import br.com.serratech.ancora.hub.data.dto.HealthResponseDto
import br.com.serratech.ancora.hub.data.dto.LoginRequestDto
import br.com.serratech.ancora.hub.data.dto.ModuleDto
import br.com.serratech.ancora.hub.data.dto.NotificationDto
import br.com.serratech.ancora.hub.data.dto.PermissionsDto
import br.com.serratech.ancora.hub.data.dto.SessionPayloadDto
import br.com.serratech.ancora.hub.data.dto.SessionPolicyDto
import br.com.serratech.ancora.hub.data.dto.ShortcutDto
import br.com.serratech.ancora.hub.data.local.AppPreferencesDataSource
import br.com.serratech.ancora.hub.data.local.SecureTokenStore
import br.com.serratech.ancora.hub.domain.model.AppModule
import br.com.serratech.ancora.hub.domain.model.DashboardAlert
import br.com.serratech.ancora.hub.domain.model.DashboardCard
import br.com.serratech.ancora.hub.domain.model.DashboardData
import br.com.serratech.ancora.hub.domain.model.DashboardShortcut
import br.com.serratech.ancora.hub.domain.model.DashboardSummary
import br.com.serratech.ancora.hub.domain.model.NotificationFeed
import br.com.serratech.ancora.hub.domain.model.NotificationFilter
import br.com.serratech.ancora.hub.domain.model.NotificationItem
import br.com.serratech.ancora.hub.domain.model.SessionPolicy
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.domain.model.UserPermissions
import java.io.IOException
import java.util.Locale
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.HttpException

sealed interface SessionValidationResult {
    data class Success(val user: SessionUser) : SessionValidationResult
    data class Expired(val message: String = "Sessão expirada. Entre novamente.") : SessionValidationResult
    data class Unavailable(val message: String = "Não foi possível validar sua sessão.") : SessionValidationResult
}

class UserFacingException(
    message: String,
    cause: Throwable? = null,
) : RuntimeException(message, cause)

class InstanceRepository(
    private val preferences: AppPreferencesDataSource,
    private val sessionManager: AppSessionManager,
    private val urlNormalizer: UrlNormalizer,
    private val json: Json,
) {
    suspend fun validate(url: String): Result<Pair<String, HealthResponseDto>> = withContext(Dispatchers.IO) {
        val normalized = urlNormalizer.normalize(url)
            ?: return@withContext Result.failure(IllegalArgumentException("Informe um endereço válido."))

        val request = Request.Builder()
            .url("$normalized/api/hub/v1/health")
            .get()
            .build()

        OkHttpClient().newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                return@withContext Result.failure(
                    IllegalStateException("Não conseguimos conectar ao endereço informado. Verifique o endereço do Âncora e tente novamente."),
                )
            }

            val body = response.body?.string().orEmpty()
            val parsed = runCatching {
                json.decodeFromString(HealthResponseDto.serializer(), body)
            }.getOrNull()
                ?: return@withContext Result.failure(
                    IllegalStateException("O endpoint de verificação desta instância não retornou um formato compatível."),
                )

            if (!parsed.ok || parsed.api.lowercase(Locale.ROOT) != "hub") {
                return@withContext Result.failure(
                    IllegalStateException("A instância informada não expôs a API do Âncora Hub esperada."),
                )
            }

            Result.success(normalized to parsed)
        }
    }

    suspend fun saveBaseUrl(url: String, clearSession: Boolean) {
        if (clearSession) {
            sessionManager.clearSession(clearInstance = false)
        }
        sessionManager.saveBaseUrl(url)
    }

    suspend fun currentBaseUrl(): String = preferences.instanceBaseUrl()
}

class AuthRepository(
    private val api: HubApiService,
    private val sessionManager: AppSessionManager,
    private val preferences: AppPreferencesDataSource,
    private val secureTokenStore: SecureTokenStore,
    private val pushNotifier: PushNotifier,
    private val json: Json,
) {
    suspend fun login(
        email: String,
        password: String,
        biometricEnabled: Boolean,
    ): SessionUser = try {
        val response = api.login(
            LoginRequestDto(
                email = email.trim(),
                password = password,
                deviceName = buildDeviceName(),
                appVersion = BuildConfig.VERSION_NAME,
                biometricEnabled = biometricEnabled,
            ),
        )

        saveSession(response)
        response.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toUserFacingMessage(
                json = json,
                defaultMessage = "Não foi possível entrar agora. Tente novamente.",
            ),
            throwable,
        )
    }

    suspend fun upgradeSessionWithBiometric(email: String, password: String): SessionUser = try {
        val previousToken = secureTokenStore.currentToken()
        val response = api.login(
            LoginRequestDto(
                email = email.trim(),
                password = password,
                deviceName = buildDeviceName(),
                appVersion = BuildConfig.VERSION_NAME,
                biometricEnabled = true,
            ),
        )

        if (!previousToken.isNullOrBlank()) {
            runCatching { logoutSpecificToken(previousToken) }
        }

        saveSession(response)
        response.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toUserFacingMessage(
                json = json,
                defaultMessage = "Não foi possível ativar a biometria agora.",
            ),
            throwable,
        )
    }

    suspend fun me(): SessionUser = when (val result = validateSession()) {
        is SessionValidationResult.Success -> result.user
        is SessionValidationResult.Expired -> throw UserFacingException(result.message)
        is SessionValidationResult.Unavailable -> throw UserFacingException(result.message)
    }

    suspend fun validateSession(): SessionValidationResult = try {
        val response = api.me()
        sessionManager.updateSessionSnapshot(
            sessionSnapshotJson = serializeSnapshot(response),
            expiresAt = response.expiresAt,
        )
        SessionValidationResult.Success(response.toDomain())
    } catch (throwable: Throwable) {
        when {
            throwable.isUnauthorized() -> SessionValidationResult.Expired()
            else -> SessionValidationResult.Unavailable(
                throwable.toUserFacingMessage(
                    json = json,
                    defaultMessage = "Não foi possível validar sua sessão.",
                ),
            )
        }
    }

    suspend fun cachedUser(): SessionUser? {
        val snapshot = sessionManager.cachedSessionSnapshotJson()
        if (snapshot.isBlank()) {
            return null
        }

        return runCatching {
            json.decodeFromString(SessionPayloadDto.serializer(), snapshot).toDomain()
        }.getOrNull()
    }

    suspend fun logout() {
        unregisterCurrentDeviceIfNeeded()
        runCatching { api.logout() }
        sessionManager.clearSession(clearInstance = false)
    }

    suspend fun registerDevice(fcmToken: String) {
        if (secureTokenStore.currentToken().isNullOrBlank()) {
            return
        }

        api.registerDevice(
            DeviceRegistrationRequestDto(
                fcmToken = fcmToken,
                deviceName = buildDeviceName(),
                appVersion = BuildConfig.VERSION_NAME,
            ),
        )
        preferences.setFcmToken(fcmToken)
    }

    suspend fun unregisterCurrentDeviceIfNeeded() {
        val token = preferences.fcmToken()
        if (token.isBlank()) {
            preferences.clearFcmToken()
            return
        }

        runCatching { api.unregisterDevice(mapOf("fcm_token" to token)) }
        preferences.clearFcmToken()
    }

    suspend fun enableBiometric() {
        sessionManager.enableBiometric(true)
    }

    suspend fun disableBiometric() {
        sessionManager.disableBiometric()
    }

    fun requestPushRegistration() {
        pushNotifier.registerCurrentDevice()
    }

    private suspend fun saveSession(response: AuthResponseDto) {
        sessionManager.saveAuthenticatedSession(
            accessToken = response.token,
            sessionSnapshotJson = serializeSnapshot(response.toSessionPayload()),
            expiresAt = response.expiresAt,
        )
    }

    private suspend fun logoutSpecificToken(accessToken: String) {
        val baseUrl = preferences.instanceBaseUrl()
        if (baseUrl.isBlank()) {
            return
        }

        val request = Request.Builder()
            .url("$baseUrl/api/hub/v1/auth/logout")
            .header("Accept", "application/json")
            .header("Authorization", "Bearer $accessToken")
            .post("{}".toRequestBody("application/json".toMediaType()))
            .build()

        OkHttpClient().newCall(request).execute().close()
    }

    private fun serializeSnapshot(snapshot: SessionPayloadDto): String =
        json.encodeToString(SessionPayloadDto.serializer(), snapshot)

    private fun buildDeviceName(): String =
        "${Build.MANUFACTURER} ${Build.MODEL}".trim()
}

class DashboardRepository(
    private val api: HubApiService,
) {
    suspend fun dashboard(): DashboardData = api.dashboard().toDomain()
}

class NotificationRepository(
    private val api: HubApiService,
) {
    private val cache = linkedMapOf<Long, NotificationItem>()

    suspend fun list(
        filter: NotificationFilter = NotificationFilter.All,
        perPage: Int = 50,
    ): NotificationFeed {
        val response = api.notifications(
            filter = filter.apiValue,
            perPage = perPage,
        )

        val items = response.items.map { it.toDomain() }
        cacheAll(items)

        return NotificationFeed(
            items = items,
            unreadCount = response.meta.unreadCount ?: items.count { it.readAt == null },
            filter = response.meta.filter.toNotificationFilter(),
        )
    }

    suspend fun unreadCount(): Int =
        api.notifications(
            filter = NotificationFilter.All.apiValue,
            perPage = 1,
        ).meta.unreadCount ?: 0

    suspend fun read(id: Long) {
        api.readNotification(id)
        cache[id]?.let { current ->
            cache[id] = current.copy(readAt = current.readAt ?: "marked")
        }
    }

    suspend fun readAll() {
        api.readAllNotifications()
        cache.keys.forEach { key ->
            cache[key]?.let { current ->
                cache[key] = current.copy(readAt = current.readAt ?: "marked")
            }
        }
    }

    suspend fun findById(id: Long): NotificationItem? {
        cache[id]?.let { return it }

        return list().items.firstOrNull { it.id == id }
    }

    private fun cacheAll(items: List<NotificationItem>) {
        items.forEach { item ->
            cache[item.id] = item
        }
    }
}

private fun AuthResponseDto.toSessionPayload(): SessionPayloadDto = SessionPayloadDto(
    expiresAt = expiresAt,
    user = user,
    modules = modules,
    permissions = permissions,
    sessionPolicy = sessionPolicy,
)

private fun AuthResponseDto.toDomain(): SessionUser = toSessionPayload().toDomain()

private fun SessionPayloadDto.toDomain(): SessionUser = SessionUser(
    id = user.id,
    name = user.name,
    email = user.email,
    role = user.role,
    isSuperadmin = user.isSuperadmin,
    initials = user.initials,
    avatarUrl = user.avatarUrl,
    modules = modules.map { it.toDomain() },
    permissions = permissions.toDomain(),
    sessionPolicy = sessionPolicy.toDomain(),
    lastLoginAt = user.lastLoginAt,
    lastSeenAt = user.lastSeenAt,
)

private fun ModuleDto.toDomain(): AppModule = AppModule(
    id = id,
    slug = slug,
    name = name,
    displayName = displayName,
    iconClass = iconClass,
    entryRouteName = entryRouteName,
    accent = accent,
    enabled = enabled,
)

private fun PermissionsDto.toDomain(): UserPermissions = UserPermissions(
    grantsAllRoutes = grantsAllRoutes,
    groupKeys = groupKeys,
    routeNames = routeNames,
)

private fun SessionPolicyDto.toDomain(): SessionPolicy = SessionPolicy(
    slidingExpiration = slidingExpiration,
    biometricEnabled = biometricEnabled,
    inactiveExpiresInSeconds = inactiveExpiresInSeconds,
    inactiveExpiresInLabel = inactiveExpiresInLabel,
)

private fun DashboardResponseDto.toDomain(): DashboardData = DashboardData(
    greeting = greeting,
    user = user?.let {
        SessionUser(
            id = it.id,
            name = it.name,
            email = it.email,
            role = it.role,
            isSuperadmin = it.isSuperadmin,
            initials = it.initials,
            avatarUrl = it.avatarUrl,
            modules = emptyList(),
            permissions = PermissionsDto().toDomain(),
            sessionPolicy = SessionPolicyDto().toDomain(),
            lastLoginAt = it.lastLoginAt,
            lastSeenAt = it.lastSeenAt,
        )
    },
    summary = summary.toDomain(),
    cards = cards.map { it.toDomain() },
    shortcuts = shortcuts.map { it.toDomain() },
    alerts = alerts.map { it.toDomain() },
    notifications = notifications.map { it.toDomain() },
    updatedAt = updatedAt,
    unreadNotificationsCount = unreadNotificationsCount,
)

private fun DashboardSummaryDto.toDomain(): DashboardSummary = DashboardSummary(
    activeModulesCount = activeModulesCount,
    availableShortcutsCount = availableShortcutsCount,
    unreadNotificationsCount = unreadNotificationsCount,
    criticalAlertsCount = criticalAlertsCount,
    focusMessage = focusMessage,
    hasCriticalAlerts = hasCriticalAlerts,
)

private fun DashboardCardDto.toDomain(): DashboardCard = DashboardCard(
    id = id,
    title = title,
    value = value,
    description = description,
    iconClass = iconClass,
    module = module,
    accent = accent,
    route = route,
    isClickable = isClickable,
)

private fun ShortcutDto.toDomain(): DashboardShortcut = DashboardShortcut(
    module = module,
    title = title,
    description = description,
    entryRouteName = entryRouteName,
    iconClass = iconClass,
    accent = accent,
    route = route,
)

private fun DashboardAlertDto.toDomain(): DashboardAlert = DashboardAlert(
    id = id,
    title = title,
    message = message,
    accent = accent,
    module = module,
    route = route,
    actionLabel = actionLabel,
)

private fun NotificationDto.toDomain(): NotificationItem = NotificationItem(
    id = id,
    title = title,
    body = body,
    type = type,
    module = module,
    entityType = entityType,
    entityId = entityId,
    actionUrl = actionUrl,
    route = route,
    actionLabel = actionLabel,
    data = data.orEmpty().mapValues { (_, value) -> value.toString().trim('"') },
    readAt = readAt,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
)

private fun String?.toNotificationFilter(): NotificationFilter = when (this?.lowercase()) {
    NotificationFilter.Unread.apiValue -> NotificationFilter.Unread
    else -> NotificationFilter.All
}

private fun Throwable.isUnauthorized(): Boolean =
    this is HttpException && code() == 401

private fun Throwable.toUserFacingMessage(
    json: Json,
    defaultMessage: String,
): String {
    apiMessageOrNull(json)?.let { message ->
        if (message.isNotBlank()) {
            return message
        }
    }

    return when {
        isUnauthorized() -> "Sessão expirada. Entre novamente."
        this is SecureTokenStore.SecureStorageUnavailableException -> message ?: defaultMessage
        this is IOException -> "Não foi possível se conectar ao Âncora agora. Tente novamente."
        else -> defaultMessage
    }
}

private fun Throwable.apiMessageOrNull(json: Json): String? {
    val exception = this as? HttpException ?: return null
    val rawBody = runCatching { exception.response()?.errorBody()?.string() }.getOrNull().orEmpty()
    if (rawBody.isBlank()) {
        return null
    }

    return runCatching {
        json.decodeFromString(ApiMessageDto.serializer(), rawBody).message
    }.getOrNull()
}

@Serializable
private data class ApiMessageDto(
    val message: String? = null,
)
