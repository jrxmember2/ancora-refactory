package br.com.serratech.ancora.hub.ui.screens.collections

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
import br.com.serratech.ancora.hub.domain.model.CollectionFilters
import br.com.serratech.ancora.hub.domain.model.CollectionListItem
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
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

data class CollectionsUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<CollectionListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: CollectionFilters = CollectionFilters(
        workflowStages = emptyList(),
        situations = emptyList(),
        billingStatuses = emptyList(),
    ),
    val query: String = "",
    val workflowStage: String? = null,
    val situation: String? = null,
    val billingStatus: String? = null,
)

class CollectionsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(CollectionsUiState())
        private set

    init {
        refresh()
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun updateWorkflowStage(value: String?) {
        uiState = uiState.copy(workflowStage = value)
    }

    fun updateSituation(value: String?) {
        uiState = uiState.copy(situation = value)
    }

    fun updateBillingStatus(value: String?) {
        uiState = uiState.copy(billingStatus = value)
    }

    fun clearFilters() {
        uiState = uiState.copy(
            workflowStage = null,
            situation = null,
            billingStatus = null,
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
            container.collectionRepository.list(
                page = page,
                query = uiState.query,
                workflowStage = uiState.workflowStage,
                situation = uiState.situation,
                billingStatus = uiState.billingStatus,
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
                error = it.message ?: "Não foi possível carregar as cobranças agora.",
            )
        }
    }
}

@Composable
fun CollectionsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenCollection: (Long) -> Unit,
    onBack: (() -> Unit)? = null,
) {
    val spacing = MaterialTheme.spacing
    var showFilters by mutableStateOf(false)
    val viewModel: CollectionsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return CollectionsViewModel(container) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Cobranças",
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
                AncoraLoadingState(label = "Carregando cobranças...")
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
                                placeholder = "Buscar por condomínio, unidade, proprietário ou status",
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
                            SelectedCollectionFilters(viewModel.uiState)
                        }
                    }

                    if (viewModel.uiState.items.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhuma cobrança encontrada",
                                message = "Tente ajustar a busca ou os filtros para localizar a cobrança desejada.",
                            )
                        }
                    } else {
                        items(
                            items = viewModel.uiState.items,
                            key = { item -> item.id },
                        ) { item ->
                            CollectionCard(
                                item = item,
                                onClick = { onOpenCollection(item.id) },
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
        CollectionsFilterSheet(
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
            onWorkflowStageChange = viewModel::updateWorkflowStage,
            onSituationChange = viewModel::updateSituation,
            onBillingStatusChange = viewModel::updateBillingStatus,
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun SelectedCollectionFilters(uiState: CollectionsUiState) {
    val spacing = MaterialTheme.spacing
    val selectedValues = buildList {
        uiState.filters.workflowStages.firstOrNull { it.value == uiState.workflowStage }?.let(::add)
        uiState.filters.situations.firstOrNull { it.value == uiState.situation }?.let(::add)
        uiState.filters.billingStatuses.firstOrNull { it.value == uiState.billingStatus }?.let(::add)
    }

    if (selectedValues.isEmpty()) {
        return
    }

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(spacing.sm),
        verticalArrangement = Arrangement.spacedBy(spacing.sm),
    ) {
        selectedValues.forEach { option ->
            AncoraStatusChip(
                label = option.label,
                tone = collectionTone(option.value),
            )
        }
    }
}

@Composable
private fun CollectionCard(
    item: CollectionListItem,
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
                label = item.workflowStageLabel,
                tone = collectionTone(item.workflowStage),
            )
            item.billingStatusLabel?.let {
                AncoraStatusChip(
                    label = it,
                    tone = billingTone(item.billingStatus),
                )
            }
        }
        Text(
            text = item.condominiumName,
            style = MaterialTheme.typography.titleMedium,
        )
        Text(
            text = "Unidade ${item.unitLabel}",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = item.debtorName,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        item.ownerName?.let {
            Text(
                text = "Proprietário: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.tenantName?.let {
            Text(
                text = "Locatário: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.agreementTotalLabel?.let {
            Text(
                text = "Valor: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.situationLabel?.let {
            AncoraStatusChip(
                label = it,
                tone = situationTone(item.situation),
            )
        }
        Text(
            text = item.lastProgressAtBr ?: item.updatedAtBr ?: "Sem atualização recente",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        AncoraGhostButton(
            text = "Ver detalhes",
            onClick = onClick,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CollectionsFilterSheet(
    uiState: CollectionsUiState,
    onDismiss: () -> Unit,
    onApply: () -> Unit,
    onClear: () -> Unit,
    onWorkflowStageChange: (String?) -> Unit,
    onSituationChange: (String?) -> Unit,
    onBillingStatusChange: (String?) -> Unit,
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
            AncoraSectionTitle(title = "Filtrar cobranças")
            CollectionFilterSection(
                title = "Etapa",
                options = uiState.filters.workflowStages,
                selectedValue = uiState.workflowStage,
                onSelect = onWorkflowStageChange,
            )
            CollectionFilterSection(
                title = "Situação",
                options = uiState.filters.situations,
                selectedValue = uiState.situation,
                onSelect = onSituationChange,
            )
            CollectionFilterSection(
                title = "Status da cobrança",
                options = uiState.filters.billingStatuses,
                selectedValue = uiState.billingStatus,
                onSelect = onBillingStatusChange,
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
private fun CollectionFilterSection(
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

fun collectionTone(value: String?): AncoraTone {
    val normalized = value.orEmpty().trim().lowercase()

    return when {
        normalized.contains("judicial") ||
            normalized.contains("ajuiz") -> AncoraTone.Brand

        normalized.contains("acordo") ||
            normalized.contains("negoci") -> AncoraTone.Warning

        normalized.contains("encerr") ||
            normalized.contains("quitad") -> AncoraTone.Success

        else -> AncoraTone.Info
    }
}

fun situationTone(value: String?): AncoraTone {
    val normalized = value.orEmpty().trim().lowercase()

    return when {
        normalized.contains("inadimpl") ||
            normalized.contains("venc") -> AncoraTone.Error

        normalized.contains("acordo") -> AncoraTone.Warning
        normalized.contains("pago") -> AncoraTone.Success
        else -> AncoraTone.Info
    }
}

fun billingTone(value: String?): AncoraTone {
    val normalized = value.orEmpty().trim().lowercase()

    return when {
        normalized.contains("pend") -> AncoraTone.Warning
        normalized.contains("venc") -> AncoraTone.Error
        normalized.contains("pago") -> AncoraTone.Success
        else -> AncoraTone.Neutral
    }
}
