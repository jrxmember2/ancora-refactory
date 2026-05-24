package br.com.serratech.ancora.hub.ui.screens.demands

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.DemandDetail
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.HubAttachment
import br.com.serratech.ancora.hub.domain.model.HubUserOption
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
import kotlinx.coroutines.launch

data class DemandDetailUiState(
    val isLoading: Boolean = true,
    val isSaving: Boolean = false,
    val error: String? = null,
    val item: DemandDetail? = null,
)

class DemandDetailViewModel(
    private val container: AppContainer,
    private val demandId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(DemandDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.demandRepository.detail(demandId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isLoading = false,
                        item = detail,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar a demanda agora.",
                    )
                }
        }
    }

    fun reply(message: String, onDone: () -> Unit) {
        if (message.isBlank()) {
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(isSaving = true, error = null)
            runCatching { container.demandRepository.reply(demandId, message) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isSaving = false,
                        item = detail,
                    )
                    onDone()
                }
                .onFailure {
                    uiState = uiState.copy(
                        isSaving = false,
                        error = it.message ?: "Não foi possível registrar a resposta agora.",
                    )
                }
        }
    }

    fun updateStatus(status: String, onDone: () -> Unit) {
        viewModelScope.launch {
            uiState = uiState.copy(isSaving = true, error = null)
            runCatching { container.demandRepository.updateStatus(demandId, status) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isSaving = false,
                        item = detail,
                    )
                    onDone()
                }
                .onFailure {
                    uiState = uiState.copy(
                        isSaving = false,
                        error = it.message ?: "Não foi possível atualizar o status agora.",
                    )
                }
        }
    }

    fun assign(userId: Long, onDone: () -> Unit) {
        viewModelScope.launch {
            uiState = uiState.copy(isSaving = true, error = null)
            runCatching { container.demandRepository.assign(demandId, userId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isSaving = false,
                        item = detail,
                    )
                    onDone()
                }
                .onFailure {
                    uiState = uiState.copy(
                        isSaving = false,
                        error = it.message ?: "Não foi possível atualizar o responsável agora.",
                    )
                }
        }
    }
}

@Composable
fun DemandDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    demandId: Long,
    onBack: () -> Unit,
) {
    var showReplyDialog by mutableStateOf(false)
    var showStatusDialog by mutableStateOf(false)
    var showAssignDialog by mutableStateOf(false)
    val spacing = MaterialTheme.spacing
    val viewModel: DemandDetailViewModel = viewModel(
        key = "demand-detail-$demandId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return DemandDetailViewModel(container, demandId) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Demanda",
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
                AncoraLoadingState(label = "Carregando demanda...")
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
                    title = "Nenhuma demanda encontrada",
                    message = "Essa demanda pode não estar mais disponível para o seu usuário.",
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
                        AncoraCard(bordered = true) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                AncoraStatusChip(
                                    label = item.summary.statusLabel,
                                    tone = demandStatusTone(item.summary.status),
                                )
                                AncoraStatusChip(
                                    label = item.summary.priorityLabel,
                                    tone = demandPriorityTone(item.summary.priority),
                                )
                            }
                            Text(
                                text = item.summary.title,
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
                            item.summary.assignee?.let {
                                Text(
                                    text = "Responsável: ${it.name}",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            AncoraStatusChip(
                                label = item.summary.sla.label,
                                tone = demandSlaTone(item.summary.sla.status),
                            )
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Descrição")
                            Text(
                                text = item.description.ifBlank { "Sem descrição informada." },
                                style = MaterialTheme.typography.bodyLarge,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Solicitante")
                            Text(
                                text = item.requester.name,
                                style = MaterialTheme.typography.titleMedium,
                            )
                            item.requester.email?.let {
                                Text(
                                    text = it,
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            Text(
                                text = "Atualizada em ${item.summary.updatedAtBr ?: item.summary.createdAtBr ?: "agora mesmo"}",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }

                    if (
                        item.availableActions.canReply ||
                        item.availableActions.canUpdateStatus ||
                        item.availableActions.canAssign
                    ) {
                        item {
                            AncoraCard {
                                AncoraSectionTitle(title = "Ações rápidas")
                                if (item.availableActions.canReply) {
                                    AncoraButton(
                                        text = "Responder",
                                        enabled = !viewModel.uiState.isSaving,
                                        onClick = { showReplyDialog = true },
                                    )
                                }
                                if (item.availableActions.canUpdateStatus) {
                                    AncoraSecondaryButton(
                                        text = "Atualizar status",
                                        enabled = !viewModel.uiState.isSaving,
                                        onClick = { showStatusDialog = true },
                                    )
                                }
                                if (item.availableActions.canAssign) {
                                    AncoraSecondaryButton(
                                        text = "Alterar responsável",
                                        enabled = !viewModel.uiState.isSaving,
                                        onClick = { showAssignDialog = true },
                                    )
                                }
                            }
                        }
                    }

                    item {
                        AncoraSectionTitle(title = "Histórico")
                    }

                    if (item.messages.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhum histórico disponível",
                                message = "As respostas e movimentações desta demanda aparecerão aqui.",
                            )
                        }
                    } else {
                        items(item.messages, key = { message -> message.id }) { message ->
                            AncoraCard(bordered = message.isInternal) {
                                AncoraStatusChip(
                                    label = if (message.isInternal) "Interno" else "Portal",
                                    tone = if (message.isInternal) AncoraTone.Brand else AncoraTone.Info,
                                )
                                Text(
                                    text = message.senderName,
                                    style = MaterialTheme.typography.titleMedium,
                                )
                                Text(
                                    text = message.message,
                                    style = MaterialTheme.typography.bodyLarge,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                                Text(
                                    text = message.createdAtBr ?: "Agora mesmo",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                                if (message.attachments.isNotEmpty()) {
                                    AttachmentSection(
                                        title = "Anexos da resposta",
                                        attachments = message.attachments,
                                    )
                                }
                            }
                        }
                    }

                    if (item.attachments.isNotEmpty()) {
                        item {
                            AttachmentSection(
                                title = "Anexos",
                                attachments = item.attachments,
                            )
                        }
                    }
                }
            }
        }
    }

    val detail = viewModel.uiState.item
    if (showReplyDialog && detail != null) {
        ReplyDemandDialog(
            isSaving = viewModel.uiState.isSaving,
            onDismiss = { showReplyDialog = false },
            onSubmit = { message ->
                viewModel.reply(message) {
                    showReplyDialog = false
                }
            },
        )
    }

    if (showStatusDialog && detail != null) {
        SelectStatusDialog(
            title = "Atualizar status",
            options = detail.statusOptions,
            currentValue = detail.summary.status,
            isSaving = viewModel.uiState.isSaving,
            onDismiss = { showStatusDialog = false },
            onConfirm = { value ->
                viewModel.updateStatus(value) {
                    showStatusDialog = false
                }
            },
        )
    }

    if (showAssignDialog && detail != null) {
        SelectAssigneeDialog(
            assignees = detail.assignees,
            currentUserId = detail.summary.assignee?.id,
            isSaving = viewModel.uiState.isSaving,
            onDismiss = { showAssignDialog = false },
            onConfirm = { value ->
                viewModel.assign(value) {
                    showAssignDialog = false
                }
            },
        )
    }
}

@Composable
private fun AttachmentSection(
    title: String,
    attachments: List<HubAttachment>,
) {
    Column(
        verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.sm),
    ) {
        AncoraSectionTitle(title = title)
        attachments.forEach { attachment ->
            AncoraCard(bordered = true) {
                Text(
                    text = attachment.name,
                    style = MaterialTheme.typography.titleSmall,
                )
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
    }
}

@Composable
private fun ReplyDemandDialog(
    isSaving: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (String) -> Unit,
) {
    var message by mutableStateOf("")

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Responder") },
        text = {
            AncoraTextField(
                value = message,
                onValueChange = { message = it },
                label = "Mensagem",
                singleLine = false,
                placeholder = "Escreva sua resposta",
            )
        },
        confirmButton = {
            AncoraGhostButton(
                text = if (isSaving) "Enviando..." else "Enviar",
                enabled = message.isNotBlank() && !isSaving,
                onClick = { onSubmit(message.trim()) },
            )
        },
        dismissButton = {
            AncoraGhostButton(
                text = "Cancelar",
                enabled = !isSaving,
                onClick = onDismiss,
            )
        },
    )
}

@Composable
private fun SelectStatusDialog(
    title: String,
    options: List<FilterValueOption>,
    currentValue: String,
    isSaving: Boolean,
    onDismiss: () -> Unit,
    onConfirm: (String) -> Unit,
) {
    var selectedValue by mutableStateOf(currentValue)

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(title) },
        text = {
            Column(
                modifier = Modifier.heightIn(max = 360.dp),
                verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.sm),
            ) {
                options.forEach { option ->
                    FilterChip(
                        selected = selectedValue == option.value,
                        onClick = { selectedValue = option.value },
                        label = { Text(option.label) },
                    )
                }
            }
        },
        confirmButton = {
            AncoraGhostButton(
                text = if (isSaving) "Salvando..." else "Salvar",
                enabled = !isSaving,
                onClick = { onConfirm(selectedValue) },
            )
        },
        dismissButton = {
            AncoraGhostButton(
                text = "Cancelar",
                enabled = !isSaving,
                onClick = onDismiss,
            )
        },
    )
}

@Composable
private fun SelectAssigneeDialog(
    assignees: List<HubUserOption>,
    currentUserId: Long?,
    isSaving: Boolean,
    onDismiss: () -> Unit,
    onConfirm: (Long) -> Unit,
) {
    var selectedUserId by mutableStateOf(currentUserId ?: assignees.firstOrNull()?.id ?: 0L)

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Alterar responsável") },
        text = {
            Column(
                modifier = Modifier.heightIn(max = 360.dp),
                verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.sm),
            ) {
                assignees.forEach { assignee ->
                    FilterChip(
                        selected = selectedUserId == assignee.id,
                        onClick = { selectedUserId = assignee.id },
                        label = { Text(assignee.name) },
                    )
                }
            }
        },
        confirmButton = {
            AncoraGhostButton(
                text = if (isSaving) "Salvando..." else "Salvar",
                enabled = !isSaving && selectedUserId > 0L,
                onClick = { onConfirm(selectedUserId) },
            )
        },
        dismissButton = {
            AncoraGhostButton(
                text = "Cancelar",
                enabled = !isSaving,
                onClick = onDismiss,
            )
        },
    )
}

private fun demandStatusTone(status: String): AncoraTone = when (status) {
    "concluida" -> AncoraTone.Success
    "cancelada" -> AncoraTone.Neutral
    "aguardando_cliente" -> AncoraTone.Warning
    "em_triagem" -> AncoraTone.Info
    else -> AncoraTone.Brand
}

private fun demandPriorityTone(priority: String): AncoraTone = when (priority) {
    "urgente" -> AncoraTone.Error
    "alta" -> AncoraTone.Warning
    "baixa" -> AncoraTone.Neutral
    else -> AncoraTone.Info
}

private fun demandSlaTone(status: String): AncoraTone = when (status) {
    "overdue" -> AncoraTone.Error
    "at_risk" -> AncoraTone.Warning
    "ok" -> AncoraTone.Success
    else -> AncoraTone.Neutral
}
