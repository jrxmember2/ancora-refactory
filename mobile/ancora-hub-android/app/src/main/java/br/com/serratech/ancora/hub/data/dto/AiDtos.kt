package br.com.serratech.ancora.hub.data.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class LemeAvailabilityDto(
    val configured: Boolean = false,
    @SerialName("ai_enabled") val aiEnabled: Boolean = false,
    @SerialName("can_chat") val canChat: Boolean = false,
    val message: String? = null,
)

@Serializable
data class LemeScopeOptionDto(
    val key: String,
    val label: String,
    val supported: Boolean = false,
    @SerialName("requires_reference") val requiresReference: Boolean = false,
    @SerialName("reference_type") val referenceType: String? = null,
    val description: String? = null,
)

@Serializable
data class LemeReferenceOptionDto(
    val id: Long,
    val label: String,
)

@Serializable
data class LemeConversationSummaryDto(
    val id: Long,
    val title: String,
    @SerialName("scope_key") val scopeKey: String,
    @SerialName("scope_label") val scopeLabel: String,
    @SerialName("client_condominium_id") val clientCondominiumId: Long? = null,
    @SerialName("messages_count") val messagesCount: Int = 0,
    @SerialName("last_message_at") val lastMessageAt: String? = null,
    @SerialName("last_message_at_br") val lastMessageAtBr: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
)

@Serializable
data class LemeConversationActionsDto(
    @SerialName("can_delete") val canDelete: Boolean = false,
)

@Serializable
data class LemeDocumentDto(
    @SerialName("document_kind") val documentKind: String? = null,
    @SerialName("document_kind_label") val documentKindLabel: String? = null,
    val title: String? = null,
    val source: String? = null,
    @SerialName("document_type") val documentType: String? = null,
)

@Serializable
data class LemeMessageDto(
    val id: Long,
    val role: String,
    val content: String,
    val status: String,
    val provider: String? = null,
    val model: String? = null,
    @SerialName("error_message") val errorMessage: String? = null,
    @SerialName("source_chunks_count") val sourceChunksCount: Int? = null,
    val documents: List<LemeDocumentDto> = emptyList(),
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("can_copy") val canCopy: Boolean = false,
)

@Serializable
data class LemeConversationDetailDto(
    val id: Long,
    val title: String,
    @SerialName("scope_key") val scopeKey: String,
    @SerialName("scope_label") val scopeLabel: String,
    @SerialName("client_condominium_id") val clientCondominiumId: Long? = null,
    @SerialName("messages_count") val messagesCount: Int = 0,
    @SerialName("last_message_at") val lastMessageAt: String? = null,
    @SerialName("last_message_at_br") val lastMessageAtBr: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    val messages: List<LemeMessageDto> = emptyList(),
    @SerialName("available_actions") val availableActions: LemeConversationActionsDto = LemeConversationActionsDto(),
)

@Serializable
data class LemeConversationsResponseDto(
    val items: List<LemeConversationSummaryDto> = emptyList(),
    @SerialName("scope_options") val scopeOptions: List<LemeScopeOptionDto> = emptyList(),
    @SerialName("condominium_options") val condominiumOptions: List<LemeReferenceOptionDto> = emptyList(),
    val availability: LemeAvailabilityDto = LemeAvailabilityDto(),
)

@Serializable
data class LemeConversationResponseDto(
    val item: LemeConversationDetailDto,
    val availability: LemeAvailabilityDto = LemeAvailabilityDto(),
)

@Serializable
data class LemeCreateConversationRequestDto(
    val scope: String = "general",
    @SerialName("client_condominium_id") val clientCondominiumId: Long? = null,
)

@Serializable
data class LemeSendMessageRequestDto(
    val message: String,
)
