package br.com.serratech.ancora.clientes.ui.screens.leme

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Send
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.ArrowDropDown
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.CleaningServices
import androidx.compose.material.icons.outlined.ContentCopy
import androidx.compose.material.icons.outlined.History
import androidx.compose.material.icons.outlined.MoreVert
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Send
import androidx.compose.material3.AssistChip
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.Condominium
import br.com.serratech.ancora.clientes.domain.model.CondominiumContext
import br.com.serratech.ancora.clientes.domain.model.LemeHistory
import br.com.serratech.ancora.clientes.domain.model.LemeMessage
import br.com.serratech.ancora.clientes.domain.model.RecentConversation
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.EmptyState
import br.com.serratech.ancora.clientes.ui.components.LoadingState
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import androidx.compose.runtime.snapshotFlow

data class LemeUiState(
    val isLoading: Boolean = true,
    val history: LemeHistory? = null,
    val condominiumContext: CondominiumContext = CondominiumContext(
        selected = null,
        items = emptyList(),
    ),
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
        load()
    }

    fun refresh() {
        load(uiState.history?.conversationId)
    }

    fun openConversation(conversationId: Long) {
        load(conversationId)
    }

    fun updateInput(value: String) {
        uiState = uiState.copy(input = value, error = null)
    }

    fun useSuggestion(value: String) {
        uiState = uiState.copy(input = value, error = null)
        send()
    }

    fun startNewConversation() {
        val currentHistory = uiState.history ?: return
        uiState = uiState.copy(
            history = currentHistory.copy(
                conversationId = null,
                messages = emptyList(),
            ),
            error = null,
        )
    }

    fun updateCondominium(condominiumId: Long) {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = uiState.history == null, error = null)
            runCatching {
                container.condominiumRepository.updateSelected(condominiumId)
                val context = container.condominiumRepository.list()
                val history = container.lemeRepository.history()
                context to history
            }.onSuccess { (context, history) ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = null,
                    history = history,
                    condominiumContext = context,
                )
            }.onFailure { error ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = error.message ?: "Nao foi possivel trocar o condominio agora.",
                )
            }
        }
    }

    fun clearCurrentConversation() {
        val conversationId = uiState.history?.conversationId ?: return

        viewModelScope.launch {
            uiState = uiState.copy(isSending = false, error = null)
            runCatching { container.lemeRepository.clear(conversationId) }
                .onSuccess { load() }
                .onFailure { error ->
                    uiState = uiState.copy(
                        error = error.message ?: "Nao foi possivel limpar a conversa agora.",
                    )
                }
        }
    }

    fun send() {
        val currentHistory = uiState.history
        val messageText = uiState.input.trim()
        if (messageText.isBlank()) return

        val requiresCondominiumSelection =
            uiState.condominiumContext.items.size > 1 && currentHistory?.activeCondominium == null
        if (requiresCondominiumSelection) {
            uiState = uiState.copy(error = "Escolha um condominio antes de falar com a Leme.")
            return
        }

        val optimisticMessages = currentHistory?.messages.orEmpty() + LemeMessage(
            id = -System.currentTimeMillis(),
            role = "user",
            content = messageText,
            createdAtBr = "agora",
        )

        uiState = uiState.copy(
            history = currentHistory?.copy(messages = optimisticMessages),
            isSending = true,
            input = "",
            error = null,
        )

        viewModelScope.launch {
            runCatching {
                container.lemeRepository.sendMessage(
                    message = messageText,
                    conversationId = uiState.history?.conversationId,
                )
            }.onSuccess { history ->
                uiState = uiState.copy(
                    isSending = false,
                    history = history,
                    error = null,
                )
            }.onFailure { error ->
                uiState = uiState.copy(
                    isSending = false,
                    error = error.message ?: "Nao consegui responder agora. Tente novamente em alguns instantes.",
                )
            }
        }
    }

    private fun load(conversationId: Long? = null) {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = uiState.history == null, error = null)
            runCatching {
                val context = container.condominiumRepository.list()
                val history = container.lemeRepository.history(conversationId)
                context to history
            }.onSuccess { (context, history) ->
                uiState = uiState.copy(
                    isLoading = false,
                    history = history,
                    condominiumContext = context,
                    error = null,
                )
            }.onFailure { error ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = error.message ?: "Nao foi possivel carregar a conversa da Leme.",
                )
            }
        }
    }
}

@Composable
fun LemeScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onBack: () -> Unit,
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
    val state = viewModel.uiState
    val messages = state.history?.messages.orEmpty()
    var showCondominiumMenu by remember { mutableStateOf(false) }
    var showActionsMenu by remember { mutableStateOf(false) }
    var showHistoryDialog by remember { mutableStateOf(false) }

    LaunchedEffect(messages.size, state.isSending, state.history?.conversationId) {
        snapshotFlow { listState.layoutInfo.totalItemsCount }
            .filter { it > 0 }
            .first()

        listState.animateScrollToItem(listState.layoutInfo.totalItemsCount - 1)
    }

    if (showHistoryDialog) {
        LemeHistoryDialog(
            conversations = state.history?.recentConversations.orEmpty(),
            currentConversationId = state.history?.conversationId,
            onDismiss = { showHistoryDialog = false },
            onSelectConversation = { conversationId ->
                showHistoryDialog = false
                viewModel.openConversation(conversationId)
            },
        )
    }

    Scaffold(
        modifier = modifier.fillMaxSize(),
        containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.18f),
        topBar = {
            Surface(shadowElevation = 4.dp) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 8.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                            contentDescription = "Voltar",
                        )
                    }

                    Column(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.spacedBy(2.dp),
                    ) {
                        Text(
                            text = "Leme IA",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.SemiBold,
                        )

                        Box {
                            Row(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(999.dp))
                                    .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f))
                                    .clickable(
                                        enabled = state.condominiumContext.items.size > 1,
                                        onClick = { showCondominiumMenu = true },
                                    )
                                    .padding(horizontal = 12.dp, vertical = 8.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(4.dp),
                            ) {
                                Text(
                                    text = state.history?.activeCondominium?.name
                                        ?: if (state.condominiumContext.items.size > 1) "Escolher condominio" else "Sem condominio selecionado",
                                    style = MaterialTheme.typography.labelLarge,
                                    color = MaterialTheme.colorScheme.primary,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis,
                                )

                                if (state.condominiumContext.items.size > 1) {
                                    Icon(
                                        imageVector = Icons.Outlined.ArrowDropDown,
                                        contentDescription = null,
                                        tint = MaterialTheme.colorScheme.primary,
                                    )
                                }
                            }

                            DropdownMenu(
                                expanded = showCondominiumMenu,
                                onDismissRequest = { showCondominiumMenu = false },
                            ) {
                                state.condominiumContext.items.forEach { condominium ->
                                    DropdownMenuItem(
                                        text = { Text(condominium.name, maxLines = 1, overflow = TextOverflow.Ellipsis) },
                                        onClick = {
                                            showCondominiumMenu = false
                                            viewModel.updateCondominium(condominium.id)
                                        },
                                    )
                                }
                            }
                        }
                    }

                    Box {
                        IconButton(onClick = { showActionsMenu = true }) {
                            Icon(
                                imageVector = Icons.Outlined.MoreVert,
                                contentDescription = "Mais opcoes",
                            )
                        }

                        DropdownMenu(
                            expanded = showActionsMenu,
                            onDismissRequest = { showActionsMenu = false },
                        ) {
                            DropdownMenuItem(
                                text = { Text("Historico") },
                                leadingIcon = {
                                    Icon(Icons.Outlined.History, contentDescription = null)
                                },
                                onClick = {
                                    showActionsMenu = false
                                    showHistoryDialog = true
                                },
                            )
                            DropdownMenuItem(
                                text = { Text("Atualizar") },
                                leadingIcon = {
                                    Icon(Icons.Outlined.Refresh, contentDescription = null)
                                },
                                onClick = {
                                    showActionsMenu = false
                                    viewModel.refresh()
                                },
                            )
                            DropdownMenuItem(
                                text = { Text("Nova conversa") },
                                leadingIcon = {
                                    Icon(Icons.Outlined.AutoAwesome, contentDescription = null)
                                },
                                onClick = {
                                    showActionsMenu = false
                                    viewModel.startNewConversation()
                                },
                            )
                            DropdownMenuItem(
                                enabled = state.history?.conversationId != null,
                                text = { Text("Limpar conversa atual") },
                                leadingIcon = {
                                    Icon(Icons.Outlined.CleaningServices, contentDescription = null)
                                },
                                onClick = {
                                    showActionsMenu = false
                                    viewModel.clearCurrentConversation()
                                },
                            )
                        }
                    }
                }
            }
        },
        bottomBar = {
            Surface(shadowElevation = 10.dp) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .imePadding(),
                    contentAlignment = Alignment.Center,
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .widthIn(max = 960.dp)
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        state.error?.let {
                            Text(
                                text = it,
                                color = MaterialTheme.colorScheme.error,
                                style = MaterialTheme.typography.bodySmall,
                            )
                        }

                        Text(
                            text = state.history?.usageStatus?.message.orEmpty(),
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.Bottom,
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                        ) {
                            OutlinedTextField(
                                value = state.input,
                                onValueChange = viewModel::updateInput,
                                modifier = Modifier.weight(1f),
                                minLines = 1,
                                maxLines = 5,
                                placeholder = { Text("Digite sua mensagem") },
                                enabled = !state.isLoading,
                            )

                            Surface(
                                modifier = Modifier.size(52.dp),
                                shape = RoundedCornerShape(18.dp),
                                color = MaterialTheme.colorScheme.primary,
                            ) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxSize()
                                        .clickable(enabled = !state.isSending && state.input.isNotBlank()) {
                                            viewModel.send()
                                        },
                                    contentAlignment = Alignment.Center,
                                ) {
                                    if (state.isSending) {
                                        CircularProgressIndicator(
                                            modifier = Modifier.size(22.dp),
                                            color = MaterialTheme.colorScheme.onPrimary,
                                            strokeWidth = 2.dp,
                                        )
                                    } else {
                                        Icon(
                                            imageVector = Icons.AutoMirrored.Outlined.Send,
                                            contentDescription = "Enviar",
                                            tint = MaterialTheme.colorScheme.onPrimary,
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        },
    ) { padding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            contentAlignment = Alignment.TopCenter,
        ) {
            if (state.isLoading && state.history == null) {
                LoadingState("Carregando conversa com a Leme...")
            } else {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .widthIn(max = 960.dp),
                    state = listState,
                    contentPadding = PaddingValues(horizontal = 16.dp, vertical = 18.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    item {
                        UsageStatusStrip(
                            activeCondominium = state.history?.activeCondominium,
                            hasMultipleCondominiums = state.condominiumContext.items.size > 1,
                        )
                    }

                    if (messages.isEmpty()) {
                        item {
                            EmptyState(
                                title = "Ola, eu sou o Leme IA.",
                                message = if (state.history?.activeCondominium == null && state.condominiumContext.items.size > 1) {
                                    "Escolha um condominio no topo para iniciar a conversa."
                                } else {
                                    "Posso te ajudar com duvidas sobre solicitacoes, processos e informacoes disponiveis no seu portal."
                                },
                            )
                        }

                        item {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .horizontalScroll(rememberScrollState()),
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                            ) {
                                listOf(
                                    "Ver minhas solicitacoes abertas",
                                    "Tenho duvida sobre um processo",
                                    "Como abrir uma solicitacao?",
                                    "Quais documentos posso consultar?",
                                    "Quero falar com o escritorio",
                                ).forEach { suggestion ->
                                    AssistChip(
                                        onClick = { viewModel.useSuggestion(suggestion) },
                                        label = {
                                            Text(
                                                text = suggestion,
                                                maxLines = 1,
                                                overflow = TextOverflow.Ellipsis,
                                            )
                                        },
                                    )
                                }
                            }
                        }
                    }

                    items(
                        items = messages,
                        key = { message -> message.id },
                    ) { message ->
                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            LemeMessageBubble(
                                message = message,
                                onCopy = if (message.role == "user") {
                                    null
                                } else {
                                    {
                                        clipboardManager.setText(AnnotatedString(message.content))
                                    }
                                },
                            )
                        }
                    }

                    if (state.isSending) {
                        item {
                            TypingBubble()
                        }
                    }

                    item {
                        Spacer(modifier = Modifier.height(4.dp))
                    }
                }
            }
        }
    }
}

@Composable
private fun UsageStatusStrip(
    activeCondominium: Condominium?,
    hasMultipleCondominiums: Boolean,
) {
    AncoraCard {
        Text(
            text = when {
                activeCondominium != null -> "Conversando com ${activeCondominium.name}"
                hasMultipleCondominiums -> "Selecione um condominio para usar a Leme."
                else -> "A Leme usa o contexto do seu portal para responder."
            },
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun LemeMessageBubble(
    message: LemeMessage,
    onCopy: (() -> Unit)?,
) {
    val isMine = message.role == "user"
    val bubbleColor = if (isMine) {
        MaterialTheme.colorScheme.primary
    } else {
        MaterialTheme.colorScheme.surface
    }
    val contentColor = if (isMine) {
        MaterialTheme.colorScheme.onPrimary
    } else {
        MaterialTheme.colorScheme.onSurface
    }

    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = if (isMine) Alignment.End else Alignment.Start,
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Surface(
            modifier = Modifier.widthIn(max = 680.dp),
            color = bubbleColor,
            shape = RoundedCornerShape(
                topStart = 22.dp,
                topEnd = 22.dp,
                bottomStart = if (isMine) 22.dp else 8.dp,
                bottomEnd = if (isMine) 8.dp else 22.dp,
            ),
            tonalElevation = if (isMine) 0.dp else 2.dp,
        ) {
            Column(
                modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                Text(
                    text = if (isMine) "Voce" else "Leme IA",
                    style = MaterialTheme.typography.labelLarge,
                    color = contentColor.copy(alpha = 0.82f),
                )
                Text(
                    text = message.content,
                    style = MaterialTheme.typography.bodyMedium,
                    color = contentColor,
                )
                message.createdAtBr?.let {
                    Text(
                        text = it,
                        style = MaterialTheme.typography.labelSmall,
                        color = contentColor.copy(alpha = 0.72f),
                    )
                }
            }
        }

        if (onCopy != null) {
            TextButton(onClick = onCopy) {
                Icon(
                    imageVector = Icons.Outlined.ContentCopy,
                    contentDescription = null,
                    modifier = Modifier.size(16.dp),
                )
                Spacer(modifier = Modifier.size(4.dp))
                Text("Copiar")
            }
        }
    }
}

@Composable
private fun TypingBubble() {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.Start,
    ) {
        Surface(
            color = MaterialTheme.colorScheme.surface,
            shape = RoundedCornerShape(22.dp),
            tonalElevation = 2.dp,
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                CircularProgressIndicator(
                    modifier = Modifier.size(18.dp),
                    strokeWidth = 2.dp,
                )
                Text(
                    text = "Leme esta digitando...",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun LemeHistoryDialog(
    conversations: List<RecentConversation>,
    currentConversationId: Long?,
    onDismiss: () -> Unit,
    onSelectConversation: (Long) -> Unit,
) {
    androidx.compose.material3.AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            TextButton(onClick = onDismiss) { Text("Fechar") }
        },
        title = { Text("Historico da Leme") },
        text = {
            if (conversations.isEmpty()) {
                Text(
                    text = "Ainda nao ha conversas salvas para esta conta.",
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            } else {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .widthIn(max = 440.dp)
                        .heightIn(max = 360.dp)
                        .verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    conversations.forEachIndexed { index, conversation ->
                        Surface(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(18.dp))
                                .clickable { onSelectConversation(conversation.id) },
                            color = if (conversation.id == currentConversationId) {
                                MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)
                            } else {
                                Color.Transparent
                            },
                        ) {
                            Column(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
                                verticalArrangement = Arrangement.spacedBy(4.dp),
                            ) {
                                Text(
                                    text = conversation.title,
                                    style = MaterialTheme.typography.bodyMedium,
                                    fontWeight = FontWeight.Medium,
                                )
                                conversation.lastMessageAt?.let {
                                    Text(
                                        text = it,
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                        }

                        if (index < conversations.lastIndex) {
                            HorizontalDivider()
                        }
                    }
                }
            }
        },
    )
}
