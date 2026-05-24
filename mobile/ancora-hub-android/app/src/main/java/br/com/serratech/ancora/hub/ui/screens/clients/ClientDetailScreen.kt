package br.com.serratech.ancora.hub.ui.screens.clients

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
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.utils.DocumentOpener
import br.com.serratech.ancora.hub.domain.model.ClientDetail
import br.com.serratech.ancora.hub.domain.model.ClientDocument
import br.com.serratech.ancora.hub.domain.model.DownloadedDocument
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

private data class ClientDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val item: ClientDetail? = null,
)

private class ClientDetailViewModel(
    private val container: AppContainer,
    private val clientId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(ClientDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.clientRepository.clientDetail(clientId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isLoading = false,
                        item = detail,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar o cliente agora.",
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
fun ClientDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    clientId: Long,
    onOpenCondominium: (Long) -> Unit,
    onOpenUnit: (Long) -> Unit,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    var openingDocumentId by mutableLongStateOf(0L)
    var actionError by mutableStateOf<String?>(null)
    val viewModel: ClientDetailViewModel = viewModel(
        key = "client-detail-$clientId",
        factory = simpleFactory { ClientDetailViewModel(container, clientId) },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Cliente",
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
                AncoraLoadingState(label = "Carregando cliente...")
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
                    message = "Esse cliente pode não estar mais disponível para o seu usuário.",
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
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                AncoraStatusChip(
                                    label = item.summary.profileScopeLabel,
                                    tone = if (item.summary.profileScope == "avulso") AncoraTone.Info else AncoraTone.Brand,
                                )
                                AncoraStatusChip(
                                    label = item.summary.statusLabel,
                                    tone = if (item.summary.isActive) AncoraTone.Success else AncoraTone.Warning,
                                )
                            }
                            Text(
                                text = item.summary.name,
                                style = MaterialTheme.typography.headlineSmall,
                            )
                            item.summary.roleLabel?.let { InfoLine(label = "Perfil", value = it) }
                            InfoLine(label = "Tipo", value = item.summary.entityTypeLabel)
                            item.summary.document?.let { InfoLine(label = "Documento", value = it) }
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Dados básicos")
                            InfoLine(label = "Nome jurídico", value = item.summary.legalName)
                            InfoLine(label = "Profissão", value = item.profession)
                            InfoLine(label = "Nacionalidade", value = item.nationality)
                            InfoLine(label = "Estado civil", value = item.maritalStatus)
                            InfoLine(label = "Nascimento", value = item.birthDateBr)
                            InfoLine(label = "Contrato até", value = item.contractEndDateBr)
                            InfoLine(label = "Descrição", value = item.description)
                            InfoLine(label = "Observações", value = item.notes)
                            InfoLine(label = "Motivo da inativação", value = item.inactiveReason)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Contatos")
                            item.contacts.phones.forEach { InfoLine(it.label ?: "Telefone", it.value) }
                            item.contacts.emails.forEach { InfoLine(it.label ?: "E-mail", it.value) }
                            item.contacts.billingEmails.forEach { InfoLine(it.label ?: "E-mail de cobrança", it.value) }
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Endereço")
                            InfoLine(label = "Principal", value = item.addresses.primary.formatted)
                            InfoLine(label = "Endereço de cobrança", value = item.addresses.billing.formatted)
                        }
                    }

                    if (item.linkedCondominiums.isNotEmpty()) {
                        item {
                            Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                                AncoraSectionTitle(title = "Condomínios")
                                item.linkedCondominiums.forEach { condominium ->
                                    AncoraCard(bordered = true) {
                                        Text(
                                            text = condominium.name,
                                            style = MaterialTheme.typography.titleMedium,
                                        )
                                        InfoLine(label = "Síndico", value = condominium.syndicName)
                                        InfoLine(label = "Administradora", value = condominium.administratorName)
                                        InfoLine(
                                            label = "Localização",
                                            value = listOfNotNull(condominium.city, condominium.state)
                                                .joinToString(" - ")
                                                .takeIf { it.isNotBlank() },
                                        )
                                        br.com.serratech.ancora.hub.ui.components.AncoraGhostButton(
                                            text = "Ver condomínio",
                                            onClick = { onOpenCondominium(condominium.id) },
                                        )
                                    }
                                }
                            }
                        }
                    }

                    if (item.linkedUnits.isNotEmpty()) {
                        item {
                            Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                                AncoraSectionTitle(title = "Unidades")
                                item.linkedUnits.forEach { unit ->
                                    AncoraCard(bordered = true) {
                                        Text(
                                            text = unit.unitLabel,
                                            style = MaterialTheme.typography.titleMedium,
                                        )
                                        InfoLine(label = "Condomínio", value = unit.condominiumName)
                                        InfoLine(label = "Relação", value = unit.relationshipLabel)
                                        InfoLine(label = "Proprietário", value = unit.ownerName)
                                        InfoLine(label = "Locatário", value = unit.tenantName)
                                        br.com.serratech.ancora.hub.ui.components.AncoraGhostButton(
                                            text = "Ver unidade",
                                            onClick = { onOpenUnit(unit.id) },
                                        )
                                    }
                                }
                            }
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
                            title = "Observações",
                            items = item.timeline,
                            emptyTitle = "Nenhuma observação encontrada",
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
