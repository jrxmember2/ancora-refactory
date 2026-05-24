package br.com.serratech.ancora.hub.ui.screens.processes

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.HubAttachment
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.ProcessDetail
import br.com.serratech.ancora.hub.domain.model.ProcessMovementItem
import br.com.serratech.ancora.hub.domain.model.ProcessParty
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class ProcessDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val item: ProcessDetail? = null,
    val isMovementsLoading: Boolean = false,
    val isLoadingMoreMovements: Boolean = false,
    val movementsError: String? = null,
    val movements: List<ProcessMovementItem> = emptyList(),
    val movementMeta: PaginationMeta? = null,
    val isAttachmentsLoading: Boolean = false,
    val isLoadingMoreAttachments: Boolean = false,
    val attachmentsError: String? = null,
    val attachments: List<HubAttachment> = emptyList(),
    val attachmentMeta: PaginationMeta? = null,
)

class ProcessDetailViewModel(
    private val container: AppContainer,
    private val processId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(ProcessDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(
                isLoading = true,
                error = null,
                movementsError = null,
                attachmentsError = null,
            )

            runCatching { container.processRepository.detail(processId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isLoading = false,
                        item = detail,
                        movements = emptyList(),
                        movementMeta = null,
                        attachments = emptyList(),
                        attachmentMeta = null,
                    )
                    loadMovements(page = 1, append = false)
                    loadAttachments(page = 1, append = false)
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar o processo agora.",
                    )
                }
        }
    }

    fun loadMoreMovements() {
        val meta = uiState.movementMeta ?: return
        if (uiState.isLoadingMoreMovements || !meta.hasMore) {
            return
        }

        viewModelScope.launch {
            loadMovements(page = meta.currentPage + 1, append = true)
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

    private suspend fun loadMovements(page: Int, append: Boolean) {
        uiState = uiState.copy(
            isMovementsLoading = !append,
            isLoadingMoreMovements = append,
            movementsError = if (append) uiState.movementsError else null,
        )

        runCatching {
            container.processRepository.movements(
                processId = processId,
                page = page,
            )
        }.onSuccess { pageData ->
            uiState = uiState.copy(
                isMovementsLoading = false,
                isLoadingMoreMovements = false,
                movementsError = null,
                movements = if (append) uiState.movements + pageData.items else pageData.items,
                movementMeta = pageData.meta,
            )
        }.onFailure {
            uiState = uiState.copy(
                isMovementsLoading = false,
                isLoadingMoreMovements = false,
                movementsError = it.message ?: "Não foi possível carregar as movimentações agora.",
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
            container.processRepository.attachments(
                processId = processId,
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
fun ProcessDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    processId: Long,
    onBack: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    val viewModel: ProcessDetailViewModel = viewModel(
        key = "process-detail-$processId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProcessDetailViewModel(container, processId) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Processo",
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
                AncoraLoadingState(label = "Carregando processo...")
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
                    title = "Nenhum processo encontrado",
                    message = "Esse processo pode não estar mais disponível para o seu usuário.",
                    actionLabel = "Voltar",
                    onAction = onBack,
                )
            }

            else -> {
                val item = viewModel.uiState.item!!

                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(
                        horizontal = spacing.lg,
                        vertical = spacing.lg,
                    ),
                    verticalArrangement = Arrangement.spacedBy(spacing.md),
                ) {
                    item {
                        ProcessSummaryCard(item = item)
                    }

                    item {
                        ProcessMainInfoCard(item = item)
                    }

                    item {
                        ProcessSidesCard(item = item)
                    }

                    if (!item.notes.isNullOrBlank()) {
                        item {
                            AncoraCard {
                                AncoraSectionTitle(title = "Observações")
                                Text(
                                    text = item.notes,
                                    style = MaterialTheme.typography.bodyLarge,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                    }

                    item {
                        AncoraSectionTitle(title = "Partes")
                    }

                    if (item.parties.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhuma parte cadastrada",
                                message = "As partes vinculadas ao processo aparecerão aqui.",
                            )
                        }
                    } else {
                        items(item.parties, key = { party -> party.id }) { party ->
                            ProcessPartyCard(party = party)
                        }
                    }

                    item {
                        AncoraSectionTitle(title = "Movimentações")
                    }

                    when {
                        viewModel.uiState.isMovementsLoading && viewModel.uiState.movements.isEmpty() -> {
                            item {
                                AncoraLoadingState(label = "Carregando movimentações...")
                            }
                        }

                        viewModel.uiState.movementsError != null && viewModel.uiState.movements.isEmpty() -> {
                            item {
                                AncoraErrorState(
                                    title = "Não foi possível carregar as informações.",
                                    message = viewModel.uiState.movementsError.orEmpty(),
                                    onRetry = viewModel::refresh,
                                )
                            }
                        }

                        viewModel.uiState.movements.isEmpty() -> {
                            item {
                                AncoraEmptyState(
                                    title = "Nenhuma movimentação encontrada",
                                    message = "As movimentações processuais aparecerão aqui.",
                                )
                            }
                        }

                        else -> {
                            items(viewModel.uiState.movements, key = { movement -> movement.id }) { movement ->
                                ProcessMovementCard(movement = movement)
                            }
                            if (viewModel.uiState.movementMeta?.hasMore == true) {
                                item {
                                    AncoraSecondaryButton(
                                        text = if (viewModel.uiState.isLoadingMoreMovements) {
                                            "Carregando..."
                                        } else {
                                            "Carregar mais movimentações"
                                        },
                                        enabled = !viewModel.uiState.isLoadingMoreMovements,
                                        onClick = viewModel::loadMoreMovements,
                                    )
                                }
                            }
                        }
                    }

                    item {
                        AncoraSectionTitle(title = "Anexos")
                    }

                    when {
                        viewModel.uiState.isAttachmentsLoading && viewModel.uiState.attachments.isEmpty() -> {
                            item {
                                AncoraLoadingState(label = "Carregando anexos...")
                            }
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
                                    message = "Os anexos do processo aparecerão aqui.",
                                )
                            }
                        }

                        else -> {
                            items(viewModel.uiState.attachments, key = { attachment -> attachment.id }) { attachment ->
                                ProcessAttachmentCard(attachment = attachment)
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
}

@Composable
private fun ProcessSummaryCard(item: ProcessDetail) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(
                label = item.summary.status ?: "Em andamento",
                tone = processStatusTone(item.summary.status),
            )
            if (item.summary.isPrivate) {
                AncoraStatusChip(
                    label = "Privado",
                    tone = AncoraTone.Warning,
                )
            }
        }
        Text(
            text = item.summary.processNumber,
            style = MaterialTheme.typography.headlineSmall,
        )
        Text(
            text = item.summary.clientName,
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        item.summary.condominiumName?.let {
            Text(
                text = "Condomínio: $it",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.summary.className?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.summary.subjectName?.let {
            Text(
                text = "Assunto: $it",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.summary.responsibleLawyer?.let {
            Text(
                text = "Responsável: $it",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.summary.lastMovementDescription?.let {
            Text(
                text = "Última movimentação: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun ProcessMainInfoCard(item: ProcessDetail) {
    AncoraCard {
        AncoraSectionTitle(title = "Dados principais")
        ProcessDetailRow(label = "Abertura", value = item.openedAtBr)
        ProcessDetailRow(label = "Encerramento", value = item.closedAtBr)
        ProcessDetailRow(label = "Tipo de ação", value = item.actionType)
        ProcessDetailRow(label = "Órgão julgador", value = item.judgingBody)
        ProcessDetailRow(label = "Posição do cliente", value = item.clientPosition)
        ProcessDetailRow(label = "Posição contrária", value = item.adversePosition)
        ProcessDetailRow(label = "Probabilidade de êxito", value = item.winProbability)
        ProcessDetailRow(label = "Forma de encerramento", value = item.closureType)
    }
}

@Composable
private fun ProcessSidesCard(item: ProcessDetail) {
    AncoraCard {
        AncoraSectionTitle(title = "Partes principais")
        SideBlock(
            title = "Cliente",
            name = item.client.name,
            condominiumName = item.client.condominiumName,
            syndicName = item.client.syndicName,
        )
        SideBlock(
            title = "Parte contrária",
            name = item.adverse.name,
            condominiumName = item.adverse.condominiumName,
            syndicName = item.adverse.syndicName,
        )
    }
}

@Composable
private fun SideBlock(
    title: String,
    name: String,
    condominiumName: String?,
    syndicName: String?,
) {
    Column(verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.xs)) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium,
        )
        Text(
            text = name,
            style = MaterialTheme.typography.bodyLarge,
        )
        condominiumName?.let {
            Text(
                text = "Condomínio: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        syndicName?.let {
            Text(
                text = "Síndico: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun ProcessPartyCard(party: ProcessParty) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = party.name,
                style = MaterialTheme.typography.titleMedium,
            )
            AncoraStatusChip(
                label = party.sideLabel,
                tone = if (party.sideLabel.lowercase().contains("cliente")) {
                    AncoraTone.Brand
                } else {
                    AncoraTone.Info
                },
            )
        }
        Text(
            text = party.partyType.replaceFirstChar { char -> char.uppercase() },
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        party.document?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun ProcessMovementCard(movement: ProcessMovementItem) {
    AncoraCard(bordered = true) {
        Text(
            text = movement.description,
            style = MaterialTheme.typography.titleMedium,
        )
        Text(
            text = movement.phaseDateBr ?: "Sem data informada",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        movement.notes?.takeIf { it.isNotBlank() }?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        movement.legalOpinion?.takeIf { it.isNotBlank() }?.let {
            Text(
                text = "Parecer: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        movement.createdByName?.let {
            Text(
                text = "Registrado por $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        if (movement.attachments.isNotEmpty()) {
            Text(
                text = "${movement.attachments.size} anexo(s)",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun ProcessAttachmentCard(attachment: HubAttachment) {
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

@Composable
private fun ProcessDetailRow(label: String, value: String?) {
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
