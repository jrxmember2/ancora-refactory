package br.com.serratech.ancora.hub.ui.screens.dashboard

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Assignment
import androidx.compose.material.icons.outlined.AccountCircle
import androidx.compose.material.icons.outlined.Gavel
import androidx.compose.material.icons.outlined.Groups
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.DashboardData
import br.com.serratech.ancora.hub.domain.model.NotificationItem
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraMetricCard
import br.com.serratech.ancora.hub.ui.components.AncoraModuleCard
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraShortcutCard
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.ancoraToneFromAccent
import br.com.serratech.ancora.hub.ui.theme.spacing
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import kotlinx.coroutines.launch

data class DashboardUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val data: DashboardData? = null,
)

class DashboardViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(DashboardUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.dashboardRepository.dashboard() }
                .onSuccess { data ->
                    uiState = uiState.copy(
                        isLoading = false,
                        data = data,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar o dashboard agora.",
                    )
                }
        }
    }
}

@Composable
fun DashboardScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    sessionUser: SessionUser?,
    onUnreadCountChanged: (Int) -> Unit,
    onOpenDemands: () -> Unit,
    onOpenProcesses: () -> Unit,
    onOpenCollections: () -> Unit,
    onOpenClients: () -> Unit,
    onOpenNotifications: () -> Unit,
    onOpenProfile: () -> Unit,
    onOpenMore: () -> Unit,
    onOpenRoute: (String) -> Unit,
    onOpenNotification: (NotificationItem) -> Unit,
) {
    val viewModel: DashboardViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return DashboardViewModel(container) as T
            }
        },
    )

    LaunchedEffect(viewModel.uiState.data?.unreadNotificationsCount) {
        onUnreadCountChanged(viewModel.uiState.data?.unreadNotificationsCount ?: 0)
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Âncora Hub")

        when {
            viewModel.uiState.isLoading -> {
                AncoraLoadingState(label = "Carregando o dashboard executivo...")
            }

            viewModel.uiState.error != null -> {
                AncoraErrorState(
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            viewModel.uiState.data == null -> {
                AncoraEmptyState(
                    title = "Nenhum registro encontrado.",
                    message = "Assim que o dashboard interno responder, o resumo executivo aparecerá aqui.",
                    actionLabel = "Atualizar",
                    onAction = viewModel::refresh,
                )
            }

            else -> {
                DashboardContent(
                    data = viewModel.uiState.data!!,
                    sessionUser = sessionUser,
                    onRefresh = viewModel::refresh,
                    onOpenDemands = onOpenDemands,
                    onOpenProcesses = onOpenProcesses,
                    onOpenCollections = onOpenCollections,
                    onOpenClients = onOpenClients,
                    onOpenNotifications = onOpenNotifications,
                    onOpenProfile = onOpenProfile,
                    onOpenMore = onOpenMore,
                    onOpenRoute = onOpenRoute,
                    onOpenNotification = onOpenNotification,
                )
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun DashboardContent(
    data: DashboardData,
    sessionUser: SessionUser?,
    onRefresh: () -> Unit,
    onOpenDemands: () -> Unit,
    onOpenProcesses: () -> Unit,
    onOpenCollections: () -> Unit,
    onOpenClients: () -> Unit,
    onOpenNotifications: () -> Unit,
    onOpenProfile: () -> Unit,
    onOpenMore: () -> Unit,
    onOpenRoute: (String) -> Unit,
    onOpenNotification: (NotificationItem) -> Unit,
) {
    val spacing = MaterialTheme.spacing
    val screenWidthDp = LocalConfiguration.current.screenWidthDp
    val cardWidth = ((screenWidthDp - 56) / 2).coerceAtLeast(148)
    val currentUser = sessionUser ?: data.user
    val updatedAtLabel = data.updatedAt.toUpdatedAtLabel()

    LazyColumn(
        contentPadding = PaddingValues(
            horizontal = spacing.lg,
            vertical = spacing.lg,
        ),
        verticalArrangement = Arrangement.spacedBy(spacing.md),
    ) {
        item {
            AncoraCard(
                contentPadding = PaddingValues(0.dp),
                bordered = true,
            ) {
                Box(modifier = Modifier.fillMaxWidth()) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(spacing.lg),
                        verticalArrangement = Arrangement.spacedBy(spacing.sm),
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                        ) {
                            AncoraStatusChip(
                                label = "Última atualização • $updatedAtLabel",
                                tone = AncoraTone.Brand,
                            )
                            AncoraStatusChip(
                                label = if (data.summary.hasCriticalAlerts) {
                                    "${data.summary.criticalAlertsCount} alertas"
                                } else {
                                    "${data.summary.unreadNotificationsCount} notificações"
                                },
                                tone = if (data.summary.hasCriticalAlerts) {
                                    AncoraTone.Warning
                                } else {
                                    AncoraTone.Success
                                },
                            )
                        }

                        Text(
                            text = data.greeting,
                            style = MaterialTheme.typography.headlineMedium,
                        )
                        Text(
                            text = currentUser?.name ?: "Equipe do Âncora",
                            style = MaterialTheme.typography.titleLarge,
                        )
                        Text(
                            text = data.summary.focusMessage,
                            style = MaterialTheme.typography.bodyLarge,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )

                        FlowRow(
                            horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                            verticalArrangement = Arrangement.spacedBy(spacing.sm),
                        ) {
                            AncoraStatusChip(
                                label = "${data.summary.activeModulesCount} módulos",
                                tone = AncoraTone.Info,
                            )
                            AncoraStatusChip(
                                label = "${data.summary.availableShortcutsCount} atalhos",
                                tone = AncoraTone.Neutral,
                            )
                            AncoraStatusChip(
                                label = "${data.unreadNotificationsCount} não lidas",
                                tone = AncoraTone.Brand,
                            )
                        }
                    }
                }
            }
        }

        if (data.alerts.isNotEmpty()) {
            item {
                AncoraSectionTitle(title = "Alertas do dia")
            }

            item {
                FlowRow(
                    horizontalArrangement = Arrangement.spacedBy(spacing.md),
                    verticalArrangement = Arrangement.spacedBy(spacing.md),
                ) {
                    data.alerts.take(4).forEach { alert ->
                        AncoraModuleCard(
                            title = alert.title,
                            description = alert.message,
                            tone = ancoraToneFromAccent(alert.accent),
                            statusLabel = alert.actionLabel,
                            enabled = !alert.route.isNullOrBlank(),
                            modifier = Modifier.width(cardWidth.dp),
                            onClick = {
                                alert.route?.let(onOpenRoute)
                            },
                        )
                    }
                }
            }
        }

        item {
            AncoraSectionTitle(
                title = "Indicadores",
                trailing = {
                    TextButton(onClick = onRefresh) {
                        Text("Atualizar")
                    }
                },
            )
        }

        item {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(spacing.md),
                verticalArrangement = Arrangement.spacedBy(spacing.md),
            ) {
                data.cards.forEach { card ->
                    val route = card.route
                    AncoraMetricCard(
                        title = card.title,
                        value = card.value.toString(),
                        description = card.description,
                        tone = ancoraToneFromAccent(card.accent),
                        modifier = Modifier.width(cardWidth.dp),
                        onClick = if (card.isClickable && !route.isNullOrBlank()) {
                            { onOpenRoute(route) }
                        } else {
                            null
                        },
                    )
                }
            }
        }

        item {
            AncoraSectionTitle(title = "Atalhos rápidos")
        }

        item {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(spacing.md),
                verticalArrangement = Arrangement.spacedBy(spacing.md),
            ) {
                quickActions(
                    user = currentUser,
                    onOpenDemands = onOpenDemands,
                    onOpenProcesses = onOpenProcesses,
                    onOpenCollections = onOpenCollections,
                    onOpenClients = onOpenClients,
                    onOpenNotifications = onOpenNotifications,
                    onOpenProfile = onOpenProfile,
                ).forEach { action ->
                    AncoraShortcutCard(
                        title = action.title,
                        description = action.description,
                        tone = action.tone,
                        icon = action.icon,
                        enabled = action.enabled,
                        modifier = Modifier.width(cardWidth.dp),
                        onClick = action.onClick,
                    )
                }
            }
        }

        if (data.shortcuts.isNotEmpty()) {
            item {
                AncoraSectionTitle(title = "Acessos internos")
            }

            item {
                FlowRow(
                    horizontalArrangement = Arrangement.spacedBy(spacing.md),
                    verticalArrangement = Arrangement.spacedBy(spacing.md),
                ) {
                    data.shortcuts.take(6).forEach { shortcut ->
                        AncoraModuleCard(
                            title = shortcut.title,
                            description = shortcut.description,
                            tone = ancoraToneFromAccent(shortcut.accent),
                            statusLabel = "Disponível",
                            enabled = !shortcut.route.isNullOrBlank(),
                            modifier = Modifier.width(cardWidth.dp),
                            onClick = {
                                shortcut.route?.let(onOpenRoute) ?: onOpenMore()
                            },
                        )
                    }
                }
            }
        }

        item {
            AncoraSectionTitle(
                title = "Notificações",
                trailing = {
                    TextButton(onClick = onOpenNotifications) {
                        Text("Ver tudo")
                    }
                },
            )
        }

        if (data.notifications.isEmpty()) {
            item {
                AncoraEmptyState(
                    title = "Você ainda não possui notificações.",
                    message = "Quando houver atualizações importantes do escritório, elas aparecerão aqui.",
                )
            }
        } else {
            items(
                items = data.notifications.take(4),
                key = { notification -> notification.id },
            ) { notification ->
                AncoraCard(
                    bordered = notification.readAt == null,
                    modifier = Modifier.clickable {
                        onOpenNotification(notification)
                    },
                ) {
                    AncoraStatusChip(
                        label = if (notification.readAt == null) "Não lida" else "Lida",
                        tone = if (notification.readAt == null) {
                            AncoraTone.Brand
                        } else {
                            AncoraTone.Neutral
                        },
                    )
                    Text(
                        text = notification.title,
                        style = MaterialTheme.typography.titleMedium,
                    )
                    Text(
                        text = notification.body,
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text(
                        text = notification.createdAtBr ?: "Agora mesmo",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text(
                        text = notification.actionLabel ?: "Ver detalhes",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }

        item {
            AncoraCard(bordered = true) {
                AncoraSectionTitle(title = "Mais do Âncora")
                Text(
                    text = "Abra o menu “Mais” para acessar Clientes, Propostas, Contratos, Assinador, Financeiro 360, Leme IA, Perfil e Configurações.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                AncoraButton(
                    text = "Abrir menu Mais",
                    onClick = onOpenMore,
                )
            }
        }
    }
}

private data class QuickAction(
    val title: String,
    val description: String,
    val tone: AncoraTone,
    val icon: androidx.compose.ui.graphics.vector.ImageVector,
    val enabled: Boolean,
    val onClick: () -> Unit,
)

private fun quickActions(
    user: SessionUser?,
    onOpenDemands: () -> Unit,
    onOpenProcesses: () -> Unit,
    onOpenCollections: () -> Unit,
    onOpenClients: () -> Unit,
    onOpenNotifications: () -> Unit,
    onOpenProfile: () -> Unit,
): List<QuickAction> {
    val moduleSlugs = user?.modules
        ?.filter { it.enabled }
        ?.map { it.slug.lowercase() }
        ?.toSet()
        .orEmpty()

    fun enabled(vararg aliases: String): Boolean {
        if (user?.isSuperadmin == true || moduleSlugs.isEmpty()) {
            return true
        }

        return aliases.any { alias -> moduleSlugs.contains(alias.lowercase()) }
    }

    return listOf(
        QuickAction(
            title = "Demandas",
            description = "Acompanhe solicitações e prioridades internas.",
            tone = AncoraTone.Info,
            icon = Icons.AutoMirrored.Outlined.Assignment,
            enabled = enabled("demandas", "demanda"),
            onClick = onOpenDemands,
        ),
        QuickAction(
            title = "Processos",
            description = "Acesse a visão móvel dos processos do escritório.",
            tone = AncoraTone.Brand,
            icon = Icons.Outlined.Gavel,
            enabled = enabled("processos", "processo"),
            onClick = onOpenProcesses,
        ),
        QuickAction(
            title = "Cobranças",
            description = "Consulte a frente de Cobranças com acesso rápido.",
            tone = AncoraTone.Warning,
            icon = Icons.Outlined.Payments,
            enabled = enabled("cobrancas", "cobrança"),
            onClick = onOpenCollections,
        ),
        QuickAction(
            title = "Clientes",
            description = "Veja os cadastros de clientes e relacionamento.",
            tone = AncoraTone.Success,
            icon = Icons.Outlined.Groups,
            enabled = enabled("clientes", "cliente"),
            onClick = onOpenClients,
        ),
        QuickAction(
            title = "Notificações",
            description = "Abra sua central de notificações do aplicativo.",
            tone = AncoraTone.Brand,
            icon = Icons.Outlined.Notifications,
            enabled = true,
            onClick = onOpenNotifications,
        ),
        QuickAction(
            title = "Perfil",
            description = "Revise permissões, biometria e sessão deste aparelho.",
            tone = AncoraTone.Neutral,
            icon = Icons.Outlined.AccountCircle,
            enabled = true,
            onClick = onOpenProfile,
        ),
    )
}

private fun String?.toUpdatedAtLabel(): String {
    if (this.isNullOrBlank()) {
        return "Agora mesmo"
    }

    return runCatching {
        OffsetDateTime.parse(this).format(DateTimeFormatter.ofPattern("dd/MM HH:mm"))
    }.getOrElse {
        this
    }
}
