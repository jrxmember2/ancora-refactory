package br.com.serratech.ancora.clientes.domain.model

data class SessionUser(
    val id: Long,
    val name: String,
    val loginKey: String,
    val email: String?,
    val mustChangePassword: Boolean,
    val permissions: UserPermissions,
    val selectedCondominium: Condominium?,
    val accessibleCondominiums: List<Condominium>,
)

data class UserPermissions(
    val canViewProcesses: Boolean,
    val canOpenDemands: Boolean,
    val canViewDemands: Boolean,
    val aiEnabled: Boolean,
)

data class Condominium(
    val id: Long,
    val name: String,
    val syndicName: String?,
    val administradoraName: String?,
    val type: String?,
)

data class CondominiumContext(
    val selected: Condominium?,
    val items: List<Condominium>,
)

data class DashboardData(
    val greeting: String,
    val selectedCondominium: Condominium?,
    val summary: DashboardSummary,
    val latestProcesses: List<ProcessItem>,
    val latestDemands: List<DemandItem>,
    val latestMovements: List<MovementItem>,
)

data class DashboardSummary(
    val processesActive: Int,
    val demandsOpen: Int,
    val demandsWaitingClient: Int,
    val notificationsUnread: Int,
)

data class MovementItem(
    val type: String,
    val title: String,
    val description: String,
    val date: String?,
)

data class StatusInfo(
    val key: String?,
    val label: String,
    val color: String?,
    val tag: String?,
)

data class ProcessItem(
    val id: Long,
    val processNumber: String,
    val clientName: String?,
    val adverseName: String?,
    val partiesLabel: String?,
    val status: StatusInfo,
    val type: String?,
    val nature: String?,
    val lastPublicPhase: ProcessPhase?,
    val updatedAt: String?,
)

data class ProcessFilterOption(
    val id: Long,
    val name: String,
    val color: String?,
)

data class ProcessListResult(
    val items: List<ProcessItem>,
    val statuses: List<ProcessFilterOption>,
)

data class ProcessDetail(
    val id: Long,
    val processNumber: String,
    val clientName: String?,
    val adverseName: String?,
    val partiesLabel: String?,
    val status: StatusInfo,
    val type: String?,
    val nature: String?,
    val court: String?,
    val phases: List<ProcessPhase>,
)

data class ProcessPhase(
    val id: Long,
    val description: String,
    val sourceLabel: String,
    val phaseDateBr: String?,
    val createdAt: String?,
)

data class DemandItem(
    val id: Long,
    val protocol: String,
    val subject: String,
    val category: String?,
    val status: StatusInfo,
    val updatedAtBr: String?,
    val hasNewResponse: Boolean,
    val condominium: Condominium?,
)

data class DemandListResult(
    val items: List<DemandItem>,
    val statusLabels: Map<String, String>,
)

data class DemandAttachment(
    val id: Long,
    val originalName: String,
    val mimeType: String?,
    val fileSize: Long,
    val downloadUrl: String,
)

data class DemandMessage(
    val id: Long,
    val senderType: String,
    val senderName: String,
    val message: String,
    val createdAtBr: String?,
    val attachments: List<DemandAttachment>,
)

data class DemandDetail(
    val id: Long,
    val protocol: String,
    val subject: String,
    val description: String,
    val category: String?,
    val status: StatusInfo,
    val condominium: Condominium?,
    val canManage: Boolean,
    val canCancel: Boolean,
    val canReply: Boolean,
    val messages: List<DemandMessage>,
    val attachments: List<DemandAttachment>,
)

data class DemandCategory(
    val id: Long,
    val name: String,
    val color: String?,
)

data class NotificationItem(
    val id: Long,
    val type: String,
    val title: String,
    val body: String,
    val data: Map<String, String>,
    val readAt: String?,
    val createdAtBr: String?,
)

data class UsageStatus(
    val allowed: Boolean,
    val message: String,
    val hasLimit: Boolean,
    val remaining: Int?,
)

data class LemeMessage(
    val id: Long,
    val role: String,
    val content: String,
    val createdAtBr: String?,
)

data class RecentConversation(
    val id: Long,
    val title: String,
    val lastMessageAt: String?,
)

data class LemeHistory(
    val conversationId: Long?,
    val activeCondominium: Condominium?,
    val usageStatus: UsageStatus,
    val messages: List<LemeMessage>,
    val recentConversations: List<RecentConversation>,
)
