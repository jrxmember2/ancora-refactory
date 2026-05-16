package br.com.serratech.ancora.clientes.ui.screens.dashboard

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
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FilterChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.CondominiumContext
import br.com.serratech.ancora.clientes.domain.model.DashboardData
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.AncoraTopBar
import br.com.serratech.ancora.clientes.ui.components.EmptyState
import br.com.serratech.ancora.clientes.ui.components.ErrorState
import br.com.serratech.ancora.clientes.ui.components.LoadingState
import br.com.serratech.ancora.clientes.ui.components.PrimaryButton
import br.com.serratech.ancora.clientes.ui.components.StatusChip
import kotlinx.coroutines.launch

data class DashboardUiState(
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val isUpdatingContext: Boolean = false,
    val error: String? = null,
    val data: DashboardData? = null,
    val condominiumContext: CondominiumContext = CondominiumContext(
        selected = null,
        items = emptyList(),
    ),
)

class DashboardViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(DashboardUiState())
        private set

    init {
        refresh(forceBlocking = true)
    }

    fun refresh(forceBlocking: Boolean = false) {
        viewModelScope.launch {
            val keepContent = !forceBlocking && uiState.data != null
            uiState = uiState.copy(
                isLoading = !keepContent,
                isRefreshing = keepContent,
                error = null,
            )

            runCatching {
                val context = container.condominiumRepository.list()
                val dashboard = container.dashboardRepository.dashboard()
                context to dashboard
            }.onSuccess { (context, dashboard) ->
                uiState = uiState.copy(
                    isLoading = false,
                    isRefreshing = false,
                    isUpdatingContext = false,
                    data = dashboard,
                    condominiumContext = context,
                    error = null,
                )
            }.onFailure {
                uiState = uiState.copy(
                    isLoading = false,
                    isRefreshing = false,
                    isUpdatingContext = false,
                    error = it.message ?: "Nao foi possivel carregar o dashboard.",
                )
            }
        }
    }

    fun selectCondominium(condominiumId: Long?) {
        viewModelScope.launch {
            uiState = uiState.copy(isUpdatingContext = true, error = null)
            runCatching {
                container.condominiumRepository.updateSelected(condominiumId)
                val context = container.condominiumRepository.list()
                val dashboard = container.dashboardRepository.dashboard()
                context to dashboard
            }.onSuccess { (context, dashboard) ->
                uiState = uiState.copy(
                    isUpdatingContext = false,
                    data = dashboard,
                    condominiumContext = context,
                )
            }.onFailure {
                uiState = uiState.copy(
                    isUpdatingContext = false,
                    error = it.message ?: "Nao foi possivel trocar o condominio agora.",
                )
            }
        }
    }
}

@Composable
fun DashboardScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    showBiometricOptIn: Boolean,
    onBiometricDecision: (Boolean) -> Unit,
    onOpenNewDemand: () -> Unit,
    onOpenLeme: () -> Unit,
) {
    val viewModel: DashboardViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return DashboardViewModel(container) as T
            }
        }
    )
    var displayOptIn by mutableStateOf(false)
    val context = LocalContext.current

    LaunchedEffect(showBiometricOptIn) {
        displayOptIn = showBiometricOptIn && container.biometricAuthenticator.isAvailable(context)
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Ancora Clientes")
        when {
            viewModel.uiState.isLoading -> LoadingState("Carregando sua area do cliente...")
            viewModel.uiState.error != null && viewModel.uiState.data == null -> {
                ErrorState(
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = { viewModel.refresh(forceBlocking = true) },
                )
            }
            viewModel.uiState.data == null -> {
                EmptyState(
                    "Sem dados por enquanto",
                    "Assim que a API retornar informacoes do portal, o resumo aparecera aqui.",
                )
            }
            else -> DashboardContent(
                data = viewModel.uiState.data!!,
                condominiumContext = viewModel.uiState.condominiumContext,
                isRefreshing = viewModel.uiState.isRefreshing,
                isUpdatingContext = viewModel.uiState.isUpdatingContext,
                error = viewModel.uiState.error,
                onRefresh = { viewModel.refresh(forceBlocking = false) },
                onSelectCondominium = viewModel::selectCondominium,
                onOpenNewDemand = onOpenNewDemand,
                onOpenLeme = onOpenLeme,
            )
        }
    }

    if (displayOptIn) {
        AlertDialog(
            onDismissRequest = {
                displayOptIn = false
                onBiometricDecision(false)
            },
            confirmButton = {
                TextButton(onClick = {
                    displayOptIn = false
                    onBiometricDecision(true)
                }) {
                    Text("Ativar biometria")
                }
            },
            dismissButton = {
                TextButton(onClick = {
                    displayOptIn = false
                    onBiometricDecision(false)
                }) {
                    Text("Agora nao")
                }
            },
            title = { Text("Ativar login por biometria?") },
            text = { Text("Voce podera desbloquear o app com sua biometria nas proximas aberturas.") },
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun DashboardContent(
    data: DashboardData,
    condominiumContext: CondominiumContext,
    isRefreshing: Boolean,
    isUpdatingContext: Boolean,
    error: String?,
    onRefresh: () -> Unit,
    onSelectCondominium: (Long?) -> Unit,
    onOpenNewDemand: () -> Unit,
    onOpenLeme: () -> Unit,
) {
    LazyColumn(
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        item {
            AncoraCard {
                Text(data.greeting, style = MaterialTheme.typography.headlineSmall)
                Text(
                    data.selectedCondominium?.name ?: "Visao geral do portal do cliente",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                if (condominiumContext.items.size > 1) {
                    Text(
                        "Condominio em foco",
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    FlowRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        FilterChip(
                            selected = condominiumContext.selected == null,
                            onClick = { onSelectCondominium(null) },
                            label = { Text("Todos") },
                        )
                        condominiumContext.items.forEach { condominium ->
                            FilterChip(
                                selected = condominiumContext.selected?.id == condominium.id,
                                onClick = { onSelectCondominium(condominium.id) },
                                label = { Text(condominium.name) },
                            )
                        }
                    }
                }
                if (isUpdatingContext) {
                    Text(
                        "Atualizando contexto do portal...",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.primary,
                    )
                }
                PrimaryButton(text = "Nova solicitacao", onClick = onOpenNewDemand)
                TextButton(onClick = onOpenLeme) { Text("Falar com Leme IA") }
            }
        }
        item {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                SummaryTile("Processos ativos", data.summary.processesActive.toString())
                SummaryTile("Solicitacoes abertas", data.summary.demandsOpen.toString())
                SummaryTile("Aguardando sua resposta", data.summary.demandsWaitingClient.toString())
                SummaryTile("Notificacoes nao lidas", data.summary.notificationsUnread.toString())
            }
        }
        if (error != null) {
            item {
                AncoraCard {
                    Text("Atualizacao parcial", style = MaterialTheme.typography.titleMedium)
                    Text(error, color = MaterialTheme.colorScheme.error)
                }
            }
        }
        if (data.latestProcesses.isNotEmpty()) {
            item { SectionHeader("Processos recentes") }
            items(data.latestProcesses) { process ->
                AncoraCard {
                    Text(process.processNumber, style = MaterialTheme.typography.titleMedium)
                    StatusChip(process.status)
                    Text(process.type ?: "Tipo nao informado", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(process.lastPublicPhase?.description ?: "Sem andamento publico recente")
                }
            }
        }
        if (data.latestDemands.isNotEmpty()) {
            item { SectionHeader("Solicitacoes") }
            items(data.latestDemands) { demand ->
                AncoraCard {
                    Text(demand.protocol, style = MaterialTheme.typography.titleMedium)
                    StatusChip(demand.status)
                    Text(demand.subject)
                }
            }
        }
        if (data.latestMovements.isNotEmpty()) {
            item { SectionHeader("Ultimas movimentacoes") }
            items(data.latestMovements) { movement ->
                AncoraCard {
                    Text(movement.title, style = MaterialTheme.typography.titleMedium)
                    Text(movement.description)
                    movement.date?.let {
                        Text(
                            it,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }
        item {
            TextButton(
                modifier = Modifier.fillMaxWidth(),
                onClick = onRefresh,
            ) {
                Text(if (isRefreshing) "Atualizando..." else "Atualizar")
            }
        }
    }
}

@Composable
private fun SummaryTile(label: String, value: String) {
    AncoraCard {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, style = MaterialTheme.typography.headlineSmall)
    }
}

@Composable
private fun SectionHeader(title: String) {
    Text(title, style = MaterialTheme.typography.titleLarge)
}
