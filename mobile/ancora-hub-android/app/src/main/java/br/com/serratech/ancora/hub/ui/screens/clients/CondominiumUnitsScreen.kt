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
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.UnitListItem
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSearchBar
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

private data class CondominiumUnitsUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<UnitListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val condominiumName: String? = null,
    val query: String = "",
)

private class CondominiumUnitsViewModel(
    private val container: AppContainer,
    private val condominiumId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(CondominiumUnitsUiState())
        private set

    init {
        refresh()
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun refresh() {
        viewModelScope.launch {
            loadPage(1, append = false)
        }
    }

    fun loadNextPage() {
        val meta = uiState.meta ?: return
        if (uiState.isLoadingMore || !meta.hasMore) {
            return
        }

        viewModelScope.launch {
            loadPage(meta.currentPage + 1, append = true)
        }
    }

    private suspend fun loadPage(page: Int, append: Boolean) {
        uiState = uiState.copy(
            isLoading = !append,
            isLoadingMore = append,
            error = if (append) uiState.error else null,
        )

        runCatching {
            container.clientRepository.condominiumUnits(
                condominiumId = condominiumId,
                page = page,
                query = uiState.query,
            )
        }.onSuccess { response ->
            uiState = uiState.copy(
                isLoading = false,
                isLoadingMore = false,
                error = null,
                items = if (append) uiState.items + response.items else response.items,
                meta = response.meta,
                condominiumName = uiState.condominiumName ?: response.items.firstOrNull()?.condominiumName,
            )
        }.onFailure {
            uiState = uiState.copy(
                isLoading = false,
                isLoadingMore = false,
                error = it.message ?: "Não foi possível carregar as unidades agora.",
            )
        }
    }
}

@Composable
fun CondominiumUnitsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    condominiumId: Long,
    onOpenUnit: (Long) -> Unit,
    onBack: () -> Unit,
) {
    val viewModel: CondominiumUnitsViewModel = viewModel(
        key = "condominium-units-$condominiumId",
        factory = simpleFactory { CondominiumUnitsViewModel(container, condominiumId) },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = viewModel.uiState.condominiumName ?: "Unidades",
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
            viewModel.uiState.isLoading && viewModel.uiState.items.isEmpty() -> {
                AncoraLoadingState(label = "Carregando unidades...")
            }

            viewModel.uiState.error != null && viewModel.uiState.items.isEmpty() -> {
                AncoraErrorState(
                    title = "Não foi possível carregar as informações.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            else -> {
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
                            AncoraSearchBar(
                                query = viewModel.uiState.query,
                                onQueryChange = viewModel::updateQuery,
                                placeholder = "Buscar por unidade, proprietário ou locatário",
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
                        }
                    }

                    if (viewModel.uiState.items.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhuma unidade encontrada",
                                message = "Tente ajustar a busca para localizar unidades, proprietários ou locatários.",
                            )
                        }
                    } else {
                        items(viewModel.uiState.items, key = { it.id }) { unit ->
                            AncoraCard(bordered = true) {
                                Text(
                                    text = unit.unitLabel,
                                    style = MaterialTheme.typography.titleMedium,
                                )
                                InfoLine(label = "Proprietário", value = unit.ownerName)
                                InfoLine(label = "Locatário", value = unit.tenantName)
                                InfoLine(label = "Última atualização", value = unit.updatedAtBr)
                                AncoraGhostButton(
                                    text = "Ver unidade",
                                    onClick = { onOpenUnit(unit.id) },
                                )
                            }
                        }
                    }

                    if (viewModel.uiState.meta?.hasMore == true) {
                        item {
                            AncoraSecondaryButton(
                                text = if (viewModel.uiState.isLoadingMore) "Carregando..." else "Carregar mais",
                                enabled = !viewModel.uiState.isLoadingMore,
                                onClick = viewModel::loadNextPage,
                            )
                        }
                    }
                }
            }
        }
    }
}
