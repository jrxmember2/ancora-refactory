package br.com.serratech.ancora.clientes.data.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class HealthResponseDto(
    val status: String,
    val app: String,
    @SerialName("mobile_api") val mobileApi: Boolean = false,
    val version: String,
)

@Serializable
data class SimpleResponseDto(
    val ok: Boolean = false,
    val message: String? = null,
)

@Serializable
data class LoginRequestDto(
    val login: String,
    val password: String,
    @SerialName("device_name") val deviceName: String?,
    @SerialName("app_version") val appVersion: String?,
    val platform: String = "android",
)

@Serializable
data class AuthResponseDto(
    @SerialName("token_type") val tokenType: String,
    @SerialName("access_token") val accessToken: String,
    @SerialName("expires_at") val expiresAt: String? = null,
    val user: UserDto,
)

@Serializable
data class UserEnvelopeDto(val user: UserDto)

@Serializable
data class UserDto(
    val id: Long,
    val name: String,
    @SerialName("login_key") val loginKey: String,
    val email: String? = null,
    @SerialName("portal_role") val portalRole: String? = null,
    @SerialName("must_change_password") val mustChangePassword: Boolean = false,
    val permissions: PermissionsDto = PermissionsDto(),
    @SerialName("selected_condominium") val selectedCondominium: CondominiumDto? = null,
    @SerialName("accessible_condominiums") val accessibleCondominiums: List<CondominiumDto> = emptyList(),
)

@Serializable
data class PermissionsDto(
    @SerialName("can_view_processes") val canViewProcesses: Boolean = false,
    @SerialName("can_view_cobrancas") val canViewCobrancas: Boolean = false,
    @SerialName("can_open_demands") val canOpenDemands: Boolean = false,
    @SerialName("can_view_demands") val canViewDemands: Boolean = false,
    @SerialName("can_view_documents") val canViewDocuments: Boolean = false,
    @SerialName("can_view_financial_summary") val canViewFinancialSummary: Boolean = false,
    @SerialName("ai_enabled") val aiEnabled: Boolean = false,
)

@Serializable
data class CondominiumDto(
    val id: Long,
    val name: String,
    @SerialName("syndic_name") val syndicName: String? = null,
    @SerialName("administradora_name") val administradoraName: String? = null,
    val type: String? = null,
)

@Serializable
data class CondominiumsResponseDto(
    @SerialName("selected_condominium") val selectedCondominium: CondominiumDto? = null,
    val items: List<CondominiumDto> = emptyList(),
)

@Serializable
data class SummaryDto(
    @SerialName("processes_active") val processesActive: Int = 0,
    @SerialName("demands_open") val demandsOpen: Int = 0,
    @SerialName("demands_waiting_client") val demandsWaitingClient: Int = 0,
    @SerialName("notifications_unread") val notificationsUnread: Int = 0,
)

@Serializable
data class DashboardResponseDto(
    val greeting: String,
    @SerialName("selected_condominium") val selectedCondominium: CondominiumDto? = null,
    val summary: SummaryDto = SummaryDto(),
    @SerialName("latest_processes") val latestProcesses: List<ProcessItemDto> = emptyList(),
    @SerialName("latest_demands") val latestDemands: List<DemandItemDto> = emptyList(),
    @SerialName("latest_movements") val latestMovements: List<MovementDto> = emptyList(),
)

@Serializable
data class MovementDto(
    val type: String,
    val title: String,
    val description: String,
    val date: String? = null,
)

@Serializable
data class StatusInfoDto(
    val label: String,
    val color: String? = null,
    val key: String? = null,
    val tag: String? = null,
)

@Serializable
data class ProcessPhaseDto(
    val id: Long,
    val description: String,
    val source: String,
    @SerialName("source_label") val sourceLabel: String,
    @SerialName("phase_date") val phaseDate: String? = null,
    @SerialName("phase_date_br") val phaseDateBr: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
)

@Serializable
data class ProcessItemDto(
    val id: Long,
    @SerialName("process_number") val processNumber: String,
    val status: StatusInfoDto,
    val type: String? = null,
    val nature: String? = null,
    @SerialName("last_public_phase") val lastPublicPhase: ProcessPhaseDto? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
)

@Serializable
data class ProcessDetailDto(
    val id: Long,
    @SerialName("process_number") val processNumber: String,
    val status: StatusInfoDto,
    val type: String? = null,
    val nature: String? = null,
    val court: String? = null,
    val phases: List<ProcessPhaseDto> = emptyList(),
    @SerialName("updated_at") val updatedAt: String? = null,
)

@Serializable
data class ProcessItemEnvelopeDto(val item: ProcessDetailDto)

@Serializable
data class ProcessListResponseDto(
    val items: List<ProcessItemDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val statuses: List<FilterOptionDto> = emptyList(),
)

@Serializable
data class FilterOptionDto(
    val id: Long,
    val name: String,
    val color: String? = null,
)

@Serializable
data class PaginationDto(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    @SerialName("per_page") val perPage: Int = 15,
    val total: Int = 0,
    @SerialName("unread_count") val unreadCount: Int? = null,
)

@Serializable
data class DemandItemDto(
    val id: Long,
    val protocol: String,
    val subject: String,
    val category: String? = null,
    val status: StatusInfoDto,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    @SerialName("has_new_response") val hasNewResponse: Boolean = false,
    @SerialName("client_condominium") val clientCondominium: CondominiumDto? = null,
)

@Serializable
data class DemandAttachmentDto(
    val id: Long,
    @SerialName("original_name") val originalName: String,
    @SerialName("mime_type") val mimeType: String? = null,
    @SerialName("file_size") val fileSize: Long = 0L,
    @SerialName("download_url") val downloadUrl: String,
)

@Serializable
data class DemandMessageDto(
    val id: Long,
    @SerialName("sender_type") val senderType: String,
    @SerialName("sender_name") val senderName: String,
    val message: String,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    val attachments: List<DemandAttachmentDto> = emptyList(),
)

@Serializable
data class DemandDetailDto(
    val id: Long,
    val protocol: String,
    val subject: String,
    val description: String,
    val category: String? = null,
    val status: StatusInfoDto,
    @SerialName("client_condominium") val clientCondominium: CondominiumDto? = null,
    @SerialName("can_manage") val canManage: Boolean = false,
    @SerialName("can_cancel") val canCancel: Boolean = false,
    @SerialName("can_reply") val canReply: Boolean = false,
    val messages: List<DemandMessageDto> = emptyList(),
    val attachments: List<DemandAttachmentDto> = emptyList(),
    @SerialName("updated_at") val updatedAt: String? = null,
)

@Serializable
data class DemandEnvelopeDto(val item: DemandDetailDto)

@Serializable
data class DemandListResponseDto(
    val items: List<DemandItemDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    @SerialName("status_labels") val statusLabels: Map<String, String> = emptyMap(),
)

@Serializable
data class DemandCategoriesResponseDto(
    val items: List<DemandCategoryDto> = emptyList(),
)

@Serializable
data class DemandCategoryDto(
    val id: Long,
    val name: String,
    val color: String? = null,
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
    @SerialName("device_id") val deviceId: Long? = null,
)

@Serializable
data class ChangePasswordRequestDto(
    @SerialName("current_password") val currentPassword: String,
    val password: String,
    @SerialName("password_confirmation") val passwordConfirmation: String,
)

@Serializable
data class NotificationDto(
    val id: Long,
    val type: String,
    val title: String,
    val body: String,
    val data: Map<String, String> = emptyMap(),
    @SerialName("read_at") val readAt: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
)

@Serializable
data class NotificationListResponseDto(
    val items: List<NotificationDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
)

@Serializable
data class LemeMessageDto(
    val id: Long,
    val role: String,
    val content: String,
    val status: String,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    val documents: List<Map<String, String>> = emptyList(),
)

@Serializable
data class UsageStatusDto(
    val allowed: Boolean = false,
    val reason: String? = null,
    val message: String,
    @SerialName("has_limit") val hasLimit: Boolean = false,
    val remaining: Int? = null,
)

@Serializable
data class RecentConversationDto(
    val id: Long,
    val title: String,
    @SerialName("last_message_at") val lastMessageAt: String? = null,
)

@Serializable
data class LemeHistoryResponseDto(
    @SerialName("conversation_id") val conversationId: Long? = null,
    @SerialName("active_condominium") val activeCondominium: CondominiumDto? = null,
    @SerialName("usage_status") val usageStatus: UsageStatusDto,
    val messages: List<LemeMessageDto> = emptyList(),
    @SerialName("recent_conversations") val recentConversations: List<RecentConversationDto> = emptyList(),
)

@Serializable
data class LemeChatRequestDto(
    val message: String,
    @SerialName("conversation_id") val conversationId: Long? = null,
    val context: Map<String, String> = emptyMap(),
)

@Serializable
data class LemeChatResponseDto(
    val answer: String,
    @SerialName("conversation_id") val conversationId: Long,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("usage_status") val usageStatus: UsageStatusDto? = null,
    val messages: List<LemeMessageDto> = emptyList(),
)
