package br.com.serratech.ancora.hub.domain.model

data class ProposalListData(
    val items: List<ProposalListItem>,
    val meta: PaginationMeta,
    val filters: ProposalFilters,
)

data class ProposalFilters(
    val statuses: List<FilterValueOption>,
    val services: List<FilterValueOption>,
)

data class ProposalListItem(
    val id: Long,
    val code: String,
    val clientName: String,
    val serviceId: Long?,
    val serviceName: String?,
    val statusId: Long?,
    val statusLabel: String,
    val statusColor: String?,
    val proposalTotal: Double?,
    val proposalTotalLabel: String?,
    val closedTotal: Double?,
    val closedTotalLabel: String?,
    val withoutAmount: Boolean,
    val proposalDate: String?,
    val proposalDateBr: String?,
    val followupDate: String?,
    val followupDateBr: String?,
    val requesterName: String?,
    val attachmentsCount: Int,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class ProposalDetail(
    val summary: ProposalListItem,
    val administradoraName: String?,
    val sendMethodName: String?,
    val requesterPhone: String?,
    val contactEmail: String?,
    val hasReferral: Boolean,
    val referralName: String?,
    val refusalReason: String?,
    val validityDays: Int?,
    val notes: String?,
    val history: List<ProposalHistoryItem>,
    val attachments: List<HubAttachment>,
)

data class ProposalHistoryItem(
    val id: Long,
    val action: String,
    val summary: String,
    val userEmail: String?,
    val createdAt: String?,
    val createdAtBr: String?,
)

data class ContractListData(
    val items: List<ContractListItem>,
    val meta: PaginationMeta,
    val filters: List<FilterValueOption>,
)

data class ContractListItem(
    val id: Long,
    val code: String,
    val title: String,
    val clientName: String,
    val objectLabel: String,
    val status: String,
    val statusLabel: String,
    val type: String?,
    val categoryName: String?,
    val value: Double?,
    val valueLabel: String?,
    val paymentMethod: String?,
    val paymentMethodLabel: String?,
    val startDate: String?,
    val startDateBr: String?,
    val endDate: String?,
    val endDateBr: String?,
    val isExpired: Boolean,
    val hasFinalPdf: Boolean,
    val signaturePending: Boolean,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class ContractDetail(
    val summary: ContractListItem,
    val description: String?,
    val contentExcerpt: String?,
    val billingType: String?,
    val billingTypeLabel: String?,
    val recurrence: String?,
    val recurrenceLabel: String?,
    val responsibleName: String?,
    val condominiumName: String?,
    val syndicName: String?,
    val financialAccountName: String?,
    val proposalCode: String?,
    val processNumber: String?,
    val documentsCount: Int,
    val latestSignature: SignatureListItem?,
)

data class ContractDocumentItem(
    val id: String,
    val kind: String,
    val kindLabel: String,
    val name: String,
    val description: String?,
    val mimeType: String?,
    val fileSize: Int,
    val fileSizeLabel: String?,
    val createdAt: String?,
    val createdAtBr: String?,
    val downloadKind: String,
    val referenceId: Long?,
)

data class SignatureListData(
    val items: List<SignatureListItem>,
    val meta: PaginationMeta,
    val filters: SignatureFilters,
)

data class SignatureFilters(
    val statuses: List<FilterValueOption>,
    val origins: List<FilterValueOption>,
)

data class SignatureListItem(
    val id: Long,
    val documentName: String,
    val status: String,
    val statusLabel: String,
    val sourceType: String,
    val sourceLabel: String,
    val signersCount: Int,
    val pendingCount: Int,
    val createdAt: String?,
    val createdAtBr: String?,
    val completedAt: String?,
    val completedAtBr: String?,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class SignatureDetail(
    val summary: SignatureListItem,
    val requestedAt: String?,
    val requestedAtBr: String?,
    val lastSyncedAt: String?,
    val lastSyncedAtBr: String?,
    val provider: String?,
    val signingUrlAvailable: Boolean,
    val signers: List<SignatureSignerItem>,
    val events: List<SignatureEventItem>,
    val availableActions: SignatureAvailableActions,
)

data class SignatureSignerItem(
    val id: Long,
    val name: String,
    val email: String?,
    val phone: String?,
    val roleLabel: String?,
    val status: String,
    val statusLabel: String,
    val requestedAtBr: String?,
    val viewedAtBr: String?,
    val signedAtBr: String?,
    val rejectedAtBr: String?,
)

data class SignatureEventItem(
    val id: Long,
    val eventType: String?,
    val label: String,
    val signerName: String?,
    val description: String,
    val receivedAt: String?,
    val receivedAtBr: String?,
)

data class SignatureAvailableActions(
    val canSync: Boolean,
)

data class FinanceDashboardData(
    val summary: FinanceDashboardSummary,
    val cards: List<FinanceSummaryCard>,
    val alerts: List<FinanceAlertItem>,
    val cashflowPreview: List<FinanceCashflowItem>,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class FinanceDashboardSummary(
    val monthLabel: String?,
    val receitasMonth: Double,
    val receitasMonthLabel: String?,
    val despesasMonth: Double,
    val despesasMonthLabel: String?,
    val saldoPrevisto: Double,
    val saldoPrevistoLabel: String?,
    val contasVencidas: Int,
    val contasAVencer: Int,
    val recebiveisEmAberto: Double,
    val recebiveisEmAbertoLabel: String?,
)

data class FinanceSummaryCard(
    val key: String,
    val title: String,
    val value: Double,
    val valueLabel: String?,
    val description: String,
    val tone: String,
)

data class FinanceAlertItem(
    val title: String,
    val message: String,
    val tone: String,
)

data class FinanceReceivablesData(
    val items: List<FinanceReceivableItem>,
    val meta: PaginationMeta,
    val filters: List<FilterValueOption>,
)

data class FinancePayablesData(
    val items: List<FinancePayableItem>,
    val meta: PaginationMeta,
    val filters: List<FilterValueOption>,
)

data class FinanceReceivableItem(
    val id: Long,
    val code: String,
    val title: String,
    val clientName: String?,
    val condominiumName: String?,
    val contractCode: String?,
    val status: String,
    val statusLabel: String,
    val dueDate: String?,
    val dueDateBr: String?,
    val amount: Double?,
    val amountLabel: String?,
    val receivedAmount: Double?,
    val receivedAmountLabel: String?,
    val outstandingAmount: Double?,
    val outstandingAmountLabel: String?,
    val updatedAtBr: String?,
)

data class FinancePayableItem(
    val id: Long,
    val code: String,
    val title: String,
    val supplierName: String?,
    val status: String,
    val statusLabel: String,
    val dueDate: String?,
    val dueDateBr: String?,
    val amount: Double?,
    val amountLabel: String?,
    val paidAmount: Double?,
    val paidAmountLabel: String?,
    val outstandingAmount: Double?,
    val outstandingAmountLabel: String?,
    val updatedAtBr: String?,
)

data class FinanceCashflowData(
    val items: List<FinanceCashflowItem>,
    val meta: PaginationMeta,
    val summary: FinanceCashflowSummary,
)

data class FinanceCashflowSummary(
    val periodLabel: String?,
    val entradas: Double,
    val entradasLabel: String?,
    val saidas: Double,
    val saidasLabel: String?,
    val saldo: Double,
    val saldoLabel: String?,
)

data class FinanceCashflowItem(
    val id: Long,
    val description: String,
    val transactionType: String,
    val transactionTypeLabel: String,
    val amount: Double?,
    val amountLabel: String?,
    val transactionDate: String?,
    val transactionDateBr: String?,
    val accountName: String?,
    val categoryName: String?,
    val documentNumber: String?,
    val reconciliationStatus: String?,
)
