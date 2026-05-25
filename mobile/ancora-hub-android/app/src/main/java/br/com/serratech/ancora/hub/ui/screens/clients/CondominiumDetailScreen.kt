package br.com.serratech.ancora.hub.ui.screens.clients

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
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.utils.ContactActionLauncher
import br.com.serratech.ancora.hub.core.utils.DocumentOpener
import br.com.serratech.ancora.hub.domain.model.ClientDocument
import br.com.serratech.ancora.hub.domain.model.CondominiumDetail
import br.com.serratech.ancora.hub.domain.model.DownloadedDocument
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

private data class CondominiumDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val item: CondominiumDetail? = null,
)

private class CondominiumDetailViewModel(
    private val container: AppContainer,
    private val condominiumId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(CondominiumDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.clientRepository.condominiumDetail(condominiumId) }
                .onSuccess { detail ->
                    uiState = uiState.copy(
                        isLoading = false,
                        item = detail,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar o condomínio agora.",
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
fun CondominiumDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    condominiumId: Long,
    onOpenUnit: (Long) -> Unit,
    onOpenUnits: (Long) -> Unit,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    var openingDocumentId by rememberSaveable { mutableStateOf(0L) }
    var actionError by rememberSaveable { mutableStateOf<String?>(null) }
    val viewModel: CondominiumDetailViewModel = viewModel(
        key = "condominium-detail-$condominiumId",
        factory = simpleFactory { CondominiumDetailViewModel(container, condominiumId) },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Condomínio",
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
                AncoraLoadingState(label = "Carregando condomínio...")
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
                    message = "Esse condomínio pode não estar mais disponível para o seu usuário.",
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
                                    label = item.summary.statusLabel,
                                    tone = if (item.summary.isActive) AncoraTone.Success else AncoraTone.Warning,
                                )
                                AncoraStatusChip(
                                    label = "${item.summary.unitsCount} unidade(s)",
                                    tone = AncoraTone.Info,
                                )
                            }
                            Text(
                                text = item.summary.name,
                                style = MaterialTheme.typography.headlineSmall,
                            )
                            InfoLine(label = "Síndico", value = item.summary.syndicName)
                            InfoLine(label = "Administradora", value = item.summary.administratorName)
                            InfoLine(label = "CNPJ", value = item.summary.cnpj)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Dados principais")
                            InfoLine(label = "Tipo", value = item.summary.typeName)
                            InfoLine(label = "Endereço", value = item.address.formatted)
                            InfoLine(label = "Características", value = item.characteristics)
                            InfoLine(label = "Dados bancários", value = item.bankDetails)
                            InfoLine(label = "Contrato até", value = item.summary.contractEndDateBr)
                            InfoLine(label = "Motivo da inativação", value = item.inactiveReason)
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Contatos")
                            item.contacts.phones.forEach { contact ->
                                InfoLine(
                                    label = listOfNotNull(contact.sourceLabel, contact.label).joinToString(" • ").ifBlank { "Telefone" },
                                    value = contact.value,
                                )
                            }
                            item.contacts.emails.forEach { contact ->
                                InfoLine(
                                    label = listOfNotNull(contact.sourceLabel, contact.label).joinToString(" • ").ifBlank { "E-mail" },
                                    value = contact.value,
                                )
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                            ) {
                                item.quickActions.phone?.let { phone ->
                                    AncoraGhostButton(
                                        text = "Ligar",
                                        onClick = {
                                            ContactActionLauncher.dial(context, phone)
                                                .exceptionOrNull()
                                                ?.let { actionError = it.message ?: "Não foi possível iniciar a ligação." }
                                        },
                                    )
                                }
                                item.quickActions.email?.let { email ->
                                    AncoraGhostButton(
                                        text = "Enviar e-mail",
                                        onClick = {
                                            ContactActionLauncher.email(context, email)
                                                .exceptionOrNull()
                                                ?.let { actionError = it.message ?: "Não foi possível abrir o e-mail." }
                                        },
                                    )
                                }
                                item.quickActions.whatsapp?.let { whatsapp ->
                                    AncoraGhostButton(
                                        text = "WhatsApp",
                                        onClick = {
                                            ContactActionLauncher.whatsapp(context, whatsapp)
                                                .exceptionOrNull()
                                                ?.let { actionError = it.message ?: "O WhatsApp não está disponível neste aparelho." }
                                        },
                                    )
                                }
                            }
                        }
                    }

                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                            AncoraSectionTitle(
                                title = "Unidades",
                                trailing = {
                                    AncoraGhostButton(
                                        text = "Ver todas",
                                        onClick = { onOpenUnits(item.summary.id) },
                                    )
                                },
                            )
                            if (item.units.isEmpty()) {
                                AncoraEmptyState(
                                    title = "Nenhuma unidade encontrada",
                                    message = "As unidades deste condomínio aparecerão aqui quando estiverem cadastradas.",
                                )
                            } else {
                                item.units.forEach { unit ->
                                    AncoraCard(bordered = true) {
                                        Text(
                                            text = unit.unitLabel,
                                            style = MaterialTheme.typography.titleMedium,
                                        )
                                        InfoLine(label = "Proprietário", value = unit.ownerName)
                                        InfoLine(label = "Locatário", value = unit.tenantName)
                                        AncoraGhostButton(
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
