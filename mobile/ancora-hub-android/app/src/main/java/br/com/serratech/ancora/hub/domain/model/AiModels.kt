package br.com.serratech.ancora.hub.domain.model

data class LemeAvailability(
    val configured: Boolean,
    val aiEnabled: Boolean,
    val canChat: Boolean,
    val message: String?,
)

data class LemeScopeOption(
    val key: String,
    val label: String,
    val supported: Boolean,
    val requiresReference: Boolean,
    val referenceType: String?,
    val description: String?,
)

data class LemeReferenceOption(
    val id: Long,
    val label: String,
)

data class LemeConversationListData(
    val items: List<LemeConversationSummary>,
    val scopeOptions: List<LemeScopeOption>,
    val condominiumOptions: List<LemeReferenceOption>,
    val availability: LemeAvailability,
)

data class LemeConversationSummary(
    val id: Long,
    val title: String,
    val scopeKey: String,
    val scopeLabel: String,
    val clientCondominiumId: Long?,
    val messagesCount: Int,
    val lastMessageAt: String?,
    val lastMessageAtBr: String?,
    val createdAt: String?,
    val createdAtBr: String?,
)

data class LemeConversationActions(
    val canDelete: Boolean,
)

data class LemeSourceDocument(
    val documentKind: String?,
    val documentKindLabel: String?,
    val title: String?,
    val source: String?,
    val documentType: String?,
)

data class LemeConversationMessage(
    val id: Long,
    val role: String,
    val content: String,
    val status: String,
    val provider: String?,
    val model: String?,
    val errorMessage: String?,
    val sourceChunksCount: Int?,
    val documents: List<LemeSourceDocument>,
    val createdAt: String?,
    val createdAtBr: String?,
    val canCopy: Boolean,
)

data class LemeConversationDetail(
    val id: Long,
    val title: String,
    val scopeKey: String,
    val scopeLabel: String,
    val clientCondominiumId: Long?,
    val messagesCount: Int,
    val lastMessageAt: String?,
    val lastMessageAtBr: String?,
    val createdAt: String?,
    val createdAtBr: String?,
    val messages: List<LemeConversationMessage>,
    val availableActions: LemeConversationActions,
)

data class LemeConversationPayload(
    val item: LemeConversationDetail,
    val availability: LemeAvailability,
)
