package br.com.serratech.ancora.hub.ui.screens.demands

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.DemandFilters
import br.com.serratech.ancora.hub.domain.model.DemandListItem
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.HubUserOption
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSearchBar
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class DemandsUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<DemandListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: DemandFilters = DemandFilters(emptyList(), emptyList(), emptyList()),
    val query: String = "",
    val status: String? = null,
    val priority: String? = null,
    val assignedUserId: Long? = null,
)

class DemandsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(DemandsUiState())
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

    fun updatePriority(value: String?) {
        uiState = uiState.copy(priority = value)
    }

    fun updateAssignee(value: Long?) {
        uiState = uiState.copy(assignedUserId = value)
    }

    fun clearFilters() {
        uiState = uiState.copy(
            status = null,
            priority = null,
            assignedUserId = null,
        )
    }

    fun refresh() {
        viewModelScope.launch {
            loadPage(page = 1, append = false)
        }
    }

    fun loadNextPage() {
        val meta = uiState.meta ?: return
        if (uiState.isLoadingMore || !meta.hasMore) {
            return
        }

        viewModelScope.launch {
            loadPage(page = meta.currentPage + 1, append = true)
        }
    }

    private suspend fun loadPage(page: Int, append: Boolean) {
        uiState = uiState.copy(
            isLoading = !append,
            isLoadingMore = append,
            error = if (append) uiState.error else null,
        )

        runCatching {
            container.demandRepository.list(
                page = page,
                status = uiState.status,
                priority = uiState.priority,
                assignedUserId = uiState.assignedUserId,
                query = uiState.query,
            )
        }.onSuccess { response ->
            uiState = uiState.copy(
                isLoading = false,
                isLoadingMore = false,
                error = null,
                items = if (append) uiState.items + response.items else response.items,
                meta = response.meta,
                filters = response.filters,
            )
        }.onFailure {
            uiState = uiState.copy(
                isLoading = false,
                isLoadingMore = false,
                error = it.message ?: "Não foi possível carregar as demandas agora.",
            )
        }
    }
}

@Composable
fun DemandsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenDemand: (Long) -> Unit,
    onBack: (() -> Unit)? = null,
) {
    val spacing = MaterialTheme.spacing
    var showFilters by mutableStateOf(false)
    val viewModel: DemandsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return DemandsViewModel(container) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Demandas",
            navigationIcon = onBack?.let {
                {
                    IconButton(onClick = it) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                            contentDescription = "Voltar",
                        )
                    }
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
                AncoraLoadingState(label = "Carregando demandas...")
            }

            viewModel.uiState.error != null && viewModel.uiState.items.isEmpty() -> {
                AncoraErrorState(
                    title = "Não foi possível carregar as informações.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            else -> {
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
                            AncoraSearchBar(
                                query = viewModel.uiState.query,
                                onQueryChange = viewModel::updateQuery,
                                placeholder = "Buscar por protocolo, título, cliente ou responsável",
                            )
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                AncoraGhostButton(
                                    text = "Limpar busca",
                                    enabled = viewModel.uiState.query.isNotBlank(),
                                    onClick = {
                                        viewModel.updateQuery("")
                                        viewModel.refresh()
                                    },
                                )
                                AncoraGhostButton(
                                    text = "Buscar",
                                    onClick = viewModel::refresh,
                                )
                            }
                            SelectedDemandFilters(viewModel.uiState)
                        }
                    }

                    if (viewModel.uiState.items.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhuma demanda encontrada",
                                message = "Tente ajustar a busca ou os filtros para localizar a informação desejada.",
                            )
                        }
                    } else {
                        items(
                            items = viewModel.uiState.items,
                            key = { item -> item.id },
                        ) { item ->
                            DemandCard(
                                item = item,
                                onClick = { onOpenDemand(item.id) },
                            )
                        }
                    }

                    if (viewModel.uiState.meta?.hasMore == true) {
                        item {
                            AncoraSecondaryButton(
                                text = if (viewModel.uiState.isLoadingMore) {
                                    "Carregando..."
                                } else {
                                    "Carregar mais"
                                },
                                enabled = !viewModel.uiState.isLoadingMore,
                                onClick = viewModel::loadNextPage,
                            )
                        }
                    }
                }
            }
        }
    }

    if (showFilters) {
        DemandsFilterSheet(
            uiState = viewModel.uiState,
            onDismiss = { showFilters = false },
            onApply = {
                showFilters = false
                viewModel.refresh()
            },
            onClear = {
                viewModel.clearFilters()
                viewModel.refresh()
                showFilters = false
            },
            onStatusChange = viewModel::updateStatus,
            onPriorityChange = viewModel::updatePriority,
            onAssigneeChange = viewModel::updateAssignee,
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun SelectedDemandFilters(uiState: DemandsUiState) {
    val spacing = MaterialTheme.spacing
    val selectedAssignee = uiState.filters.assignees.firstOrNull { it.id == uiState.assignedUserId }

    if (uiState.status == null && uiState.priority == null && selectedAssignee == null) {
        return
    }

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(spacing.sm),
        verticalArrangement = Arrangement.spacedBy(spacing.sm),
    ) {
        uiState.filters.statuses.firstOrNull { it.value == uiState.status }?.let {
            AncoraStatusChip(label = it.label, tone = AncoraTone.Brand)
        }
        uiState.filters.priorities.firstOrNull { it.value == uiState.priority }?.let {
            AncoraStatusChip(label = it.label, tone = AncoraTone.Warning)
        }
        selectedAssignee?.let {
            AncoraStatusChip(label = it.name, tone = AncoraTone.Info)
        }
    }
}

@Composable
private fun DemandCard(
    item: DemandListItem,
    onClick: () -> Unit,
) {
    AncoraCard(
        bordered = true,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.sm),
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                AncoraStatusChip(
                    label = item.statusLabel,
                    tone = demandStatusTone(item.status),
                )
                AncoraStatusChip(
                    label = item.priorityLabel,
                    tone = demandPriorityTone(item.priority),
                )
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
            item.condominiumName?.let {
                Text(
                    text = "Condomínio: $it",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text(
                    text = item.updatedAtBr ?: item.createdAtBr ?: "Agora mesmo",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                AncoraStatusChip(
                    label = item.sla.label,
                    tone = demandSlaTone(item.sla.status),
                )
            }
            item.assignee?.let {
                Text(
                    text = "Responsável: ${it.name}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            AncoraGhostButton(
                text = "Ver detalhes",
                onClick = onClick,
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DemandsFilterSheet(
    uiState: DemandsUiState,
    onDismiss: () -> Unit,
    onApply: () -> Unit,
    onClear: () -> Unit,
    onStatusChange: (String?) -> Unit,
    onPriorityChange: (String?) -> Unit,
    onAssigneeChange: (Long?) -> Unit,
) {
    val spacing = MaterialTheme.spacing

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = spacing.lg)
                .padding(bottom = spacing.xl)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(spacing.md),
        ) {
            AncoraSectionTitle(title = "Filtrar demandas")

            FilterValueSection(
                title = "Status",
                options = uiState.filters.statuses,
                selectedValue = uiState.status,
                onSelect = onStatusChange,
            )

            FilterValueSection(
                title = "Prioridade",
                options = uiState.filters.priorities,
                selectedValue = uiState.priority,
                onSelect = onPriorityChange,
            )

            AssigneeSection(
                assignees = uiState.filters.assignees,
                selectedId = uiState.assignedUserId,
                onSelect = onAssigneeChange,
            )

            AncoraSecondaryButton(
                text = "Limpar filtros",
                onClick = onClear,
            )
            AncoraSecondaryButton(
                text = "Aplicar filtros",
                onClick = onApply,
            )
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun FilterValueSection(
    title: String,
    options: List<FilterValueOption>,
    selectedValue: String?,
    onSelect: (String?) -> Unit,
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
                onClick = { onSelect(null) },
                label = { Text("Todos") },
            )
            options.forEach { option ->
                FilterChip(
                    selected = selectedValue == option.value,
                    onClick = { onSelect(option.value) },
                    label = { Text(option.label) },
                )
            }
        }
    }
}

@Composable
private fun AssigneeSection(
    assignees: List<HubUserOption>,
    selectedId: Long?,
    onSelect: (Long?) -> Unit,
) {
    val spacing = MaterialTheme.spacing

    Column(verticalArrangement = Arrangement.spacedBy(spacing.sm)) {
        Text(
            text = "Responsável",
            style = MaterialTheme.typography.titleMedium,
        )
        Column(
            modifier = Modifier.heightIn(max = 240.dp),
            verticalArrangement = Arrangement.spacedBy(spacing.xs),
        ) {
            FilterChip(
                selected = selectedId == null,
                onClick = { onSelect(null) },
                label = { Text("Todos") },
            )
            assignees.forEach { assignee ->
                FilterChip(
                    selected = selectedId == assignee.id,
                    onClick = { onSelect(assignee.id) },
                    label = { Text(assignee.name) },
                )
            }
        }
    }
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
