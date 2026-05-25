package br.com.serratech.ancora.hub.domain.model

data class PaginationMeta(
    val currentPage: Int,
    val lastPage: Int,
    val perPage: Int,
    val total: Int,
) {
    val hasMore: Boolean
        get() = currentPage < lastPage
}

data class FilterValueOption(
    val value: String,
    val label: String,
    val color: String? = null,
)

data class HubUserOption(
    val id: Long,
    val name: String,
    val email: String? = null,
    val initials: String = "U",
)

data class HubAttachment(
    val id: Long,
    val name: String,
    val mimeType: String?,
    val fileSize: Int,
    val createdAt: String?,
    val createdAtBr: String?,
    val uploadedByName: String? = null,
    val tagLabel: String? = null,
)

data class DemandListData(
    val items: List<DemandListItem>,
    val meta: PaginationMeta,
    val filters: DemandFilters,
)

data class DemandFilters(
    val statuses: List<FilterValueOption>,
    val priorities: List<FilterValueOption>,
    val categories: List<FilterValueOption>,
    val tags: List<FilterValueOption>,
    val assignees: List<HubUserOption>,
    val canCreate: Boolean,
)

data class DemandListItem(
    val id: Long,
    val protocol: String,
    val title: String,
    val clientName: String,
    val condominiumName: String?,
    val status: String,
    val statusLabel: String,
    val priority: String,
    val priorityLabel: String,
    val categoryName: String?,
    val assignee: HubUserOption?,
    val sla: DemandSla,
    val messagesCount: Int,
    val attachmentsCount: Int,
    val createdAt: String?,
    val createdAtBr: String?,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class DemandSla(
    val status: String,
    val label: String,
    val dueAt: String?,
    val dueAtBr: String?,
    val progressPercent: Int,
)

data class DemandDetail(
    val summary: DemandListItem,
    val description: String,
    val requester: DemandRequester,
    val entityName: String?,
    val messages: List<DemandMessageItem>,
    val attachments: List<HubAttachment>,
    val availableActions: DemandAvailableActions,
    val statusOptions: List<FilterValueOption>,
    val tagOptions: List<FilterValueOption>,
    val assignees: List<HubUserOption>,
    val closedAt: String?,
    val closedAtBr: String?,
)

data class DemandRequester(
    val name: String,
    val email: String?,
)

data class DemandMessageItem(
    val id: Long,
    val senderType: String,
    val senderName: String,
    val isInternal: Boolean,
    val message: String,
    val createdAt: String?,
    val createdAtBr: String?,
    val attachments: List<HubAttachment>,
)

data class DemandAvailableActions(
    val canReply: Boolean,
    val canUpdateStatus: Boolean,
    val canMove: Boolean,
    val canAssign: Boolean,
)

data class ProcessListData(
    val items: List<ProcessListItem>,
    val meta: PaginationMeta,
    val filters: ProcessFilters,
)

data class ProcessFilters(
    val statuses: List<FilterValueOption>,
)

data class ProcessListItem(
    val id: Long,
    val processNumber: String,
    val clientName: String,
    val condominiumName: String?,
    val className: String?,
    val subjectName: String?,
    val status: String?,
    val responsibleLawyer: String?,
    val lastMovementAt: String?,
    val lastMovementAtBr: String?,
    val lastMovementDescription: String?,
    val isPrivate: Boolean,
    val createdAt: String?,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class ProcessDetail(
    val summary: ProcessListItem,
    val openedAt: String?,
    val openedAtBr: String?,
    val closedAt: String?,
    val closedAtBr: String?,
    val notes: String?,
    val judgingBody: String?,
    val actionType: String?,
    val clientPosition: String?,
    val adversePosition: String?,
    val winProbability: String?,
    val closureType: String?,
    val client: ProcessSide,
    val adverse: ProcessSide,
    val parties: List<ProcessParty>,
    val movementsPreview: List<ProcessMovementItem>,
    val attachmentsPreview: List<HubAttachment>,
)

data class ProcessSide(
    val name: String,
    val condominiumName: String?,
    val syndicName: String? = null,
)

data class ProcessParty(
    val id: Long,
    val partyType: String,
    val name: String,
    val document: String?,
    val sideLabel: String,
)

data class ProcessMovementPage(
    val items: List<ProcessMovementItem>,
    val meta: PaginationMeta,
)

data class ProcessMovementItem(
    val id: Long,
    val description: String,
    val phaseDate: String?,
    val phaseDateBr: String?,
    val phaseTime: String?,
    val notes: String?,
    val legalOpinion: String?,
    val conference: String?,
    val isReviewed: Boolean,
    val source: String,
    val createdByName: String?,
    val attachments: List<HubAttachment>,
)

data class AttachmentPage(
    val items: List<HubAttachment>,
    val meta: PaginationMeta,
)

data class CollectionListData(
    val items: List<CollectionListItem>,
    val meta: PaginationMeta,
    val filters: CollectionFilters,
)

data class CollectionFilters(
    val workflowStages: List<FilterValueOption>,
    val situations: List<FilterValueOption>,
    val billingStatuses: List<FilterValueOption>,
    val canCreate: Boolean,
)

data class CollectionListItem(
    val id: Long,
    val osNumber: String,
    val condominiumId: Long?,
    val unitId: Long?,
    val condominiumName: String,
    val unitLabel: String,
    val debtorName: String,
    val ownerName: String?,
    val tenantName: String?,
    val agreementTotal: Double?,
    val agreementTotalLabel: String?,
    val workflowStage: String,
    val workflowStageLabel: String,
    val situation: String?,
    val situationLabel: String?,
    val billingStatus: String?,
    val billingStatusLabel: String?,
    val lastProgressAt: String?,
    val lastProgressAtBr: String?,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class CollectionDetail(
    val summary: CollectionListItem,
    val chargeType: String?,
    val caseMode: String?,
    val debtorDocument: String?,
    val entryAmount: Double?,
    val entryAmountLabel: String?,
    val feesAmount: Double?,
    val feesAmountLabel: String?,
    val billingDate: String?,
    val billingDateBr: String?,
    val judicialCaseNumber: String?,
    val syndicName: String?,
    val administratorName: String?,
    val contacts: List<CollectionContact>,
    val quotas: List<CollectionQuota>,
    val agreement: CollectionAgreementInfo,
    val availableActions: CollectionAvailableActions,
    val options: CollectionOptions,
)

data class CollectionAvailableActions(
    val canEdit: Boolean,
    val canCalculateTjes: Boolean,
    val canRequestBoleto: Boolean,
)

data class CollectionOptions(
    val chargeTypes: List<FilterValueOption>,
    val workflowStages: List<FilterValueOption>,
    val billingStatuses: List<FilterValueOption>,
)

data class CollectionContact(
    val id: Long,
    val type: String,
    val value: String,
    val isPrimary: Boolean,
    val isWhatsapp: Boolean,
)

data class CollectionQuota(
    val id: Long,
    val referenceLabel: String,
    val dueDate: String?,
    val dueDateBr: String?,
    val status: String?,
    val originalAmount: Double?,
    val originalAmountLabel: String?,
    val updatedAmount: Double?,
    val updatedAmountLabel: String?,
)

data class CollectionAgreementInfo(
    val hasTerm: Boolean,
    val hasSignatureRequests: Boolean,
    val signatureRequestsCount: Int,
)

data class CollectionInstallmentPage(
    val items: List<CollectionInstallmentItem>,
    val meta: PaginationMeta,
)

data class CollectionInstallmentItem(
    val id: Long,
    val label: String,
    val installmentType: String?,
    val installmentNumber: Int?,
    val dueDate: String?,
    val dueDateBr: String?,
    val amount: Double?,
    val amountLabel: String?,
    val status: String?,
)

data class CollectionTimelinePage(
    val items: List<CollectionTimelineItem>,
    val meta: PaginationMeta,
)

data class CollectionTimelineItem(
    val id: Long,
    val eventType: String?,
    val description: String,
    val userName: String?,
    val createdAt: String?,
    val createdAtBr: String?,
)

data class CollectionTjesPreview(
    val settings: CollectionTjesSettings,
    val items: List<CollectionTjesItem>,
    val totals: CollectionTjesTotals,
    val summary: CollectionTjesSummary,
)

data class CollectionTjesSettings(
    val indexLabel: String?,
    val finalDate: String?,
    val interestLabel: String?,
    val attorneyFeeLabel: String?,
)

data class CollectionTjesItem(
    val quotaId: Long,
    val referenceLabel: String,
    val dueDate: String?,
    val original: String?,
    val factor: String?,
    val corrected: String?,
    val interestPercent: String?,
    val interest: String?,
    val fine: String?,
    val total: String?,
)

data class CollectionTjesTotals(
    val original: String?,
    val corrected: String?,
    val interest: String?,
    val fine: String?,
    val costsCorrected: String?,
    val boletoFee: String?,
    val boletoCancellationFee: String?,
    val abatement: String?,
    val debitTotal: String?,
    val attorneyFee: String?,
    val grandTotal: String?,
)

data class CollectionTjesSummary(
    val debitTotal: String?,
    val attorneyFee: String?,
    val boletoFee: String?,
    val boletoCancellationFee: String?,
    val grandTotal: String?,
    val finalDate: String?,
)
