package br.com.serratech.ancora.hub.ui.screens.clients

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
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
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.utils.DocumentOpener
import br.com.serratech.ancora.hub.domain.model.ClientDocument
import br.com.serratech.ancora.hub.domain.model.DownloadedDocument
import br.com.serratech.ancora.hub.domain.model.UnitDetail
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

private data class UnitDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val item: UnitDetail? = null,
)

private class UnitDetailViewModel(
    private val container: AppContainer,
    private val unitId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(UnitDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.clientRepository.unitDetail(unitId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isLoading = false,
                        item = detail,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar a unidade agora.",
                    )
                }
        }
    }

    fun downloadDocument(
        document: ClientDocument,
        onResult: (Result<DownloadedDocument>) -> Unit,
    ) {
        viewModelScope.launch {
            onResult(
                runCatching {
                    container.clientRepository.downloadDocument(document.id, document.name)
                },
            )
        }
    }
}

@Composable
fun UnitDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    unitId: Long,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    var openingDocumentId by mutableLongStateOf(0L)
    var actionError by mutableStateOf<String?>(null)
    val viewModel: UnitDetailViewModel = viewModel(
        key = "unit-detail-$unitId",
        factory = simpleFactory { UnitDetailViewModel(container, unitId) },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Unidade",
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
                AncoraLoadingState(label = "Carregando unidade...")
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
                    message = "Essa unidade pode não estar mais disponível para o seu usuário.",
                    actionLabel = "Voltar",
                    onAction = onBack,
                )
            }

            else -> {
                val item = viewModel.uiState.item!!
                val spacing = MaterialTheme.spacing

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
                            Text(
                                text = item.summary.unitLabel,
                                style = MaterialTheme.typography.headlineSmall,
                            )
                            InfoLine(label = "Condomínio", value = item.summary.condominiumName)
                            InfoLine(label = "Bloco ou torre", value = item.summary.blockName)
                            InfoLine(label = "Tipo", value = item.summary.typeName)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Contatos")
                            InfoLine(label = "Proprietário", value = item.owner?.name)
                            item.contacts.ownerPhones.forEach { InfoLine(it.label ?: "Telefone do proprietário", it.value) }
                            item.contacts.ownerEmails.forEach { InfoLine(it.label ?: "E-mail do proprietário", it.value) }
                            InfoLine(label = "Locatário", value = item.tenant?.name)
                            item.contacts.tenantPhones.forEach { InfoLine(it.label ?: "Telefone do locatário", it.value) }
                            item.contacts.tenantEmails.forEach { InfoLine(it.label ?: "E-mail do locatário", it.value) }
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Endereço de cobrança")
                            InfoLine(label = "Endereço", value = item.billingAddress.formatted)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Observações")
                            InfoLine(label = "Observações do proprietário", value = item.ownerNotes)
                            InfoLine(label = "Observações do locatário", value = item.tenantNotes)
                        }
                    }

                    item {
                        DocumentGroupsSection(
                            title = "Documentos",
                            groups = item.documentGroups,
                            openingDocumentId = openingDocumentId.takeIf { it > 0L },
                            emptyTitle = "Nenhum documento encontrado",
                            onOpenDocument = { document ->
                                openingDocumentId = document.id
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

                    item {
                        TimelineSection(
                            title = "Histórico",
                            items = item.timeline,
                            emptyTitle = "Nenhum histórico encontrado",
                        )
                    }

                    if (item.partyHistory.isNotEmpty()) {
                        item {
                            Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                                AncoraSectionTitle(title = "Movimentação de partes")
                                item.partyHistory.forEach { history ->
                                    AncoraCard(bordered = true) {
                                        Text(
                                            text = history.name,
                                            style = MaterialTheme.typography.titleMedium,
                                        )
                                        InfoLine(label = "Papel", value = history.partyTypeLabel)
                                        InfoLine(label = "Início", value = history.startedAtBr)
                                        InfoLine(label = "Fim", value = history.endedAtBr)
                                        InfoLine(label = "Alterado por", value = history.changedByName)
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
