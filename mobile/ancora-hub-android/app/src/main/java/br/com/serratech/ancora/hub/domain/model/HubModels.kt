package br.com.serratech.ancora.hub.domain.model

data class SessionUser(
    val id: Long,
    val name: String,
    val email: String,
    val role: String,
    val isSuperadmin: Boolean,
    val initials: String,
    val avatarUrl: String?,
    val modules: List<AppModule>,
    val permissions: UserPermissions,
    val sessionPolicy: SessionPolicy,
    val lastLoginAt: String?,
    val lastSeenAt: String?,
)

data class AppModule(
    val id: Long,
    val slug: String,
    val name: String,
    val displayName: String,
    val iconClass: String?,
    val entryRouteName: String?,
    val accent: String?,
    val enabled: Boolean,
)

data class UserPermissions(
    val grantsAllRoutes: Boolean,
    val groupKeys: List<String>,
    val routeNames: List<String>,
)

data class SessionPolicy(
    val slidingExpiration: Boolean,
    val biometricEnabled: Boolean,
    val inactiveExpiresInSeconds: Int,
    val inactiveExpiresInLabel: String,
)

data class DashboardData(
    val greeting: String,
    val user: SessionUser?,
    val summary: DashboardSummary,
    val cards: List<DashboardCard>,
    val shortcuts: List<DashboardShortcut>,
    val alerts: List<DashboardAlert>,
    val notifications: List<NotificationItem>,
    val updatedAt: String?,
    val unreadNotificationsCount: Int,
)

data class DashboardSummary(
    val activeModulesCount: Int,
    val availableShortcutsCount: Int,
    val unreadNotificationsCount: Int,
    val criticalAlertsCount: Int,
    val focusMessage: String,
    val hasCriticalAlerts: Boolean,
)

data class DashboardCard(
    val id: String,
    val title: String,
    val value: Int,
    val description: String,
    val iconClass: String?,
    val module: String?,
    val accent: String?,
    val route: String?,
    val isClickable: Boolean,
)

data class DashboardShortcut(
    val module: String,
    val title: String,
    val description: String,
    val entryRouteName: String?,
    val iconClass: String?,
    val accent: String?,
    val route: String?,
)

data class DashboardAlert(
    val id: String,
    val title: String,
    val message: String,
    val accent: String?,
    val module: String?,
    val route: String?,
    val actionLabel: String?,
)

data class NotificationItem(
    val id: Long,
    val title: String,
    val body: String,
    val type: String?,
    val module: String?,
    val entityType: String?,
    val entityId: Long?,
    val actionUrl: String?,
    val route: String?,
    val actionLabel: String?,
    val data: Map<String, String>,
    val readAt: String?,
    val createdAt: String?,
    val createdAtBr: String?,
)

enum class NotificationFilter(
    val apiValue: String,
) {
    All("all"),
    Unread("unread"),
}

data class NotificationFeed(
    val items: List<NotificationItem>,
    val unreadCount: Int,
    val filter: NotificationFilter = NotificationFilter.All,
)
