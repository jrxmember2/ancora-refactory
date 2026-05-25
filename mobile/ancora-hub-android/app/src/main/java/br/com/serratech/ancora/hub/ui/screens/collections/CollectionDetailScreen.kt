package br.com.serratech.ancora.hub.ui.screens.collections

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.data.dto.CollectionTjesPreviewRequestDto
import br.com.serratech.ancora.hub.domain.model.CollectionDetail
import br.com.serratech.ancora.hub.domain.model.CollectionInstallmentItem
import br.com.serratech.ancora.hub.domain.model.CollectionQuota
import br.com.serratech.ancora.hub.domain.model.CollectionTjesPreview
import br.com.serratech.ancora.hub.domain.model.CollectionTimelineItem
import br.com.serratech.ancora.hub.domain.model.HubAttachment
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTextField
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import kotlinx.coroutines.launch

data class CollectionDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val item: CollectionDetail? = null,
    val isInstallmentsLoading: Boolean = false,
    val isLoadingMoreInstallments: Boolean = false,
    val installmentsError: String? = null,
    val installments: List<CollectionInstallmentItem> = emptyList(),
    val installmentMeta: PaginationMeta? = null,
    val isTimelineLoading: Boolean = false,
    val isLoadingMoreTimeline: Boolean = false,
    val timelineError: String? = null,
    val timeline: List<CollectionTimelineItem> = emptyList(),
    val timelineMeta: PaginationMeta? = null,
    val isAttachmentsLoading: Boolean = false,
    val isLoadingMoreAttachments: Boolean = false,
    val attachmentsError: String? = null,
    val attachments: List<HubAttachment> = emptyList(),
    val attachmentMeta: PaginationMeta? = null,
    val isActionLoading: Boolean = false,
    val actionMessage: String? = null,
    val tjesPreview: CollectionTjesPreview? = null,
)

class CollectionDetailViewModel(
    private val container: AppContainer,
    private val collectionId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(CollectionDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(
                isLoading = true,
                error = null,
                installmentsError = null,
                timelineError = null,
                attachmentsError = null,
                actionMessage = null,
            )

            runCatching { container.collectionRepository.detail(collectionId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isLoading = false,
                        item = detail,
                        installments = emptyList(),
                        installmentMeta = null,
                        timeline = emptyList(),
                        timelineMeta = null,
                        attachments = emptyList(),
                        attachmentMeta = null,
                        tjesPreview = null,
                    )
                    loadInstallments(page = 1, append = false)
                    loadTimeline(page = 1, append = false)
                    loadAttachments(page = 1, append = false)
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar a cobrança agora.",
                    )
                }
        }
    }

    fun loadMoreInstallments() {
        val meta = uiState.installmentMeta ?: return
        if (uiState.isLoadingMoreInstallments || !meta.hasMore) {
            return
        }

        viewModelScope.launch {
            loadInstallments(page = meta.currentPage + 1, append = true)
        }
    }

    fun loadMoreTimeline() {
        val meta = uiState.timelineMeta ?: return
        if (uiState.isLoadingMoreTimeline || !meta.hasMore) {
            return
        }

        viewModelScope.launch {
            loadTimeline(page = meta.currentPage + 1, append = true)
        }
    }

    fun loadMoreAttachments() {
        val meta = uiState.attachmentMeta ?: return
        if (uiState.isLoadingMoreAttachments || !meta.hasMore) {
            return
        }

        viewModelScope.launch {
            loadAttachments(page = meta.currentPage + 1, append = true)
        }
    }

    fun previewTjes(payload: CollectionTjesPreviewRequestDto) {
        viewModelScope.launch {
            uiState = uiState.copy(
                isActionLoading = true,
                actionMessage = null,
            )

            runCatching {
                container.collectionRepository.previewTjes(collectionId, payload)
            }.onSuccess { preview ->
                uiState = uiState.copy(
                    isActionLoading = false,
                    tjesPreview = preview,
                    actionMessage = "Simulação TJES atualizada.",
                )
            }.onFailure {
                uiState = uiState.copy(
                    isActionLoading = false,
                    actionMessage = it.message ?: "Não foi possível gerar a simulação TJES agora.",
                )
            }
        }
    }

    fun applyTjes(payload: CollectionTjesPreviewRequestDto, onDone: () -> Unit) {
        viewModelScope.launch {
            uiState = uiState.copy(
                isActionLoading = true,
                actionMessage = null,
            )

            runCatching {
                container.collectionRepository.applyTjes(collectionId, payload)
            }.onSuccess { (detail, preview) ->
                uiState = uiState.copy(
                    isActionLoading = false,
                    item = detail,
                    tjesPreview = preview ?: uiState.tjesPreview,
                    actionMessage = "Cálculo TJES aplicado com sucesso.",
                )
                onDone()
                refresh()
            }.onFailure {
                uiState = uiState.copy(
                    isActionLoading = false,
                    actionMessage = it.message ?: "Não foi possível aplicar o cálculo TJES agora.",
                )
            }
        }
    }

    fun requestBoleto() {
        viewModelScope.launch {
            uiState = uiState.copy(
                isActionLoading = true,
                actionMessage = null,
            )

            runCatching {
                container.collectionRepository.requestBoleto(collectionId)
            }.onSuccess { message ->
                uiState = uiState.copy(
                    isActionLoading = false,
                    actionMessage = message,
                )
                refresh()
            }.onFailure {
                uiState = uiState.copy(
                    isActionLoading = false,
                    actionMessage = it.message ?: "Não foi possível solicitar o boleto agora.",
                )
            }
        }
    }

    private suspend fun loadInstallments(page: Int, append: Boolean) {
        uiState = uiState.copy(
            isInstallmentsLoading = !append,
            isLoadingMoreInstallments = append,
            installmentsError = if (append) uiState.installmentsError else null,
        )

        runCatching {
            container.collectionRepository.installments(
                collectionId = collectionId,
                page = page,
            )
        }.onSuccess { pageData ->
            uiState = uiState.copy(
                isInstallmentsLoading = false,
                isLoadingMoreInstallments = false,
                installmentsError = null,
                installments = if (append) uiState.installments + pageData.items else pageData.items,
                installmentMeta = pageData.meta,
            )
        }.onFailure {
            uiState = uiState.copy(
                isInstallmentsLoading = false,
                isLoadingMoreInstallments = false,
                installmentsError = it.message ?: "Não foi possível carregar as parcelas agora.",
            )
        }
    }

    private suspend fun loadTimeline(page: Int, append: Boolean) {
        uiState = uiState.copy(
            isTimelineLoading = !append,
            isLoadingMoreTimeline = append,
            timelineError = if (append) uiState.timelineError else null,
        )

        runCatching {
            container.collectionRepository.timeline(
                collectionId = collectionId,
                page = page,
            )
        }.onSuccess { pageData ->
            uiState = uiState.copy(
                isTimelineLoading = false,
                isLoadingMoreTimeline = false,
                timelineError = null,
                timeline = if (append) uiState.timeline + pageData.items else pageData.items,
                timelineMeta = pageData.meta,
            )
        }.onFailure {
            uiState = uiState.copy(
                isTimelineLoading = false,
                isLoadingMoreTimeline = false,
                timelineError = it.message ?: "Não foi possível carregar o histórico agora.",
            )
        }
    }

    private suspend fun loadAttachments(page: Int, append: Boolean) {
        uiState = uiState.copy(
            isAttachmentsLoading = !append,
            isLoadingMoreAttachments = append,
            attachmentsError = if (append) uiState.attachmentsError else null,
        )

        runCatching {
            container.collectionRepository.attachments(
                collectionId = collectionId,
                page = page,
            )
        }.onSuccess { pageData ->
            uiState = uiState.copy(
                isAttachmentsLoading = false,
                isLoadingMoreAttachments = false,
                attachmentsError = null,
                attachments = if (append) uiState.attachments + pageData.items else pageData.items,
                attachmentMeta = pageData.meta,
            )
        }.onFailure {
            uiState = uiState.copy(
                isAttachmentsLoading = false,
                isLoadingMoreAttachments = false,
                attachmentsError = it.message ?: "Não foi possível carregar os anexos agora.",
            )
        }
    }
}

@Composable
fun CollectionDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    collectionId: Long,
    onEditCollection: (Long) -> Unit = {},
    onBack: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    var showTjesSheet by rememberSaveable { mutableStateOf(false) }
    val viewModel: CollectionDetailViewModel = viewModel(
        key = "collection-detail-$collectionId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return CollectionDetailViewModel(container, collectionId) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Cobrança",
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                        contentDescription = "Voltar",
                    )
                }
            },
        )

        when {
            viewModel.uiState.isLoading -> {
                AncoraLoadingState(label = "Carregando cobrança...")
            }

            viewModel.uiState.error != null && viewModel.uiState.item == null -> {
                AncoraErrorState(
                    title = "Não foi possível carregar as informações.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            viewModel.uiState.item == null -> {
                AncoraEmptyState(
                    title = "Nenhuma cobrança encontrada",
                    message = "Essa cobrança pode não estar mais disponível para o seu usuário.",
                    actionLabel = "Voltar",
                    onAction = onBack,
                )
            }

            else -> {
                val item = viewModel.uiState.item!!

                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.lg),
                    verticalArrangement = Arrangement.spacedBy(spacing.md),
                ) {
                    item { CollectionSummaryCard(item = item) }

                    viewModel.uiState.actionMessage?.let { message ->
                        item {
                            AncoraCard(bordered = true) {
                                Text(
                                    text = message,
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurface,
                                )
                            }
                        }
                    }

                    if (
                        item.availableActions.canEdit ||
                        item.availableActions.canCalculateTjes ||
                        item.availableActions.canRequestBoleto
                    ) {
                        item {
                            AncoraCard {
                                AncoraSectionTitle(title = "Ações rápidas")
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                                ) {
                                    if (item.availableActions.canEdit) {
                                        AncoraGhostButton(
                                            text = "Editar OS",
                                            onClick = { onEditCollection(item.summary.id) },
                                        )
                                    }
                                    if (item.availableActions.canCalculateTjes) {
                                        AncoraSecondaryButton(
                                            text = "Calcular TJES",
                                            onClick = { showTjesSheet = true },
                                        )
                                    }
                                }
                                if (item.availableActions.canRequestBoleto) {
                                    AncoraButton(
                                        text = if (viewModel.uiState.isActionLoading) "Solicitando..." else "Solicitar boleto",
                                        enabled = !viewModel.uiState.isActionLoading,
                                        onClick = viewModel::requestBoleto,
                                    )
                                }
                            }
                        }
                    }

                    item { CollectionInfoCard(item = item) }

                    if (item.contacts.isNotEmpty()) {
                        item { AncoraSectionTitle(title = "Contatos") }
                        items(item.contacts, key = { contact -> contact.id }) { contact ->
                            AncoraCard(bordered = true) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                ) {
                                    Text(
                                        text = contact.value,
                                        style = MaterialTheme.typography.titleMedium,
                                    )
                                    if (contact.isPrimary) {
                                        AncoraStatusChip(
                                            label = "Principal",
                                            tone = AncoraTone.Brand,
                                        )
                                    }
                                }
                                Text(
                                    text = contact.type.replaceFirstChar { char -> char.uppercase() },
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                                if (contact.isWhatsapp) {
                                    AncoraStatusChip(
                                        label = "WhatsApp",
                                        tone = AncoraTone.Success,
                                    )
                                }
                            }
                        }
                    }

                    item { AncoraSectionTitle(title = "Cotas") }

                    if (item.quotas.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhuma cota encontrada",
                                message = "As cotas vinculadas a esta cobrança aparecerão aqui.",
                            )
                        }
                    } else {
                        items(item.quotas, key = { quota -> quota.id }) { quota ->
                            QuotaCard(quota = quota)
                        }
                    }

                    item { AgreementCard(item = item) }

                    item { AncoraSectionTitle(title = "Parcelas") }

                    when {
                        viewModel.uiState.isInstallmentsLoading && viewModel.uiState.installments.isEmpty() -> {
                            item { AncoraLoadingState(label = "Carregando parcelas...") }
                        }

                        viewModel.uiState.installmentsError != null && viewModel.uiState.installments.isEmpty() -> {
                            item {
                                AncoraErrorState(
                                    title = "Não foi possível carregar as informações.",
                                    message = viewModel.uiState.installmentsError.orEmpty(),
                                    onRetry = viewModel::refresh,
                                )
                            }
                        }

                        viewModel.uiState.installments.isEmpty() -> {
                            item {
                                AncoraEmptyState(
                                    title = "Nenhuma parcela encontrada",
                                    message = "As parcelas desta cobrança aparecerão aqui.",
                                )
                            }
                        }

                        else -> {
                            items(viewModel.uiState.installments, key = { installment -> installment.id }) { installment ->
                                InstallmentCard(installment = installment)
                            }
                            if (viewModel.uiState.installmentMeta?.hasMore == true) {
                                item {
                                    AncoraSecondaryButton(
                                        text = if (viewModel.uiState.isLoadingMoreInstallments) {
                                            "Carregando..."
                                        } else {
                                            "Carregar mais parcelas"
                                        },
                                        enabled = !viewModel.uiState.isLoadingMoreInstallments,
                                        onClick = viewModel::loadMoreInstallments,
                                    )
                                }
                            }
                        }
                    }

                    item { AncoraSectionTitle(title = "Histórico") }

                    when {
                        viewModel.uiState.isTimelineLoading && viewModel.uiState.timeline.isEmpty() -> {
                            item { AncoraLoadingState(label = "Carregando histórico...") }
                        }

                        viewModel.uiState.timelineError != null && viewModel.uiState.timeline.isEmpty() -> {
                            item {
                                AncoraErrorState(
                                    title = "Não foi possível carregar as informações.",
                                    message = viewModel.uiState.timelineError.orEmpty(),
                                    onRetry = viewModel::refresh,
                                )
                            }
                        }

                        viewModel.uiState.timeline.isEmpty() -> {
                            item {
                                AncoraEmptyState(
                                    title = "Nenhum histórico encontrado",
                                    message = "As movimentações desta cobrança aparecerão aqui.",
                                )
                            }
                        }

                        else -> {
                            items(viewModel.uiState.timeline, key = { timeline -> timeline.id }) { timeline ->
                                TimelineCard(timeline = timeline)
                            }
                            if (viewModel.uiState.timelineMeta?.hasMore == true) {
                                item {
                                    AncoraSecondaryButton(
                                        text = if (viewModel.uiState.isLoadingMoreTimeline) {
                                            "Carregando..."
                                        } else {
                                            "Carregar mais histórico"
                                        },
                                        enabled = !viewModel.uiState.isLoadingMoreTimeline,
                                        onClick = viewModel::loadMoreTimeline,
                                    )
                                }
                            }
                        }
                    }

                    item { AncoraSectionTitle(title = "Anexos") }

                    when {
                        viewModel.uiState.isAttachmentsLoading && viewModel.uiState.attachments.isEmpty() -> {
                            item { AncoraLoadingState(label = "Carregando anexos...") }
                        }

                        viewModel.uiState.attachmentsError != null && viewModel.uiState.attachments.isEmpty() -> {
                            item {
                                AncoraErrorState(
                                    title = "Não foi possível carregar as informações.",
                                    message = viewModel.uiState.attachmentsError.orEmpty(),
                                    onRetry = viewModel::refresh,
                                )
                            }
                        }

                        viewModel.uiState.attachments.isEmpty() -> {
                            item {
                                AncoraEmptyState(
                                    title = "Nenhum anexo encontrado",
                                    message = "Os anexos desta cobrança aparecerão aqui.",
                                )
                            }
                        }

                        else -> {
                            items(viewModel.uiState.attachments, key = { attachment -> attachment.id }) { attachment ->
                                CollectionAttachmentCard(attachment = attachment)
                            }
                            if (viewModel.uiState.attachmentMeta?.hasMore == true) {
                                item {
                                    AncoraSecondaryButton(
                                        text = if (viewModel.uiState.isLoadingMoreAttachments) {
                                            "Carregando..."
                                        } else {
                                            "Carregar mais anexos"
                                        },
                                        enabled = !viewModel.uiState.isLoadingMoreAttachments,
                                        onClick = viewModel::loadMoreAttachments,
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (showTjesSheet && viewModel.uiState.item != null) {
        CollectionTjesSheet(
            item = viewModel.uiState.item!!,
            preview = viewModel.uiState.tjesPreview,
            isLoading = viewModel.uiState.isActionLoading,
            onDismiss = { showTjesSheet = false },
            onPreview = viewModel::previewTjes,
            onApply = { payload ->
                viewModel.applyTjes(payload) {
                    showTjesSheet = false
                }
            },
        )
    }
}

@Composable
private fun CollectionSummaryCard(item: CollectionDetail) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(
                label = item.summary.workflowStageLabel,
                tone = collectionTone(item.summary.workflowStage),
            )
            item.summary.billingStatusLabel?.let {
                AncoraStatusChip(
                    label = it,
                    tone = billingTone(item.summary.billingStatus),
                )
            }
        }
        Text(
            text = item.summary.condominiumName,
            style = MaterialTheme.typography.headlineSmall,
        )
        Text(
            text = "Unidade ${item.summary.unitLabel}",
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = item.summary.debtorName,
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        item.summary.ownerName?.let {
            Text(
                text = "Proprietário: $it",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.summary.tenantName?.let {
            Text(
                text = "Locatário: $it",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.summary.agreementTotalLabel?.let {
            Text(
                text = "Valor atualizado: $it",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.summary.situationLabel?.let {
            AncoraStatusChip(
                label = it,
                tone = situationTone(item.summary.situation),
            )
        }
    }
}

@Composable
private fun CollectionInfoCard(item: CollectionDetail) {
    AncoraCard {
        AncoraSectionTitle(title = "Dados principais")
        CollectionDetailRow(label = "Tipo de cobrança", value = item.chargeType)
        CollectionDetailRow(label = "Modo", value = item.caseMode)
        CollectionDetailRow(label = "Documento do devedor", value = item.debtorDocument)
        CollectionDetailRow(label = "Data-base", value = item.billingDateBr)
        CollectionDetailRow(label = "Processo judicial", value = item.judicialCaseNumber)
        CollectionDetailRow(label = "Síndico", value = item.syndicName)
        CollectionDetailRow(label = "Administradora", value = item.administratorName)
        CollectionDetailRow(label = "Entrada", value = item.entryAmountLabel)
        CollectionDetailRow(label = "Honorários", value = item.feesAmountLabel)
    }
}

@Composable
private fun QuotaCard(quota: CollectionQuota) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = quota.referenceLabel,
                style = MaterialTheme.typography.titleMedium,
            )
            quota.status?.let {
                AncoraStatusChip(
                    label = it,
                    tone = situationTone(it),
                )
            }
        }
        quota.dueDateBr?.let {
            Text(
                text = "Vencimento: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        quota.originalAmountLabel?.let {
            Text(
                text = "Valor original: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        quota.updatedAmountLabel?.let {
            Text(
                text = "Valor atualizado: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun AgreementCard(item: CollectionDetail) {
    AncoraCard {
        AncoraSectionTitle(title = "Acordos e assinaturas")
        val hasAgreement = item.agreement.hasTerm || item.agreement.hasSignatureRequests
        if (!hasAgreement) {
            Text(
                text = "Nenhum termo ou assinatura pendente no momento.",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            return@AncoraCard
        }

        if (item.agreement.hasTerm) {
            AncoraStatusChip(
                label = "Termo de acordo disponível",
                tone = AncoraTone.Warning,
            )
        }
        if (item.agreement.hasSignatureRequests) {
            Text(
                text = "${item.agreement.signatureRequestsCount} solicitação(ões) de assinatura vinculada(s).",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun InstallmentCard(installment: CollectionInstallmentItem) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = installment.label,
                style = MaterialTheme.typography.titleMedium,
            )
            installment.status?.let {
                AncoraStatusChip(
                    label = it,
                    tone = billingTone(it),
                )
            }
        }
        installment.dueDateBr?.let {
            Text(
                text = "Vencimento: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        installment.amountLabel?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodyLarge,
            )
        }
    }
}

@Composable
private fun TimelineCard(timeline: CollectionTimelineItem) {
    AncoraCard(bordered = true) {
        timeline.eventType?.let {
            AncoraStatusChip(
                label = it.replaceFirstChar { char -> char.uppercase() },
                tone = AncoraTone.Info,
            )
        }
        Text(
            text = timeline.description,
            style = MaterialTheme.typography.bodyLarge,
        )
        Text(
            text = timeline.createdAtBr ?: "Agora mesmo",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        timeline.userName?.let {
            Text(
                text = "Registrado por $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun CollectionAttachmentCard(attachment: HubAttachment) {
    AncoraCard(bordered = true) {
        Text(
            text = attachment.name,
            style = MaterialTheme.typography.titleSmall,
        )
        attachment.tagLabel?.let {
            AncoraStatusChip(
                label = it,
                tone = AncoraTone.Info,
            )
        }
        Text(
            text = attachment.createdAtBr ?: "Agora mesmo",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        attachment.uploadedByName?.let {
            Text(
                text = "Enviado por $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CollectionTjesSheet(
    item: CollectionDetail,
    preview: CollectionTjesPreview?,
    isLoading: Boolean,
    onDismiss: () -> Unit,
    onPreview: (CollectionTjesPreviewRequestDto) -> Unit,
    onApply: (CollectionTjesPreviewRequestDto) -> Unit,
) {
    val spacing = MaterialTheme.spacing
    var finalDate by rememberSaveable { mutableStateOf(item.billingDate ?: item.summary.updatedAt?.take(10).orEmpty()) }
    var interestType by rememberSaveable { mutableStateOf("legal") }
    var interestRateMonthly by rememberSaveable { mutableStateOf("") }
    var finePercent by rememberSaveable { mutableStateOf("2,00") }
    var attorneyFeeType by rememberSaveable { mutableStateOf("percent") }
    var attorneyFeeValue by rememberSaveable { mutableStateOf("10,00") }
    var costsAmount by rememberSaveable { mutableStateOf("") }
    var costsDate by rememberSaveable { mutableStateOf("") }
    var abatementAmount by rememberSaveable { mutableStateOf("") }
    var selectedQuotaIds by rememberSaveable { mutableStateOf(item.quotas.map { it.id }) }

    fun payload(): CollectionTjesPreviewRequestDto = CollectionTjesPreviewRequestDto(
        finalDate = normalizeCollectionDate(finalDate),
        indexCode = "ATM",
        quotaIds = selectedQuotaIds,
        interestType = interestType,
        interestRateMonthly = normalizeCollectionMoney(interestRateMonthly),
        finePercent = normalizeCollectionMoney(finePercent),
        attorneyFeeType = attorneyFeeType,
        attorneyFeeValue = normalizeCollectionMoney(attorneyFeeValue),
        costsAmount = normalizeCollectionMoney(costsAmount),
        costsDate = normalizeCollectionDate(costsDate),
        abatementAmount = normalizeCollectionMoney(abatementAmount),
    )

    ModalBottomSheet(onDismissRequest = onDismiss) {
        LazyColumn(
            modifier = Modifier.fillMaxWidth(),
            contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.md),
            verticalArrangement = Arrangement.spacedBy(spacing.md),
        ) {
            item {
                Text(
                    text = "Cálculo TJES",
                    style = MaterialTheme.typography.headlineSmall,
                )
            }

            item {
                AncoraCard {
                    AncoraTextField(
                        value = finalDate,
                        onValueChange = { finalDate = it },
                        label = "Data final",
                        placeholder = "AAAA-MM-DD",
                    )
                    Text(
                        text = "Tipo de juros",
                        style = MaterialTheme.typography.titleSmall,
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(spacing.sm)) {
                        listOf(
                            "legal" to "Legal",
                            "contractual" to "Contratual",
                            "none" to "Sem juros",
                        ).forEach { option ->
                            FilterChip(
                                selected = interestType == option.first,
                                onClick = { interestType = option.first },
                                label = { Text(option.second) },
                            )
                        }
                    }
                    if (interestType == "contractual") {
                        AncoraTextField(
                            value = interestRateMonthly,
                            onValueChange = { interestRateMonthly = it },
                            label = "Juros mensais (%)",
                            placeholder = "1,00",
                        )
                    }
                    AncoraTextField(
                        value = finePercent,
                        onValueChange = { finePercent = it },
                        label = "Multa (%)",
                        placeholder = "2,00",
                    )
                    Text(
                        text = "Honorários",
                        style = MaterialTheme.typography.titleSmall,
                    )
                    Row(horizontalArrangement = Arrangement.spacedBy(spacing.sm)) {
                        listOf(
                            "percent" to "Percentual",
                            "fixed" to "Fixo",
                            "none" to "Sem honorários",
                        ).forEach { option ->
                            FilterChip(
                                selected = attorneyFeeType == option.first,
                                onClick = { attorneyFeeType = option.first },
                                label = { Text(option.second) },
                            )
                        }
                    }
                    if (attorneyFeeType != "none") {
                        AncoraTextField(
                            value = attorneyFeeValue,
                            onValueChange = { attorneyFeeValue = it },
                            label = if (attorneyFeeType == "percent") "Honorários (%)" else "Honorários fixos",
                            placeholder = if (attorneyFeeType == "percent") "10,00" else "500,00",
                        )
                    }
                    AncoraTextField(
                        value = costsAmount,
                        onValueChange = { costsAmount = it },
                        label = "Custas",
                        placeholder = "0,00",
                    )
                    AncoraTextField(
                        value = costsDate,
                        onValueChange = { costsDate = it },
                        label = "Data das custas",
                        placeholder = "AAAA-MM-DD",
                    )
                    AncoraTextField(
                        value = abatementAmount,
                        onValueChange = { abatementAmount = it },
                        label = "Abatimento",
                        placeholder = "0,00",
                    )
                }
            }

            if (item.quotas.isNotEmpty()) {
                item {
                    AncoraCard {
                        AncoraSectionTitle(title = "Cotas incluídas")
                        Column(verticalArrangement = Arrangement.spacedBy(spacing.sm)) {
                            item.quotas.forEach { quota ->
                                FilterChip(
                                    selected = selectedQuotaIds.contains(quota.id),
                                    onClick = {
                                        selectedQuotaIds = if (selectedQuotaIds.contains(quota.id)) {
                                            selectedQuotaIds - quota.id
                                        } else {
                                            selectedQuotaIds + quota.id
                                        }
                                    },
                                    label = {
                                        Text("${quota.referenceLabel} · ${quota.originalAmountLabel ?: "Sem valor"}")
                                    },
                                )
                            }
                        }
                    }
                }
            }

            preview?.let { currentPreview ->
                item {
                    AncoraCard(bordered = true) {
                        AncoraSectionTitle(title = "Resumo da simulação")
                        CollectionDetailRow("Data final", currentPreview.summary.finalDate)
                        CollectionDetailRow("Total do débito", currentPreview.summary.debitTotal)
                        CollectionDetailRow("Honorários", currentPreview.summary.attorneyFee)
                        CollectionDetailRow("Taxa de boleto", currentPreview.summary.boletoFee)
                        CollectionDetailRow("Total geral", currentPreview.summary.grandTotal)
                    }
                }
            }

            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                ) {
                    AncoraSecondaryButton(
                        text = if (isLoading) "Processando..." else "Simular",
                        enabled = !isLoading,
                        onClick = { onPreview(payload()) },
                    )
                    AncoraButton(
                        text = if (isLoading) "Aplicando..." else "Aplicar cálculo",
                        enabled = !isLoading,
                        onClick = { onApply(payload()) },
                    )
                }
            }
        }
    }
}

@Composable
private fun CollectionDetailRow(label: String, value: String?) {
    if (value.isNullOrBlank()) {
        return
    }

    Column(verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.xs)) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyLarge,
        )
    }
}

private fun normalizeCollectionMoney(value: String): Double? {
    val raw = value.trim()
    if (raw.isBlank()) {
        return null
    }

    return raw
        .replace(".", "")
        .replace(",", ".")
        .toDoubleOrNull()
}

private fun normalizeCollectionDate(value: String): String? {
    val raw = value.trim()
    if (raw.isBlank()) {
        return null
    }

    return runCatching {
        if (raw.contains("/")) {
            LocalDate.parse(raw, DateTimeFormatter.ofPattern("dd/MM/yyyy")).toString()
        } else {
            LocalDate.parse(raw).toString()
        }
    }.getOrNull() ?: raw
}
