package br.com.serratech.ancora.hub.ui.screens.signatures

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
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.SignatureFilters
import br.com.serratech.ancora.hub.domain.model.SignatureListItem
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

data class SignaturesUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<SignatureListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: SignatureFilters = SignatureFilters(emptyList(), emptyList()),
    val query: String = "",
    val status: String? = null,
    val origin: String? = null,
)

class SignaturesViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(SignaturesUiState())
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

    fun updateOrigin(value: String?) {
        uiState = uiState.copy(origin = value)
    }

    fun clearFilters() {
        uiState = uiState.copy(status = null, origin = null)
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
            container.signatureRepository.list(
                page = page,
                status = uiState.status,
                origin = uiState.origin,
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
                error = it.message ?: "Não foi possível carregar o Assinador Eletrônico agora.",
            )
        }
    }
}

@Composable
fun SignaturesScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenSignature: (Long) -> Unit,
    onBack: () -> Unit,
) {
    var showFilters by mutableStateOf(false)
    val viewModel: SignaturesViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return SignaturesViewModel(container) as T
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
                AncoraLoadingState(label = "Carregando o Assinador Eletrônico...")
            }

            viewModel.uiState.error != null && viewModel.uiState.items.isEmpty() -> {
                AncoraErrorState(
                    title = "Não foi possível carregar as informações.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            else -> {
                SignaturesContent(
                    uiState = viewModel.uiState,
                    onQueryChange = viewModel::updateQuery,
                    onSearch = viewModel::refresh,
                    onClearSearch = {
                        viewModel.updateQuery("")
                        viewModel.refresh()
                    },
                    onOpenSignature = onOpenSignature,
                    onLoadMore = viewModel::loadNextPage,
                )
            }
        }
    }

    if (showFilters) {
        SignatureFiltersSheet(
            uiState = viewModel.uiState,
            onDismiss = { showFilters = false },
            onStatusChange = viewModel::updateStatus,
            onOriginChange = viewModel::updateOrigin,
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
private fun SignaturesContent(
    uiState: SignaturesUiState,
    onQueryChange: (String) -> Unit,
    onSearch: () -> Unit,
    onClearSearch: () -> Unit,
    onOpenSignature: (Long) -> Unit,
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
                    placeholder = "Buscar por documento ou signatário",
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
                    uiState.filters.statuses.firstOrNull { it.value == uiState.status }?.let(::add)
                    uiState.filters.origins.firstOrNull { it.value == uiState.origin }?.let(::add)
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
                    title = "Nenhum registro encontrado.",
                    message = "Nenhum documento para assinatura foi encontrado com os filtros atuais.",
                )
            }
        } else {
            items(uiState.items, key = { it.id }) { item ->
                SignatureCard(item = item, onClick = { onOpenSignature(item.id) })
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
private fun SignatureCard(
    item: SignatureListItem,
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
                tone = signatureTone(item.status),
            )
            AncoraStatusChip(
                label = "${item.pendingCount} pendências",
                tone = if (item.pendingCount > 0) AncoraTone.Warning else AncoraTone.Success,
            )
        }
        Text(
            text = item.documentName,
            style = MaterialTheme.typography.titleMedium,
        )
        Text(
            text = item.sourceLabel,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = "${item.signersCount} signatários · ${item.createdAtBr ?: "Data não informada"}",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun SignatureFiltersSheet(
    uiState: SignaturesUiState,
    onDismiss: () -> Unit,
    onStatusChange: (String?) -> Unit,
    onOriginChange: (String?) -> Unit,
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
                text = "Filtrar assinaturas",
                style = MaterialTheme.typography.headlineSmall,
            )

            SignatureFilterGroup(
                title = "Status",
                options = uiState.filters.statuses,
                selectedValue = uiState.status,
                onSelected = onStatusChange,
            )

            SignatureFilterGroup(
                title = "Origem",
                options = uiState.filters.origins,
                selectedValue = uiState.origin,
                onSelected = onOriginChange,
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
private fun SignatureFilterGroup(
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
                label = { Text("Todos") },
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

internal fun signatureTone(status: String): AncoraTone =
    when (status.lowercase()) {
        "certificated" -> AncoraTone.Success
        "pending_signatures", "partially_signed", "uploaded", "metadata_ready", "certificating" -> AncoraTone.Warning
        "rejected_by_signer", "rejected_by_user", "expired", "failed" -> AncoraTone.Neutral
        else -> AncoraTone.Brand
    }
