package br.com.serratech.ancora.hub.ui.screens.finance

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.FilterChip
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
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.FinanceCashflowData
import br.com.serratech.ancora.hub.domain.model.FinanceCashflowItem
import br.com.serratech.ancora.hub.domain.model.FinanceDashboardData
import br.com.serratech.ancora.hub.domain.model.FinancePayableItem
import br.com.serratech.ancora.hub.domain.model.FinancePayablesData
import br.com.serratech.ancora.hub.domain.model.FinanceReceivableItem
import br.com.serratech.ancora.hub.domain.model.FinanceReceivablesData
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraMetricCard
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

enum class FinanceTab(val label: String) {
    Receivables("Contas a receber"),
    Payables("Contas a pagar"),
    Cashflow("Fluxo de caixa"),
}

data class FinanceUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val dashboard: FinanceDashboardData? = null,
    val activeTab: FinanceTab = FinanceTab.Receivables,
    val receivables: FinanceReceivablesData? = null,
    val isLoadingReceivablesMore: Boolean = false,
    val receivablesFilter: String = "all",
    val payables: FinancePayablesData? = null,
    val isLoadingPayablesMore: Boolean = false,
    val payablesFilter: String = "all",
    val cashflow: FinanceCashflowData? = null,
    val isLoadingCashflowMore: Boolean = false,
    val cashflowPeriod: String = "30d",
)

class FinanceViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(FinanceUiState())
        private set

    init {
        refreshAll()
    }

    fun refreshAll() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.financeRepository.dashboard() }
                .onSuccess { dashboard ->
                    uiState = uiState.copy(isLoading = false, dashboard = dashboard)
                    ensureTabLoaded(uiState.activeTab, force = true)
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar o Financeiro 360 agora.",
                    )
                }
        }
    }

    fun changeTab(tab: FinanceTab) {
        uiState = uiState.copy(activeTab = tab)
        ensureTabLoaded(tab, force = false)
    }

    fun updateReceivablesFilter(value: String) {
        uiState = uiState.copy(receivablesFilter = value)
        ensureTabLoaded(FinanceTab.Receivables, force = true)
    }

    fun updatePayablesFilter(value: String) {
        uiState = uiState.copy(payablesFilter = value)
        ensureTabLoaded(FinanceTab.Payables, force = true)
    }

    fun updateCashflowPeriod(value: String) {
        uiState = uiState.copy(cashflowPeriod = value)
        ensureTabLoaded(FinanceTab.Cashflow, force = true)
    }

    fun loadMoreReceivables() {
        val data = uiState.receivables ?: return
        if (uiState.isLoadingReceivablesMore || !data.meta.hasMore) {
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(isLoadingReceivablesMore = true)
            runCatching {
                container.financeRepository.receivables(
                    page = data.meta.currentPage + 1,
                    filter = uiState.receivablesFilter,
                )
            }.onSuccess { response ->
                uiState = uiState.copy(
                    isLoadingReceivablesMore = false,
                    receivables = response.copy(items = data.items + response.items),
                )
            }.onFailure {
                uiState = uiState.copy(
                    isLoadingReceivablesMore = false,
                    error = it.message ?: "Não foi possível carregar mais contas a receber.",
                )
            }
        }
    }

    fun loadMorePayables() {
        val data = uiState.payables ?: return
        if (uiState.isLoadingPayablesMore || !data.meta.hasMore) {
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(isLoadingPayablesMore = true)
            runCatching {
                container.financeRepository.payables(
                    page = data.meta.currentPage + 1,
                    filter = uiState.payablesFilter,
                )
            }.onSuccess { response ->
                uiState = uiState.copy(
                    isLoadingPayablesMore = false,
                    payables = response.copy(items = data.items + response.items),
                )
            }.onFailure {
                uiState = uiState.copy(
                    isLoadingPayablesMore = false,
                    error = it.message ?: "Não foi possível carregar mais contas a pagar.",
                )
            }
        }
    }

    fun loadMoreCashflow() {
        val data = uiState.cashflow ?: return
        if (uiState.isLoadingCashflowMore || !data.meta.hasMore) {
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(isLoadingCashflowMore = true)
            runCatching {
                container.financeRepository.cashflow(
                    page = data.meta.currentPage + 1,
                    period = uiState.cashflowPeriod,
                )
            }.onSuccess { response ->
                uiState = uiState.copy(
                    isLoadingCashflowMore = false,
                    cashflow = response.copy(items = data.items + response.items),
                )
            }.onFailure {
                uiState = uiState.copy(
                    isLoadingCashflowMore = false,
                    error = it.message ?: "Não foi possível carregar mais lançamentos do fluxo de caixa.",
                )
            }
        }
    }

    private fun ensureTabLoaded(tab: FinanceTab, force: Boolean) {
        viewModelScope.launch {
            when (tab) {
                FinanceTab.Receivables -> if (force || uiState.receivables == null) {
                    runCatching {
                        container.financeRepository.receivables(filter = uiState.receivablesFilter)
                    }.onSuccess { response ->
                        uiState = uiState.copy(receivables = response, error = null)
                    }.onFailure {
                        uiState = uiState.copy(error = it.message ?: "Não foi possível carregar as contas a receber.")
                    }
                }

                FinanceTab.Payables -> if (force || uiState.payables == null) {
                    runCatching {
                        container.financeRepository.payables(filter = uiState.payablesFilter)
                    }.onSuccess { response ->
                        uiState = uiState.copy(payables = response, error = null)
                    }.onFailure {
                        uiState = uiState.copy(error = it.message ?: "Não foi possível carregar as contas a pagar.")
                    }
                }

                FinanceTab.Cashflow -> if (force || uiState.cashflow == null) {
                    runCatching {
                        container.financeRepository.cashflow(period = uiState.cashflowPeriod)
                    }.onSuccess { response ->
                        uiState = uiState.copy(cashflow = response, error = null)
                    }.onFailure {
                        uiState = uiState.copy(error = it.message ?: "Não foi possível carregar o fluxo de caixa.")
                    }
                }
            }
        }
    }
}

@Composable
fun FinanceScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onBack: () -> Unit,
) {
    val viewModel: FinanceViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return FinanceViewModel(container) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Financeiro 360",
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
            viewModel.uiState.isLoading && viewModel.uiState.dashboard == null -> {
                AncoraLoadingState(label = "Carregando o Financeiro 360...")
            }

            viewModel.uiState.error != null && viewModel.uiState.dashboard == null -> {
                AncoraErrorState(
                    title = "Não foi possível carregar as informações.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refreshAll,
                )
            }

            viewModel.uiState.dashboard == null -> {
                AncoraEmptyState(
                    title = "Nenhum registro encontrado.",
                    message = "Assim que o Financeiro 360 responder, o resumo aparecerá aqui.",
                    actionLabel = "Atualizar",
                    onAction = viewModel::refreshAll,
                )
            }

            else -> {
                FinanceContent(
                    uiState = viewModel.uiState,
                    onTabChange = viewModel::changeTab,
                    onReceivablesFilterChange = viewModel::updateReceivablesFilter,
                    onPayablesFilterChange = viewModel::updatePayablesFilter,
                    onCashflowPeriodChange = viewModel::updateCashflowPeriod,
                    onLoadMoreReceivables = viewModel::loadMoreReceivables,
                    onLoadMorePayables = viewModel::loadMorePayables,
                    onLoadMoreCashflow = viewModel::loadMoreCashflow,
                )
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun FinanceContent(
    uiState: FinanceUiState,
    onTabChange: (FinanceTab) -> Unit,
    onReceivablesFilterChange: (String) -> Unit,
    onPayablesFilterChange: (String) -> Unit,
    onCashflowPeriodChange: (String) -> Unit,
    onLoadMoreReceivables: () -> Unit,
    onLoadMorePayables: () -> Unit,
    onLoadMoreCashflow: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    val dashboard = uiState.dashboard ?: return

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.lg),
        verticalArrangement = Arrangement.spacedBy(spacing.md),
    ) {
        item {
            AncoraCard(bordered = true) {
                AncoraStatusChip(
                    label = dashboard.summary.monthLabel ?: "Mês atual",
                    tone = AncoraTone.Brand,
                )
                Text(
                    text = "Acompanhamento financeiro do escritório",
                    style = MaterialTheme.typography.headlineSmall,
                )
                Text(
                    text = "Receitas, despesas e saldo previsto para apoiar decisões rápidas no celular.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }

        item {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(spacing.md),
                verticalArrangement = Arrangement.spacedBy(spacing.md),
            ) {
                dashboard.cards.forEach { card ->
                    AncoraMetricCard(
                        title = card.title,
                        value = card.valueLabel ?: "R$ 0,00",
                        description = card.description,
                        tone = when (card.tone) {
                            "success" -> AncoraTone.Success
                            "warning" -> AncoraTone.Warning
                            "info" -> AncoraTone.Info
                            else -> AncoraTone.Brand
                        },
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            }
        }

        if (dashboard.alerts.isNotEmpty()) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                    AncoraSectionTitle(title = "Alertas")
                    dashboard.alerts.forEach { alert ->
                        AncoraCard(bordered = true) {
                            AncoraStatusChip(
                                label = alert.title,
                                tone = when (alert.tone) {
                                    "info" -> AncoraTone.Info
                                    else -> AncoraTone.Warning
                                },
                            )
                            Text(
                                text = alert.message,
                                style = MaterialTheme.typography.bodyLarge,
                            )
                        }
                    }
                }
            }
        }

        item {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                verticalArrangement = Arrangement.spacedBy(spacing.sm),
            ) {
                FinanceTab.entries.forEach { tab ->
                    FilterChip(
                        selected = uiState.activeTab == tab,
                        onClick = { onTabChange(tab) },
                        label = { Text(tab.label) },
                    )
                }
            }
        }

        when (uiState.activeTab) {
            FinanceTab.Receivables -> {
                val data = uiState.receivables
                item {
                    FilterOptionsRow(
                        title = "Contas a receber",
                        options = data?.filters.orEmpty(),
                        selectedValue = uiState.receivablesFilter,
                        onSelected = onReceivablesFilterChange,
                    )
                }
                if (data == null || data.items.isEmpty()) {
                    item {
                        AncoraEmptyState(
                            title = "Nenhum registro encontrado.",
                            message = "Nenhuma conta a receber foi encontrada para o filtro atual.",
                        )
                    }
                } else {
                    items(data.items, key = { it.id }) { item ->
                        FinanceReceivableCard(item)
                    }
                    if (data.meta.hasMore) {
                        item {
                            AncoraSecondaryButton(
                                text = if (uiState.isLoadingReceivablesMore) "Carregando..." else "Carregar mais",
                                enabled = !uiState.isLoadingReceivablesMore,
                                onClick = onLoadMoreReceivables,
                            )
                        }
                    }
                }
            }

            FinanceTab.Payables -> {
                val data = uiState.payables
                item {
                    FilterOptionsRow(
                        title = "Contas a pagar",
                        options = data?.filters.orEmpty(),
                        selectedValue = uiState.payablesFilter,
                        onSelected = onPayablesFilterChange,
                    )
                }
                if (data == null || data.items.isEmpty()) {
                    item {
                        AncoraEmptyState(
                            title = "Nenhum registro encontrado.",
                            message = "Nenhuma conta a pagar foi encontrada para o filtro atual.",
                        )
                    }
                } else {
                    items(data.items, key = { it.id }) { item ->
                        FinancePayableCard(item)
                    }
                    if (data.meta.hasMore) {
                        item {
                            AncoraSecondaryButton(
                                text = if (uiState.isLoadingPayablesMore) "Carregando..." else "Carregar mais",
                                enabled = !uiState.isLoadingPayablesMore,
                                onClick = onLoadMorePayables,
                            )
                        }
                    }
                }
            }

            FinanceTab.Cashflow -> {
                val data = uiState.cashflow
                item {
                    FilterOptionsRow(
                        title = "Fluxo de caixa",
                        options = listOf(
                            FilterValueOption("7d", "7 dias"),
                            FilterValueOption("30d", "30 dias"),
                            FilterValueOption("90d", "90 dias"),
                        ),
                        selectedValue = uiState.cashflowPeriod,
                        onSelected = onCashflowPeriodChange,
                    )
                }
                item {
                    AncoraCard(bordered = true) {
                        InfoRow("Entradas", data?.summary?.entradasLabel ?: "R$ 0,00")
                        InfoRow("Saídas", data?.summary?.saidasLabel ?: "R$ 0,00")
                        InfoRow("Saldo", data?.summary?.saldoLabel ?: "R$ 0,00")
                    }
                }
                if (data == null || data.items.isEmpty()) {
                    item {
                        AncoraEmptyState(
                            title = "Nenhum registro encontrado.",
                            message = "Nenhum lançamento do fluxo de caixa foi encontrado para o período atual.",
                        )
                    }
                } else {
                    items(data.items, key = { it.id }) { item ->
                        FinanceCashflowCard(item)
                    }
                    if (data.meta.hasMore) {
                        item {
                            AncoraSecondaryButton(
                                text = if (uiState.isLoadingCashflowMore) "Carregando..." else "Carregar mais",
                                enabled = !uiState.isLoadingCashflowMore,
                                onClick = onLoadMoreCashflow,
                            )
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun FilterOptionsRow(
    title: String,
    options: List<FilterValueOption>,
    selectedValue: String,
    onSelected: (String) -> Unit,
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

@Composable
private fun FinanceReceivableCard(item: FinanceReceivableItem) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(label = item.statusLabel, tone = financeTone(item.status))
            Text(
                text = item.amountLabel ?: "R$ 0,00",
                style = MaterialTheme.typography.titleMedium,
            )
        }
        Text(text = item.title, style = MaterialTheme.typography.titleMedium)
        item.clientName?.let { Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        item.condominiumName?.let { Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        InfoRow("Vencimento", item.dueDateBr ?: "Não informado")
        InfoRow("Em aberto", item.outstandingAmountLabel ?: "R$ 0,00")
    }
}

@Composable
private fun FinancePayableCard(item: FinancePayableItem) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(label = item.statusLabel, tone = financeTone(item.status))
            Text(
                text = item.amountLabel ?: "R$ 0,00",
                style = MaterialTheme.typography.titleMedium,
            )
        }
        Text(text = item.title, style = MaterialTheme.typography.titleMedium)
        item.supplierName?.let { Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        InfoRow("Vencimento", item.dueDateBr ?: "Não informado")
        InfoRow("Em aberto", item.outstandingAmountLabel ?: "R$ 0,00")
    }
}

@Composable
private fun FinanceCashflowCard(item: FinanceCashflowItem) {
    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
        ) {
            AncoraStatusChip(label = item.transactionTypeLabel, tone = financeTone(item.transactionType))
            Text(
                text = item.amountLabel ?: "R$ 0,00",
                style = MaterialTheme.typography.titleMedium,
            )
        }
        Text(text = item.description, style = MaterialTheme.typography.titleMedium)
        InfoRow("Data", item.transactionDateBr ?: "Não informada")
        InfoRow("Conta", item.accountName ?: "Não informada")
        InfoRow("Categoria", item.categoryName ?: "Não informada")
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Column(verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.xs)) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyLarge,
        )
    }
}

private fun financeTone(status: String): AncoraTone =
    when (status.lowercase()) {
        "recebido", "pago", "entrada" -> AncoraTone.Success
        "vencido", "aberto", "parcial", "saida", "reembolso", "repasse" -> AncoraTone.Warning
        else -> AncoraTone.Info
    }
