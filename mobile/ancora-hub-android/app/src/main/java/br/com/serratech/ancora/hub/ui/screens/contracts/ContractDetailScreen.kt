package br.com.serratech.ancora.hub.ui.screens.contracts

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
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.utils.DocumentOpener
import br.com.serratech.ancora.hub.domain.model.ContractDetail
import br.com.serratech.ancora.hub.domain.model.ContractDocumentItem
import br.com.serratech.ancora.hub.domain.model.DownloadedDocument
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.screens.clients.ActionErrorDialog
import br.com.serratech.ancora.hub.ui.screens.clients.InfoLine
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

private data class ContractDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val item: ContractDetail? = null,
    val documents: List<ContractDocumentItem> = emptyList(),
)

private class ContractDetailViewModel(
    private val container: AppContainer,
    private val contractId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(ContractDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            val detailResult = runCatching { container.contractRepository.detail(contractId) }
            val documentsResult = runCatching { container.contractRepository.documents(contractId) }

            detailResult
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isLoading = false,
                        item = detail,
                        documents = documentsResult.getOrDefault(emptyList()),
                        error = documentsResult.exceptionOrNull()?.message,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar o contrato agora.",
                    )
                }
        }
    }

    fun downloadDocument(
        document: ContractDocumentItem,
        onResult: (Result<DownloadedDocument>) -> Unit,
    ) {
        viewModelScope.launch {
            onResult(
                runCatching {
                    container.contractRepository.downloadDocument(contractId, document)
                },
            )
        }
    }
}

@Composable
fun ContractDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    contractId: Long,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    var openingDocumentId by rememberSaveable { mutableStateOf(0L) }
    var actionError by rememberSaveable { mutableStateOf<String?>(null) }
    val viewModel: ContractDetailViewModel = viewModel(
        key = "contract-detail-$contractId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ContractDetailViewModel(container, contractId) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Contrato",
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
                AncoraLoadingState(label = "Carregando contrato...")
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
                    title = "Nenhum contrato encontrado",
                    message = "Esse contrato pode não estar mais disponível para o seu usuário.",
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
                                    tone = contractTone(item.summary.status),
                                )
                                if (item.summary.signaturePending) {
                                    AncoraStatusChip(
                                        label = "Assinaturas pendentes",
                                        tone = AncoraTone.Warning,
                                    )
                                }
                            }
                            Text(
                                text = item.summary.title,
                                style = MaterialTheme.typography.headlineSmall,
                            )
                            InfoLine(label = "Cliente", value = item.summary.clientName)
                            InfoLine(label = "Vigência", value = listOfNotNull(item.summary.startDateBr, item.summary.endDateBr).joinToString(" até ").takeIf { it.isNotBlank() })
                            InfoLine(label = "Valor", value = item.summary.valueLabel)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Dados principais")
                            InfoLine(label = "Código", value = item.summary.code)
                            InfoLine(label = "Objeto", value = item.summary.objectLabel)
                            InfoLine(label = "Categoria", value = item.summary.categoryName)
                            InfoLine(label = "Tipo", value = item.summary.type)
                            InfoLine(label = "Forma de pagamento", value = item.summary.paymentMethodLabel)
                            InfoLine(label = "Financeiro", value = item.financialAccountName)
                            InfoLine(label = "Condomínio", value = item.condominiumName)
                            InfoLine(label = "Síndico", value = item.syndicName)
                            InfoLine(label = "Responsável", value = item.responsibleName)
                            InfoLine(label = "Proposta vinculada", value = item.proposalCode)
                            InfoLine(label = "Processo vinculado", value = item.processNumber)
                            InfoLine(label = "Vigência", value = item.summary.endDateBr)
                            InfoLine(label = "Recorrência", value = item.recurrenceLabel)
                            InfoLine(label = "Tipo de cobrança", value = item.billingTypeLabel)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Resumo")
                            InfoLine(label = "Descrição", value = item.description)
                            InfoLine(label = "Prévia do conteúdo", value = item.contentExcerpt)
                        }
                    }

                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                            AncoraSectionTitle(title = "Documentos")
                            if (viewModel.uiState.documents.isEmpty()) {
                                AncoraEmptyState(
                                    title = "Nenhum documento encontrado",
                                    message = "Os documentos do contrato aparecerão aqui quando estiverem disponíveis.",
                                )
                            } else {
                                viewModel.uiState.documents.forEach { document ->
                                    ContractDocumentCard(
                                        document = document,
                                        isOpening = openingDocumentId == (document.referenceId ?: document.id.hashCode().toLong()),
                                        onOpen = {
                                            openingDocumentId = document.referenceId ?: document.id.hashCode().toLong()
                                            viewModel.downloadDocument(document) { result ->
                                                openingDocumentId = 0L
                                                result
                                                    .onSuccess { downloaded ->
                                                        DocumentOpener.open(context, downloaded)
                                                            .exceptionOrNull()
                                                            ?.let { actionError = it.message ?: "Não foi possível abrir o documento agora." }
                                                    }
                                                    .onFailure {
                                                        actionError = it.message ?: "Não foi possível abrir o documento agora."
                                                    }
                                            }
                                        },
                                    )
                                }
                            }
                        }
                    }

                    item {
                        item.latestSignature?.let { signature ->
                            AncoraCard {
                                AncoraSectionTitle(title = "Assinatura")
                                InfoLine(label = "Documento", value = signature.documentName)
                                InfoLine(label = "Status", value = signature.statusLabel)
                                InfoLine(label = "Origem", value = signature.sourceLabel)
                                InfoLine(label = "Pendências", value = signature.pendingCount.toString())
                            }
                        } ?: AncoraEmptyState(
                            title = "Nenhuma assinatura encontrada.",
                            message = "Quando houver fluxo de assinatura vinculado a este contrato, ele aparecerá aqui.",
                        )
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

@Composable
private fun ContractDocumentCard(
    document: ContractDocumentItem,
    isOpening: Boolean,
    onOpen: () -> Unit,
) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = document.name,
                style = MaterialTheme.typography.titleMedium,
            )
            AncoraStatusChip(
                label = document.kindLabel,
                tone = when (document.kind) {
                    "main" -> AncoraTone.Brand
                    "version" -> AncoraTone.Info
                    else -> AncoraTone.Neutral
                },
            )
        }
        InfoLine(label = "Descrição", value = document.description)
        InfoLine(label = "Tamanho", value = document.fileSizeLabel)
        InfoLine(label = "Enviado em", value = document.createdAtBr)
        AncoraGhostButton(
            text = if (isOpening) "Abrindo..." else "Ver detalhes",
            enabled = !isOpening,
            onClick = onOpen,
        )
    }
}
