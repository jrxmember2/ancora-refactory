package br.com.serratech.ancora.hub.data.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ValueLabelDto(
    val value: String,
    val label: String,
    val color: String? = null,
)

@Serializable
data class UserOptionDto(
    val id: Long,
    val name: String,
    val email: String? = null,
    val initials: String = "U",
)

@Serializable
data class DemandListResponseDto(
    val items: List<DemandSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: DemandFiltersDto = DemandFiltersDto(),
    val actions: DemandListActionsDto = DemandListActionsDto(),
)

@Serializable
data class DemandFiltersDto(
    val statuses: List<ValueLabelDto> = emptyList(),
    val priorities: List<ValueLabelDto> = emptyList(),
    val categories: List<ValueLabelDto> = emptyList(),
    val tags: List<ValueLabelDto> = emptyList(),
    val assignees: List<UserOptionDto> = emptyList(),
)

@Serializable
data class DemandListActionsDto(
    @SerialName("can_create") val canCreate: Boolean = false,
)

@Serializable
data class DemandSummaryDto(
    val id: Long,
    val protocol: String,
    val title: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("condominium_name") val condominiumName: String? = null,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    val priority: String,
    @SerialName("priority_label") val priorityLabel: String,
    @SerialName("category_name") val categoryName: String? = null,
    val assignee: UserOptionDto? = null,
    val sla: DemandSlaDto = DemandSlaDto(),
    @SerialName("messages_count") val messagesCount: Int = 0,
    @SerialName("attachments_count") val attachmentsCount: Int = 0,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class DemandSlaDto(
    val status: String = "none",
    val label: String = "Sem SLA",
    @SerialName("due_at") val dueAt: String? = null,
    @SerialName("due_at_br") val dueAtBr: String? = null,
    @SerialName("progress_percent") val progressPercent: Int = 0,
)

@Serializable
data class DemandDetailResponseDto(
    val item: DemandDetailDto,
)

@Serializable
data class DemandDetailDto(
    val id: Long,
    val protocol: String,
    val title: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("condominium_name") val condominiumName: String? = null,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    val priority: String,
    @SerialName("priority_label") val priorityLabel: String,
    @SerialName("category_name") val categoryName: String? = null,
    val assignee: UserOptionDto? = null,
    val sla: DemandSlaDto = DemandSlaDto(),
    @SerialName("messages_count") val messagesCount: Int = 0,
    @SerialName("attachments_count") val attachmentsCount: Int = 0,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    val description: String = "",
    val requester: DemandRequesterDto = DemandRequesterDto(),
    @SerialName("entity_name") val entityName: String? = null,
    val messages: List<DemandMessageDto> = emptyList(),
    val attachments: List<AttachmentDto> = emptyList(),
    @SerialName("available_actions") val availableActions: DemandActionsDto = DemandActionsDto(),
    @SerialName("status_options") val statusOptions: List<ValueLabelDto> = emptyList(),
    @SerialName("tag_options") val tagOptions: List<ValueLabelDto> = emptyList(),
    val assignees: List<UserOptionDto> = emptyList(),
    @SerialName("closed_at") val closedAt: String? = null,
    @SerialName("closed_at_br") val closedAtBr: String? = null,
)

@Serializable
data class DemandRequesterDto(
    val name: String = "Não informado",
    val email: String? = null,
)

@Serializable
data class DemandMessageDto(
    val id: Long,
    @SerialName("sender_type") val senderType: String,
    @SerialName("sender_name") val senderName: String,
    @SerialName("is_internal") val isInternal: Boolean = true,
    val message: String,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    val attachments: List<AttachmentDto> = emptyList(),
)

@Serializable
data class DemandActionsDto(
    @SerialName("can_reply") val canReply: Boolean = false,
    @SerialName("can_update_status") val canUpdateStatus: Boolean = false,
    @SerialName("can_move") val canMove: Boolean = false,
    @SerialName("can_assign") val canAssign: Boolean = false,
)

@Serializable
data class AttachmentDto(
    val id: Long,
    val name: String,
    @SerialName("mime_type") val mimeType: String? = null,
    @SerialName("file_size") val fileSize: Int = 0,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("uploaded_by_name") val uploadedByName: String? = null,
    @SerialName("file_role") val fileRole: String? = null,
    @SerialName("phase_id") val phaseId: Long? = null,
    @SerialName("tag_label") val tagLabel: String? = null,
)

@Serializable
data class DemandActionResponseDto(
    val ok: Boolean = false,
    val message: String? = null,
    val item: DemandDetailDto? = null,
)

@Serializable
data class DemandCreateRequestDto(
    @SerialName("category_id") val categoryId: Long,
    @SerialName("demand_tag_id") val demandTagId: Long? = null,
    val priority: String,
    @SerialName("assigned_user_id") val assignedUserId: Long? = null,
    val subject: String,
    val description: String,
)

@Serializable
data class ProcessListResponseDto(
    val items: List<ProcessSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: ProcessFiltersDto = ProcessFiltersDto(),
)

@Serializable
data class ProcessFiltersDto(
    val statuses: List<ProcessStatusDto> = emptyList(),
)

@Serializable
data class ProcessStatusDto(
    val id: Long,
    val label: String,
    val color: String? = null,
)

@Serializable
data class ProcessSummaryDto(
    val id: Long,
    @SerialName("process_number") val processNumber: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("condominium_name") val condominiumName: String? = null,
    @SerialName("class_name") val className: String? = null,
    @SerialName("subject_name") val subjectName: String? = null,
    val status: String? = null,
    @SerialName("responsible_lawyer") val responsibleLawyer: String? = null,
    @SerialName("last_movement_at") val lastMovementAt: String? = null,
    @SerialName("last_movement_at_br") val lastMovementAtBr: String? = null,
    @SerialName("last_movement_description") val lastMovementDescription: String? = null,
    @SerialName("is_private") val isPrivate: Boolean = false,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class ProcessDetailResponseDto(
    val item: ProcessDetailDto,
)

@Serializable
data class ProcessDetailDto(
    val id: Long,
    @SerialName("process_number") val processNumber: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("condominium_name") val condominiumName: String? = null,
    @SerialName("class_name") val className: String? = null,
    @SerialName("subject_name") val subjectName: String? = null,
    val status: String? = null,
    @SerialName("responsible_lawyer") val responsibleLawyer: String? = null,
    @SerialName("last_movement_at") val lastMovementAt: String? = null,
    @SerialName("last_movement_at_br") val lastMovementAtBr: String? = null,
    @SerialName("last_movement_description") val lastMovementDescription: String? = null,
    @SerialName("is_private") val isPrivate: Boolean = false,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    @SerialName("opened_at") val openedAt: String? = null,
    @SerialName("opened_at_br") val openedAtBr: String? = null,
    @SerialName("closed_at") val closedAt: String? = null,
    @SerialName("closed_at_br") val closedAtBr: String? = null,
    val notes: String? = null,
    @SerialName("judging_body") val judgingBody: String? = null,
    @SerialName("action_type") val actionType: String? = null,
    @SerialName("client_position") val clientPosition: String? = null,
    @SerialName("adverse_position") val adversePosition: String? = null,
    @SerialName("win_probability") val winProbability: String? = null,
    @SerialName("closure_type") val closureType: String? = null,
    val client: ProcessSideDto = ProcessSideDto(),
    val adverse: ProcessSideDto = ProcessSideDto(),
    val parties: List<ProcessPartyDto> = emptyList(),
    @SerialName("movements_preview") val movementsPreview: List<ProcessMovementDto> = emptyList(),
    @SerialName("attachments_preview") val attachmentsPreview: List<AttachmentDto> = emptyList(),
)

@Serializable
data class ProcessSideDto(
    val name: String = "Não informado",
    @SerialName("condominium_name") val condominiumName: String? = null,
    @SerialName("syndic_name") val syndicName: String? = null,
)

@Serializable
data class ProcessPartyDto(
    val id: Long,
    @SerialName("party_type") val partyType: String,
    val name: String,
    val document: String? = null,
    @SerialName("side_label") val sideLabel: String,
)

@Serializable
data class ProcessMovementsResponseDto(
    val items: List<ProcessMovementDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
)

@Serializable
data class ProcessMovementDto(
    val id: Long,
    val description: String,
    @SerialName("phase_date") val phaseDate: String? = null,
    @SerialName("phase_date_br") val phaseDateBr: String? = null,
    @SerialName("phase_time") val phaseTime: String? = null,
    val notes: String? = null,
    @SerialName("legal_opinion") val legalOpinion: String? = null,
    val conference: String? = null,
    @SerialName("is_reviewed") val isReviewed: Boolean = false,
    val source: String = "manual",
    @SerialName("created_by_name") val createdByName: String? = null,
    val attachments: List<AttachmentDto> = emptyList(),
)

@Serializable
data class AttachmentsResponseDto(
    val items: List<AttachmentDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
)

@Serializable
data class CollectionListResponseDto(
    val items: List<CollectionSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: CollectionFiltersDto = CollectionFiltersDto(),
    val actions: CollectionListActionsDto = CollectionListActionsDto(),
)

@Serializable
data class CollectionListActionsDto(
    @SerialName("can_create") val canCreate: Boolean = false,
)

@Serializable
data class CollectionFiltersDto(
    @SerialName("workflow_stages") val workflowStages: List<ValueLabelDto> = emptyList(),
    val situations: List<ValueLabelDto> = emptyList(),
    @SerialName("billing_statuses") val billingStatuses: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class CollectionSummaryDto(
    val id: Long,
    @SerialName("os_number") val osNumber: String,
    @SerialName("condominium_id") val condominiumId: Long? = null,
    @SerialName("unit_id") val unitId: Long? = null,
    @SerialName("condominium_name") val condominiumName: String,
    @SerialName("unit_label") val unitLabel: String,
    @SerialName("debtor_name") val debtorName: String,
    @SerialName("owner_name") val ownerName: String? = null,
    @SerialName("tenant_name") val tenantName: String? = null,
    @SerialName("agreement_total") val agreementTotal: Double? = null,
    @SerialName("agreement_total_label") val agreementTotalLabel: String? = null,
    @SerialName("workflow_stage") val workflowStage: String,
    @SerialName("workflow_stage_label") val workflowStageLabel: String,
    val situation: String? = null,
    @SerialName("situation_label") val situationLabel: String? = null,
    @SerialName("billing_status") val billingStatus: String? = null,
    @SerialName("billing_status_label") val billingStatusLabel: String? = null,
    @SerialName("last_progress_at") val lastProgressAt: String? = null,
    @SerialName("last_progress_at_br") val lastProgressAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class CollectionDetailResponseDto(
    val item: CollectionDetailDto,
)

@Serializable
data class CollectionDetailDto(
    val id: Long,
    @SerialName("os_number") val osNumber: String,
    @SerialName("condominium_id") val condominiumId: Long? = null,
    @SerialName("unit_id") val unitId: Long? = null,
    @SerialName("condominium_name") val condominiumName: String,
    @SerialName("unit_label") val unitLabel: String,
    @SerialName("debtor_name") val debtorName: String,
    @SerialName("owner_name") val ownerName: String? = null,
    @SerialName("tenant_name") val tenantName: String? = null,
    @SerialName("agreement_total") val agreementTotal: Double? = null,
    @SerialName("agreement_total_label") val agreementTotalLabel: String? = null,
    @SerialName("workflow_stage") val workflowStage: String,
    @SerialName("workflow_stage_label") val workflowStageLabel: String,
    val situation: String? = null,
    @SerialName("situation_label") val situationLabel: String? = null,
    @SerialName("billing_status") val billingStatus: String? = null,
    @SerialName("billing_status_label") val billingStatusLabel: String? = null,
    @SerialName("last_progress_at") val lastProgressAt: String? = null,
    @SerialName("last_progress_at_br") val lastProgressAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    @SerialName("charge_type") val chargeType: String? = null,
    @SerialName("case_mode") val caseMode: String? = null,
    @SerialName("debtor_document") val debtorDocument: String? = null,
    @SerialName("entry_amount") val entryAmount: Double? = null,
    @SerialName("entry_amount_label") val entryAmountLabel: String? = null,
    @SerialName("fees_amount") val feesAmount: Double? = null,
    @SerialName("fees_amount_label") val feesAmountLabel: String? = null,
    @SerialName("billing_date") val billingDate: String? = null,
    @SerialName("billing_date_br") val billingDateBr: String? = null,
    @SerialName("judicial_case_number") val judicialCaseNumber: String? = null,
    @SerialName("syndic_name") val syndicName: String? = null,
    @SerialName("administrator_name") val administratorName: String? = null,
    val contacts: List<CollectionContactDto> = emptyList(),
    val quotas: List<CollectionQuotaDto> = emptyList(),
    val agreement: CollectionAgreementDto = CollectionAgreementDto(),
    @SerialName("available_actions") val availableActions: CollectionAvailableActionsDto = CollectionAvailableActionsDto(),
    val options: CollectionOptionsDto = CollectionOptionsDto(),
)

@Serializable
data class CollectionAvailableActionsDto(
    @SerialName("can_edit") val canEdit: Boolean = false,
    @SerialName("can_calculate_tjes") val canCalculateTjes: Boolean = false,
    @SerialName("can_request_boleto") val canRequestBoleto: Boolean = false,
)

@Serializable
data class CollectionOptionsDto(
    @SerialName("charge_types") val chargeTypes: List<ValueLabelDto> = emptyList(),
    @SerialName("workflow_stages") val workflowStages: List<ValueLabelDto> = emptyList(),
    @SerialName("billing_statuses") val billingStatuses: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class CollectionContactDto(
    val id: Long,
    val type: String,
    val value: String,
    @SerialName("is_primary") val isPrimary: Boolean = false,
    @SerialName("is_whatsapp") val isWhatsapp: Boolean = false,
)

@Serializable
data class CollectionQuotaDto(
    val id: Long,
    @SerialName("reference_label") val referenceLabel: String,
    @SerialName("due_date") val dueDate: String? = null,
    @SerialName("due_date_br") val dueDateBr: String? = null,
    val status: String? = null,
    @SerialName("original_amount") val originalAmount: Double? = null,
    @SerialName("original_amount_label") val originalAmountLabel: String? = null,
    @SerialName("updated_amount") val updatedAmount: Double? = null,
    @SerialName("updated_amount_label") val updatedAmountLabel: String? = null,
)

@Serializable
data class CollectionAgreementDto(
    @SerialName("has_term") val hasTerm: Boolean = false,
    @SerialName("has_signature_requests") val hasSignatureRequests: Boolean = false,
    @SerialName("signature_requests_count") val signatureRequestsCount: Int = 0,
)

@Serializable
data class CollectionInstallmentsResponseDto(
    val items: List<CollectionInstallmentDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
)

@Serializable
data class CollectionInstallmentDto(
    val id: Long,
    val label: String,
    @SerialName("installment_type") val installmentType: String? = null,
    @SerialName("installment_number") val installmentNumber: Int? = null,
    @SerialName("due_date") val dueDate: String? = null,
    @SerialName("due_date_br") val dueDateBr: String? = null,
    val amount: Double? = null,
    @SerialName("amount_label") val amountLabel: String? = null,
    val status: String? = null,
)

@Serializable
data class CollectionTimelineResponseDto(
    val items: List<CollectionTimelineDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
)

@Serializable
data class CollectionTimelineDto(
    val id: Long,
    @SerialName("event_type") val eventType: String? = null,
    val description: String,
    @SerialName("user_name") val userName: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
)

@Serializable
data class CollectionCreateRequestDto(
    @SerialName("unit_id") val unitId: Long,
    @SerialName("charge_type") val chargeType: String,
    @SerialName("workflow_stage") val workflowStage: String,
    @SerialName("billing_status") val billingStatus: String,
    @SerialName("agreement_total") val agreementTotal: Double? = null,
    @SerialName("billing_date") val billingDate: String? = null,
    @SerialName("alert_message") val alertMessage: String? = null,
    val notes: String? = null,
    @SerialName("entry_status") val entryStatus: String? = null,
    @SerialName("entry_due_date") val entryDueDate: String? = null,
    @SerialName("entry_amount") val entryAmount: Double? = null,
    @SerialName("fees_amount") val feesAmount: Double? = null,
    @SerialName("judicial_case_number") val judicialCaseNumber: String? = null,
    @SerialName("calc_base_date") val calcBaseDate: String? = null,
    val quotas: List<CollectionQuotaInputDto>,
)

@Serializable
data class CollectionQuotaInputDto(
    @SerialName("reference_label") val referenceLabel: String,
    @SerialName("due_date") val dueDate: String,
    @SerialName("original_amount") val originalAmount: Double,
    @SerialName("updated_amount") val updatedAmount: Double? = null,
    val status: String? = null,
)

@Serializable
data class CollectionActionResponseDto(
    val ok: Boolean = false,
    val message: String? = null,
    val item: CollectionDetailDto? = null,
    val preview: CollectionTjesPreviewDto? = null,
    @SerialName("update_id") val updateId: Long? = null,
)

@Serializable
data class CollectionTjesPreviewRequestDto(
    @SerialName("final_date") val finalDate: String? = null,
    @SerialName("index_code") val indexCode: String = "ATM",
    @SerialName("quota_ids") val quotaIds: List<Long> = emptyList(),
    @SerialName("interest_type") val interestType: String = "legal",
    @SerialName("interest_rate_monthly") val interestRateMonthly: Double? = null,
    @SerialName("fine_percent") val finePercent: Double? = null,
    @SerialName("attorney_fee_type") val attorneyFeeType: String = "percent",
    @SerialName("attorney_fee_value") val attorneyFeeValue: Double? = null,
    @SerialName("costs_amount") val costsAmount: Double? = null,
    @SerialName("costs_date") val costsDate: String? = null,
    @SerialName("abatement_amount") val abatementAmount: Double? = null,
)

@Serializable
data class CollectionTjesPreviewEnvelopeDto(
    val ok: Boolean = false,
    val message: String? = null,
    val preview: CollectionTjesPreviewDto? = null,
)

@Serializable
data class CollectionTjesPreviewDto(
    val settings: CollectionTjesSettingsDto = CollectionTjesSettingsDto(),
    val items: List<CollectionTjesItemDto> = emptyList(),
    val totals: CollectionTjesTotalsDto = CollectionTjesTotalsDto(),
    val summary: CollectionTjesSummaryDto = CollectionTjesSummaryDto(),
)

@Serializable
data class CollectionTjesSettingsDto(
    @SerialName("index_label") val indexLabel: String? = null,
    @SerialName("final_date") val finalDate: String? = null,
    @SerialName("interest_label") val interestLabel: String? = null,
    @SerialName("attorney_fee_label") val attorneyFeeLabel: String? = null,
)

@Serializable
data class CollectionTjesItemDto(
    @SerialName("quota_id") val quotaId: Long,
    @SerialName("reference_label") val referenceLabel: String,
    @SerialName("due_date") val dueDate: String? = null,
    val original: String? = null,
    val factor: String? = null,
    val corrected: String? = null,
    @SerialName("interest_percent") val interestPercent: String? = null,
    val interest: String? = null,
    val fine: String? = null,
    val total: String? = null,
)

@Serializable
data class CollectionTjesTotalsDto(
    val original: String? = null,
    val corrected: String? = null,
    val interest: String? = null,
    val fine: String? = null,
    @SerialName("costs_corrected") val costsCorrected: String? = null,
    @SerialName("boleto_fee") val boletoFee: String? = null,
    @SerialName("boleto_cancellation_fee") val boletoCancellationFee: String? = null,
    val abatement: String? = null,
    @SerialName("debit_total") val debitTotal: String? = null,
    @SerialName("attorney_fee") val attorneyFee: String? = null,
    @SerialName("grand_total") val grandTotal: String? = null,
)

@Serializable
data class CollectionTjesSummaryDto(
    @SerialName("debit_total") val debitTotal: String? = null,
    @SerialName("attorney_fee") val attorneyFee: String? = null,
    @SerialName("boleto_fee") val boletoFee: String? = null,
    @SerialName("boleto_cancellation_fee") val boletoCancellationFee: String? = null,
    @SerialName("grand_total") val grandTotal: String? = null,
    @SerialName("final_date") val finalDate: String? = null,
)
