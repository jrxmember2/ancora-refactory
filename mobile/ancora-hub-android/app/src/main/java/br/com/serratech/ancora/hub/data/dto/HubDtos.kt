package br.com.serratech.ancora.hub.data.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

@Serializable
data class HealthResponseDto(
    val ok: Boolean = false,
    val app: String,
    val api: String,
    val version: String,
    val timestamp: String? = null,
)

@Serializable
data class SimpleResponseDto(
    val ok: Boolean = false,
    val message: String? = null,
)

@Serializable
data class LoginRequestDto(
    val email: String,
    val password: String,
    @SerialName("device_name") val deviceName: String?,
    val platform: String = "android",
    @SerialName("app_version") val appVersion: String?,
    @SerialName("biometric_enabled") val biometricEnabled: Boolean,
)

@Serializable
data class AuthResponseDto(
    @SerialName("token_type") val tokenType: String,
    val token: String,
    @SerialName("expires_at") val expiresAt: String? = null,
    val user: UserDto,
    val modules: List<ModuleDto> = emptyList(),
    val permissions: PermissionsDto = PermissionsDto(),
    @SerialName("session_policy") val sessionPolicy: SessionPolicyDto = SessionPolicyDto(),
)

@Serializable
data class SessionPayloadDto(
    @SerialName("expires_at") val expiresAt: String? = null,
    val user: UserDto,
    val modules: List<ModuleDto> = emptyList(),
    val permissions: PermissionsDto = PermissionsDto(),
    @SerialName("session_policy") val sessionPolicy: SessionPolicyDto = SessionPolicyDto(),
)

@Serializable
data class UserDto(
    val id: Long,
    val name: String,
    val email: String,
    val role: String,
    @SerialName("is_superadmin") val isSuperadmin: Boolean = false,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("theme_preference") val themePreference: String = "dark",
    @SerialName("avatar_url") val avatarUrl: String? = null,
    val initials: String = "U",
    @SerialName("last_login_at") val lastLoginAt: String? = null,
    @SerialName("last_seen_at") val lastSeenAt: String? = null,
)

@Serializable
data class ModuleDto(
    val id: Long,
    val slug: String,
    val name: String,
    @SerialName("display_name") val displayName: String,
    @SerialName("icon_class") val iconClass: String? = null,
    @SerialName("route_prefix") val routePrefix: String? = null,
    @SerialName("entry_route_name") val entryRouteName: String? = null,
    val accent: String? = null,
    @SerialName("app_route") val appRoute: String? = null,
    val enabled: Boolean = true,
)

@Serializable
data class PermissionsDto(
    @SerialName("grants_all_routes") val grantsAllRoutes: Boolean = false,
    @SerialName("group_keys") val groupKeys: List<String> = emptyList(),
    @SerialName("route_names") val routeNames: List<String> = emptyList(),
)

@Serializable
data class SessionPolicyDto(
    @SerialName("sliding_expiration") val slidingExpiration: Boolean = true,
    @SerialName("biometric_enabled") val biometricEnabled: Boolean = false,
    @SerialName("inactive_expires_in_seconds") val inactiveExpiresInSeconds: Int = 0,
    @SerialName("inactive_expires_in_label") val inactiveExpiresInLabel: String = "",
)

@Serializable
data class DashboardResponseDto(
    val greeting: String,
    val user: UserDto? = null,
    val summary: DashboardSummaryDto = DashboardSummaryDto(),
    val cards: List<DashboardCardDto> = emptyList(),
    val shortcuts: List<ShortcutDto> = emptyList(),
    val alerts: List<DashboardAlertDto> = emptyList(),
    val notifications: List<NotificationDto> = emptyList(),
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("unread_notifications_count") val unreadNotificationsCount: Int = 0,
)

@Serializable
data class DashboardSummaryDto(
    @SerialName("active_modules_count") val activeModulesCount: Int = 0,
    @SerialName("available_shortcuts_count") val availableShortcutsCount: Int = 0,
    @SerialName("unread_notifications_count") val unreadNotificationsCount: Int = 0,
    @SerialName("critical_alerts_count") val criticalAlertsCount: Int = 0,
    @SerialName("focus_message") val focusMessage: String = "",
    @SerialName("has_critical_alerts") val hasCriticalAlerts: Boolean = false,
)

@Serializable
data class DashboardCardDto(
    val id: String,
    val title: String,
    val value: Int = 0,
    val description: String,
    @SerialName("icon_class") val iconClass: String? = null,
    val module: String? = null,
    val accent: String? = null,
    val route: String? = null,
    @SerialName("is_clickable") val isClickable: Boolean = false,
)

@Serializable
data class ShortcutDto(
    val module: String,
    val title: String,
    val description: String,
    @SerialName("entry_route_name") val entryRouteName: String? = null,
    @SerialName("icon_class") val iconClass: String? = null,
    val accent: String? = null,
    val route: String? = null,
)

@Serializable
data class DashboardAlertDto(
    val id: String,
    val title: String,
    val message: String,
    val accent: String? = null,
    val module: String? = null,
    val route: String? = null,
    @SerialName("action_label") val actionLabel: String? = null,
)

@Serializable
data class DeviceRegistrationRequestDto(
    @SerialName("fcm_token") val fcmToken: String,
    @SerialName("device_name") val deviceName: String?,
    @SerialName("app_version") val appVersion: String?,
    val platform: String = "android",
)

@Serializable
data class DeviceRegistrationResponseDto(
    val ok: Boolean = false,
    val message: String? = null,
    @SerialName("device_id") val deviceId: Long? = null,
)

@Serializable
data class NotificationListResponseDto(
    val items: List<NotificationDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
)

@Serializable
data class PaginationDto(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    @SerialName("per_page") val perPage: Int = 20,
    val total: Int = 0,
    val filter: String? = null,
    @SerialName("unread_count") val unreadCount: Int? = null,
)

@Serializable
data class NotificationDto(
    val id: Long,
    val title: String,
    val body: String,
    val type: String? = null,
    val module: String? = null,
    @SerialName("entity_type") val entityType: String? = null,
    @SerialName("entity_id") val entityId: Long? = null,
    @SerialName("action_url") val actionUrl: String? = null,
    val route: String? = null,
    @SerialName("action_label") val actionLabel: String? = null,
    val data: JsonObject? = null,
    @SerialName("read_at") val readAt: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
)
