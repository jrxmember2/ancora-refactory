package br.com.serratech.ancora.hub.ui.screens.processes

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
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.ProcessFilters
import br.com.serratech.ancora.hub.domain.model.ProcessListItem
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

data class ProcessesUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<ProcessListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: ProcessFilters = ProcessFilters(emptyList()),
    val query: String = "",
    val statusOptionId: Long? = null,
)

class ProcessesViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(ProcessesUiState())
        private set

    init {
        refresh()
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun updateStatusOption(value: Long?) {
        uiState = uiState.copy(statusOptionId = value)
    }

    fun clearFilters() {
        uiState = uiState.copy(statusOptionId = null)
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
            container.processRepository.list(
                page = page,
                statusOptionId = uiState.statusOptionId,
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
                error = it.message ?: "Não foi possível carregar os processos agora.",
            )
        }
    }
}

@Composable
fun ProcessesScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenProcess: (Long) -> Unit,
    onBack: (() -> Unit)? = null,
) {
    val spacing = MaterialTheme.spacing
    var showFilters by mutableStateOf(false)
    val viewModel: ProcessesViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProcessesViewModel(container) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Processos",
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
                AncoraLoadingState(label = "Carregando processos...")
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
                                placeholder = "Buscar por número, parte, cliente ou condomínio",
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
                            SelectedProcessFilters(viewModel.uiState)
                        }
                    }

                    if (viewModel.uiState.items.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhum processo encontrado",
                                message = "Tente ajustar a busca ou os filtros para localizar o processo desejado.",
                            )
                        }
                    } else {
                        items(
                            items = viewModel.uiState.items,
                            key = { item -> item.id },
                        ) { item ->
                            ProcessCard(
                                item = item,
                                onClick = { onOpenProcess(item.id) },
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
        ProcessesFilterSheet(
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
            onStatusChange = viewModel::updateStatusOption,
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun SelectedProcessFilters(uiState: ProcessesUiState) {
    val spacing = MaterialTheme.spacing
    val selectedStatus = uiState.filters.statuses.firstOrNull {
        it.value.toLongOrNull() == uiState.statusOptionId
    }

    if (selectedStatus == null) {
        return
    }

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(spacing.sm),
        verticalArrangement = Arrangement.spacedBy(spacing.sm),
    ) {
        AncoraStatusChip(
            label = selectedStatus.label,
            tone = processStatusTone(selectedStatus.label),
        )
    }
}

@Composable
private fun ProcessCard(
    item: ProcessListItem,
    onClick: () -> Unit,
) {
    AncoraCard(
        bordered = true,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(
                label = item.status ?: "Em andamento",
                tone = processStatusTone(item.status),
            )
            if (item.isPrivate) {
                AncoraStatusChip(
                    label = "Privado",
                    tone = AncoraTone.Warning,
                )
            }
        }
        Text(
            text = item.processNumber,
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
        item.className?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.lastMovementDescription?.let {
            Text(
                text = "Última movimentação: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            Text(
                text = item.lastMovementAtBr ?: item.updatedAtBr ?: "Sem atualização recente",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            item.responsibleLawyer?.takeIf { it.isNotBlank() }?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        AncoraGhostButton(
            text = "Ver detalhes",
            onClick = onClick,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ProcessesFilterSheet(
    uiState: ProcessesUiState,
    onDismiss: () -> Unit,
    onApply: () -> Unit,
    onClear: () -> Unit,
    onStatusChange: (Long?) -> Unit,
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
            AncoraSectionTitle(title = "Filtrar processos")
            ProcessStatusSection(
                options = uiState.filters.statuses,
                selectedValue = uiState.statusOptionId,
                onSelect = onStatusChange,
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
private fun ProcessStatusSection(
    options: List<FilterValueOption>,
    selectedValue: Long?,
    onSelect: (Long?) -> Unit,
) {
    val spacing = MaterialTheme.spacing

    Column(verticalArrangement = Arrangement.spacedBy(spacing.sm)) {
        Text(
            text = "Status",
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
                val optionId = option.value.toLongOrNull()
                FilterChip(
                    selected = selectedValue != null && selectedValue == optionId,
                    onClick = { onSelect(optionId) },
                    label = { Text(option.label) },
                )
            }
        }
    }
}

fun processStatusTone(status: String?): AncoraTone {
    val normalized = status.orEmpty().trim().lowercase()

    return when {
        normalized.contains("baix") ||
            normalized.contains("encerr") ||
            normalized.contains("arquiv") -> AncoraTone.Neutral

        normalized.contains("susp") ||
            normalized.contains("aguard") -> AncoraTone.Warning

        normalized.contains("ativo") ||
            normalized.contains("andamento") ||
            normalized.contains("cumpr") -> AncoraTone.Brand

        else -> AncoraTone.Info
    }
}
