package br.com.serratech.ancora.hub.ui.screens.notifications

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
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
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.NotificationFeed
import br.com.serratech.ancora.hub.domain.model.NotificationFilter
import br.com.serratech.ancora.hub.domain.model.NotificationItem
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSearchBar
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class NotificationsUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val feed: NotificationFeed = NotificationFeed(emptyList(), 0),
    val filter: NotificationFilter = NotificationFilter.All,
)

class NotificationsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(NotificationsUiState())
        private set

    init {
        refresh()
    }

    fun updateFilter(filter: NotificationFilter) {
        if (uiState.filter == filter) {
            return
        }

        uiState = uiState.copy(filter = filter)
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.notificationRepository.list(uiState.filter) }
                .onSuccess { feed ->
                    uiState = uiState.copy(
                        isLoading = false,
                        feed = feed,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        error = it.message ?: "Não foi possível carregar as informações.",
                    )
                }
        }
    }

    fun readAll() {
        viewModelScope.launch {
            runCatching { container.notificationRepository.readAll() }
            refresh()
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun NotificationsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onUnreadCountChanged: (Int) -> Unit,
    onOpenNotification: (NotificationItem) -> Unit,
    onBack: (() -> Unit)? = null,
) {
    val spacing = MaterialTheme.spacing
    var query by rememberSaveable { mutableStateOf("") }
    val viewModel: NotificationsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return NotificationsViewModel(container) as T
            }
        },
    )

    LaunchedEffect(viewModel.uiState.feed.unreadCount) {
        onUnreadCountChanged(viewModel.uiState.feed.unreadCount)
    }

    val filteredItems = viewModel.uiState.feed.items.filter { item ->
        if (query.isBlank()) {
            true
        } else {
            val normalizedQuery = query.trim().lowercase()
            item.title.lowercase().contains(normalizedQuery) ||
                item.body.lowercase().contains(normalizedQuery) ||
                item.module.orEmpty().lowercase().contains(normalizedQuery)
        }
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Notificações",
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
        )

        when {
            viewModel.uiState.isLoading -> {
                AncoraLoadingState(label = "Buscando suas notificações...")
            }

            viewModel.uiState.error != null -> {
                AncoraErrorState(
                    title = viewModel.uiState.error.orEmpty(),
                    message = "Verifique sua conexão e tente novamente.",
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
                        AncoraSearchBar(
                            query = query,
                            onQueryChange = { query = it },
                            placeholder = "Buscar notificação",
                        )
                    }

                    item {
                        AncoraCard(bordered = true) {
                            Text(
                                text = "Filtrar notificações",
                                style = MaterialTheme.typography.titleMedium,
                            )

                            FlowRow(
                                horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                                verticalArrangement = Arrangement.spacedBy(spacing.sm),
                            ) {
                                FilterChip(
                                    selected = viewModel.uiState.filter == NotificationFilter.All,
                                    onClick = { viewModel.updateFilter(NotificationFilter.All) },
                                    label = { Text("Todas") },
                                )
                                FilterChip(
                                    selected = viewModel.uiState.filter == NotificationFilter.Unread,
                                    onClick = { viewModel.updateFilter(NotificationFilter.Unread) },
                                    label = { Text("Não lidas") },
                                )
                                AncoraStatusChip(
                                    label = "${viewModel.uiState.feed.unreadCount} não lidas",
                                    tone = AncoraTone.Brand,
                                )
                            }

                            if (viewModel.uiState.feed.unreadCount > 0) {
                                AncoraSecondaryButton(
                                    text = "Marcar todas como lidas",
                                    onClick = viewModel::readAll,
                                )
                            }
                        }
                    }

                    if (viewModel.uiState.feed.items.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Você ainda não possui notificações.",
                                message = "Quando houver movimentações internas ou alertas do escritório, elas aparecerão aqui.",
                            )
                        }
                        return@LazyColumn
                    }

                    if (filteredItems.isEmpty()) {
                        item {
                            AncoraEmptyState(
                                title = "Nenhuma notificação encontrada",
                                message = "Tente ajustar a busca ou trocar o filtro selecionado.",
                            )
                        }
                        return@LazyColumn
                    }

                    items(
                        items = filteredItems,
                        key = { item -> item.id },
                    ) { item ->
                        AncoraCard(
                            bordered = item.readAt == null,
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { onOpenNotification(item) },
                        ) {
                            AncoraStatusChip(
                                label = if (item.readAt == null) "Não lida" else "Lida",
                                tone = if (item.readAt == null) AncoraTone.Brand else AncoraTone.Neutral,
                            )
                            Text(
                                text = item.title,
                                style = MaterialTheme.typography.titleMedium,
                            )
                            Text(
                                text = item.body,
                                style = MaterialTheme.typography.bodyLarge,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                            item.module?.takeIf { module -> module.isNotBlank() }?.let { module ->
                                Text(
                                    text = "Módulo: $module",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            Text(
                                text = item.createdAtBr ?: "Agora mesmo",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                            Text(
                                text = item.actionLabel ?: "Ver detalhes",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.primary,
                            )
                        }
                    }
                }
            }
        }
    }
}
