package br.com.serratech.ancora.clientes.ui.screens.leme

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.AssistChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.LemeHistory
import br.com.serratech.ancora.clientes.domain.model.LemeMessage
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.AncoraTopBar
import br.com.serratech.ancora.clientes.ui.components.ChatBubble
import br.com.serratech.ancora.clientes.ui.components.EmptyState
import br.com.serratech.ancora.clientes.ui.components.LoadingState
import br.com.serratech.ancora.clientes.ui.components.PrimaryButton
import kotlinx.coroutines.launch

data class LemeUiState(
    val isLoading: Boolean = true,
    val history: LemeHistory? = null,
    val error: String? = null,
    val input: String = "",
    val isSending: Boolean = false,
)

class LemeViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(LemeUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching { container.lemeRepository.history() }
                .onSuccess { uiState = uiState.copy(isLoading = false, history = it) }
                .onFailure { uiState = uiState.copy(isLoading = false, error = it.message ?: "Nao foi possivel carregar o historico da Leme.") }
        }
    }

    fun updateInput(value: String) {
        uiState = uiState.copy(input = value)
    }

    fun useSuggestion(value: String) {
        uiState = uiState.copy(input = value)
        send()
    }

    fun send() {
        if (uiState.input.isBlank()) return
        val optimisticMessages = (uiState.history?.messages ?: emptyList()) + LemeMessage(
            id = -System.currentTimeMillis(),
            role = "user",
            content = uiState.input,
            createdAtBr = "agora",
        )
        uiState = uiState.copy(
            history = uiState.history?.copy(messages = optimisticMessages),
            isSending = true,
            input = "",
        )

        viewModelScope.launch {
            runCatching {
                container.lemeRepository.sendMessage(
                    message = optimisticMessages.last().content,
                    conversationId = uiState.history?.conversationId,
                )
            }.onSuccess {
                uiState = uiState.copy(isSending = false, history = it, error = null)
            }.onFailure {
                uiState = uiState.copy(
                    isSending = false,
                    error = it.message ?: "Nao consegui responder agora. Tente novamente em alguns instantes.",
                )
            }
        }
    }

    fun clearHistory() {
        viewModelScope.launch {
            runCatching { container.lemeRepository.clear(uiState.history?.conversationId) }
            refresh()
        }
    }
}

@Composable
fun LemeScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
) {
    val viewModel: LemeViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return LemeViewModel(container) as T
            }
        }
    )
    val listState = rememberLazyListState()
    val clipboardManager = LocalClipboardManager.current
    val messages = viewModel.uiState.history?.messages.orEmpty()

    LaunchedEffect(messages.size, viewModel.uiState.isSending) {
        if (messages.isNotEmpty()) {
            listState.animateScrollToItem(messages.lastIndex)
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .navigationBarsPadding()
            .imePadding(),
    ) {
        AncoraTopBar(title = "Leme IA")
        if (viewModel.uiState.isLoading && viewModel.uiState.history == null) {
            LoadingState("Carregando conversa com a Leme...")
        } else {
            LazyColumn(
                modifier = Modifier.weight(1f),
                state = listState,
                verticalArrangement = Arrangement.spacedBy(12.dp),
                contentPadding = PaddingValues(20.dp),
            ) {
                viewModel.uiState.history?.activeCondominium?.let { condominium ->
                    item {
                        AncoraCard {
                            Text("Contexto atual", style = MaterialTheme.typography.titleMedium)
                            Text(condominium.name)
                            Text(
                                viewModel.uiState.history?.usageStatus?.message.orEmpty(),
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
                if (messages.isEmpty()) {
                    item {
                        EmptyState(
                            title = "Ola, eu sou o Leme IA.",
                            message = "Posso te ajudar com duvidas sobre solicitacoes, processos e informacoes disponiveis no seu portal.",
                        )
                    }
                    item {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            listOf(
                                "Ver minhas solicitacoes abertas",
                                "Tenho duvida sobre um processo",
                                "Como abrir uma solicitacao?",
                                "Quais documentos posso consultar?",
                                "Quero falar com o escritorio",
                            ).forEach { suggestion ->
                                AssistChip(
                                    onClick = { viewModel.useSuggestion(suggestion) },
                                    label = { Text(suggestion) },
                                )
                            }
                        }
                    }
                }
                items(messages) { message ->
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        ChatBubble(
                            message = message.content,
                            author = if (message.role == "user") "Voce" else "Leme IA",
                            timestamp = message.createdAtBr,
                            isMine = message.role == "user",
                        )
                        if (message.role != "user") {
                            TextButton(
                                modifier = Modifier.fillMaxWidth(),
                                onClick = {
                                    clipboardManager.setText(AnnotatedString(message.content))
                                },
                            ) {
                                Text("Copiar resposta")
                            }
                        }
                    }
                }
                if (viewModel.uiState.isSending) {
                    item {
                        AncoraCard {
                            Text("Leme esta digitando...", color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
        }

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            viewModel.uiState.error?.let {
                Text(it, color = MaterialTheme.colorScheme.error)
            }
            OutlinedTextField(
                value = viewModel.uiState.input,
                onValueChange = viewModel::updateInput,
                modifier = Modifier.fillMaxWidth(),
                minLines = 2,
                maxLines = 6,
                label = { Text("Digite sua mensagem") },
            )
            PrimaryButton(text = "Enviar", loading = viewModel.uiState.isSending, onClick = viewModel::send)
            TextButton(onClick = viewModel::clearHistory) { Text("Limpar conversa") }
        }
    }
}
