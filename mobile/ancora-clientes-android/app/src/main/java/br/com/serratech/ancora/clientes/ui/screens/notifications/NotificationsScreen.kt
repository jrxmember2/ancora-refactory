package br.com.serratech.ancora.clientes.ui.screens.notifications

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.MaterialTheme
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
import br.com.serratech.ancora.clientes.domain.model.NotificationItem
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.AncoraTopBar
import br.com.serratech.ancora.clientes.ui.components.EmptyState
import br.com.serratech.ancora.clientes.ui.components.ErrorState
import br.com.serratech.ancora.clientes.ui.components.LoadingState
import kotlinx.coroutines.launch

class NotificationsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var isLoading by mutableStateOf(true)
        private set
    var error by mutableStateOf<String?>(null)
        private set
    var items by mutableStateOf<List<NotificationItem>>(emptyList())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            isLoading = true
            error = null
            runCatching { container.notificationRepository.list() }
                .onSuccess { items = it; isLoading = false }
                .onFailure { error = it.message ?: "Não foi possível carregar as notificações."; isLoading = false }
        }
    }

    fun readAll() {
        viewModelScope.launch {
            runCatching { container.notificationRepository.readAll() }
            refresh()
        }
    }
}

@Composable
fun NotificationsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
) {
    val viewModel: NotificationsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return NotificationsViewModel(container) as T
            }
        }
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Notificações", actions = { TextButton(onClick = viewModel::readAll) { Text("Ler tudo") } })
        when {
            viewModel.isLoading -> LoadingState("Buscando notificações...")
            viewModel.error != null -> ErrorState(viewModel.error.orEmpty(), onRetry = viewModel::refresh)
            viewModel.items.isEmpty() -> EmptyState("Sem notificações por enquanto", "As atualizações públicas do seu portal aparecerão aqui.")
            else -> LazyColumn(
                contentPadding = PaddingValues(20.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                items(viewModel.items) { item ->
                    AncoraCard {
                        Text(item.title, style = MaterialTheme.typography.titleMedium)
                        Text(item.body)
                        item.createdAtBr?.let {
                            Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
        }
    }
}
