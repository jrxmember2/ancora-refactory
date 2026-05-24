package br.com.serratech.ancora.hub.ui.screens.clients

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
import androidx.compose.material.icons.outlined.Apartment
import androidx.compose.material.icons.outlined.FilterList
import androidx.compose.material.icons.outlined.Groups
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.ClientFilters
import br.com.serratech.ancora.hub.domain.model.ClientListItem
import br.com.serratech.ancora.hub.domain.model.CondominiumListItem
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

private data class ClientsUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<ClientListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: ClientFilters = ClientFilters(emptyList(), emptyList()),
    val query: String = "",
    val scope: String? = null,
    val status: String? = null,
)

private class ClientsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(ClientsUiState())
        private set

    init {
        refresh()
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun updateScope(value: String?) {
        uiState = uiState.copy(scope = value)
    }

    fun updateStatus(value: String?) {
        uiState = uiState.copy(status = value)
    }

    fun clearFilters() {
        uiState = uiState.copy(scope = null, status = null)
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
            container.clientRepository.listClients(
                page = page,
                scope = uiState.scope,
                status = uiState.status,
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
                error = it.message ?: "Não foi possível carregar os clientes agora.",
            )
        }
    }
}

private data class CondominiumsUiState(
    val isLoading: Boolean = true,
    val isLoadingMore: Boolean = false,
    val error: String? = null,
    val items: List<CondominiumListItem> = emptyList(),
    val meta: PaginationMeta? = null,
    val filters: List<FilterValueOption> = emptyList(),
    val query: String = "",
    val status: String? = null,
)

private class CondominiumsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(CondominiumsUiState())
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
            container.clientRepository.listCondominiums(
                page = page,
                status = uiState.status,
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
                error = it.message ?: "Não foi possível carregar os condomínios agora.",
            )
        }
    }
}

@Composable
fun ClientsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenClient: (Long) -> Unit,
    onOpenCondominium: (Long) -> Unit,
) {
    var selectedTab by mutableIntStateOf(0)
    var showClientFilters by mutableStateOf(false)
    val clientsViewModel: ClientsViewModel = viewModel(
        factory = simpleFactory { ClientsViewModel(container) },
    )
    val condominiumsViewModel: CondominiumsViewModel = viewModel(
        factory = simpleFactory { CondominiumsViewModel(container) },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Clientes",
            actions = {
                if (selectedTab == 0) {
                    IconButton(onClick = { showClientFilters = true }) {
                        Icon(
                            imageVector = Icons.Outlined.FilterList,
                            contentDescription = "Filtrar",
                        )
                    }
                }
            },
        )

        TabRow(selectedTabIndex = selectedTab) {
            Tab(
                selected = selectedTab == 0,
                onClick = { selectedTab = 0 },
                text = { Text("Clientes") },
                icon = {
                    Icon(
                        imageVector = Icons.Outlined.Groups,
                        contentDescription = null,
                    )
                },
            )
            Tab(
                selected = selectedTab == 1,
                onClick = { selectedTab = 1 },
                text = { Text("Condomínios") },
                icon = {
                    Icon(
                        imageVector = Icons.Outlined.Apartment,
                        contentDescription = null,
                    )
                },
            )
        }

        if (selectedTab == 0) {
            ClientsTabContent(
                uiState = clientsViewModel.uiState,
                onQueryChange = clientsViewModel::updateQuery,
                onRefresh = clientsViewModel::refresh,
                onClearSearch = {
                    clientsViewModel.updateQuery("")
                    clientsViewModel.refresh()
                },
                onLoadMore = clientsViewModel::loadNextPage,
                onOpenItem = onOpenClient,
            )
        } else {
            CondominiumsTabContent(
                uiState = condominiumsViewModel.uiState,
                onQueryChange = condominiumsViewModel::updateQuery,
                onStatusChange = {
                    condominiumsViewModel.updateStatus(it)
                    condominiumsViewModel.refresh()
                },
                onRefresh = condominiumsViewModel::refresh,
                onClearSearch = {
                    condominiumsViewModel.updateQuery("")
                    condominiumsViewModel.refresh()
                },
                onLoadMore = condominiumsViewModel::loadNextPage,
                onOpenItem = onOpenCondominium,
            )
        }
    }

    if (showClientFilters) {
        ClientFilterSheet(
            uiState = clientsViewModel.uiState,
            onDismiss = { showClientFilters = false },
            onApply = {
                showClientFilters = false
                clientsViewModel.refresh()
            },
            onClear = {
                clientsViewModel.clearFilters()
                clientsViewModel.refresh()
                showClientFilters = false
            },
            onScopeChange = clientsViewModel::updateScope,
            onStatusChange = clientsViewModel::updateStatus,
        )
    }
}

@Composable
private fun ClientsTabContent(
    uiState: ClientsUiState,
    onQueryChange: (String) -> Unit,
    onRefresh: () -> Unit,
    onClearSearch: () -> Unit,
    onLoadMore: () -> Unit,
    onOpenItem: (Long) -> Unit,
) {
    val spacing = MaterialTheme.spacing

    when {
        uiState.isLoading && uiState.items.isEmpty() -> {
            AncoraLoadingState(label = "Carregando clientes...")
        }

        uiState.error != null && uiState.items.isEmpty() -> {
            AncoraErrorState(
                title = "Não foi possível carregar as informações.",
                message = uiState.error.orEmpty(),
                onRetry = onRefresh,
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
                            query = uiState.query,
                            onQueryChange = onQueryChange,
                            placeholder = "Buscar por nome, documento, e-mail ou telefone",
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
                                onClick = onRefresh,
                            )
                        }
                        SelectedClientFilters(uiState)
                    }
                }

                if (uiState.items.isEmpty()) {
                    item {
                        AncoraEmptyState(
                            title = "Nenhum registro encontrado.",
                            message = "Tente ajustar a busca para localizar clientes, proprietários, locatários ou síndicos.",
                        )
                    }
                } else {
                    items(uiState.items, key = { it.id }) { item ->
                        ClientCard(
                            item = item,
                            onClick = { onOpenItem(item.id) },
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
    }
}

@Composable
private fun CondominiumsTabContent(
    uiState: CondominiumsUiState,
    onQueryChange: (String) -> Unit,
    onStatusChange: (String?) -> Unit,
    onRefresh: () -> Unit,
    onClearSearch: () -> Unit,
    onLoadMore: () -> Unit,
    onOpenItem: (Long) -> Unit,
) {
    val spacing = MaterialTheme.spacing

    when {
        uiState.isLoading && uiState.items.isEmpty() -> {
            AncoraLoadingState(label = "Carregando condomínios...")
        }

        uiState.error != null && uiState.items.isEmpty() -> {
            AncoraErrorState(
                title = "Não foi possível carregar as informações.",
                message = uiState.error.orEmpty(),
                onRetry = onRefresh,
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
                            query = uiState.query,
                            onQueryChange = onQueryChange,
                            placeholder = "Buscar por nome, CNPJ, síndico ou administradora",
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
                                onClick = onRefresh,
                            )
                        }
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(spacing.sm),
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
                    }
                }

                if (uiState.items.isEmpty()) {
                    item {
                        AncoraEmptyState(
                            title = "Nenhum registro encontrado.",
                            message = "Tente buscar por outro condomínio, síndico ou administradora.",
                        )
                    }
                } else {
                    items(uiState.items, key = { it.id }) { item ->
                        CondominiumCard(
                            item = item,
                            onClick = { onOpenItem(item.id) },
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
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun SelectedClientFilters(uiState: ClientsUiState) {
    val spacing = MaterialTheme.spacing
    val selectedScope = uiState.filters.scopes.firstOrNull { it.value == uiState.scope }
    val selectedStatus = uiState.filters.statuses.firstOrNull { it.value == uiState.status }

    if (selectedScope == null && selectedStatus == null) {
        return
    }

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(spacing.sm),
        verticalArrangement = Arrangement.spacedBy(spacing.sm),
    ) {
        selectedScope?.let {
            AncoraStatusChip(label = it.label, tone = AncoraTone.Brand)
        }
        selectedStatus?.let {
            AncoraStatusChip(label = it.label, tone = AncoraTone.Info)
        }
    }
}

@Composable
private fun ClientCard(
    item: ClientListItem,
    onClick: () -> Unit,
) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(
                label = item.profileScopeLabel,
                tone = if (item.profileScope == "avulso") AncoraTone.Info else AncoraTone.Brand,
            )
            AncoraStatusChip(
                label = item.statusLabel,
                tone = if (item.isActive) AncoraTone.Success else AncoraTone.Warning,
            )
        }
        Text(
            text = item.name,
            style = MaterialTheme.typography.titleMedium,
        )
        item.roleLabel?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.document?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.primaryPhone?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.primaryEmail?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        if (item.linkedUnitsCount > 0) {
            AncoraStatusChip(
                label = "${item.linkedUnitsCount} unidade(s) vinculada(s)",
                tone = AncoraTone.Info,
            )
        }
        AncoraGhostButton(
            text = "Ver detalhes",
            onClick = onClick,
        )
    }
}

@Composable
private fun CondominiumCard(
    item: CondominiumListItem,
    onClick: () -> Unit,
) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(
                label = item.statusLabel,
                tone = if (item.isActive) AncoraTone.Success else AncoraTone.Warning,
            )
            AncoraStatusChip(
                label = "${item.unitsCount} unidade(s)",
                tone = AncoraTone.Info,
            )
        }
        Text(
            text = item.name,
            style = MaterialTheme.typography.titleMedium,
        )
        item.syndicName?.let {
            Text(
                text = "Síndico: $it",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.administratorName?.let {
            Text(
                text = "Administradora: $it",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        val location = listOfNotNull(item.city, item.state).joinToString(" - ")
        if (location.isNotBlank()) {
            Text(
                text = location,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.cnpj?.let {
            Text(
                text = it,
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

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun ClientFilterSheet(
    uiState: ClientsUiState,
    onDismiss: () -> Unit,
    onApply: () -> Unit,
    onClear: () -> Unit,
    onScopeChange: (String?) -> Unit,
    onStatusChange: (String?) -> Unit,
) {
    val spacing = MaterialTheme.spacing

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = spacing.lg)
                .padding(bottom = spacing.xl),
            verticalArrangement = Arrangement.spacedBy(spacing.md),
        ) {
            FilterSection(
                title = "Tipo de cliente",
                options = uiState.filters.scopes,
                selectedValue = uiState.scope,
                onSelect = onScopeChange,
            )
            FilterSection(
                title = "Status",
                options = uiState.filters.statuses,
                selectedValue = uiState.status,
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
private fun FilterSection(
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

internal fun <T : ViewModel> simpleFactory(create: () -> T): ViewModelProvider.Factory =
    object : ViewModelProvider.Factory {
        override fun <VM : ViewModel> create(modelClass: Class<VM>): VM {
            @Suppress("UNCHECKED_CAST")
            return create() as VM
        }
    }
