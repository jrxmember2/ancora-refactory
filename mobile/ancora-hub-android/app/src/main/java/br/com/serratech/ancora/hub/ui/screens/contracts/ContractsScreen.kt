package br.com.serratech.ancora.hub.ui.screens.contracts

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.FilterList
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
import br.com.serratech.ancora.hub.domain.model.ContractListItem
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSearchBar
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class ContractsUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<ContractListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: List<FilterValueOption> = emptyList(),
    val query: String = "",
    val status: String? = null,
)

class ContractsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(ContractsUiState())
        private set

    init {
        refresh()
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun updateStatus(value: String?) {
        uiState = uiState.copy(status = value)
    }

    fun refresh() {
        viewModelScope.launch { loadPage(1, append = false) }
    }

    fun clearFilters() {
        uiState = uiState.copy(status = null)
    }

    fun loadNextPage() {
        val meta = uiState.meta ?: return
        if (uiState.isLoadingMore || !meta.hasMore) {
            return
        }

        viewModelScope.launch { loadPage(meta.currentPage + 1, append = true) }
    }

    private suspend fun loadPage(page: Int, append: Boolean) {
        uiState = uiState.copy(
            isLoading = !append,
            isLoadingMore = append,
            error = if (append) uiState.error else null,
        )

        runCatching {
            container.contractRepository.list(
                page = page,
                status = uiState.status,
                query = uiState.query,
            )
        }.onSuccess { data ->
            uiState = uiState.copy(
                isLoading = false,
                isLoadingMore = false,
                error = null,
                items = if (append) uiState.items + data.items else data.items,
                meta = data.meta,
                filters = data.filters,
            )
        }.onFailure {
            uiState = uiState.copy(
                isLoading = false,
                isLoadingMore = false,
                error = it.message ?: "Não foi possível carregar os contratos agora.",
            )
        }
    }
}

@Composable
fun ContractsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenContract: (Long) -> Unit,
    onBack: () -> Unit,
) {
    var showFilters by rememberSaveable { mutableStateOf(false) }
    val viewModel: ContractsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ContractsViewModel(container) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Contratos",
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                        contentDescription = "Voltar",
                    )
                }
            },
            actions = {
                IconButton(onClick = { showFilters = true }) {
                    Icon(
                        imageVector = Icons.Outlined.FilterList,
                        contentDescription = "Filtrar",
                    )
                }
            },
        )

        when {
            viewModel.uiState.isLoading && viewModel.uiState.items.isEmpty() -> {
                AncoraLoadingState(label = "Carregando contratos...")
            }

            viewModel.uiState.error != null && viewModel.uiState.items.isEmpty() -> {
                AncoraErrorState(
                    title = "Não foi possível carregar as informações.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            else -> {
                ContractsContent(
                    uiState = viewModel.uiState,
                    onQueryChange = viewModel::updateQuery,
                    onSearch = viewModel::refresh,
                    onClearSearch = {
                        viewModel.updateQuery("")
                        viewModel.refresh()
                    },
                    onOpenContract = onOpenContract,
                    onLoadMore = viewModel::loadNextPage,
                )
            }
        }
    }

    if (showFilters) {
        ContractFiltersSheet(
            uiState = viewModel.uiState,
            onDismiss = { showFilters = false },
            onStatusChange = viewModel::updateStatus,
            onApply = {
                showFilters = false
                viewModel.refresh()
            },
            onClear = {
                viewModel.clearFilters()
                showFilters = false
                viewModel.refresh()
            },
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ContractsContent(
    uiState: ContractsUiState,
    onQueryChange: (String) -> Unit,
    onSearch: () -> Unit,
    onClearSearch: () -> Unit,
    onOpenContract: (Long) -> Unit,
    onLoadMore: () -> Unit,
) {
    val spacing = MaterialTheme.spacing

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.lg),
        verticalArrangement = Arrangement.spacedBy(spacing.md),
    ) {
        item {
            AncoraCard(bordered = true) {
                AncoraSearchBar(
                    query = uiState.query,
                    onQueryChange = onQueryChange,
                    placeholder = "Buscar por cliente, contrato ou serviço",
                )
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    AncoraGhostButton(
                        text = "Limpar busca",
                        enabled = uiState.query.isNotBlank(),
                        onClick = onClearSearch,
                    )
                    AncoraGhostButton(
                        text = "Buscar",
                        onClick = onSearch,
                    )
                }

                uiState.filters.firstOrNull { it.value == uiState.status }?.let { option ->
                    FlowRow {
                        AncoraStatusChip(label = option.label, tone = contractTone(option.value))
                    }
                }
            }
        }

        if (uiState.items.isEmpty()) {
            item {
                AncoraEmptyState(
                    title = "Nenhum contrato encontrado",
                    message = "Tente ajustar a busca ou os filtros para localizar o contrato desejado.",
                )
            }
        } else {
            items(uiState.items, key = { it.id }) { item ->
                ContractCard(item = item, onClick = { onOpenContract(item.id) })
            }
        }

        if (uiState.meta?.hasMore == true) {
            item {
                AncoraSecondaryButton(
                    text = if (uiState.isLoadingMore) "Carregando..." else "Carregar mais",
                    enabled = !uiState.isLoadingMore,
                    onClick = onLoadMore,
                )
            }
        }
    }
}

@Composable
private fun ContractCard(
    item: ContractListItem,
    onClick: () -> Unit,
) {
    AncoraCard(
        bordered = true,
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(
                label = item.statusLabel,
                tone = contractTone(item.status),
            )
            if (item.signaturePending) {
                AncoraStatusChip(
                    label = "Assinaturas pendentes",
                    tone = AncoraTone.Warning,
                )
            }
        }
        Text(
            text = item.title,
            style = MaterialTheme.typography.titleMedium,
        )
        Text(
            text = item.clientName,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = item.valueLabel ?: "Valor não informado",
                style = MaterialTheme.typography.bodyLarge,
            )
            Text(
                text = item.endDateBr ?: "Sem vigência final",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.paymentMethodLabel?.let {
            Text(
                text = "Pagamento: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun ContractFiltersSheet(
    uiState: ContractsUiState,
    onDismiss: () -> Unit,
    onStatusChange: (String?) -> Unit,
    onApply: () -> Unit,
    onClear: () -> Unit,
) {
    val spacing = MaterialTheme.spacing

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(
            modifier = Modifier.padding(horizontal = spacing.lg, vertical = spacing.md),
            verticalArrangement = Arrangement.spacedBy(spacing.lg),
        ) {
            Text(
                text = "Filtrar contratos",
                style = MaterialTheme.typography.headlineSmall,
            )

            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                verticalArrangement = Arrangement.spacedBy(spacing.sm),
            ) {
                FilterChip(
                    selected = uiState.status == null,
                    onClick = { onStatusChange(null) },
                    label = { Text("Todos") },
                )
                uiState.filters.forEach { option ->
                    FilterChip(
                        selected = uiState.status == option.value,
                        onClick = { onStatusChange(option.value) },
                        label = { Text(option.label) },
                    )
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                AncoraGhostButton(text = "Limpar", onClick = onClear)
                AncoraSecondaryButton(text = "Aplicar", onClick = onApply)
            }
        }
    }
}

internal fun contractTone(value: String): AncoraTone =
    when (value.lowercase()) {
        "ativo", "assinado" -> AncoraTone.Success
        "aguardando_assinatura", "pendente", "vencido" -> AncoraTone.Warning
        "rescindido", "cancelado", "arquivado" -> AncoraTone.Neutral
        else -> AncoraTone.Brand
    }
