package br.com.serratech.ancora.hub.data.repository

import br.com.serratech.ancora.hub.data.api.HubApiService
import br.com.serratech.ancora.hub.data.dto.AttachmentsResponseDto
import br.com.serratech.ancora.hub.data.dto.AttachmentDto
import br.com.serratech.ancora.hub.data.dto.CollectionAgreementDto
import br.com.serratech.ancora.hub.data.dto.CollectionContactDto
import br.com.serratech.ancora.hub.data.dto.CollectionDetailDto
import br.com.serratech.ancora.hub.data.dto.CollectionFiltersDto
import br.com.serratech.ancora.hub.data.dto.CollectionInstallmentDto
import br.com.serratech.ancora.hub.data.dto.CollectionInstallmentsResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionListResponseDto
import br.com.serratech.ancora.hub.data.dto.CollectionQuotaDto
import br.com.serratech.ancora.hub.data.dto.CollectionSummaryDto
import br.com.serratech.ancora.hub.data.dto.CollectionTimelineDto
import br.com.serratech.ancora.hub.data.dto.CollectionTimelineResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandActionResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandActionsDto
import br.com.serratech.ancora.hub.data.dto.DemandDetailDto
import br.com.serratech.ancora.hub.data.dto.DemandFiltersDto
import br.com.serratech.ancora.hub.data.dto.DemandListResponseDto
import br.com.serratech.ancora.hub.data.dto.DemandMessageDto
import br.com.serratech.ancora.hub.data.dto.DemandRequesterDto
import br.com.serratech.ancora.hub.data.dto.DemandSlaDto
import br.com.serratech.ancora.hub.data.dto.DemandSummaryDto
import br.com.serratech.ancora.hub.data.dto.PaginationDto
import br.com.serratech.ancora.hub.data.dto.ProcessDetailDto
import br.com.serratech.ancora.hub.data.dto.ProcessFiltersDto
import br.com.serratech.ancora.hub.data.dto.ProcessListResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessMovementDto
import br.com.serratech.ancora.hub.data.dto.ProcessMovementsResponseDto
import br.com.serratech.ancora.hub.data.dto.ProcessPartyDto
import br.com.serratech.ancora.hub.data.dto.ProcessSideDto
import br.com.serratech.ancora.hub.data.dto.ProcessStatusDto
import br.com.serratech.ancora.hub.data.dto.ProcessSummaryDto
import br.com.serratech.ancora.hub.data.dto.UserOptionDto
import br.com.serratech.ancora.hub.data.dto.ValueLabelDto
import br.com.serratech.ancora.hub.domain.model.AttachmentPage
import br.com.serratech.ancora.hub.domain.model.CollectionAgreementInfo
import br.com.serratech.ancora.hub.domain.model.CollectionContact
import br.com.serratech.ancora.hub.domain.model.CollectionDetail
import br.com.serratech.ancora.hub.domain.model.CollectionFilters
import br.com.serratech.ancora.hub.domain.model.CollectionInstallmentItem
import br.com.serratech.ancora.hub.domain.model.CollectionInstallmentPage
import br.com.serratech.ancora.hub.domain.model.CollectionListData
import br.com.serratech.ancora.hub.domain.model.CollectionListItem
import br.com.serratech.ancora.hub.domain.model.CollectionQuota
import br.com.serratech.ancora.hub.domain.model.CollectionTimelineItem
import br.com.serratech.ancora.hub.domain.model.CollectionTimelinePage
import br.com.serratech.ancora.hub.domain.model.DemandAvailableActions
import br.com.serratech.ancora.hub.domain.model.DemandDetail
import br.com.serratech.ancora.hub.domain.model.DemandFilters
import br.com.serratech.ancora.hub.domain.model.DemandListData
import br.com.serratech.ancora.hub.domain.model.DemandListItem
import br.com.serratech.ancora.hub.domain.model.DemandMessageItem
import br.com.serratech.ancora.hub.domain.model.DemandRequester
import br.com.serratech.ancora.hub.domain.model.DemandSla
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.HubAttachment
import br.com.serratech.ancora.hub.domain.model.HubUserOption
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.ProcessDetail
import br.com.serratech.ancora.hub.domain.model.ProcessFilters
import br.com.serratech.ancora.hub.domain.model.ProcessListData
import br.com.serratech.ancora.hub.domain.model.ProcessListItem
import br.com.serratech.ancora.hub.domain.model.ProcessMovementItem
import br.com.serratech.ancora.hub.domain.model.ProcessMovementPage
import br.com.serratech.ancora.hub.domain.model.ProcessParty
import br.com.serratech.ancora.hub.domain.model.ProcessSide
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import retrofit2.HttpException

class DemandRepository(
    private val api: HubApiService,
    private val json: Json,
) {
    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        status: String? = null,
        priority: String? = null,
        assignedUserId: Long? = null,
        query: String? = null,
    ): DemandListData = try {
        api.demands(
            page = page,
            perPage = perPage,
            status = status,
            priority = priority,
            assignedUserId = assignedUserId,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as demandas agora.",
            ),
            throwable,
        )
    }

    suspend fun detail(demandId: Long): DemandDetail = try {
        api.demand(demandId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar a demanda agora.",
            ),
            throwable,
        )
    }

    suspend fun reply(demandId: Long, message: String): DemandDetail = mutateDemand {
        api.replyDemand(
            demandId = demandId,
            payload = mapOf("message" to message),
        )
    }

    suspend fun updateStatus(demandId: Long, status: String): DemandDetail = mutateDemand {
        api.updateDemandStatus(
            demandId = demandId,
            payload = mapOf("status" to status),
        )
    }

    suspend fun assign(demandId: Long, assignedUserId: Long): DemandDetail = try {
        val response = api.assignDemand(
            demandId = demandId,
            payload = mapOf("assigned_user_id" to assignedUserId),
        )
        response.item?.toDomain()
            ?: detail(demandId)
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível atualizar o responsável agora.",
            ),
            throwable,
        )
    }

    private suspend fun mutateDemand(block: suspend () -> DemandActionResponseDto): DemandDetail = try {
        val response = block()
        response.item?.toDomain()
            ?: throw UserFacingException("Não foi possível atualizar a demanda agora.")
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível atualizar a demanda agora.",
            ),
            throwable,
        )
    }
}

class ProcessRepository(
    private val api: HubApiService,
    private val json: Json,
) {
    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        statusOptionId: Long? = null,
        query: String? = null,
    ): ProcessListData = try {
        api.processes(
            page = page,
            perPage = perPage,
            statusOptionId = statusOptionId,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os processos agora.",
            ),
            throwable,
        )
    }

    suspend fun detail(processId: Long): ProcessDetail = try {
        api.process(processId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o processo agora.",
            ),
            throwable,
        )
    }

    suspend fun movements(
        processId: Long,
        page: Int = 1,
        perPage: Int = 20,
    ): ProcessMovementPage = try {
        api.processMovements(
            processId = processId,
            page = page,
            perPage = perPage,
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as movimentações agora.",
            ),
            throwable,
        )
    }

    suspend fun attachments(
        processId: Long,
        page: Int = 1,
        perPage: Int = 20,
    ): AttachmentPage = try {
        api.processAttachments(
            processId = processId,
            page = page,
            perPage = perPage,
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os anexos agora.",
            ),
            throwable,
        )
    }
}

class CollectionRepository(
    private val api: HubApiService,
    private val json: Json,
) {
    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        status: String? = null,
        workflowStage: String? = null,
        situation: String? = null,
        billingStatus: String? = null,
        query: String? = null,
    ): CollectionListData = try {
        api.collections(
            page = page,
            perPage = perPage,
            status = status,
            workflowStage = workflowStage,
            situation = situation,
            billingStatus = billingStatus,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as cobranças agora.",
            ),
            throwable,
        )
    }

    suspend fun detail(collectionId: Long): CollectionDetail = try {
        api.collection(collectionId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar a cobrança agora.",
            ),
            throwable,
        )
    }

    suspend fun installments(
        collectionId: Long,
        page: Int = 1,
        perPage: Int = 20,
    ): CollectionInstallmentPage = try {
        api.collectionInstallments(
            collectionId = collectionId,
            page = page,
            perPage = perPage,
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as parcelas agora.",
            ),
            throwable,
        )
    }

    suspend fun timeline(
        collectionId: Long,
        page: Int = 1,
        perPage: Int = 20,
    ): CollectionTimelinePage = try {
        api.collectionTimeline(
            collectionId = collectionId,
            page = page,
            perPage = perPage,
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o histórico agora.",
            ),
            throwable,
        )
    }

    suspend fun attachments(
        collectionId: Long,
        page: Int = 1,
        perPage: Int = 20,
    ): AttachmentPage = try {
        api.collectionAttachments(
            collectionId = collectionId,
            page = page,
            perPage = perPage,
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toModuleFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os anexos agora.",
            ),
            throwable,
        )
    }
}

private fun DemandListResponseDto.toDomain(): DemandListData = DemandListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.toDomain(),
)

private fun DemandFiltersDto.toDomain(): DemandFilters = DemandFilters(
    statuses = statuses.map { it.toDomain() },
    priorities = priorities.map { it.toDomain() },
    assignees = assignees.map { it.toDomain() },
)

private fun DemandSummaryDto.toDomain(): DemandListItem = DemandListItem(
    id = id,
    protocol = protocol,
    title = title,
    clientName = clientName,
    condominiumName = condominiumName,
    status = status,
    statusLabel = statusLabel,
    priority = priority,
    priorityLabel = priorityLabel,
    categoryName = categoryName,
    assignee = assignee?.toDomain(),
    sla = sla.toDomain(),
    messagesCount = messagesCount,
    attachmentsCount = attachmentsCount,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun DemandSlaDto.toDomain(): DemandSla = DemandSla(
    status = status,
    label = label,
    dueAt = dueAt,
    dueAtBr = dueAtBr,
    progressPercent = progressPercent,
)

private fun DemandDetailDto.toDomain(): DemandDetail = DemandDetail(
    summary = toSummary(),
    description = description,
    requester = requester.toDomain(),
    entityName = entityName,
    messages = messages.map { it.toDomain() },
    attachments = attachments.map { it.toDomain() },
    availableActions = availableActions.toDomain(),
    statusOptions = statusOptions.map { it.toDomain() },
    assignees = assignees.map { it.toDomain() },
    closedAt = closedAt,
    closedAtBr = closedAtBr,
)

private fun DemandDetailDto.toSummary(): DemandListItem = DemandListItem(
    id = id,
    protocol = protocol,
    title = title,
    clientName = clientName,
    condominiumName = condominiumName,
    status = status,
    statusLabel = statusLabel,
    priority = priority,
    priorityLabel = priorityLabel,
    categoryName = categoryName,
    assignee = assignee?.toDomain(),
    sla = sla.toDomain(),
    messagesCount = messagesCount,
    attachmentsCount = attachmentsCount,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun DemandRequesterDto.toDomain(): DemandRequester = DemandRequester(
    name = name,
    email = email,
)

private fun DemandMessageDto.toDomain(): DemandMessageItem = DemandMessageItem(
    id = id,
    senderType = senderType,
    senderName = senderName,
    isInternal = isInternal,
    message = message,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    attachments = attachments.map { it.toDomain() },
)

private fun DemandActionsDto.toDomain(): DemandAvailableActions = DemandAvailableActions(
    canReply = canReply,
    canUpdateStatus = canUpdateStatus,
    canAssign = canAssign,
)

private fun ProcessListResponseDto.toDomain(): ProcessListData = ProcessListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.toDomain(),
)

private fun ProcessFiltersDto.toDomain(): ProcessFilters = ProcessFilters(
    statuses = statuses.map { it.toDomain() },
)

private fun ProcessStatusDto.toDomain(): FilterValueOption = FilterValueOption(
    value = id.toString(),
    label = label,
    color = color,
)

private fun ProcessSummaryDto.toDomain(): ProcessListItem = ProcessListItem(
    id = id,
    processNumber = processNumber,
    clientName = clientName,
    condominiumName = condominiumName,
    className = className,
    subjectName = subjectName,
    status = status,
    responsibleLawyer = responsibleLawyer,
    lastMovementAt = lastMovementAt,
    lastMovementAtBr = lastMovementAtBr,
    lastMovementDescription = lastMovementDescription,
    isPrivate = isPrivate,
    createdAt = createdAt,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ProcessDetailDto.toDomain(): ProcessDetail = ProcessDetail(
    summary = toSummary(),
    openedAt = openedAt,
    openedAtBr = openedAtBr,
    closedAt = closedAt,
    closedAtBr = closedAtBr,
    notes = notes,
    judgingBody = judgingBody,
    actionType = actionType,
    clientPosition = clientPosition,
    adversePosition = adversePosition,
    winProbability = winProbability,
    closureType = closureType,
    client = client.toDomain(),
    adverse = adverse.toDomain(),
    parties = parties.map { it.toDomain() },
    movementsPreview = movementsPreview.map { it.toDomain() },
    attachmentsPreview = attachmentsPreview.map { it.toDomain() },
)

private fun ProcessDetailDto.toSummary(): ProcessListItem = ProcessListItem(
    id = id,
    processNumber = processNumber,
    clientName = clientName,
    condominiumName = condominiumName,
    className = className,
    subjectName = subjectName,
    status = status,
    responsibleLawyer = responsibleLawyer,
    lastMovementAt = lastMovementAt,
    lastMovementAtBr = lastMovementAtBr,
    lastMovementDescription = lastMovementDescription,
    isPrivate = isPrivate,
    createdAt = createdAt,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ProcessSideDto.toDomain(): ProcessSide = ProcessSide(
    name = name,
    condominiumName = condominiumName,
    syndicName = syndicName,
)

private fun ProcessPartyDto.toDomain(): ProcessParty = ProcessParty(
    id = id,
    partyType = partyType,
    name = name,
    document = document,
    sideLabel = sideLabel,
)

private fun ProcessMovementsResponseDto.toDomain(): ProcessMovementPage = ProcessMovementPage(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
)

private fun ProcessMovementDto.toDomain(): ProcessMovementItem = ProcessMovementItem(
    id = id,
    description = description,
    phaseDate = phaseDate,
    phaseDateBr = phaseDateBr,
    phaseTime = phaseTime,
    notes = notes,
    legalOpinion = legalOpinion,
    conference = conference,
    isReviewed = isReviewed,
    source = source,
    createdByName = createdByName,
    attachments = attachments.map { it.toDomain() },
)

private fun AttachmentsResponseDto.toDomain(): AttachmentPage = AttachmentPage(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
)

private fun CollectionListResponseDto.toDomain(): CollectionListData = CollectionListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.toDomain(),
)

private fun CollectionFiltersDto.toDomain(): CollectionFilters = CollectionFilters(
    workflowStages = workflowStages.map { it.toDomain() },
    situations = situations.map { it.toDomain() },
    billingStatuses = billingStatuses.map { it.toDomain() },
)

private fun CollectionSummaryDto.toDomain(): CollectionListItem = CollectionListItem(
    id = id,
    osNumber = osNumber,
    condominiumName = condominiumName,
    unitLabel = unitLabel,
    debtorName = debtorName,
    ownerName = ownerName,
    tenantName = tenantName,
    agreementTotal = agreementTotal,
    agreementTotalLabel = agreementTotalLabel,
    workflowStage = workflowStage,
    workflowStageLabel = workflowStageLabel,
    situation = situation,
    situationLabel = situationLabel,
    billingStatus = billingStatus,
    billingStatusLabel = billingStatusLabel,
    lastProgressAt = lastProgressAt,
    lastProgressAtBr = lastProgressAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun CollectionDetailDto.toDomain(): CollectionDetail = CollectionDetail(
    summary = toSummary(),
    chargeType = chargeType,
    caseMode = caseMode,
    debtorDocument = debtorDocument,
    entryAmount = entryAmount,
    entryAmountLabel = entryAmountLabel,
    feesAmount = feesAmount,
    feesAmountLabel = feesAmountLabel,
    billingDate = billingDate,
    billingDateBr = billingDateBr,
    judicialCaseNumber = judicialCaseNumber,
    syndicName = syndicName,
    administratorName = administratorName,
    contacts = contacts.map { it.toDomain() },
    quotas = quotas.map { it.toDomain() },
    agreement = agreement.toDomain(),
)

private fun CollectionDetailDto.toSummary(): CollectionListItem = CollectionListItem(
    id = id,
    osNumber = osNumber,
    condominiumName = condominiumName,
    unitLabel = unitLabel,
    debtorName = debtorName,
    ownerName = ownerName,
    tenantName = tenantName,
    agreementTotal = agreementTotal,
    agreementTotalLabel = agreementTotalLabel,
    workflowStage = workflowStage,
    workflowStageLabel = workflowStageLabel,
    situation = situation,
    situationLabel = situationLabel,
    billingStatus = billingStatus,
    billingStatusLabel = billingStatusLabel,
    lastProgressAt = lastProgressAt,
    lastProgressAtBr = lastProgressAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun CollectionContactDto.toDomain(): CollectionContact = CollectionContact(
    id = id,
    type = type,
    value = value,
    isPrimary = isPrimary,
    isWhatsapp = isWhatsapp,
)

private fun CollectionQuotaDto.toDomain(): CollectionQuota = CollectionQuota(
    id = id,
    referenceLabel = referenceLabel,
    dueDate = dueDate,
    dueDateBr = dueDateBr,
    status = status,
    originalAmount = originalAmount,
    originalAmountLabel = originalAmountLabel,
    updatedAmount = updatedAmount,
    updatedAmountLabel = updatedAmountLabel,
)

private fun CollectionAgreementDto.toDomain(): CollectionAgreementInfo = CollectionAgreementInfo(
    hasTerm = hasTerm,
    hasSignatureRequests = hasSignatureRequests,
    signatureRequestsCount = signatureRequestsCount,
)

private fun CollectionInstallmentsResponseDto.toDomain(): CollectionInstallmentPage = CollectionInstallmentPage(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
)

private fun CollectionInstallmentDto.toDomain(): CollectionInstallmentItem = CollectionInstallmentItem(
    id = id,
    label = label,
    installmentType = installmentType,
    installmentNumber = installmentNumber,
    dueDate = dueDate,
    dueDateBr = dueDateBr,
    amount = amount,
    amountLabel = amountLabel,
    status = status,
)

private fun CollectionTimelineResponseDto.toDomain(): CollectionTimelinePage = CollectionTimelinePage(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
)

private fun CollectionTimelineDto.toDomain(): CollectionTimelineItem = CollectionTimelineItem(
    id = id,
    eventType = eventType,
    description = description,
    userName = userName,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
)

private fun PaginationDto.toDomain(): PaginationMeta = PaginationMeta(
    currentPage = currentPage,
    lastPage = lastPage,
    perPage = perPage,
    total = total,
)

private fun ValueLabelDto.toDomain(): FilterValueOption = FilterValueOption(
    value = value,
    label = label,
    color = color,
)

private fun UserOptionDto.toDomain(): HubUserOption = HubUserOption(
    id = id,
    name = name,
    email = email,
    initials = initials,
)

private fun AttachmentDto.toDomain(): HubAttachment = HubAttachment(
    id = id,
    name = name,
    mimeType = mimeType,
    fileSize = fileSize,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    uploadedByName = uploadedByName,
    tagLabel = fileRole ?: tagLabel,
)

private fun Throwable.toModuleFacingMessage(
    json: Json,
    defaultMessage: String,
): String {
    apiModuleMessageOrNull(json)?.let { message ->
        if (message.isNotBlank()) {
            return message
        }
    }

    return when {
        this is HttpException && code() == 401 -> "Sessão expirada. Entre novamente."
        this is HttpException && code() == 403 -> "Você não possui permissão para acessar este módulo."
        else -> defaultMessage
    }
}

private fun Throwable.apiModuleMessageOrNull(json: Json): String? {
    val exception = this as? HttpException ?: return null
    val rawBody = runCatching { exception.response()?.errorBody()?.string() }.getOrNull().orEmpty()
    if (rawBody.isBlank()) {
        return null
    }

    return runCatching {
        json.decodeFromString(ApiMessagePayloadDto.serializer(), rawBody).message
    }.getOrNull()
}

@Serializable
private data class ApiMessagePayloadDto(
    val message: String? = null,
)
