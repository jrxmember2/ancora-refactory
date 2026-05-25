package br.com.serratech.ancora.hub.ui.screens.proposals

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
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.ProposalFilters
import br.com.serratech.ancora.hub.domain.model.ProposalListItem
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

data class ProposalsUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<ProposalListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: ProposalFilters = ProposalFilters(emptyList(), emptyList()),
    val query: String = "",
    val statusId: Long? = null,
    val serviceId: Long? = null,
)

class ProposalsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(ProposalsUiState())
        private set

    init {
        refresh()
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun updateStatusId(value: Long?) {
        uiState = uiState.copy(statusId = value)
    }

    fun updateServiceId(value: Long?) {
        uiState = uiState.copy(serviceId = value)
    }

    fun clearFilters() {
        uiState = uiState.copy(statusId = null, serviceId = null)
    }

    fun refresh() {
        viewModelScope.launch { loadPage(1, append = false) }
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
            container.proposalRepository.list(
                page = page,
                statusId = uiState.statusId,
                serviceId = uiState.serviceId,
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
                error = it.message ?: "Não foi possível carregar as propostas agora.",
            )
        }
    }
}

@Composable
fun ProposalsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenProposal: (Long) -> Unit,
    onBack: () -> Unit,
) {
    var showFilters by rememberSaveable { mutableStateOf(false) }
    val viewModel: ProposalsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProposalsViewModel(container) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Propostas",
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
                AncoraLoadingState(label = "Carregando propostas...")
            }

            viewModel.uiState.error != null && viewModel.uiState.items.isEmpty() -> {
                AncoraErrorState(
                    title = "Não foi possível carregar as informações.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            else -> {
                ProposalsContent(
                    uiState = viewModel.uiState,
                    onQueryChange = viewModel::updateQuery,
                    onSearch = viewModel::refresh,
                    onClearSearch = {
                        viewModel.updateQuery("")
                        viewModel.refresh()
                    },
                    onOpenProposal = onOpenProposal,
                    onLoadMore = viewModel::loadNextPage,
                )
            }
        }
    }

    if (showFilters) {
        ProposalFiltersSheet(
            uiState = viewModel.uiState,
            onDismiss = { showFilters = false },
            onStatusChange = viewModel::updateStatusId,
            onServiceChange = viewModel::updateServiceId,
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
private fun ProposalsContent(
    uiState: ProposalsUiState,
    onQueryChange: (String) -> Unit,
    onSearch: () -> Unit,
    onClearSearch: () -> Unit,
    onOpenProposal: (Long) -> Unit,
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
                    placeholder = "Buscar por cliente, serviço ou status",
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

                val selected = buildList {
                    uiState.filters.statuses.firstOrNull { it.value.toLongOrNull() == uiState.statusId }?.let(::add)
                    uiState.filters.services.firstOrNull { it.value.toLongOrNull() == uiState.serviceId }?.let(::add)
                }

                if (selected.isNotEmpty()) {
                    FlowRow(
                        horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                        verticalArrangement = Arrangement.spacedBy(spacing.sm),
                    ) {
                        selected.forEach { option ->
                            AncoraStatusChip(label = option.label, tone = AncoraTone.Info)
                        }
                    }
                }
            }
        }

        if (uiState.items.isEmpty()) {
            item {
                AncoraEmptyState(
                    title = "Nenhuma proposta encontrada",
                    message = "Tente ajustar a busca ou os filtros para localizar a proposta desejada.",
                )
            }
        } else {
            items(uiState.items, key = { it.id }) { item ->
                ProposalCard(
                    item = item,
                    onClick = { onOpenProposal(item.id) },
                )
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
private fun ProposalCard(
    item: ProposalListItem,
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
                tone = proposalTone(item.statusLabel),
            )
            item.followupDateBr?.let {
                AncoraStatusChip(
                    label = "Próximo follow-up: $it",
                    tone = AncoraTone.Warning,
                )
            }
        }
        Text(
            text = item.clientName,
            style = MaterialTheme.typography.titleMedium,
        )
        item.serviceName?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = item.proposalTotalLabel ?: "Valor não informado",
                style = MaterialTheme.typography.bodyLarge,
            )
            Text(
                text = item.proposalDateBr ?: "Sem data",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.requesterName?.let {
            Text(
                text = "Contato: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun ProposalFiltersSheet(
    uiState: ProposalsUiState,
    onDismiss: () -> Unit,
    onStatusChange: (Long?) -> Unit,
    onServiceChange: (Long?) -> Unit,
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
                text = "Filtrar propostas",
                style = MaterialTheme.typography.headlineSmall,
            )

            ProposalFilterGroup(
                title = "Status",
                options = uiState.filters.statuses,
                selectedValue = uiState.statusId?.toString(),
                onSelected = { onStatusChange(it?.toLongOrNull()) },
            )

            ProposalFilterGroup(
                title = "Serviço",
                options = uiState.filters.services,
                selectedValue = uiState.serviceId?.toString(),
                onSelected = { onServiceChange(it?.toLongOrNull()) },
            )

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

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ProposalFilterGroup(
    title: String,
    options: List<FilterValueOption>,
    selectedValue: String?,
    onSelected: (String?) -> Unit,
) {
    val spacing = MaterialTheme.spacing
    Column(verticalArrangement = Arrangement.spacedBy(spacing.sm)) {
        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium,
        )
        FlowRow(
            horizontalArrangement = Arrangement.spacedBy(spacing.sm),
            verticalArrangement = Arrangement.spacedBy(spacing.sm),
        ) {
            FilterChip(
                selected = selectedValue == null,
                onClick = { onSelected(null) },
                label = { Text("Todas") },
            )
            options.forEach { option ->
                FilterChip(
                    selected = selectedValue == option.value,
                    onClick = { onSelected(option.value) },
                    label = { Text(option.label) },
                )
            }
        }
    }
}

internal fun proposalTone(label: String): AncoraTone {
    val normalized = label.lowercase()
    return when {
        normalized.contains("fech") || normalized.contains("assinado") -> AncoraTone.Success
        normalized.contains("pend") || normalized.contains("aguard") -> AncoraTone.Warning
        normalized.contains("reprov") || normalized.contains("cancel") || normalized.contains("recus") -> AncoraTone.Neutral
        else -> AncoraTone.Brand
    }
}
