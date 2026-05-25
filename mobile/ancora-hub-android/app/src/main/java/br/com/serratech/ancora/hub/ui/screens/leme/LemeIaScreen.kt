package br.com.serratech.ancora.hub.ui.screens.leme

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.automirrored.outlined.Send
import androidx.compose.material.icons.outlined.AddComment
import androidx.compose.material.icons.outlined.ArrowDropDown
import androidx.compose.material.icons.outlined.ContentCopy
import androidx.compose.material.icons.outlined.DeleteOutline
import androidx.compose.material.icons.outlined.History
import androidx.compose.material.icons.outlined.MoreVert
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material3.AssistChip
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
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
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.LemeConversationActions
import br.com.serratech.ancora.hub.domain.model.LemeConversationDetail
import br.com.serratech.ancora.hub.domain.model.LemeConversationListData
import br.com.serratech.ancora.hub.domain.model.LemeConversationMessage
import br.com.serratech.ancora.hub.domain.model.LemeConversationSummary
import br.com.serratech.ancora.hub.domain.model.LemeReferenceOption
import br.com.serratech.ancora.hub.domain.model.LemeScopeOption
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

private const val ScopeGeneral = "general"
private const val ScopeCondominium = "condominium"

data class LemeIaUiState(
    val isLoading: Boolean = true,
    val overview: LemeConversationListData? = null,
    val activeConversation: LemeConversationDetail? = null,
    val selectedScopeKey: String = ScopeGeneral,
    val selectedCondominiumId: Long? = null,
    val input: String = "",
    val isSending: Boolean = false,
    val error: String? = null,
)

class LemeIaViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(LemeIaUiState())
        private set

    init {
        loadOverview()
    }

    fun refresh() {
        loadOverview(reloadActiveConversation = true)
    }

    fun updateInput(value: String) {
        uiState = uiState.copy(input = value, error = null)
    }

    fun selectScope(scopeKey: String) {
        val overview = uiState.overview ?: return
        val option = overview.scopeOptions.firstOrNull { it.key == scopeKey } ?: return
        if (!option.supported || isConversationLocked()) {
            return
        }

        uiState = uiState.copy(
            selectedScopeKey = option.key,
            selectedCondominiumId = if (option.requiresReference) uiState.selectedCondominiumId else null,
            error = null,
        )
    }

    fun selectCondominium(condominiumId: Long) {
        if (isConversationLocked()) {
            return
        }

        uiState = uiState.copy(
            selectedCondominiumId = condominiumId,
            error = null,
        )
    }

    fun openConversation(conversationId: Long) {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = uiState.overview == null, error = null)
            runCatching { container.lemeRepository.conversation(conversationId) }
                .onSuccess { payload ->
                    uiState = uiState.copy(
                        isLoading = false,
                        activeConversation = payload.item,
                        selectedScopeKey = payload.item.scopeKey,
                        selectedCondominiumId = payload.item.clientCondominiumId,
                        error = null,
                    )
                }
                .onFailure { throwable ->
                    uiState = uiState.copy(
                        isLoading = false,
                        error = throwable.message ?: "Não foi possível carregar a conversa agora.",
                    )
                }
        }
    }

    fun startNewConversation() {
        uiState = uiState.copy(
            activeConversation = null,
            selectedScopeKey = ScopeGeneral,
            selectedCondominiumId = null,
            input = "",
            error = null,
        )
    }

    fun deleteCurrentConversation() {
        val conversationId = uiState.activeConversation?.id?.takeIf { it > 0 } ?: return

        viewModelScope.launch {
            uiState = uiState.copy(error = null)
            runCatching {
                container.lemeRepository.deleteConversation(conversationId)
            }.onSuccess {
                uiState = uiState.copy(
                    activeConversation = null,
                    input = "",
                    error = null,
                )
                loadOverview(reloadActiveConversation = false)
            }.onFailure { throwable ->
                uiState = uiState.copy(
                    error = throwable.message ?: "Não foi possível limpar a conversa agora.",
                )
            }
        }
    }

    fun useSuggestion(value: String) {
        uiState = uiState.copy(input = value, error = null)
        send()
    }

    fun send() {
        val overview = uiState.overview ?: return
        val messageText = uiState.input.trim()
        if (messageText.isBlank() || uiState.isSending) {
            return
        }

        if (!overview.availability.canChat) {
            uiState = uiState.copy(
                error = overview.availability.message ?: "Não foi possível obter resposta agora. Tente novamente.",
            )
            return
        }

        val selectedScope = overview.scopeOptions.firstOrNull { it.key == uiState.selectedScopeKey }
            ?: overview.scopeOptions.firstOrNull { it.key == ScopeGeneral }
        if (selectedScope == null || !selectedScope.supported) {
            uiState = uiState.copy(
                error = "Este escopo ainda não está disponível no aplicativo.",
            )
            return
        }

        if (selectedScope.requiresReference && uiState.selectedCondominiumId == null) {
            uiState = uiState.copy(
                error = "Selecione um condomínio antes de enviar a mensagem.",
            )
            return
        }

        val previousConversation = uiState.activeConversation
        val optimisticConversation = (previousConversation ?: draftConversation(overview)).copy(
            messages = (previousConversation?.messages ?: emptyList()) + optimisticUserMessage(messageText),
        )

        uiState = uiState.copy(
            activeConversation = optimisticConversation,
            input = "",
            isSending = true,
            error = null,
        )

        viewModelScope.launch {
            var persistedConversation = previousConversation

            runCatching {
                if (persistedConversation == null || persistedConversation.id <= 0L) {
                    val created = container.lemeRepository.createConversation(
                        scope = uiState.selectedScopeKey,
                        clientCondominiumId = uiState.selectedCondominiumId,
                    )
                    persistedConversation = created.item
                }

                container.lemeRepository.sendMessage(
                    conversationId = persistedConversation?.id ?: 0L,
                    message = messageText,
                )
            }.onSuccess { payload ->
                val refreshedOverview = runCatching { container.lemeRepository.overview() }.getOrElse { overview }

                uiState = uiState.copy(
                    isSending = false,
                    overview = refreshedOverview,
                    activeConversation = payload.item,
                    selectedScopeKey = payload.item.scopeKey,
                    selectedCondominiumId = payload.item.clientCondominiumId,
                    error = null,
                )
            }.onFailure { throwable ->
                uiState = uiState.copy(
                    isSending = false,
                    activeConversation = persistedConversation?.takeIf { it.id > 0L } ?: previousConversation,
                    input = messageText,
                    error = throwable.message ?: "Não foi possível obter resposta agora. Tente novamente.",
                )
            }
        }
    }

    private fun loadOverview(reloadActiveConversation: Boolean = false) {
        val activeConversationId = if (reloadActiveConversation) {
            uiState.activeConversation?.id?.takeIf { it > 0L }
        } else {
            null
        }

        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching {
                val overview = container.lemeRepository.overview()
                val activeConversation = activeConversationId?.let {
                    container.lemeRepository.conversation(it).item
                } ?: uiState.activeConversation

                overview to activeConversation
            }.onSuccess { (overview, activeConversation) ->
                val selectedScope = when {
                    activeConversation != null -> activeConversation.scopeKey
                    overview.scopeOptions.any { it.key == uiState.selectedScopeKey } -> uiState.selectedScopeKey
                    else -> ScopeGeneral
                }

                val selectedCondominiumId = activeConversation?.clientCondominiumId
                    ?: uiState.selectedCondominiumId
                    ?: overview.condominiumOptions.firstOrNull()?.id

                uiState = uiState.copy(
                    isLoading = false,
                    overview = overview,
                    activeConversation = activeConversation,
                    selectedScopeKey = selectedScope,
                    selectedCondominiumId = if (selectedScope == ScopeCondominium) selectedCondominiumId else null,
                    error = null,
                )
            }.onFailure { throwable ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = throwable.message ?: "Não foi possível carregar o Leme IA agora.",
                )
            }
        }
    }

    private fun draftConversation(overview: LemeConversationListData): LemeConversationDetail {
        val scopeKey = uiState.selectedScopeKey
        val scopeLabel = currentScopeLabel(overview, scopeKey, uiState.selectedCondominiumId)

        return LemeConversationDetail(
            id = 0L,
            title = "Nova conversa",
            scopeKey = scopeKey,
            scopeLabel = scopeLabel,
            clientCondominiumId = uiState.selectedCondominiumId,
            messagesCount = 0,
            lastMessageAt = null,
            lastMessageAtBr = null,
            createdAt = null,
            createdAtBr = null,
            messages = emptyList(),
            availableActions = LemeConversationActions(canDelete = false),
        )
    }

    private fun optimisticUserMessage(content: String): LemeConversationMessage = LemeConversationMessage(
        id = -System.currentTimeMillis(),
        role = "user",
        content = content,
        status = "pending",
        provider = null,
        model = null,
        errorMessage = null,
        sourceChunksCount = null,
        documents = emptyList(),
        createdAt = null,
        createdAtBr = "agora",
        canCopy = false,
    )

    private fun currentScopeLabel(
        overview: LemeConversationListData,
        scopeKey: String,
        condominiumId: Long?,
    ): String {
        return when (scopeKey) {
            ScopeCondominium -> overview.condominiumOptions.firstOrNull { it.id == condominiumId }?.label ?: "Condomínio"
            else -> overview.scopeOptions.firstOrNull { it.key == scopeKey }?.label ?: "Geral"
        }
    }

    private fun isConversationLocked(): Boolean =
        (uiState.activeConversation?.id ?: 0L) > 0L && uiState.activeConversation?.messages?.isNotEmpty() == true
}

@Composable
fun LemeIaScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onBack: () -> Unit,
) {
    val viewModel: LemeIaViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return LemeIaViewModel(container) as T
            }
        },
    )

    val state = viewModel.uiState
    val spacing = MaterialTheme.spacing
    val listState = rememberLazyListState()
    val clipboardManager = LocalClipboardManager.current
    val snackbarHostState = remember { SnackbarHostState() }
    val coroutineScope = rememberCoroutineScope()
    val messages = state.activeConversation?.messages.orEmpty()
    val overview = state.overview
    val scopeOptions = overview?.scopeOptions.orEmpty()
    val condominiumOptions = overview?.condominiumOptions.orEmpty()
    val availability = overview?.availability
    val isScopeLocked = (state.activeConversation?.id ?: 0L) > 0L && messages.isNotEmpty()
    var showActionsMenu by remember { mutableStateOf(false) }
    var showHistorySheet by remember { mutableStateOf(false) }
    var showCondominiumMenu by remember { mutableStateOf(false) }

    LaunchedEffect(messages.size, state.isSending, state.activeConversation?.id) {
        snapshotFlow { listState.layoutInfo.totalItemsCount }
            .filter { it > 0 }
            .first()

        listState.animateScrollToItem(listState.layoutInfo.totalItemsCount - 1)
    }

    if (showHistorySheet) {
        LemeHistorySheet(
            conversations = overview?.items.orEmpty(),
            currentConversationId = state.activeConversation?.id?.takeIf { it > 0L },
            onDismiss = { showHistorySheet = false },
            onSelectConversation = { conversationId ->
                showHistorySheet = false
                viewModel.openConversation(conversationId)
            },
        )
    }

    Scaffold(
        modifier = modifier.fillMaxSize(),
        containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.18f),
        snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
        topBar = {
            AncoraTopBar(
                title = "Leme IA",
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                            contentDescription = "Voltar",
                        )
                    }
                },
                actions = {
                    IconButton(onClick = { showHistorySheet = true }) {
                        Icon(
                            imageVector = Icons.Outlined.History,
                            contentDescription = "Histórico de conversas",
                        )
                    }

                    IconButton(onClick = { viewModel.startNewConversation() }) {
                        Icon(
                            imageVector = Icons.Outlined.AddComment,
                            contentDescription = "Nova conversa",
                        )
                    }

                    Box {
                        IconButton(onClick = { showActionsMenu = true }) {
                            Icon(
                                imageVector = Icons.Outlined.MoreVert,
                                contentDescription = "Mais ações",
                            )
                        }

                        DropdownMenu(
                            expanded = showActionsMenu,
                            onDismissRequest = { showActionsMenu = false },
                        ) {
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
                                enabled = state.activeConversation?.availableActions?.canDelete == true,
                                text = { Text("Limpar conversa") },
                                leadingIcon = {
                                    Icon(Icons.Outlined.DeleteOutline, contentDescription = null)
                                },
                                onClick = {
                                    showActionsMenu = false
                                    viewModel.deleteCurrentConversation()
                                },
                            )
                        }
                    }
                },
            )
        },
        bottomBar = {
            Surface(shadowElevation = 8.dp) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .imePadding()
                        .padding(horizontal = spacing.lg, vertical = spacing.md),
                    verticalArrangement = Arrangement.spacedBy(spacing.sm),
                ) {
                    state.error?.let { message ->
                        Text(
                            text = message,
                            color = MaterialTheme.colorScheme.error,
                            style = MaterialTheme.typography.bodySmall,
                        )
                    }

                    availability?.message?.takeIf { !availability.canChat }?.let { message ->
                        Text(
                            text = message,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            style = MaterialTheme.typography.bodySmall,
                        )
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.Bottom,
                        horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                    ) {
                        OutlinedTextField(
                            value = state.input,
                            onValueChange = viewModel::updateInput,
                            modifier = Modifier.weight(1f),
                            enabled = !state.isLoading && !state.isSending && (availability?.canChat != false),
                            minLines = 1,
                            maxLines = 5,
                            placeholder = { Text("Digite sua mensagem") },
                            shape = RoundedCornerShape(22.dp),
                        )

                        Surface(
                            modifier = Modifier.size(54.dp),
                            shape = RoundedCornerShape(18.dp),
                            color = MaterialTheme.colorScheme.primary,
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clickable(
                                        enabled = !state.isSending &&
                                            state.input.isNotBlank() &&
                                            !state.isLoading &&
                                            (availability?.canChat != false),
                                    ) {
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
        },
    ) { padding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            contentAlignment = Alignment.TopCenter,
        ) {
            when {
                state.isLoading && overview == null -> {
                    AncoraLoadingState(
                        label = "Carregando o Leme IA...",
                        modifier = Modifier.padding(top = spacing.xl),
                    )
                }

                overview == null -> {
                    AncoraErrorState(
                        message = state.error ?: "Não foi possível carregar as informações.",
                        modifier = Modifier.padding(top = spacing.xl),
                        onRetry = viewModel::refresh,
                    )
                }

                else -> {
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxSize()
                            .widthIn(max = 960.dp),
                        state = listState,
                        contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.lg),
                        verticalArrangement = Arrangement.spacedBy(spacing.md),
                    ) {
                        item {
                            LemeConversationHeader(
                                state = state,
                                scopeOptions = scopeOptions,
                                condominiumOptions = condominiumOptions,
                                isScopeLocked = isScopeLocked,
                                onSelectScope = viewModel::selectScope,
                                onSelectCondominium = viewModel::selectCondominium,
                                onOpenCondominiumMenu = { showCondominiumMenu = true },
                                onDismissCondominiumMenu = { showCondominiumMenu = false },
                                showCondominiumMenu = showCondominiumMenu,
                            )
                        }

                        if (messages.isEmpty()) {
                            item {
                                AncoraEmptyState(
                                    title = "Como posso ajudar hoje?",
                                    message = "Faça uma pergunta objetiva ou use uma das sugestões rápidas para começar a conversa com o Leme IA.",
                                )
                            }

                            item {
                                SuggestionsRow(
                                    suggestions = listOf(
                                        "Resumir processo",
                                        "Analisar cobrança",
                                        "Gerar resposta para cliente",
                                        "Consultar convenção",
                                        "Criar minuta",
                                    ),
                                    onSuggestion = viewModel::useSuggestion,
                                )
                            }
                        } else {
                            items(messages, key = { message -> message.id }) { message ->
                                LemeMessageBubble(
                                    message = message,
                                    onCopy = if (message.canCopy) {
                                        {
                                            clipboardManager.setText(AnnotatedString(message.content))
                                            coroutineScope.launch {
                                                snackbarHostState.showSnackbar("Resposta copiada.")
                                            }
                                        }
                                    } else {
                                        null
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
                            Spacer(modifier = Modifier.height(spacing.xs))
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun LemeConversationHeader(
    state: LemeIaUiState,
    scopeOptions: List<LemeScopeOption>,
    condominiumOptions: List<LemeReferenceOption>,
    isScopeLocked: Boolean,
    showCondominiumMenu: Boolean,
    onSelectScope: (String) -> Unit,
    onSelectCondominium: (Long) -> Unit,
    onOpenCondominiumMenu: () -> Unit,
    onDismissCondominiumMenu: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    val availability = state.overview?.availability
    val conversation = state.activeConversation
    val activeScopeLabel = conversation?.scopeLabel
        ?: scopeOptions.firstOrNull { it.key == state.selectedScopeKey }?.label
        ?: "Geral"
    val selectedCondominium = condominiumOptions.firstOrNull { it.id == state.selectedCondominiumId }

    AncoraCard(bordered = true) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.Top,
        ) {
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(spacing.xs),
            ) {
                AncoraStatusChip(
                    label = activeScopeLabel,
                    tone = AncoraTone.Brand,
                )
                Text(
                    text = conversation?.title ?: "Nova conversa",
                    style = MaterialTheme.typography.headlineSmall,
                )
                Text(
                    text = if (conversation != null && conversation.messages.isNotEmpty()) {
                        "Continue a conversa abaixo ou abra o histórico para trocar de contexto."
                    } else {
                        "Use o Leme IA para consultas rápidas, apoio técnico e respostas com mais agilidade."
                    },
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            if (conversation?.lastMessageAtBr != null) {
                Text(
                    text = conversation.lastMessageAtBr,
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }

        availability?.message?.let { message ->
            AncoraCard(
                bordered = true,
                contentPadding = PaddingValues(horizontal = spacing.md, vertical = spacing.md),
            ) {
                AncoraStatusChip(
                    label = if (availability.canChat) "Leme IA disponível" else "Atenção",
                    tone = if (availability.canChat) AncoraTone.Success else AncoraTone.Warning,
                )
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }

        if (isScopeLocked) {
            Text(
                text = "O escopo fica travado após a primeira mensagem desta conversa.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        } else {
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                verticalArrangement = Arrangement.spacedBy(spacing.sm),
            ) {
                scopeOptions.forEach { option ->
                    FilterChip(
                        selected = state.selectedScopeKey == option.key,
                        enabled = option.supported,
                        onClick = { onSelectScope(option.key) },
                        label = { Text(option.label) },
                    )
                }
            }

            if (state.selectedScopeKey == ScopeCondominium) {
                Box {
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(18.dp))
                            .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f))
                            .clickable(onClick = onOpenCondominiumMenu)
                            .padding(horizontal = spacing.md, vertical = spacing.sm),
                        horizontalArrangement = Arrangement.spacedBy(spacing.xs),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(
                            text = selectedCondominium?.label ?: "Selecionar condomínio",
                            style = MaterialTheme.typography.labelLarge,
                            color = MaterialTheme.colorScheme.primary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                        Icon(
                            imageVector = Icons.Outlined.ArrowDropDown,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.primary,
                        )
                    }

                    DropdownMenu(
                        expanded = showCondominiumMenu,
                        onDismissRequest = onDismissCondominiumMenu,
                    ) {
                        condominiumOptions.forEach { item ->
                            DropdownMenuItem(
                                text = { Text(item.label, maxLines = 1, overflow = TextOverflow.Ellipsis) },
                                onClick = {
                                    onDismissCondominiumMenu()
                                    onSelectCondominium(item.id)
                                },
                            )
                        }
                    }
                }
            }
        }

        Text(
            text = "Histórico de conversas",
            style = MaterialTheme.typography.titleSmall,
        )
        Text(
            text = "Abra o histórico para retomar conversas antigas ou inicie uma nova conversa quando quiser.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}

@Composable
private fun SuggestionsRow(
    suggestions: List<String>,
    onSuggestion: (String) -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        suggestions.forEach { suggestion ->
            AssistChip(
                onClick = { onSuggestion(suggestion) },
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

@Composable
private fun LemeMessageBubble(
    message: LemeConversationMessage,
    onCopy: (() -> Unit)?,
) {
    val isMine = message.role == "user"
    val spacing = MaterialTheme.spacing
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
        verticalArrangement = Arrangement.spacedBy(spacing.xs),
    ) {
        Surface(
            modifier = Modifier.widthIn(max = 700.dp),
            color = bubbleColor,
            shape = RoundedCornerShape(
                topStart = 24.dp,
                topEnd = 24.dp,
                bottomStart = if (isMine) 24.dp else 10.dp,
                bottomEnd = if (isMine) 10.dp else 24.dp,
            ),
            tonalElevation = if (isMine) 0.dp else 2.dp,
        ) {
            Column(
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 14.dp),
                verticalArrangement = Arrangement.spacedBy(spacing.sm),
            ) {
                Text(
                    text = if (isMine) "Você" else "Leme IA",
                    style = MaterialTheme.typography.labelLarge,
                    color = contentColor.copy(alpha = 0.84f),
                )
                Text(
                    text = message.content,
                    style = MaterialTheme.typography.bodyLarge,
                    color = contentColor,
                )

                if (!isMine && message.documents.isNotEmpty()) {
                    Column(verticalArrangement = Arrangement.spacedBy(spacing.xs)) {
                        message.documents.take(4).forEach { document ->
                            Row(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(16.dp))
                                    .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.7f))
                                    .padding(horizontal = spacing.sm, vertical = spacing.xs),
                                horizontalArrangement = Arrangement.spacedBy(spacing.xs),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Text(
                                    text = document.documentKindLabel ?: "Fonte",
                                    style = MaterialTheme.typography.labelMedium,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                                Text(
                                    text = document.title ?: document.source ?: "Documento consultado",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis,
                                )
                            }
                        }
                    }
                }

                message.createdAtBr?.let {
                    Text(
                        text = it,
                        style = MaterialTheme.typography.labelSmall,
                        color = contentColor.copy(alpha = 0.7f),
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
                Text("Copiar resposta")
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
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                CircularProgressIndicator(
                    modifier = Modifier.size(18.dp),
                    strokeWidth = 2.dp,
                )
                Text(
                    text = "Consultando informações...",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun LemeHistorySheet(
    conversations: List<LemeConversationSummary>,
    currentConversationId: Long?,
    onDismiss: () -> Unit,
    onSelectConversation: (Long) -> Unit,
) {
    val spacing = MaterialTheme.spacing

    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = spacing.lg, vertical = spacing.md),
            verticalArrangement = Arrangement.spacedBy(spacing.md),
        ) {
            Text(
                text = "Histórico de conversas",
                style = MaterialTheme.typography.headlineSmall,
            )

            if (conversations.isEmpty()) {
                AncoraEmptyState(
                    title = "Nenhuma conversa encontrada",
                    message = "Inicie uma nova conversa para começar a usar o Leme IA.",
                )
            } else {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(max = 420.dp),
                    verticalArrangement = Arrangement.spacedBy(spacing.sm),
                ) {
                    items(conversations, key = { conversation -> conversation.id }) { conversation ->
                        Surface(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(20.dp))
                                .clickable { onSelectConversation(conversation.id) },
                            color = if (conversation.id == currentConversationId) {
                                MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)
                            } else {
                                Color.Transparent
                            },
                        ) {
                            Column(
                                modifier = Modifier.padding(horizontal = spacing.md, vertical = spacing.md),
                                verticalArrangement = Arrangement.spacedBy(spacing.xs),
                            ) {
                                Text(
                                    text = conversation.title,
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.SemiBold,
                                )
                                Text(
                                    text = conversation.scopeLabel,
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                                Text(
                                    text = conversation.lastMessageAtBr ?: conversation.createdAtBr ?: "Sem movimentação recente",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                        }
                        HorizontalDivider()
                    }
                }
            }
        }
    }
}
