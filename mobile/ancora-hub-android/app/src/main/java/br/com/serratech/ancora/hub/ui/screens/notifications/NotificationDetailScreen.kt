package br.com.serratech.ancora.hub.ui.screens.notifications

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.NotificationItem
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class NotificationDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val notification: NotificationItem? = null,
    val didMarkAsRead: Boolean = false,
)

class NotificationDetailViewModel(
    private val container: AppContainer,
    private val notificationId: Long,
) : ViewModel() {
    var uiState by mutableStateOf(NotificationDetailUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null, didMarkAsRead = false)

            runCatching {
                val loaded = container.notificationRepository.findById(notificationId)
                if (loaded != null && loaded.readAt == null) {
                    runCatching { container.notificationRepository.read(notificationId) }
                    container.notificationRepository.findById(notificationId)
                        ?: loaded.copy(readAt = "marked")
                } else {
                    loaded
                }
            }.onSuccess { notification ->
                uiState = uiState.copy(
                    isLoading = false,
                    notification = notification,
                    didMarkAsRead = notification?.readAt != null,
                )
            }.onFailure {
                uiState = uiState.copy(
                    isLoading = false,
                    error = it.message ?: "Não foi possível carregar as informações.",
                )
            }
        }
    }
}

@Composable
fun NotificationDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    notificationId: Long,
    onUnreadCountChanged: (Int) -> Unit,
    onOpenRoute: (String) -> Unit,
    onBack: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    val viewModel: NotificationDetailViewModel = viewModel(
        key = "notification-detail-$notificationId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return NotificationDetailViewModel(container, notificationId) as T
            }
        },
    )

    LaunchedEffect(viewModel.uiState.didMarkAsRead) {
        if (viewModel.uiState.didMarkAsRead) {
            onUnreadCountChanged(
                runCatching { container.notificationRepository.unreadCount() }.getOrDefault(0),
            )
        }
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Notificações",
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
            viewModel.uiState.isLoading -> {
                AncoraLoadingState(label = "Abrindo notificação...")
            }

            viewModel.uiState.error != null -> {
                AncoraErrorState(
                    title = viewModel.uiState.error.orEmpty(),
                    message = "Verifique sua conexão e tente novamente.",
                    onRetry = viewModel::refresh,
                )
            }

            viewModel.uiState.notification == null -> {
                AncoraEmptyState(
                    title = "Nenhuma notificação encontrada",
                    message = "Essa notificação pode ter sido removida ou ainda não foi sincronizada.",
                    actionLabel = "Atualizar",
                    onAction = viewModel::refresh,
                )
            }

            else -> {
                val notification = viewModel.uiState.notification!!
                val route = notification.route

                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(horizontal = spacing.lg, vertical = spacing.lg),
                    verticalArrangement = Arrangement.spacedBy(spacing.md),
                ) {
                    AncoraCard(bordered = true) {
                        AncoraStatusChip(
                            label = if (notification.readAt == null) "Não lida" else "Lida",
                            tone = if (notification.readAt == null) AncoraTone.Brand else AncoraTone.Neutral,
                        )
                        Text(
                            text = notification.title,
                            style = MaterialTheme.typography.headlineSmall,
                        )
                        Text(
                            text = notification.body,
                            style = MaterialTheme.typography.bodyLarge,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }

                    AncoraCard {
                        Text(
                            text = "Detalhes",
                            style = MaterialTheme.typography.titleMedium,
                        )
                        notification.module?.takeIf { it.isNotBlank() }?.let { module ->
                            Text(
                                text = "Módulo: $module",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        notification.createdAtBr?.let { createdAt ->
                            Text(
                                text = "Recebida em $createdAt",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        notification.type?.takeIf { it.isNotBlank() }?.let { type ->
                            Text(
                                text = "Tipo: $type",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }

                    if (!route.isNullOrBlank() && route != "notifications") {
                        AncoraButton(
                            text = notification.actionLabel ?: "Ver detalhes",
                            modifier = Modifier.fillMaxWidth(),
                            onClick = { onOpenRoute(route) },
                        )
                    }
                }
            }
        }
    }
}
