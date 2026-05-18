package br.com.serratech.ancora.clientes.ui.screens.demands

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.clientes.BuildConfig
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.CondominiumContext
import br.com.serratech.ancora.clientes.domain.model.DemandAttachment
import br.com.serratech.ancora.clientes.domain.model.DemandCategory
import br.com.serratech.ancora.clientes.domain.model.DemandDetail
import br.com.serratech.ancora.clientes.domain.model.DemandItem
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.AncoraDropdownField
import br.com.serratech.ancora.clientes.ui.components.AncoraTopBar
import br.com.serratech.ancora.clientes.ui.components.AttachmentPicker
import br.com.serratech.ancora.clientes.ui.components.ChatBubble
import br.com.serratech.ancora.clientes.ui.components.DropdownOption
import br.com.serratech.ancora.clientes.ui.components.EmptyState
import br.com.serratech.ancora.clientes.ui.components.ErrorState
import br.com.serratech.ancora.clientes.ui.components.LoadingState
import br.com.serratech.ancora.clientes.ui.components.PrimaryButton
import br.com.serratech.ancora.clientes.ui.components.StatusChip
import coil.compose.AsyncImage
import java.io.File
import kotlinx.coroutines.launch

data class DemandsUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val query: String = "",
    val selectedStatus: String? = null,
    val hasInitializedCondominiumFilter: Boolean = false,
    val selectedCondominiumId: Long? = null,
    val condominiumContext: CondominiumContext = CondominiumContext(
        selected = null,
        items = emptyList(),
    ),
    val statusLabels: Map<String, String> = emptyMap(),
    val items: List<DemandItem> = emptyList(),
)

class DemandsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(DemandsUiState())
        private set

    init {
        refresh(forceBlocking = true)
    }

    fun updateQuery(value: String) {
        uiState = uiState.copy(query = value)
    }

    fun updateStatus(status: String?) {
        uiState = uiState.copy(selectedStatus = status)
        refresh(forceBlocking = false)
    }

    fun updateCondominium(condominiumId: Long?) {
        uiState = uiState.copy(
            selectedCondominiumId = condominiumId,
            hasInitializedCondominiumFilter = true,
        )
        refresh(forceBlocking = false)
    }

    fun refresh(forceBlocking: Boolean = false) {
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
                val useAllCondominiums = uiState.hasInitializedCondominiumFilter && effectiveCondominiumId == null
                val result = container.demandRepository.list(
                    query = uiState.query,
                    status = uiState.selectedStatus,
                    condominiumId = effectiveCondominiumId,
                    allCondominiums = useAllCondominiums,
                )

                Triple(context, effectiveCondominiumId, result)
            }.onSuccess { (context, effectiveCondominiumId, result) ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = null,
                    selectedCondominiumId = effectiveCondominiumId,
                    hasInitializedCondominiumFilter = true,
                    condominiumContext = context,
                    statusLabels = result.statusLabels,
                    items = result.items,
                )
            }.onFailure {
                uiState = uiState.copy(
                    isLoading = false,
                    error = it.message ?: "Nao foi possivel carregar as solicitacoes.",
                )
            }
        }
    }
}

class DemandCreateViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var categories by mutableStateOf<List<DemandCategory>>(emptyList())
        private set
    var condominiumContext by mutableStateOf(
        CondominiumContext(
            selected = null,
            items = emptyList(),
        )
    )
        private set
    var selectedCategoryId by mutableStateOf<Long?>(null)
    var selectedCondominiumId by mutableStateOf<Long?>(null)
    var subject by mutableStateOf("")
    var description by mutableStateOf("")
    var isLoading by mutableStateOf(true)
        private set
    var isSaving by mutableStateOf(false)
        private set
    var error by mutableStateOf<String?>(null)
        private set
    var createdDemandId by mutableStateOf<Long?>(null)
        private set
    val selectedFiles = mutableStateListOf<Uri>()

    init {
        load()
    }

    fun addFiles(uris: List<Uri>) {
        selectedFiles.addAll(uris)
    }

    fun removeFile(uri: Uri) {
        selectedFiles.remove(uri)
    }

    fun load() {
        viewModelScope.launch {
            isLoading = true
            error = null
            runCatching {
                val categories = container.demandRepository.categories()
                val condominiumContext = container.condominiumRepository.list()
                categories to condominiumContext
            }.onSuccess { (loadedCategories, loadedCondominiumContext) ->
                categories = loadedCategories.sortedWith(
                    compareBy<DemandCategory> { it.name.trim().equals("outros", ignoreCase = true) }
                        .thenBy { it.name.lowercase() }
                )
                condominiumContext = loadedCondominiumContext
                selectedCategoryId = categories.firstOrNull()?.id
                selectedCondominiumId = loadedCondominiumContext.selected?.id
                    ?: loadedCondominiumContext.items.singleOrNull()?.id
                isLoading = false
            }.onFailure {
                error = it.message ?: "Nao foi possivel carregar as categorias."
                isLoading = false
            }
        }
    }

    fun create() {
        val categoryId = selectedCategoryId ?: return
        viewModelScope.launch {
            isSaving = true
            error = null
            runCatching {
                container.demandRepository.create(
                    categoryId = categoryId,
                    condominiumId = selectedCondominiumId,
                    subject = subject,
                    description = description,
                    files = selectedFiles.toList(),
                )
            }.onSuccess {
                createdDemandId = it.id
                isSaving = false
            }.onFailure {
                error = it.message ?: "Nao foi possivel enviar a solicitacao."
                isSaving = false
            }
        }
    }

    fun showError(message: String) {
        error = message
    }
}

class DemandDetailViewModel(
    private val container: AppContainer,
    private val demandId: Long,
) : ViewModel() {
    var detail by mutableStateOf<DemandDetail?>(null)
        private set
    var isLoading by mutableStateOf(true)
        private set
    var isSending by mutableStateOf(false)
        private set
    var error by mutableStateOf<String?>(null)
        private set
    var replyMessage by mutableStateOf("")
    val selectedFiles = mutableStateListOf<Uri>()

    init {
        refresh()
    }

    fun addFiles(uris: List<Uri>) {
        selectedFiles.addAll(uris)
    }

    fun removeFile(uri: Uri) {
        selectedFiles.remove(uri)
    }

    fun refresh() {
        viewModelScope.launch {
            isLoading = true
            error = null
            runCatching { container.demandRepository.detail(demandId) }
                .onSuccess { detail = it; isLoading = false }
                .onFailure { error = it.message ?: "Nao foi possivel carregar a solicitacao."; isLoading = false }
        }
    }

    fun sendReply() {
        viewModelScope.launch {
            isSending = true
            error = null
            runCatching {
                container.demandRepository.reply(demandId, replyMessage, selectedFiles.toList())
                replyMessage = ""
                selectedFiles.clear()
                refresh()
            }.onFailure { error = it.message ?: "Nao foi possivel enviar a resposta." }
            isSending = false
        }
    }

    fun cancelDemand() {
        viewModelScope.launch {
            runCatching {
                container.demandRepository.cancel(demandId, "Cancelada pelo app")
                refresh()
            }.onFailure { error = it.message ?: "Nao foi possivel cancelar a solicitacao." }
        }
    }

    fun showTransientError(message: String) {
        error = message
    }
}

@Composable
fun DemandsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onOpenDetail: (Long) -> Unit,
    onCreateDemand: () -> Unit,
) {
    val viewModel: DemandsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return DemandsViewModel(container) as T
            }
        }
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Solicitacoes")
        when {
            viewModel.uiState.isLoading -> LoadingState("Buscando solicitacoes...")
            viewModel.uiState.error != null && viewModel.uiState.items.isEmpty() -> {
                ErrorState(
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = { viewModel.refresh(forceBlocking = true) },
                )
            }
            else -> DemandsContent(
                state = viewModel.uiState,
                onQueryChanged = viewModel::updateQuery,
                onSearch = { viewModel.refresh(forceBlocking = false) },
                onStatusSelected = viewModel::updateStatus,
                onCondominiumSelected = viewModel::updateCondominium,
                onCreateDemand = onCreateDemand,
                onOpenDetail = onOpenDetail,
            )
        }
    }
}

@Composable
private fun DemandsContent(
    state: DemandsUiState,
    onQueryChanged: (String) -> Unit,
    onSearch: () -> Unit,
    onStatusSelected: (String?) -> Unit,
    onCondominiumSelected: (Long?) -> Unit,
    onCreateDemand: () -> Unit,
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
                label = { Text("Buscar por protocolo ou assunto") },
                trailingIcon = {
                    TextButton(onClick = onSearch) { Text("Buscar") }
                },
            )
        }
        item {
            PrimaryButton(text = "Nova solicitacao", onClick = onCreateDemand)
        }
        if (state.condominiumContext.items.size > 1) {
            item {
                AncoraDropdownField(
                    label = "Condominio",
                    selectedValue = state.selectedCondominiumId,
                    options = buildList {
                        add(DropdownOption<Long>(null, "Todos"))
                        state.condominiumContext.items.forEach { condominium ->
                            add(DropdownOption(condominium.id, condominium.name))
                        }
                    },
                    onSelected = onCondominiumSelected,
                )
            }
        }
        if (state.statusLabels.isNotEmpty()) {
            item {
                AncoraDropdownField(
                    label = "Status",
                    selectedValue = state.selectedStatus,
                    options = buildList {
                        add(DropdownOption<String>(null, "Todas"))
                        state.statusLabels.forEach { (statusKey, label) ->
                            add(DropdownOption(statusKey, label))
                        }
                    },
                    onSelected = onStatusSelected,
                )
            }
        }
        if (state.items.isEmpty()) {
            item {
                EmptyState(
                    "Nenhuma solicitacao encontrada",
                    "Abra sua primeira solicitacao para conversar com a equipe pelo app.",
                    primaryActionLabel = "Nova solicitacao",
                    onPrimaryAction = onCreateDemand,
                )
            }
        } else {
            items(state.items) { demand ->
                AncoraCard(modifier = Modifier.clickable { onOpenDetail(demand.id) }) {
                    Text(demand.protocol, style = MaterialTheme.typography.titleMedium)
                    StatusChip(demand.status)
                    Text(demand.subject)
                    demand.updatedAtBr?.let {
                        Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        }
    }
}

@Composable
fun DemandCreateScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onBack: () -> Unit,
    onHome: () -> Unit,
    onCreated: (Long) -> Unit,
) {
    val viewModel: DemandCreateViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return DemandCreateViewModel(container) as T
            }
        }
    )
    val context = LocalContext.current
    val filePicker = rememberLauncherForActivityResult(ActivityResultContracts.OpenMultipleDocuments()) { uris ->
        persistReadPermissions(context, uris)
        viewModel.addFiles(uris)
    }

    androidx.compose.runtime.LaunchedEffect(viewModel.createdDemandId) {
        viewModel.createdDemandId?.let(onCreated)
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Nova solicitacao",
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Voltar")
                }
            },
            actions = {
                IconButton(onClick = onHome) {
                    Icon(Icons.Outlined.Home, contentDescription = "Ir para o inicio")
                }
            },
        )
        when {
            viewModel.isLoading -> LoadingState("Carregando categorias...")
            viewModel.error != null && viewModel.categories.isEmpty() -> ErrorState(viewModel.error.orEmpty(), onRetry = viewModel::load)
            else -> DemandCreateContent(
                viewModel = viewModel,
                context = context,
                onOpenPicker = { filePicker.launch(arrayOf("*/*")) },
                onBack = onBack,
            )
        }
    }
}

@Composable
private fun DemandCreateContent(
    viewModel: DemandCreateViewModel,
    context: Context,
    onOpenPicker: () -> Unit,
    onBack: () -> Unit,
) {
    LazyColumn(
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        viewModel.error?.takeIf { viewModel.categories.isNotEmpty() }?.let { errorMessage ->
            item {
                AncoraCard {
                    Text("Atencao", style = MaterialTheme.typography.titleMedium)
                    Text(errorMessage, color = MaterialTheme.colorScheme.error)
                }
            }
        }
        if (viewModel.condominiumContext.items.size > 1) {
            item {
                AncoraCard {
                    AncoraDropdownField(
                        label = "Condominio",
                        selectedValue = viewModel.selectedCondominiumId,
                        options = viewModel.condominiumContext.items.map { condominium ->
                            DropdownOption(condominium.id, condominium.name)
                        },
                        onSelected = { viewModel.selectedCondominiumId = it },
                    )
                }
            }
        }
        item {
            AncoraCard {
                AncoraDropdownField(
                    label = "Categoria",
                    selectedValue = viewModel.selectedCategoryId,
                    options = viewModel.categories.map { category ->
                        DropdownOption(category.id, category.name)
                    },
                    onSelected = { viewModel.selectedCategoryId = it },
                )
            }
        }
        item {
            OutlinedTextField(
                value = viewModel.subject,
                onValueChange = { viewModel.subject = it },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Assunto") },
            )
        }
        item {
            OutlinedTextField(
                value = viewModel.description,
                onValueChange = { viewModel.description = it },
                modifier = Modifier.fillMaxWidth(),
                minLines = 6,
                label = { Text("Descricao") },
            )
        }
        item {
            AttachmentPicker(
                attachments = viewModel.selectedFiles.mapIndexed { index, uri ->
                    DemandAttachment(index.toLong(), context.resolveDisplayName(uri), null, 0, uri.toString())
                },
                onAddClick = onOpenPicker,
                onAttachmentClick = { attachment ->
                    viewModel.selectedFiles.firstOrNull { it.toString() == attachment.downloadUrl }?.let(viewModel::removeFile)
                },
                addButtonLabel = "Anexar arquivo ate 30 MB",
            )
        }
        item {
            PrimaryButton(text = "Enviar solicitacao", loading = viewModel.isSaving, onClick = viewModel::create)
        }
        item {
            TextButton(onClick = onBack) { Text("Voltar") }
        }
    }
}

@Composable
fun DemandDetailScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    demandId: Long,
    onBack: () -> Unit,
    onHome: () -> Unit,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val viewModel: DemandDetailViewModel = viewModel(
        key = "demand-detail-$demandId",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return DemandDetailViewModel(container, demandId) as T
            }
        }
    )
    val filePicker = rememberLauncherForActivityResult(ActivityResultContracts.OpenMultipleDocuments()) { uris ->
        persistReadPermissions(context, uris)
        viewModel.addFiles(uris)
    }
    var previewImageFile by remember { mutableStateOf<File?>(null) }
    var previewTitle by remember { mutableStateOf("") }
    var confirmCancel by remember { mutableStateOf(false) }

    fun openAttachment(attachment: DemandAttachment) {
        scope.launch {
            runCatching {
                val isImage = attachment.mimeType?.startsWith("image/") == true ||
                    attachment.originalName.lowercase().endsWith(".png") ||
                    attachment.originalName.lowercase().endsWith(".jpg") ||
                    attachment.originalName.lowercase().endsWith(".jpeg") ||
                    attachment.originalName.lowercase().endsWith(".webp")

                if (isImage) {
                    previewTitle = attachment.originalName
                    previewImageFile = container.demandRepository.downloadAttachmentToCache(attachment)
                } else {
                    val file = container.demandRepository.downloadAttachmentToCache(attachment)
                    val uri = FileProvider.getUriForFile(
                        context,
                        "${BuildConfig.APPLICATION_ID}.fileprovider",
                        file,
                    )
                    val intent = Intent(Intent.ACTION_VIEW).apply {
                        setDataAndType(uri, attachment.mimeType ?: "*/*")
                        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                    }
                    ContextCompat.startActivity(context, intent, null)
                }
            }.onFailure {
                viewModel.showTransientError(it.message ?: "Nao foi possivel abrir o anexo agora.")
            }
        }
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Solicitacao",
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Voltar")
                }
            },
            actions = {
                IconButton(onClick = onHome) {
                    Icon(Icons.Outlined.Home, contentDescription = "Ir para o inicio")
                }
            },
        )
        when {
            viewModel.isLoading -> LoadingState("Carregando conversa...")
            viewModel.error != null && viewModel.detail == null -> ErrorState(viewModel.error.orEmpty(), onRetry = viewModel::refresh)
            viewModel.detail == null -> EmptyState("Solicitacao indisponivel", "Nao foi possivel localizar essa solicitacao agora.")
            else -> LazyColumn(
                modifier = Modifier.weight(1f),
                contentPadding = PaddingValues(20.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                item {
                    AncoraCard {
                        Text(viewModel.detail!!.protocol, style = MaterialTheme.typography.titleLarge)
                        StatusChip(viewModel.detail!!.status)
                        Text(viewModel.detail!!.subject)
                        viewModel.detail!!.condominium?.name?.let {
                            Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
                if (viewModel.error != null) {
                    item {
                        AncoraCard {
                            Text("Atenção", style = MaterialTheme.typography.titleMedium)
                            Text(viewModel.error.orEmpty(), color = MaterialTheme.colorScheme.error)
                        }
                    }
                }
                items(viewModel.detail!!.messages) { message ->
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        ChatBubble(
                            message = message.message,
                            author = message.senderName,
                            timestamp = message.createdAtBr,
                            isMine = message.senderType == "client",
                        )
                        if (message.attachments.isNotEmpty()) {
                            AttachmentPicker(
                                attachments = message.attachments,
                                onAddClick = {},
                                showAddButton = false,
                                onAttachmentClick = ::openAttachment,
                            )
                        }
                    }
                }
                item {
                    if (viewModel.detail!!.canReply) {
                        AncoraCard {
                            OutlinedTextField(
                                value = viewModel.replyMessage,
                                onValueChange = { viewModel.replyMessage = it },
                                modifier = Modifier.fillMaxWidth(),
                                minLines = 4,
                                label = { Text("Responder") },
                            )
                            AttachmentPicker(
                                attachments = viewModel.selectedFiles.mapIndexed { index, uri ->
                                    DemandAttachment(index.toLong(), context.resolveDisplayName(uri), null, 0, uri.toString())
                                },
                                onAddClick = { filePicker.launch(arrayOf("*/*")) },
                                onAttachmentClick = { attachment ->
                                    viewModel.selectedFiles.firstOrNull { it.toString() == attachment.downloadUrl }?.let(viewModel::removeFile)
                                },
                                addButtonLabel = "Anexar arquivo ate 30 MB",
                            )
                            PrimaryButton(text = "Enviar", loading = viewModel.isSending, onClick = viewModel::sendReply)
                            if (viewModel.detail!!.canCancel) {
                                TextButton(onClick = { confirmCancel = true }) { Text("Cancelar solicitacao") }
                            }
                        }
                    }
                }
                if (viewModel.detail!!.attachments.isNotEmpty()) {
                    item { Text("Anexos publicos", style = MaterialTheme.typography.titleLarge) }
                    item {
                        AttachmentPicker(
                            attachments = viewModel.detail!!.attachments,
                            onAddClick = {},
                            showAddButton = false,
                            onAttachmentClick = ::openAttachment,
                        )
                    }
                }
                item { TextButton(onClick = onBack) { Text("Voltar") } }
            }
        }
    }

    if (previewImageFile != null) {
        AlertDialog(
            onDismissRequest = { previewImageFile = null },
            confirmButton = {
                TextButton(onClick = { previewImageFile = null }) { Text("Fechar") }
            },
            title = { Text(previewTitle.ifBlank { "Preview" }) },
            text = {
                AsyncImage(
                    model = previewImageFile,
                    contentDescription = previewTitle,
                    modifier = Modifier.fillMaxWidth(),
                )
            },
        )
    }

    if (confirmCancel) {
        AlertDialog(
            onDismissRequest = { confirmCancel = false },
            confirmButton = {
                TextButton(onClick = {
                    confirmCancel = false
                    viewModel.cancelDemand()
                }) {
                    Text("Cancelar")
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmCancel = false }) { Text("Voltar") }
            },
            title = { Text("Cancelar solicitacao") },
            text = { Text("Essa acao encerra a solicitacao atual no app.") },
        )
    }
}

private fun persistReadPermissions(context: Context, uris: List<Uri>) {
    uris.forEach { uri ->
        runCatching {
            context.contentResolver.takePersistableUriPermission(
                uri,
                Intent.FLAG_GRANT_READ_URI_PERMISSION,
            )
        }
    }
}

private fun Context.resolveDisplayName(uri: Uri): String {
    contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use { cursor ->
        if (cursor.moveToFirst()) {
            val index = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (index >= 0) {
                return cursor.getString(index) ?: "arquivo"
            }
        }
    }

    return uri.lastPathSegment?.substringAfterLast('/') ?: "arquivo"
}
