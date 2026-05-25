package br.com.serratech.ancora.hub.data.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

@Serializable
data class ProposalListResponseDto(
    val items: List<ProposalSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: ProposalFiltersDto = ProposalFiltersDto(),
)

@Serializable
data class ProposalFiltersDto(
    val statuses: List<ValueLabelDto> = emptyList(),
    val services: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class ProposalSummaryDto(
    val id: Long,
    val code: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("service_id") val serviceId: Long? = null,
    @SerialName("service_name") val serviceName: String? = null,
    @SerialName("status_id") val statusId: Long? = null,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("status_color") val statusColor: String? = null,
    @SerialName("proposal_total") val proposalTotal: Double? = null,
    @SerialName("proposal_total_label") val proposalTotalLabel: String? = null,
    @SerialName("closed_total") val closedTotal: Double? = null,
    @SerialName("closed_total_label") val closedTotalLabel: String? = null,
    @SerialName("without_amount") val withoutAmount: Boolean = false,
    @SerialName("proposal_date") val proposalDate: String? = null,
    @SerialName("proposal_date_br") val proposalDateBr: String? = null,
    @SerialName("followup_date") val followupDate: String? = null,
    @SerialName("followup_date_br") val followupDateBr: String? = null,
    @SerialName("requester_name") val requesterName: String? = null,
    @SerialName("attachments_count") val attachmentsCount: Int = 0,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class ProposalDetailResponseDto(
    val item: ProposalDetailDto,
)

@Serializable
data class ProposalDetailDto(
    val id: Long,
    val code: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("service_id") val serviceId: Long? = null,
    @SerialName("service_name") val serviceName: String? = null,
    @SerialName("status_id") val statusId: Long? = null,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("status_color") val statusColor: String? = null,
    @SerialName("proposal_total") val proposalTotal: Double? = null,
    @SerialName("proposal_total_label") val proposalTotalLabel: String? = null,
    @SerialName("closed_total") val closedTotal: Double? = null,
    @SerialName("closed_total_label") val closedTotalLabel: String? = null,
    @SerialName("without_amount") val withoutAmount: Boolean = false,
    @SerialName("proposal_date") val proposalDate: String? = null,
    @SerialName("proposal_date_br") val proposalDateBr: String? = null,
    @SerialName("followup_date") val followupDate: String? = null,
    @SerialName("followup_date_br") val followupDateBr: String? = null,
    @SerialName("requester_name") val requesterName: String? = null,
    @SerialName("attachments_count") val attachmentsCount: Int = 0,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    @SerialName("administradora_name") val administradoraName: String? = null,
    @SerialName("send_method_name") val sendMethodName: String? = null,
    @SerialName("requester_phone") val requesterPhone: String? = null,
    @SerialName("contact_email") val contactEmail: String? = null,
    @SerialName("has_referral") val hasReferral: Boolean = false,
    @SerialName("referral_name") val referralName: String? = null,
    @SerialName("refusal_reason") val refusalReason: String? = null,
    @SerialName("validity_days") val validityDays: Int? = null,
    val notes: String? = null,
    val history: List<ProposalHistoryDto> = emptyList(),
    val attachments: List<AttachmentDto> = emptyList(),
)

@Serializable
data class ProposalHistoryDto(
    val id: Long,
    val action: String,
    val summary: String,
    @SerialName("user_email") val userEmail: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
)

@Serializable
data class ContractListResponseDto(
    val items: List<ContractSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: ContractFiltersDto = ContractFiltersDto(),
)

@Serializable
data class ContractFiltersDto(
    val statuses: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class ContractSummaryDto(
    val id: Long,
    val code: String,
    val title: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("object_label") val objectLabel: String,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    val type: String? = null,
    @SerialName("category_name") val categoryName: String? = null,
    val value: Double? = null,
    @SerialName("value_label") val valueLabel: String? = null,
    @SerialName("payment_method") val paymentMethod: String? = null,
    @SerialName("payment_method_label") val paymentMethodLabel: String? = null,
    @SerialName("start_date") val startDate: String? = null,
    @SerialName("start_date_br") val startDateBr: String? = null,
    @SerialName("end_date") val endDate: String? = null,
    @SerialName("end_date_br") val endDateBr: String? = null,
    @SerialName("is_expired") val isExpired: Boolean = false,
    @SerialName("has_final_pdf") val hasFinalPdf: Boolean = false,
    @SerialName("signature_pending") val signaturePending: Boolean = false,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class ContractDetailResponseDto(
    val item: ContractDetailDto,
)

@Serializable
data class ContractDetailDto(
    val id: Long,
    val code: String,
    val title: String,
    @SerialName("client_name") val clientName: String,
    @SerialName("object_label") val objectLabel: String,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    val type: String? = null,
    @SerialName("category_name") val categoryName: String? = null,
    val value: Double? = null,
    @SerialName("value_label") val valueLabel: String? = null,
    @SerialName("payment_method") val paymentMethod: String? = null,
    @SerialName("payment_method_label") val paymentMethodLabel: String? = null,
    @SerialName("start_date") val startDate: String? = null,
    @SerialName("start_date_br") val startDateBr: String? = null,
    @SerialName("end_date") val endDate: String? = null,
    @SerialName("end_date_br") val endDateBr: String? = null,
    @SerialName("is_expired") val isExpired: Boolean = false,
    @SerialName("has_final_pdf") val hasFinalPdf: Boolean = false,
    @SerialName("signature_pending") val signaturePending: Boolean = false,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    val description: String? = null,
    @SerialName("content_excerpt") val contentExcerpt: String? = null,
    @SerialName("billing_type") val billingType: String? = null,
    @SerialName("billing_type_label") val billingTypeLabel: String? = null,
    val recurrence: String? = null,
    @SerialName("recurrence_label") val recurrenceLabel: String? = null,
    @SerialName("responsible_name") val responsibleName: String? = null,
    @SerialName("condominium_name") val condominiumName: String? = null,
    @SerialName("syndic_name") val syndicName: String? = null,
    @SerialName("financial_account_name") val financialAccountName: String? = null,
    @SerialName("proposal_code") val proposalCode: String? = null,
    @SerialName("process_number") val processNumber: String? = null,
    @SerialName("documents_count") val documentsCount: Int = 0,
    @SerialName("latest_signature") val latestSignature: SignatureSummaryDto? = null,
)

@Serializable
data class ContractDocumentsResponseDto(
    val items: List<ContractDocumentDto> = emptyList(),
)

@Serializable
data class ContractDocumentDto(
    val id: String,
    val kind: String,
    @SerialName("kind_label") val kindLabel: String,
    val name: String,
    val description: String? = null,
    @SerialName("mime_type") val mimeType: String? = null,
    @SerialName("file_size") val fileSize: Int = 0,
    @SerialName("file_size_label") val fileSizeLabel: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("download_kind") val downloadKind: String,
    @SerialName("reference_id") val referenceId: Long? = null,
)

@Serializable
data class SignatureListResponseDto(
    val items: List<SignatureSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: SignatureFiltersDto = SignatureFiltersDto(),
)

@Serializable
data class SignatureFiltersDto(
    val statuses: List<ValueLabelDto> = emptyList(),
    val origins: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class SignatureSummaryDto(
    val id: Long,
    @SerialName("document_name") val documentName: String,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("source_type") val sourceType: String,
    @SerialName("source_label") val sourceLabel: String,
    @SerialName("signers_count") val signersCount: Int = 0,
    @SerialName("pending_count") val pendingCount: Int = 0,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("completed_at") val completedAt: String? = null,
    @SerialName("completed_at_br") val completedAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class SignatureDetailResponseDto(
    val item: SignatureDetailDto,
)

@Serializable
data class SignatureDetailDto(
    val id: Long,
    @SerialName("document_name") val documentName: String,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("source_type") val sourceType: String,
    @SerialName("source_label") val sourceLabel: String,
    @SerialName("signers_count") val signersCount: Int = 0,
    @SerialName("pending_count") val pendingCount: Int = 0,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("completed_at") val completedAt: String? = null,
    @SerialName("completed_at_br") val completedAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    @SerialName("requested_at") val requestedAt: String? = null,
    @SerialName("requested_at_br") val requestedAtBr: String? = null,
    @SerialName("last_synced_at") val lastSyncedAt: String? = null,
    @SerialName("last_synced_at_br") val lastSyncedAtBr: String? = null,
    val provider: String? = null,
    @SerialName("signing_url_available") val signingUrlAvailable: Boolean = false,
    val summary: JsonObject = JsonObject(emptyMap()),
    val signers: List<SignatureSignerDto> = emptyList(),
    val events: List<SignatureEventDto> = emptyList(),
    @SerialName("available_actions") val availableActions: SignatureActionsDto = SignatureActionsDto(),
)

@Serializable
data class SignatureSignerDto(
    val id: Long,
    val name: String,
    val email: String? = null,
    val phone: String? = null,
    @SerialName("role_label") val roleLabel: String? = null,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("requested_at_br") val requestedAtBr: String? = null,
    @SerialName("viewed_at_br") val viewedAtBr: String? = null,
    @SerialName("signed_at_br") val signedAtBr: String? = null,
    @SerialName("rejected_at_br") val rejectedAtBr: String? = null,
)

@Serializable
data class SignatureEventDto(
    val id: Long,
    @SerialName("event_type") val eventType: String? = null,
    val label: String,
    @SerialName("signer_name") val signerName: String? = null,
    val description: String,
    @SerialName("received_at") val receivedAt: String? = null,
    @SerialName("received_at_br") val receivedAtBr: String? = null,
)

@Serializable
data class SignatureActionsDto(
    @SerialName("can_sync") val canSync: Boolean = false,
)

@Serializable
data class SignatureActionResponseDto(
    val ok: Boolean = false,
    val message: String? = null,
    val item: SignatureDetailDto? = null,
)

@Serializable
data class FinanceDashboardResponseDto(
    val summary: FinanceDashboardSummaryDto = FinanceDashboardSummaryDto(),
    val cards: List<FinanceSummaryCardDto> = emptyList(),
    val alerts: List<FinanceAlertDto> = emptyList(),
    @SerialName("cashflow_preview") val cashflowPreview: List<FinanceCashflowItemDto> = emptyList(),
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class FinanceDashboardSummaryDto(
    @SerialName("month_label") val monthLabel: String? = null,
    @SerialName("receitas_month") val receitasMonth: Double = 0.0,
    @SerialName("receitas_month_label") val receitasMonthLabel: String? = null,
    @SerialName("despesas_month") val despesasMonth: Double = 0.0,
    @SerialName("despesas_month_label") val despesasMonthLabel: String? = null,
    @SerialName("saldo_previsto") val saldoPrevisto: Double = 0.0,
    @SerialName("saldo_previsto_label") val saldoPrevistoLabel: String? = null,
    @SerialName("contas_vencidas") val contasVencidas: Int = 0,
    @SerialName("contas_a_vencer") val contasAVencer: Int = 0,
    @SerialName("recebiveis_em_aberto") val recebiveisEmAberto: Double = 0.0,
    @SerialName("recebiveis_em_aberto_label") val recebiveisEmAbertoLabel: String? = null,
)

@Serializable
data class FinanceSummaryCardDto(
    val key: String,
    val title: String,
    val value: Double = 0.0,
    @SerialName("value_label") val valueLabel: String? = null,
    val description: String,
    val tone: String = "brand",
)

@Serializable
data class FinanceAlertDto(
    val title: String,
    val message: String,
    val tone: String = "warning",
)

@Serializable
data class FinanceReceivablesResponseDto(
    val items: List<FinanceReceivableDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: FinanceStateFiltersDto = FinanceStateFiltersDto(),
)

@Serializable
data class FinancePayablesResponseDto(
    val items: List<FinancePayableDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: FinanceStateFiltersDto = FinanceStateFiltersDto(),
)

@Serializable
data class FinanceStateFiltersDto(
    val states: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class FinanceReceivableDto(
    val id: Long,
    val code: String,
    val title: String,
    @SerialName("client_name") val clientName: String? = null,
    @SerialName("condominium_name") val condominiumName: String? = null,
    @SerialName("contract_code") val contractCode: String? = null,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("due_date") val dueDate: String? = null,
    @SerialName("due_date_br") val dueDateBr: String? = null,
    val amount: Double? = null,
    @SerialName("amount_label") val amountLabel: String? = null,
    @SerialName("received_amount") val receivedAmount: Double? = null,
    @SerialName("received_amount_label") val receivedAmountLabel: String? = null,
    @SerialName("outstanding_amount") val outstandingAmount: Double? = null,
    @SerialName("outstanding_amount_label") val outstandingAmountLabel: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class FinancePayableDto(
    val id: Long,
    val code: String,
    val title: String,
    @SerialName("supplier_name") val supplierName: String? = null,
    val status: String,
    @SerialName("status_label") val statusLabel: String,
    @SerialName("due_date") val dueDate: String? = null,
    @SerialName("due_date_br") val dueDateBr: String? = null,
    val amount: Double? = null,
    @SerialName("amount_label") val amountLabel: String? = null,
    @SerialName("paid_amount") val paidAmount: Double? = null,
    @SerialName("paid_amount_label") val paidAmountLabel: String? = null,
    @SerialName("outstanding_amount") val outstandingAmount: Double? = null,
    @SerialName("outstanding_amount_label") val outstandingAmountLabel: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class FinanceCashflowResponseDto(
    val items: List<FinanceCashflowItemDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val summary: FinanceCashflowSummaryDto = FinanceCashflowSummaryDto(),
)

@Serializable
data class FinanceCashflowSummaryDto(
    @SerialName("period_label") val periodLabel: String? = null,
    val entradas: Double = 0.0,
    @SerialName("entradas_label") val entradasLabel: String? = null,
    val saidas: Double = 0.0,
    @SerialName("saidas_label") val saidasLabel: String? = null,
    val saldo: Double = 0.0,
    @SerialName("saldo_label") val saldoLabel: String? = null,
)

@Serializable
data class FinanceCashflowItemDto(
    val id: Long,
    val description: String,
    @SerialName("transaction_type") val transactionType: String,
    @SerialName("transaction_type_label") val transactionTypeLabel: String,
    val amount: Double? = null,
    @SerialName("amount_label") val amountLabel: String? = null,
    @SerialName("transaction_date") val transactionDate: String? = null,
    @SerialName("transaction_date_br") val transactionDateBr: String? = null,
    @SerialName("account_name") val accountName: String? = null,
    @SerialName("category_name") val categoryName: String? = null,
    @SerialName("document_number") val documentNumber: String? = null,
    @SerialName("reconciliation_status") val reconciliationStatus: String? = null,
)
