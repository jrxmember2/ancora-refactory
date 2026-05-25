package br.com.serratech.ancora.hub.ui.screens.signatures

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
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
import br.com.serratech.ancora.hub.domain.model.SignatureDetail
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.screens.clients.ActionErrorDialog
import br.com.serratech.ancora.hub.ui.screens.clients.InfoLine
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

private data class SignatureDetailUiState(
    val isLoading: Boolean = true,
    val isSyncing: Boolean = false,
    val error: String? = null,
    val item: SignatureDetail? = null,
)

private class SignatureDetailViewModel(
    private val container: AppContainer,
    private val signatureId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(SignatureDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.signatureRepository.detail(signatureId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(isLoading = false, item = detail)
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar a assinatura agora.",
                    )
                }
        }
    }

    fun sync(onFailure: (String) -> Unit) {
        if (uiState.isSyncing) {
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(isSyncing = true)
            runCatching { container.signatureRepository.sync(signatureId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(isSyncing = false, item = detail, error = null)
                }
                .onFailure {
                    uiState = uiState.copy(isSyncing = false)
                    onFailure(it.message ?: "Não foi possível sincronizar a assinatura agora.")
                }
        }
    }
}

@Composable
fun SignatureDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    signatureId: Long,
    onBack: () -> Unit,
) {
    var actionError by rememberSaveable { mutableStateOf<String?>(null) }
    val viewModel: SignatureDetailViewModel = viewModel(
        key = "signature-detail-$signatureId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return SignatureDetailViewModel(container, signatureId) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Assinador Eletrônico",
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
                AncoraLoadingState(label = "Carregando assinatura...")
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
                    message = "Essa assinatura pode não estar mais disponível para o seu usuário.",
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
                                    tone = signatureTone(item.summary.status),
                                )
                                AncoraStatusChip(
                                    label = "${item.summary.pendingCount} pendências",
                                    tone = if (item.summary.pendingCount > 0) AncoraTone.Warning else AncoraTone.Success,
                                )
                            }
                            Text(
                                text = item.summary.documentName,
                                style = MaterialTheme.typography.headlineSmall,
                            )
                            InfoLine(label = "Origem", value = item.summary.sourceLabel)
                            InfoLine(label = "Criado em", value = item.summary.createdAtBr)
                            InfoLine(label = "Última atualização", value = item.summary.updatedAtBr)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Resumo")
                            InfoLine(label = "Status", value = item.summary.statusLabel)
                            InfoLine(label = "Solicitado em", value = item.requestedAtBr)
                            InfoLine(label = "Última sincronização", value = item.lastSyncedAtBr)
                            InfoLine(label = "Provedor", value = item.provider)
                            InfoLine(label = "Signatários", value = item.summary.signersCount.toString())
                            if (item.availableActions.canSync) {
                                AncoraSecondaryButton(
                                    text = if (viewModel.uiState.isSyncing) "Sincronizando..." else "Sincronizar",
                                    enabled = !viewModel.uiState.isSyncing,
                                    onClick = { viewModel.sync { actionError = it } },
                                )
                            }
                        }
                    }

                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                            AncoraSectionTitle(title = "Signatários")
                            if (item.signers.isEmpty()) {
                                AncoraEmptyState(
                                    title = "Nenhum signatário encontrado.",
                                    message = "Quando houver participantes vinculados a esta assinatura, eles aparecerão aqui.",
                                )
                            } else {
                                item.signers.forEach { signer ->
                                    AncoraCard(bordered = true) {
                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.SpaceBetween,
                                        ) {
                                            Text(
                                                text = signer.name,
                                                style = MaterialTheme.typography.titleMedium,
                                            )
                                            AncoraStatusChip(
                                                label = signer.statusLabel,
                                                tone = when (signer.status) {
                                                    "signed" -> AncoraTone.Success
                                                    "rejected" -> AncoraTone.Neutral
                                                    else -> AncoraTone.Warning
                                                },
                                            )
                                        }
                                        InfoLine(label = "E-mail", value = signer.email)
                                        InfoLine(label = "Telefone", value = signer.phone)
                                        InfoLine(label = "Função", value = signer.roleLabel)
                                        InfoLine(label = "Assinado em", value = signer.signedAtBr)
                                        InfoLine(label = "Recusado em", value = signer.rejectedAtBr)
                                    }
                                }
                            }
                        }
                    }

                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                            AncoraSectionTitle(title = "Histórico")
                            if (item.events.isEmpty()) {
                                AncoraEmptyState(
                                    title = "Nenhum evento encontrado.",
                                    message = "Os eventos de assinatura aparecerão aqui conforme o documento avançar.",
                                )
                            } else {
                                item.events.forEach { event ->
                                    AncoraCard(bordered = true) {
                                        Text(
                                            text = event.label,
                                            style = MaterialTheme.typography.titleMedium,
                                        )
                                        InfoLine(label = "Descrição", value = event.description)
                                        InfoLine(label = "Signatário", value = event.signerName)
                                        InfoLine(label = "Recebido em", value = event.receivedAtBr)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    actionError?.let { message ->
        ActionErrorDialog(
            message = message,
            onDismiss = { actionError = null },
        )
    }
}
