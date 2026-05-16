package br.com.serratech.ancora.clientes.ui.screens.processes

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.CondominiumContext
import br.com.serratech.ancora.clientes.domain.model.ProcessDetail
import br.com.serratech.ancora.clientes.domain.model.ProcessFilterOption
import br.com.serratech.ancora.clientes.domain.model.ProcessItem
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.AncoraTopBar
import br.com.serratech.ancora.clientes.ui.components.EmptyState
import br.com.serratech.ancora.clientes.ui.components.ErrorState
import br.com.serratech.ancora.clientes.ui.components.LoadingState
import br.com.serratech.ancora.clientes.ui.components.StatusChip
import br.com.serratech.ancora.clientes.ui.components.TimelineItem
import kotlinx.coroutines.launch

data class ProcessesUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val query: String = "",
    val selectedStatusId: Long? = null,
    val hasInitializedCondominiumFilter: Boolean = false,
    val selectedCondominiumId: Long? = null,
    val condominiumContext: CondominiumContext = CondominiumContext(
        selected = null,
        items = emptyList(),
    ),
    val statuses: List<ProcessFilterOption> = emptyList(),
    val items: List<ProcessItem> = emptyList(),
)

class ProcessesViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(ProcessesUiState())
        private set

    init {
        search(forceBlocking = true)
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun updateStatus(statusId: Long?) {
        uiState = uiState.copy(selectedStatusId = statusId)
        search(forceBlocking = false)
    }

    fun updateCondominium(condominiumId: Long?) {
        uiState = uiState.copy(
            selectedCondominiumId = condominiumId,
            hasInitializedCondominiumFilter = true,
        )
        search(forceBlocking = false)
    }

    fun search(forceBlocking: Boolean = false) {
        viewModelScope.launch {
            uiState = uiState.copy(
                isLoading = forceBlocking || uiState.items.isEmpty(),
                error = null,
            )
            runCatching {
                val context = container.condominiumRepository.list()
                val effectiveCondominiumId = if (uiState.hasInitializedCondominiumFilter) {
                    uiState.selectedCondominiumId
                } else {
                    context.selected?.id
                }
                val result = container.processRepository.list(
                    query = uiState.query,
                    statusOptionId = uiState.selectedStatusId,
                    condominiumId = effectiveCondominiumId,
                )

                Triple(context, effectiveCondominiumId, result)
            }.onSuccess { (context, effectiveCondominiumId, result) ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = null,
                    selectedCondominiumId = effectiveCondominiumId,
                    hasInitializedCondominiumFilter = true,
                    condominiumContext = context,
                    statuses = result.statuses,
                    items = result.items,
                )
            }.onFailure {
                uiState = uiState.copy(
                    isLoading = false,
                    error = it.message ?: "Nao foi possivel carregar os processos.",
                )
            }
        }
    }
}

class ProcessDetailViewModel(
    private val container: AppContainer,
    private val processId: Long,
) : ViewModel() {
    var detail by mutableStateOf<ProcessDetail?>(null)
        private set
    var isLoading by mutableStateOf(true)
        private set
    var error by mutableStateOf<String?>(null)
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            isLoading = true
            error = null
            runCatching { container.processRepository.detail(processId) }
                .onSuccess { detail = it; isLoading = false }
                .onFailure { error = it.message ?: "Nao foi possivel carregar o processo."; isLoading = false }
        }
    }
}

@Composable
fun ProcessesScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenDetail: (Long) -> Unit,
) {
    val viewModel: ProcessesViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProcessesViewModel(container) as T
            }
        }
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Processos")
        when {
            viewModel.uiState.isLoading -> LoadingState("Buscando processos...")
            viewModel.uiState.error != null && viewModel.uiState.items.isEmpty() -> {
                ErrorState(
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = { viewModel.search(forceBlocking = true) },
                )
            }
            viewModel.uiState.items.isEmpty() -> {
                EmptyState(
                    "Nenhum processo disponivel",
                    "Assim que houver processos publicos vinculados a sua conta, eles aparecerao aqui.",
                )
            }
            else -> ProcessesContent(
                state = viewModel.uiState,
                onQueryChanged = viewModel::updateQuery,
                onSearch = { viewModel.search(forceBlocking = false) },
                onStatusSelected = viewModel::updateStatus,
                onCondominiumSelected = viewModel::updateCondominium,
                onOpenDetail = onOpenDetail,
            )
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ProcessesContent(
    state: ProcessesUiState,
    onQueryChanged: (String) -> Unit,
    onSearch: () -> Unit,
    onStatusSelected: (Long?) -> Unit,
    onCondominiumSelected: (Long?) -> Unit,
    onOpenDetail: (Long) -> Unit,
) {
    LazyColumn(
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item {
            OutlinedTextField(
                value = state.query,
                onValueChange = onQueryChanged,
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Buscar por numero, parte ou assunto") },
                trailingIcon = {
                    TextButton(onClick = onSearch) { Text("Buscar") }
                },
            )
        }
        if (state.condominiumContext.items.size > 1) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Condominio", style = MaterialTheme.typography.titleMedium)
                    FlowRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        FilterChip(
                            selected = state.selectedCondominiumId == null,
                            onClick = { onCondominiumSelected(null) },
                            label = { Text("Todos") },
                        )
                        state.condominiumContext.items.forEach { condominium ->
                            FilterChip(
                                selected = state.selectedCondominiumId == condominium.id,
                                onClick = { onCondominiumSelected(condominium.id) },
                                label = { Text(condominium.name) },
                            )
                        }
                    }
                }
            }
        }
        if (state.statuses.isNotEmpty()) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Status", style = MaterialTheme.typography.titleMedium)
                    FlowRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        FilterChip(
                            selected = state.selectedStatusId == null,
                            onClick = { onStatusSelected(null) },
                            label = { Text("Todos") },
                        )
                        state.statuses.forEach { status ->
                            FilterChip(
                                selected = state.selectedStatusId == status.id,
                                onClick = { onStatusSelected(status.id) },
                                label = { Text(status.name) },
                            )
                        }
                    }
                }
            }
        }
        items(state.items) { process ->
            AncoraCard(modifier = Modifier.clickable { onOpenDetail(process.id) }) {
                Text(process.processNumber, style = MaterialTheme.typography.titleMedium)
                StatusChip(process.status)
                Text(process.type ?: "Tipo nao informado")
                Text(
                    process.lastPublicPhase?.description ?: "Sem andamento publico recente",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                process.lastPublicPhase?.phaseDateBr?.let {
                    Text(
                        it,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
    }
}

@Composable
fun ProcessDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    processId: Long,
    onBack: () -> Unit,
) {
    val viewModel: ProcessDetailViewModel = viewModel(
        key = "process-detail-$processId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProcessDetailViewModel(container, processId) as T
            }
        }
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Detalhe do processo")
        when {
            viewModel.isLoading -> LoadingState("Carregando processo...")
            viewModel.error != null -> ErrorState(viewModel.error.orEmpty(), onRetry = viewModel::refresh)
            viewModel.detail == null -> EmptyState("Processo indisponivel", "Nao foi possivel localizar esse processo agora.")
            else -> LazyColumn(
                contentPadding = PaddingValues(20.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                item {
                    AncoraCard {
                        Text(viewModel.detail!!.processNumber, style = MaterialTheme.typography.headlineSmall)
                        StatusChip(viewModel.detail!!.status)
                        Text(viewModel.detail!!.type ?: "Tipo nao informado")
                        Text(viewModel.detail!!.nature ?: "Natureza nao informada")
                        Text(
                            viewModel.detail!!.court ?: "Tribunal nao configurado",
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                item {
                    Text("Andamentos publicos", style = MaterialTheme.typography.titleLarge)
                }
                items(viewModel.detail!!.phases) { phase ->
                    TimelineItem(
                        title = phase.description,
                        subtitle = phase.sourceLabel,
                        trailing = phase.phaseDateBr,
                    )
                }
                item {
                    TextButton(onClick = onBack) { Text("Voltar") }
                }
            }
        }
    }
}
