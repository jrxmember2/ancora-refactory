package br.com.serratech.ancora.hub.data.repository

import br.com.serratech.ancora.hub.data.api.HubApiService
import br.com.serratech.ancora.hub.data.dto.LemeAvailabilityDto
import br.com.serratech.ancora.hub.data.dto.LemeConversationActionsDto
import br.com.serratech.ancora.hub.data.dto.LemeConversationDetailDto
import br.com.serratech.ancora.hub.data.dto.LemeConversationResponseDto
import br.com.serratech.ancora.hub.data.dto.LemeConversationSummaryDto
import br.com.serratech.ancora.hub.data.dto.LemeConversationsResponseDto
import br.com.serratech.ancora.hub.data.dto.LemeDocumentDto
import br.com.serratech.ancora.hub.data.dto.LemeMessageDto
import br.com.serratech.ancora.hub.data.dto.LemeScopeOptionDto
import br.com.serratech.ancora.hub.data.dto.LemeReferenceOptionDto
import br.com.serratech.ancora.hub.data.dto.LemeCreateConversationRequestDto
import br.com.serratech.ancora.hub.data.dto.LemeSendMessageRequestDto
import br.com.serratech.ancora.hub.domain.model.LemeAvailability
import br.com.serratech.ancora.hub.domain.model.LemeConversationActions
import br.com.serratech.ancora.hub.domain.model.LemeConversationDetail
import br.com.serratech.ancora.hub.domain.model.LemeConversationListData
import br.com.serratech.ancora.hub.domain.model.LemeConversationMessage
import br.com.serratech.ancora.hub.domain.model.LemeConversationPayload
import br.com.serratech.ancora.hub.domain.model.LemeConversationSummary
import br.com.serratech.ancora.hub.domain.model.LemeReferenceOption
import br.com.serratech.ancora.hub.domain.model.LemeScopeOption
import br.com.serratech.ancora.hub.domain.model.LemeSourceDocument
import java.io.IOException
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import retrofit2.HttpException

class LemeRepository(
    private val api: HubApiService,
    private val json: Json,
) {
    suspend fun overview(): LemeConversationListData = try {
        api.lemeConversations().toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toLemeUserMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o histórico de conversas agora.",
            ),
            throwable,
        )
    }

    suspend fun createConversation(
        scope: String = "general",
        clientCondominiumId: Long? = null,
    ): LemeConversationPayload = try {
        api.createLemeConversation(
            LemeCreateConversationRequestDto(
                scope = scope,
                clientCondominiumId = clientCondominiumId,
            ),
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toLemeUserMessage(
                json = json,
                defaultMessage = "Não foi possível iniciar uma nova conversa agora.",
            ),
            throwable,
        )
    }

    suspend fun conversation(conversationId: Long): LemeConversationPayload = try {
        api.lemeConversation(conversationId).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toLemeUserMessage(
                json = json,
                defaultMessage = "Não foi possível carregar a conversa agora.",
            ),
            throwable,
        )
    }

    suspend fun sendMessage(
        conversationId: Long,
        message: String,
    ): LemeConversationPayload = try {
        api.sendLemeMessage(
            conversationId = conversationId,
            payload = LemeSendMessageRequestDto(message = message),
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toLemeUserMessage(
                json = json,
                defaultMessage = "Não foi possível obter resposta agora. Tente novamente.",
            ),
            throwable,
        )
    }

    suspend fun deleteConversation(conversationId: Long) {
        try {
            api.deleteLemeConversation(conversationId)
        } catch (throwable: Throwable) {
            throw UserFacingException(
                throwable.toLemeUserMessage(
                    json = json,
                    defaultMessage = "Não foi possível limpar a conversa agora.",
                ),
                throwable,
            )
        }
    }
}

private fun LemeConversationsResponseDto.toDomain(): LemeConversationListData = LemeConversationListData(
    items = items.map { it.toDomain() },
    scopeOptions = scopeOptions.map { it.toDomain() },
    condominiumOptions = condominiumOptions.map { it.toDomain() },
    availability = availability.toDomain(),
)

private fun LemeConversationResponseDto.toDomain(): LemeConversationPayload = LemeConversationPayload(
    item = item.toDomain(),
    availability = availability.toDomain(),
)

private fun LemeAvailabilityDto.toDomain(): LemeAvailability = LemeAvailability(
    configured = configured,
    aiEnabled = aiEnabled,
    canChat = canChat,
    message = message,
)

private fun LemeScopeOptionDto.toDomain(): LemeScopeOption = LemeScopeOption(
    key = key,
    label = label,
    supported = supported,
    requiresReference = requiresReference,
    referenceType = referenceType,
    description = description,
)

private fun LemeReferenceOptionDto.toDomain(): LemeReferenceOption = LemeReferenceOption(
    id = id,
    label = label,
)

private fun LemeConversationSummaryDto.toDomain(): LemeConversationSummary = LemeConversationSummary(
    id = id,
    title = title,
    scopeKey = scopeKey,
    scopeLabel = scopeLabel,
    clientCondominiumId = clientCondominiumId,
    messagesCount = messagesCount,
    lastMessageAt = lastMessageAt,
    lastMessageAtBr = lastMessageAtBr,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
)

private fun LemeConversationDetailDto.toDomain(): LemeConversationDetail = LemeConversationDetail(
    id = id,
    title = title,
    scopeKey = scopeKey,
    scopeLabel = scopeLabel,
    clientCondominiumId = clientCondominiumId,
    messagesCount = messagesCount,
    lastMessageAt = lastMessageAt,
    lastMessageAtBr = lastMessageAtBr,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    messages = messages.map { it.toDomain() },
    availableActions = availableActions.toDomain(),
)

private fun LemeConversationActionsDto.toDomain(): LemeConversationActions = LemeConversationActions(
    canDelete = canDelete,
)

private fun LemeMessageDto.toDomain(): LemeConversationMessage = LemeConversationMessage(
    id = id,
    role = role,
    content = content,
    status = status,
    provider = provider,
    model = model,
    errorMessage = errorMessage,
    sourceChunksCount = sourceChunksCount,
    documents = documents.map { it.toDomain() },
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    canCopy = canCopy,
)

private fun LemeDocumentDto.toDomain(): LemeSourceDocument = LemeSourceDocument(
    documentKind = documentKind,
    documentKindLabel = documentKindLabel,
    title = title,
    source = source,
    documentType = documentType,
)

private fun Throwable.toLemeUserMessage(
    json: Json,
    defaultMessage: String,
): String {
    apiMessageOrNull(json)?.let { message ->
        if (message.isNotBlank()) {
            return message
        }
    }

    return when {
        this is HttpException && code() == 401 -> "Sessão expirada. Entre novamente."
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
        json.decodeFromString(LemeApiMessageDto.serializer(), rawBody).message
    }.getOrNull()
}

@Serializable
private data class LemeApiMessageDto(
    val message: String? = null,
)
