package br.com.serratech.ancora.hub.ui.screens.proposals

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
import br.com.serratech.ancora.hub.domain.model.ProposalDetail
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.screens.clients.InfoLine
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

private data class ProposalDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val item: ProposalDetail? = null,
)

private class ProposalDetailViewModel(
    private val container: AppContainer,
    private val proposalId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(ProposalDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.proposalRepository.detail(proposalId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(isLoading = false, item = detail)
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar a proposta agora.",
                    )
                }
        }
    }
}

@Composable
fun ProposalDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    proposalId: Long,
    onBack: () -> Unit,
) {
    val viewModel: ProposalDetailViewModel = viewModel(
        key = "proposal-detail-$proposalId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProposalDetailViewModel(container, proposalId) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Proposta",
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
                AncoraLoadingState(label = "Carregando proposta...")
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
                    title = "Nenhum registro encontrado.",
                    message = "Essa proposta pode não estar mais disponível para o seu usuário.",
                    actionLabel = "Voltar",
                    onAction = onBack,
                )
            }

            else -> {
                val item = viewModel.uiState.item!!
                val spacing = MaterialTheme.spacing

                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.lg),
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
                                    tone = proposalTone(item.summary.statusLabel),
                                )
                                item.summary.followupDateBr?.let {
                                    AncoraStatusChip(
                                        label = "Próximo follow-up: $it",
                                        tone = AncoraTone.Warning,
                                    )
                                }
                            }
                            Text(
                                text = item.summary.clientName,
                                style = MaterialTheme.typography.headlineSmall,
                            )
                            item.summary.serviceName?.let { InfoLine(label = "Serviço", value = it) }
                            InfoLine(label = "Valor", value = item.summary.proposalTotalLabel)
                            InfoLine(label = "Data da proposta", value = item.summary.proposalDateBr)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Dados da proposta")
                            InfoLine(label = "Código", value = item.summary.code)
                            InfoLine(label = "Administradora", value = item.administradoraName)
                            InfoLine(label = "Forma de envio", value = item.sendMethodName)
                            InfoLine(label = "Validade", value = item.validityDays?.let { "$it dias" })
                            InfoLine(label = "Valor fechado", value = item.summary.closedTotalLabel)
                            InfoLine(label = "Motivo da recusa", value = item.refusalReason)
                            InfoLine(label = "Observações", value = item.notes)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Cliente e contato")
                            InfoLine(label = "Solicitante", value = item.summary.requesterName)
                            InfoLine(label = "Telefone", value = item.requesterPhone)
                            InfoLine(label = "E-mail", value = item.contactEmail)
                            InfoLine(label = "Indicação", value = if (item.hasReferral) item.referralName ?: "Sim" else "Não")
                        }
                    }

                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                            AncoraSectionTitle(title = "Histórico")
                            if (item.history.isEmpty()) {
                                AncoraEmptyState(
                                    title = "Nenhum histórico encontrado.",
                                    message = "As atualizações desta proposta aparecerão aqui quando existirem registros.",
                                )
                            } else {
                                item.history.forEach { history ->
                                    AncoraCard(bordered = true) {
                                        Text(
                                            text = history.summary,
                                            style = MaterialTheme.typography.bodyLarge,
                                        )
                                        history.userEmail?.let { InfoLine(label = "Usuário", value = it) }
                                        InfoLine(label = "Data", value = history.createdAtBr)
                                    }
                                }
                            }
                        }
                    }

                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                            AncoraSectionTitle(title = "Anexos")
                            if (item.attachments.isEmpty()) {
                                AncoraEmptyState(
                                    title = "Nenhum anexo encontrado.",
                                    message = "Os anexos da proposta aparecerão aqui quando estiverem disponíveis.",
                                )
                            } else {
                                item.attachments.forEach { attachment ->
                                    AncoraCard(bordered = true) {
                                        Text(
                                            text = attachment.name,
                                            style = MaterialTheme.typography.titleMedium,
                                        )
                                        InfoLine(label = "Tipo", value = attachment.mimeType)
                                        InfoLine(label = "Tamanho", value = formatAttachmentSize(attachment.fileSize))
                                        InfoLine(label = "Enviado em", value = attachment.createdAtBr)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

private fun formatAttachmentSize(bytes: Int): String =
    when {
        bytes < 1024 -> "$bytes B"
        bytes < 1048576 -> "${"%.1f".format(bytes / 1024f)} KB"
        else -> "${"%.1f".format(bytes / 1048576f)} MB"
    }
