package br.com.serratech.ancora.hub.data.repository

import android.app.Application
import br.com.serratech.ancora.hub.data.api.HubApiService
import br.com.serratech.ancora.hub.data.dto.AttachmentDto
import br.com.serratech.ancora.hub.data.dto.ContractDetailDto
import br.com.serratech.ancora.hub.data.dto.ContractDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ContractDocumentDto
import br.com.serratech.ancora.hub.data.dto.ContractDocumentsResponseDto
import br.com.serratech.ancora.hub.data.dto.ContractFiltersDto
import br.com.serratech.ancora.hub.data.dto.ContractListResponseDto
import br.com.serratech.ancora.hub.data.dto.ContractSummaryDto
import br.com.serratech.ancora.hub.data.dto.FinanceAlertDto
import br.com.serratech.ancora.hub.data.dto.FinanceCashflowItemDto
import br.com.serratech.ancora.hub.data.dto.FinanceCashflowResponseDto
import br.com.serratech.ancora.hub.data.dto.FinanceCashflowSummaryDto
import br.com.serratech.ancora.hub.data.dto.FinanceDashboardResponseDto
import br.com.serratech.ancora.hub.data.dto.FinanceDashboardSummaryDto
import br.com.serratech.ancora.hub.data.dto.FinancePayableDto
import br.com.serratech.ancora.hub.data.dto.FinancePayablesResponseDto
import br.com.serratech.ancora.hub.data.dto.FinanceReceivableDto
import br.com.serratech.ancora.hub.data.dto.FinanceReceivablesResponseDto
import br.com.serratech.ancora.hub.data.dto.FinanceStateFiltersDto
import br.com.serratech.ancora.hub.data.dto.FinanceSummaryCardDto
import br.com.serratech.ancora.hub.data.dto.PaginationDto
import br.com.serratech.ancora.hub.data.dto.ProposalDetailDto
import br.com.serratech.ancora.hub.data.dto.ProposalDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ProposalFiltersDto
import br.com.serratech.ancora.hub.data.dto.ProposalHistoryDto
import br.com.serratech.ancora.hub.data.dto.ProposalListResponseDto
import br.com.serratech.ancora.hub.data.dto.ProposalSummaryDto
import br.com.serratech.ancora.hub.data.dto.SignatureActionResponseDto
import br.com.serratech.ancora.hub.data.dto.SignatureActionsDto
import br.com.serratech.ancora.hub.data.dto.SignatureDetailDto
import br.com.serratech.ancora.hub.data.dto.SignatureDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.SignatureEventDto
import br.com.serratech.ancora.hub.data.dto.SignatureFiltersDto
import br.com.serratech.ancora.hub.data.dto.SignatureListResponseDto
import br.com.serratech.ancora.hub.data.dto.SignatureSignerDto
import br.com.serratech.ancora.hub.data.dto.SignatureSummaryDto
import br.com.serratech.ancora.hub.data.dto.ValueLabelDto
import br.com.serratech.ancora.hub.domain.model.ContractDetail
import br.com.serratech.ancora.hub.domain.model.ContractDocumentItem
import br.com.serratech.ancora.hub.domain.model.ContractListData
import br.com.serratech.ancora.hub.domain.model.ContractListItem
import br.com.serratech.ancora.hub.domain.model.DownloadedDocument
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.FinanceAlertItem
import br.com.serratech.ancora.hub.domain.model.FinanceCashflowData
import br.com.serratech.ancora.hub.domain.model.FinanceCashflowItem
import br.com.serratech.ancora.hub.domain.model.FinanceCashflowSummary
import br.com.serratech.ancora.hub.domain.model.FinanceDashboardData
import br.com.serratech.ancora.hub.domain.model.FinanceDashboardSummary
import br.com.serratech.ancora.hub.domain.model.FinancePayableItem
import br.com.serratech.ancora.hub.domain.model.FinancePayablesData
import br.com.serratech.ancora.hub.domain.model.FinanceReceivableItem
import br.com.serratech.ancora.hub.domain.model.FinanceReceivablesData
import br.com.serratech.ancora.hub.domain.model.FinanceSummaryCard
import br.com.serratech.ancora.hub.domain.model.HubAttachment
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.ProposalDetail
import br.com.serratech.ancora.hub.domain.model.ProposalFilters
import br.com.serratech.ancora.hub.domain.model.ProposalHistoryItem
import br.com.serratech.ancora.hub.domain.model.ProposalListData
import br.com.serratech.ancora.hub.domain.model.ProposalListItem
import br.com.serratech.ancora.hub.domain.model.SignatureAvailableActions
import br.com.serratech.ancora.hub.domain.model.SignatureDetail
import br.com.serratech.ancora.hub.domain.model.SignatureEventItem
import br.com.serratech.ancora.hub.domain.model.SignatureFilters
import br.com.serratech.ancora.hub.domain.model.SignatureListData
import br.com.serratech.ancora.hub.domain.model.SignatureListItem
import br.com.serratech.ancora.hub.domain.model.SignatureSignerItem
import java.io.File
import java.io.IOException
import java.net.URLConnection
import java.util.Locale
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.HttpException

class ProposalRepository(
    private val api: HubApiService,
    private val json: Json,
) {
    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        statusId: Long? = null,
        serviceId: Long? = null,
        query: String? = null,
    ): ProposalListData = try {
        api.proposals(
            page = page,
            perPage = perPage,
            statusId = statusId,
            serviceId = serviceId,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as propostas agora.",
            ),
            throwable,
        )
    }

    suspend fun detail(proposalId: Long): ProposalDetail = try {
        api.proposal(proposalId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar a proposta agora.",
            ),
            throwable,
        )
    }
}

class ContractRepository(
    application: Application,
    private val api: HubApiService,
    private val json: Json,
) {
    private val cacheDirectory = File(application.cacheDir, "hub-contracts").apply { mkdirs() }

    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        status: String? = null,
        query: String? = null,
    ): ContractListData = try {
        api.contracts(
            page = page,
            perPage = perPage,
            status = status,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os contratos agora.",
            ),
            throwable,
        )
    }

    suspend fun detail(contractId: Long): ContractDetail = try {
        api.contract(contractId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o contrato agora.",
            ),
            throwable,
        )
    }

    suspend fun documents(contractId: Long): List<ContractDocumentItem> = try {
        api.contractDocuments(contractId).items.map { it.toDomain() }
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os documentos do contrato agora.",
            ),
            throwable,
        )
    }

    suspend fun downloadDocument(
        contractId: Long,
        document: ContractDocumentItem,
    ): DownloadedDocument = withContext(Dispatchers.IO) {
        try {
            val response = api.downloadContractDocument(
                contractId = contractId,
                kind = document.downloadKind,
                referenceId = document.referenceId,
            )
            if (!response.isSuccessful) {
                throw HttpException(response)
            }

            val body = response.body()
                ?: throw UserFacingException("Documento indisponível no momento.")

            val safeName = sanitizeFileName(document.name)
            val targetFile = File(cacheDirectory, safeName)
            body.byteStream().use { input ->
                targetFile.outputStream().use { output ->
                    input.copyTo(output)
                }
            }

            val mimeType = response.headers()["Content-Type"]
                ?: document.mimeType
                ?: URLConnection.guessContentTypeFromName(safeName)
                ?: "application/octet-stream"

            DownloadedDocument(
                file = targetFile,
                displayName = document.name,
                mimeType = mimeType,
            )
        } catch (throwable: Throwable) {
            throw UserFacingException(
                throwable.toBusinessFacingMessage(
                    json = json,
                    defaultMessage = "Não foi possível abrir o documento agora.",
                ),
                throwable,
            )
        }
    }

    private fun sanitizeFileName(name: String): String {
        val trimmed = name.trim().ifBlank { "contrato" }
        return trimmed.replace(Regex("[^A-Za-z0-9._-]"), "_")
    }
}

class SignatureRepository(
    private val api: HubApiService,
    private val json: Json,
) {
    suspend fun list(
        page: Int = 1,
        perPage: Int = 20,
        status: String? = null,
        origin: String? = null,
        query: String? = null,
    ): SignatureListData = try {
        api.signatures(
            page = page,
            perPage = perPage,
            status = status,
            origin = origin,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o Assinador Eletrônico agora.",
            ),
            throwable,
        )
    }

    suspend fun detail(signatureId: Long): SignatureDetail = try {
        api.signature(signatureId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar a assinatura agora.",
            ),
            throwable,
        )
    }

    suspend fun create(
        fileName: String,
        mimeType: String,
        bytes: ByteArray,
        title: String,
        description: String?,
        category: String?,
        signerMessage: String?,
        signers: List<SignatureDraftSignerPayload>,
    ): SignatureDetail = try {
        val documentPart = MultipartBody.Part.createFormData(
            "document_file",
            fileName,
            bytes.toRequestBody((mimeType.ifBlank { "application/pdf" }).toMediaTypeOrNull()),
        )

        val response = api.createSignature(
            documentFile = documentPart,
            title = title.toRequestBody("text/plain".toMediaTypeOrNull()),
            description = description?.takeIf { it.isNotBlank() }?.toRequestBody("text/plain".toMediaTypeOrNull()),
            category = category?.takeIf { it.isNotBlank() }?.toRequestBody("text/plain".toMediaTypeOrNull()),
            signersJson = json.encodeToString(signers).toRequestBody("application/json".toMediaTypeOrNull()),
            signerMessage = signerMessage?.takeIf { it.isNotBlank() }?.toRequestBody("text/plain".toMediaTypeOrNull()),
        )

        response.item?.toDomain()
            ?: throw UserFacingException("Não foi possível iniciar a assinatura agora.")
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível enviar o documento para assinatura agora.",
            ),
            throwable,
        )
    }

    suspend fun sync(signatureId: Long): SignatureDetail = try {
        val response = api.syncSignature(signatureId)
        response.item?.toDomain()
            ?: detail(signatureId)
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível sincronizar a assinatura agora.",
            ),
            throwable,
        )
    }
}

class FinanceRepository(
    private val api: HubApiService,
    private val json: Json,
) {
    suspend fun dashboard(): FinanceDashboardData = try {
        api.financeDashboard().toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o Financeiro 360 agora.",
            ),
            throwable,
        )
    }

    suspend fun receivables(
        page: Int = 1,
        perPage: Int = 20,
        filter: String? = null,
        query: String? = null,
    ): FinanceReceivablesData = try {
        api.financeReceivables(
            page = page,
            perPage = perPage,
            filter = filter,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as contas a receber agora.",
            ),
            throwable,
        )
    }

    suspend fun payables(
        page: Int = 1,
        perPage: Int = 20,
        filter: String? = null,
        query: String? = null,
    ): FinancePayablesData = try {
        api.financePayables(
            page = page,
            perPage = perPage,
            filter = filter,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as contas a pagar agora.",
            ),
            throwable,
        )
    }

    suspend fun cashflow(
        page: Int = 1,
        perPage: Int = 20,
        period: String = "30d",
    ): FinanceCashflowData = try {
        api.financeCashflow(
            page = page,
            perPage = perPage,
            period = period,
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toBusinessFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o fluxo de caixa agora.",
            ),
            throwable,
        )
    }
}

private fun ProposalListResponseDto.toDomain(): ProposalListData = ProposalListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.toDomain(),
)

private fun ProposalFiltersDto.toDomain(): ProposalFilters = ProposalFilters(
    statuses = statuses.map { it.toDomain() },
    services = services.map { it.toDomain() },
)

private fun ProposalSummaryDto.toDomain(): ProposalListItem = ProposalListItem(
    id = id,
    code = code,
    clientName = clientName,
    serviceId = serviceId,
    serviceName = serviceName,
    statusId = statusId,
    statusLabel = statusLabel,
    statusColor = statusColor,
    proposalTotal = proposalTotal,
    proposalTotalLabel = proposalTotalLabel,
    closedTotal = closedTotal,
    closedTotalLabel = closedTotalLabel,
    withoutAmount = withoutAmount,
    proposalDate = proposalDate,
    proposalDateBr = proposalDateBr,
    followupDate = followupDate,
    followupDateBr = followupDateBr,
    requesterName = requesterName,
    attachmentsCount = attachmentsCount,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ProposalDetailDto.toDomain(): ProposalDetail = ProposalDetail(
    summary = toSummary(),
    administradoraName = administradoraName,
    sendMethodName = sendMethodName,
    requesterPhone = requesterPhone,
    contactEmail = contactEmail,
    hasReferral = hasReferral,
    referralName = referralName,
    refusalReason = refusalReason,
    validityDays = validityDays,
    notes = notes,
    history = history.map { it.toDomain() },
    attachments = attachments.map { it.toDomain() },
)

private fun ProposalDetailDto.toSummary(): ProposalListItem = ProposalListItem(
    id = id,
    code = code,
    clientName = clientName,
    serviceId = serviceId,
    serviceName = serviceName,
    statusId = statusId,
    statusLabel = statusLabel,
    statusColor = statusColor,
    proposalTotal = proposalTotal,
    proposalTotalLabel = proposalTotalLabel,
    closedTotal = closedTotal,
    closedTotalLabel = closedTotalLabel,
    withoutAmount = withoutAmount,
    proposalDate = proposalDate,
    proposalDateBr = proposalDateBr,
    followupDate = followupDate,
    followupDateBr = followupDateBr,
    requesterName = requesterName,
    attachmentsCount = attachmentsCount,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ProposalHistoryDto.toDomain(): ProposalHistoryItem = ProposalHistoryItem(
    id = id,
    action = action,
    summary = summary,
    userEmail = userEmail,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
)

private fun ContractListResponseDto.toDomain(): ContractListData = ContractListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.statuses.map { it.toDomain() },
)

private fun ContractSummaryDto.toDomain(): ContractListItem = ContractListItem(
    id = id,
    code = code,
    title = title,
    clientName = clientName,
    objectLabel = objectLabel,
    status = status,
    statusLabel = statusLabel,
    type = type,
    categoryName = categoryName,
    value = value,
    valueLabel = valueLabel,
    paymentMethod = paymentMethod,
    paymentMethodLabel = paymentMethodLabel,
    startDate = startDate,
    startDateBr = startDateBr,
    endDate = endDate,
    endDateBr = endDateBr,
    isExpired = isExpired,
    hasFinalPdf = hasFinalPdf,
    signaturePending = signaturePending,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ContractDetailDto.toDomain(): ContractDetail = ContractDetail(
    summary = toSummary(),
    description = description,
    contentExcerpt = contentExcerpt,
    billingType = billingType,
    billingTypeLabel = billingTypeLabel,
    recurrence = recurrence,
    recurrenceLabel = recurrenceLabel,
    responsibleName = responsibleName,
    condominiumName = condominiumName,
    syndicName = syndicName,
    financialAccountName = financialAccountName,
    proposalCode = proposalCode,
    processNumber = processNumber,
    documentsCount = documentsCount,
    latestSignature = latestSignature?.toDomain(),
)

private fun ContractDetailDto.toSummary(): ContractListItem = ContractListItem(
    id = id,
    code = code,
    title = title,
    clientName = clientName,
    objectLabel = objectLabel,
    status = status,
    statusLabel = statusLabel,
    type = type,
    categoryName = categoryName,
    value = value,
    valueLabel = valueLabel,
    paymentMethod = paymentMethod,
    paymentMethodLabel = paymentMethodLabel,
    startDate = startDate,
    startDateBr = startDateBr,
    endDate = endDate,
    endDateBr = endDateBr,
    isExpired = isExpired,
    hasFinalPdf = hasFinalPdf,
    signaturePending = signaturePending,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ContractDocumentDto.toDomain(): ContractDocumentItem = ContractDocumentItem(
    id = id,
    kind = kind,
    kindLabel = kindLabel,
    name = name,
    description = description,
    mimeType = mimeType,
    fileSize = fileSize,
    fileSizeLabel = fileSizeLabel,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    downloadKind = downloadKind,
    referenceId = referenceId,
)

private fun SignatureListResponseDto.toDomain(): SignatureListData = SignatureListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.toDomain(),
)

private fun SignatureFiltersDto.toDomain(): SignatureFilters = SignatureFilters(
    statuses = statuses.map { it.toDomain() },
    origins = origins.map { it.toDomain() },
)

private fun SignatureSummaryDto.toDomain(): SignatureListItem = SignatureListItem(
    id = id,
    documentName = documentName,
    status = status,
    statusLabel = statusLabel,
    sourceType = sourceType,
    sourceLabel = sourceLabel,
    signersCount = signersCount,
    pendingCount = pendingCount,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    completedAt = completedAt,
    completedAtBr = completedAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun SignatureDetailDto.toDomain(): SignatureDetail = SignatureDetail(
    summary = toSummary(),
    requestedAt = requestedAt,
    requestedAtBr = requestedAtBr,
    lastSyncedAt = lastSyncedAt,
    lastSyncedAtBr = lastSyncedAtBr,
    provider = provider,
    signingUrlAvailable = signingUrlAvailable,
    signers = signers.map { it.toDomain() },
    events = events.map { it.toDomain() },
    availableActions = availableActions.toDomain(),
)

private fun SignatureDetailDto.toSummary(): SignatureListItem = SignatureListItem(
    id = id,
    documentName = documentName,
    status = status,
    statusLabel = statusLabel,
    sourceType = sourceType,
    sourceLabel = sourceLabel,
    signersCount = signersCount,
    pendingCount = pendingCount,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    completedAt = completedAt,
    completedAtBr = completedAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun SignatureSignerDto.toDomain(): SignatureSignerItem = SignatureSignerItem(
    id = id,
    name = name,
    email = email,
    phone = phone,
    roleLabel = roleLabel,
    status = status,
    statusLabel = statusLabel,
    requestedAtBr = requestedAtBr,
    viewedAtBr = viewedAtBr,
    signedAtBr = signedAtBr,
    rejectedAtBr = rejectedAtBr,
)

private fun SignatureEventDto.toDomain(): SignatureEventItem = SignatureEventItem(
    id = id,
    eventType = eventType,
    label = label,
    signerName = signerName,
    description = description,
    receivedAt = receivedAt,
    receivedAtBr = receivedAtBr,
)

private fun SignatureActionsDto.toDomain(): SignatureAvailableActions = SignatureAvailableActions(
    canSync = canSync,
)

private fun FinanceDashboardResponseDto.toDomain(): FinanceDashboardData = FinanceDashboardData(
    summary = summary.toDomain(),
    cards = cards.map { it.toDomain() },
    alerts = alerts.map { it.toDomain() },
    cashflowPreview = cashflowPreview.map { it.toDomain() },
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun FinanceDashboardSummaryDto.toDomain(): FinanceDashboardSummary = FinanceDashboardSummary(
    monthLabel = monthLabel,
    receitasMonth = receitasMonth,
    receitasMonthLabel = receitasMonthLabel,
    despesasMonth = despesasMonth,
    despesasMonthLabel = despesasMonthLabel,
    saldoPrevisto = saldoPrevisto,
    saldoPrevistoLabel = saldoPrevistoLabel,
    contasVencidas = contasVencidas,
    contasAVencer = contasAVencer,
    recebiveisEmAberto = recebiveisEmAberto,
    recebiveisEmAbertoLabel = recebiveisEmAbertoLabel,
)

private fun FinanceSummaryCardDto.toDomain(): FinanceSummaryCard = FinanceSummaryCard(
    key = key,
    title = title,
    value = value,
    valueLabel = valueLabel,
    description = description,
    tone = tone,
)

private fun FinanceAlertDto.toDomain(): FinanceAlertItem = FinanceAlertItem(
    title = title,
    message = message,
    tone = tone,
)

private fun FinanceReceivablesResponseDto.toDomain(): FinanceReceivablesData = FinanceReceivablesData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.states.map { it.toDomain() },
)

private fun FinancePayablesResponseDto.toDomain(): FinancePayablesData = FinancePayablesData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.states.map { it.toDomain() },
)

private fun FinanceReceivableDto.toDomain(): FinanceReceivableItem = FinanceReceivableItem(
    id = id,
    code = code,
    title = title,
    clientName = clientName,
    condominiumName = condominiumName,
    contractCode = contractCode,
    status = status,
    statusLabel = statusLabel,
    dueDate = dueDate,
    dueDateBr = dueDateBr,
    amount = amount,
    amountLabel = amountLabel,
    receivedAmount = receivedAmount,
    receivedAmountLabel = receivedAmountLabel,
    outstandingAmount = outstandingAmount,
    outstandingAmountLabel = outstandingAmountLabel,
    updatedAtBr = updatedAtBr,
)

private fun FinancePayableDto.toDomain(): FinancePayableItem = FinancePayableItem(
    id = id,
    code = code,
    title = title,
    supplierName = supplierName,
    status = status,
    statusLabel = statusLabel,
    dueDate = dueDate,
    dueDateBr = dueDateBr,
    amount = amount,
    amountLabel = amountLabel,
    paidAmount = paidAmount,
    paidAmountLabel = paidAmountLabel,
    outstandingAmount = outstandingAmount,
    outstandingAmountLabel = outstandingAmountLabel,
    updatedAtBr = updatedAtBr,
)

private fun FinanceCashflowResponseDto.toDomain(): FinanceCashflowData = FinanceCashflowData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    summary = summary.toDomain(),
)

private fun FinanceCashflowSummaryDto.toDomain(): FinanceCashflowSummary = FinanceCashflowSummary(
    periodLabel = periodLabel,
    entradas = entradas,
    entradasLabel = entradasLabel,
    saidas = saidas,
    saidasLabel = saidasLabel,
    saldo = saldo,
    saldoLabel = saldoLabel,
)

private fun FinanceCashflowItemDto.toDomain(): FinanceCashflowItem = FinanceCashflowItem(
    id = id,
    description = description,
    transactionType = transactionType,
    transactionTypeLabel = transactionTypeLabel,
    amount = amount,
    amountLabel = amountLabel,
    transactionDate = transactionDate,
    transactionDateBr = transactionDateBr,
    accountName = accountName,
    categoryName = categoryName,
    documentNumber = documentNumber,
    reconciliationStatus = reconciliationStatus,
)

private fun AttachmentDto.toDomain(): HubAttachment = HubAttachment(
    id = id,
    name = name,
    mimeType = mimeType,
    fileSize = fileSize,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    uploadedByName = uploadedByName,
    tagLabel = tagLabel,
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

private fun Throwable.toBusinessFacingMessage(
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
        json.decodeFromString(BusinessApiMessageDto.serializer(), rawBody).message
    }.getOrNull()
}

@Serializable
private data class BusinessApiMessageDto(
    val message: String? = null,
)

@Serializable
data class SignatureDraftSignerPayload(
    val name: String,
    val email: String,
    val phone: String? = null,
    val document_number: String? = null,
    val role_label: String,
    val order_index: Int? = null,
)
