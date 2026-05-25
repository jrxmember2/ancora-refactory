package br.com.serratech.ancora.hub.ui.screens.signatures

import android.net.Uri
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.AttachFile
import androidx.compose.material.icons.outlined.DeleteOutline
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.listSaver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.data.repository.SignatureDraftSignerPayload
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraTextField
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

private data class SignatureSignerFormState(
    val name: String = "",
    val email: String = "",
    val phone: String = "",
    val documentNumber: String = "",
    val roleLabel: String = "Signatário",
)

private val SignatureSignerFormSaver = listSaver<SignatureSignerFormState, String>(
    save = {
        listOf(
            it.name,
            it.email,
            it.phone,
            it.documentNumber,
            it.roleLabel,
        )
    },
    restore = { values ->
        SignatureSignerFormState(
            name = values.getOrNull(0).orEmpty(),
            email = values.getOrNull(1).orEmpty(),
            phone = values.getOrNull(2).orEmpty(),
            documentNumber = values.getOrNull(3).orEmpty(),
            roleLabel = values.getOrNull(4).orEmpty().ifBlank { "Signatário" },
        )
    },
)

private val SignatureSignerListSaver = listSaver<MutableList<SignatureSignerFormState>, String>(
    save = { stateList ->
        stateList.flatMap { signer ->
            listOf(
                signer.name,
                signer.email,
                signer.phone,
                signer.documentNumber,
                signer.roleLabel,
            )
        }
    },
    restore = { values ->
        values.chunked(5)
            .map { chunk ->
                SignatureSignerFormState(
                    name = chunk.getOrNull(0).orEmpty(),
                    email = chunk.getOrNull(1).orEmpty(),
                    phone = chunk.getOrNull(2).orEmpty(),
                    documentNumber = chunk.getOrNull(3).orEmpty(),
                    roleLabel = chunk.getOrNull(4).orEmpty().ifBlank { "Signatário" },
                )
            }
            .toMutableList()
    },
)

@Composable
fun SignatureCreateScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onCreated: (Long) -> Unit,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    val spacing = MaterialTheme.spacing
    val coroutineScope = rememberCoroutineScope()
    val signers = rememberSaveable(saver = SignatureSignerListSaver) {
        mutableStateListOf(SignatureSignerFormState())
    }
    var title by rememberSaveable { mutableStateOf("") }
    var description by rememberSaveable { mutableStateOf("") }
    var category by rememberSaveable { mutableStateOf("") }
    var signerMessage by rememberSaveable { mutableStateOf("") }
    var selectedFileUri by rememberSaveable { mutableStateOf<String?>(null) }
    var selectedFileName by rememberSaveable { mutableStateOf("") }
    var isSaving by rememberSaveable { mutableStateOf(false) }
    var errorMessage by rememberSaveable { mutableStateOf<String?>(null) }

    val picker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent(),
    ) { uri ->
        if (uri != null) {
            selectedFileUri = uri.toString()
            selectedFileName = context.resolveDisplayName(uri) ?: "documento.pdf"
            errorMessage = null
        }
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Nova assinatura",
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                        contentDescription = "Voltar",
                    )
                }
            },
        )

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.lg),
            verticalArrangement = Arrangement.spacedBy(spacing.md),
        ) {
            item {
                AncoraCard(bordered = true) {
                    AncoraSectionTitle(title = "Documento")
                    Text(
                        text = if (selectedFileName.isBlank()) {
                            "Selecione um PDF para iniciar a assinatura digital."
                        } else {
                            selectedFileName
                        },
                        style = MaterialTheme.typography.bodyLarge,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                    ) {
                        AncoraSecondaryButton(
                            text = "Selecionar PDF",
                            icon = Icons.Outlined.AttachFile,
                            onClick = { picker.launch("application/pdf") },
                        )
                        if (selectedFileName.isNotBlank()) {
                            AncoraGhostButton(
                                text = "Trocar arquivo",
                                onClick = { picker.launch("application/pdf") },
                            )
                        }
                    }
                }
            }

            item {
                AncoraCard {
                    AncoraSectionTitle(title = "Dados do documento")
                    AncoraTextField(
                        value = title,
                        onValueChange = { title = it },
                        label = "Título",
                        placeholder = "Ex.: Termo de acordo",
                    )
                    AncoraTextField(
                        value = description,
                        onValueChange = { description = it },
                        label = "Descrição",
                        placeholder = "Informações rápidas para a equipe",
                        singleLine = false,
                    )
                    AncoraTextField(
                        value = category,
                        onValueChange = { category = it },
                        label = "Categoria",
                        placeholder = "Ex.: Acordo, contrato, proposta",
                    )
                    AncoraTextField(
                        value = signerMessage,
                        onValueChange = { signerMessage = it },
                        label = "Mensagem aos signatários",
                        placeholder = "Mensagem opcional para o e-mail de assinatura",
                        singleLine = false,
                    )
                }
            }

            item {
                Column(verticalArrangement = Arrangement.spacedBy(spacing.md)) {
                    AncoraSectionTitle(title = "Signatários")
                    signers.forEachIndexed { index, signer ->
                        AncoraCard(bordered = true) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                Text(
                                    text = "Signatário ${index + 1}",
                                    style = MaterialTheme.typography.titleMedium,
                                )
                                if (signers.size > 1) {
                                    IconButton(onClick = { signers.removeAt(index) }) {
                                        Icon(
                                            imageVector = Icons.Outlined.DeleteOutline,
                                            contentDescription = "Remover signatário",
                                        )
                                    }
                                }
                            }
                            AncoraTextField(
                                value = signer.name,
                                onValueChange = { signers[index] = signer.copy(name = it) },
                                label = "Nome",
                                placeholder = "Nome completo",
                            )
                            AncoraTextField(
                                value = signer.email,
                                onValueChange = { signers[index] = signer.copy(email = it) },
                                label = "E-mail",
                                placeholder = "email@empresa.com.br",
                            )
                            AncoraTextField(
                                value = signer.phone,
                                onValueChange = { signers[index] = signer.copy(phone = it) },
                                label = "Telefone",
                                placeholder = "(27) 99999-9999",
                            )
                            AncoraTextField(
                                value = signer.documentNumber,
                                onValueChange = { signers[index] = signer.copy(documentNumber = it) },
                                label = "CPF ou CNPJ",
                                placeholder = "Opcional",
                            )
                            AncoraTextField(
                                value = signer.roleLabel,
                                onValueChange = { signers[index] = signer.copy(roleLabel = it) },
                                label = "Função no documento",
                                placeholder = "Ex.: Síndico, cliente, testemunha",
                            )
                        }
                    }
                    AncoraSecondaryButton(
                        text = "Adicionar signatário",
                        icon = Icons.Outlined.Add,
                        onClick = { signers.add(SignatureSignerFormState()) },
                    )
                }
            }

            errorMessage?.let { message ->
                item {
                    AncoraCard(bordered = true) {
                        Text(
                            text = message,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.error,
                        )
                    }
                }
            }

            item {
                AncoraButton(
                    text = if (isSaving) "Enviando..." else "Enviar para assinatura",
                    enabled = !isSaving,
                    onClick = {
                        val fileUri = selectedFileUri
                        if (fileUri.isNullOrBlank()) {
                            errorMessage = "Selecione um PDF antes de continuar."
                            return@AncoraButton
                        }

                        if (title.isBlank()) {
                            errorMessage = "Informe o título do documento."
                            return@AncoraButton
                        }

                        if (signers.any { it.name.isBlank() || it.email.isBlank() || it.roleLabel.isBlank() }) {
                            errorMessage = "Preencha nome, e-mail e função de todos os signatários."
                            return@AncoraButton
                        }

                        coroutineScope.launch {
                            isSaving = true
                            errorMessage = null

                            runCatching {
                                val uri = Uri.parse(fileUri)
                                val bytes = withContext(Dispatchers.IO) {
                                    context.contentResolver.openInputStream(uri)?.use { input ->
                                        input.readBytes()
                                    }
                                } ?: throw IllegalStateException("Não foi possível ler o PDF selecionado.")

                                container.signatureRepository.create(
                                    fileName = selectedFileName.ifBlank { "documento.pdf" },
                                    mimeType = context.contentResolver.getType(uri) ?: "application/pdf",
                                    bytes = bytes,
                                    title = title.trim(),
                                    description = description.trim().takeIf { it.isNotBlank() },
                                    category = category.trim().takeIf { it.isNotBlank() },
                                    signerMessage = signerMessage.trim().takeIf { it.isNotBlank() },
                                    signers = signers.mapIndexed { index, signer ->
                                        SignatureDraftSignerPayload(
                                            name = signer.name.trim(),
                                            email = signer.email.trim(),
                                            phone = signer.phone.trim().takeIf { it.isNotBlank() },
                                            document_number = signer.documentNumber.trim().takeIf { it.isNotBlank() },
                                            role_label = signer.roleLabel.trim(),
                                            order_index = index + 1,
                                        )
                                    },
                                )
                            }.onSuccess { detail ->
                                isSaving = false
                                onCreated(detail.summary.id)
                            }.onFailure {
                                isSaving = false
                                errorMessage = it.message ?: "Não foi possível enviar o documento para assinatura agora."
                            }
                        }
                    },
                )
            }
        }
    }
}

private fun android.content.Context.resolveDisplayName(uri: Uri): String? {
    return contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)
        ?.use { cursor ->
            val columnIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (columnIndex >= 0 && cursor.moveToFirst()) {
                cursor.getString(columnIndex)
            } else {
                null
            }
        }
}
