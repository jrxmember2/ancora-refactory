package br.com.serratech.ancora.clientes.ui.screens.profile

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Logout
import androidx.compose.material.icons.outlined.PhotoCamera
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material.icons.outlined.SupportAgent
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.clientes.BuildConfig
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.SessionUser
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.AncoraTopBar
import br.com.serratech.ancora.clientes.ui.components.PrimaryButton
import coil.compose.AsyncImage
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.DateTimeParseException
import kotlinx.coroutines.launch

private val birthDateFormatter: DateTimeFormatter = DateTimeFormatter.ofPattern("dd/MM/yyyy")

data class ProfileUiState(
    val isLoading: Boolean = true,
    val isSavingProfile: Boolean = false,
    val isSavingPassword: Boolean = false,
    val error: String? = null,
    val successMessage: String? = null,
    val user: SessionUser? = null,
    val email: String = "",
    val phone: String = "",
    val birthDateInput: String = "",
    val avatarUri: Uri? = null,
    val currentPassword: String = "",
    val newPassword: String = "",
    val confirmPassword: String = "",
    val biometricEnabled: Boolean = false,
    val biometricAvailable: Boolean = false,
    val currentBaseUrl: String = "",
)

class ProfileViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(ProfileUiState())
        private set

    fun load(initialUser: SessionUser?) {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null, successMessage = null)
            val biometricEnabled = container.preferences.isBiometricEnabled()
            val currentBaseUrl = container.preferences.instanceBaseUrl()
            runCatching { container.authRepository.me() }
                .onSuccess { user ->
                    uiState = uiState.copy(
                        isLoading = false,
                        user = user,
                        email = user.email.orEmpty(),
                        phone = user.phone.orEmpty(),
                        birthDateInput = user.birthDate?.toDisplayBirthDate().orEmpty(),
                        biometricEnabled = biometricEnabled,
                        currentBaseUrl = currentBaseUrl,
                    )
                }
                .onFailure {
                    uiState = uiState.copy(
                        isLoading = false,
                        user = initialUser,
                        email = initialUser?.email.orEmpty(),
                        phone = initialUser?.phone.orEmpty(),
                        birthDateInput = initialUser?.birthDate?.toDisplayBirthDate().orEmpty(),
                        biometricEnabled = biometricEnabled,
                        currentBaseUrl = currentBaseUrl,
                        error = it.message ?: "Nao foi possivel carregar o perfil agora.",
                    )
                }
        }
    }

    fun setBiometricAvailable(available: Boolean) {
        uiState = uiState.copy(biometricAvailable = available)
    }

    fun updateEmail(value: String) {
        uiState = uiState.copy(email = value, error = null, successMessage = null)
    }

    fun updatePhone(value: String) {
        uiState = uiState.copy(phone = value, error = null, successMessage = null)
    }

    fun updateBirthDate(value: String) {
        uiState = uiState.copy(birthDateInput = value, error = null, successMessage = null)
    }

    fun updateCurrentPassword(value: String) {
        uiState = uiState.copy(currentPassword = value, error = null, successMessage = null)
    }

    fun updateNewPassword(value: String) {
        uiState = uiState.copy(newPassword = value, error = null, successMessage = null)
    }

    fun updateConfirmPassword(value: String) {
        uiState = uiState.copy(confirmPassword = value, error = null, successMessage = null)
    }

    fun updateAvatar(uri: Uri?) {
        uiState = uiState.copy(avatarUri = uri, error = null, successMessage = null)
    }

    fun updateBiometric(enabled: Boolean) {
        uiState = uiState.copy(biometricEnabled = enabled)
    }

    fun saveProfile(onUpdated: (SessionUser) -> Unit) {
        val birthDate = uiState.birthDateInput.toApiBirthDate()
            ?: return run {
                uiState = uiState.copy(error = "Informe a data de nascimento no formato dd/mm/aaaa.")
            }

        viewModelScope.launch {
            uiState = uiState.copy(isSavingProfile = true, error = null, successMessage = null)
            runCatching {
                container.authRepository.updateProfile(
                    email = uiState.email,
                    phone = uiState.phone,
                    birthDate = birthDate,
                    avatar = uiState.avatarUri,
                )
            }.onSuccess { user ->
                uiState = uiState.copy(
                    isSavingProfile = false,
                    user = user,
                    avatarUri = null,
                    email = user.email.orEmpty(),
                    phone = user.phone.orEmpty(),
                    birthDateInput = user.birthDate?.toDisplayBirthDate().orEmpty(),
                    successMessage = "Perfil atualizado com sucesso.",
                )
                onUpdated(user)
            }.onFailure {
                uiState = uiState.copy(
                    isSavingProfile = false,
                    error = it.message ?: "Nao foi possivel atualizar o perfil.",
                )
            }
        }
    }

    fun savePassword(onUpdated: (SessionUser) -> Unit) {
        if (uiState.newPassword.length < 8) {
            uiState = uiState.copy(error = "A nova senha precisa ter pelo menos 8 caracteres.")
            return
        }

        if (uiState.newPassword != uiState.confirmPassword) {
            uiState = uiState.copy(error = "A confirmacao da senha nao confere.")
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(isSavingPassword = true, error = null, successMessage = null)
            runCatching {
                container.authRepository.changePassword(
                    currentPassword = uiState.currentPassword,
                    newPassword = uiState.newPassword,
                    confirmation = uiState.confirmPassword,
                )
            }.onSuccess { user ->
                uiState = uiState.copy(
                    isSavingPassword = false,
                    user = user,
                    currentPassword = "",
                    newPassword = "",
                    confirmPassword = "",
                    successMessage = "Senha alterada com sucesso.",
                )
                onUpdated(user)
            }.onFailure {
                uiState = uiState.copy(
                    isSavingPassword = false,
                    error = it.message ?: "Nao foi possivel alterar a senha.",
                )
            }
        }
    }
}

@Composable
fun ProfileScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    sessionUser: SessionUser?,
    onLogout: () -> Unit,
    onOpenInstanceSettings: () -> Unit,
    onBiometricChanged: (Boolean) -> Unit,
    onProfileUpdated: (SessionUser) -> Unit,
) {
    val context = LocalContext.current
    val viewModel: ProfileViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProfileViewModel(container) as T
            }
        }
    )

    val avatarPicker = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri != null) {
            persistReadPermission(context, uri)
        }
        viewModel.updateAvatar(uri)
    }

    LaunchedEffect(sessionUser?.id) {
        viewModel.load(sessionUser)
        viewModel.setBiometricAvailable(container.biometricAuthenticator.isAvailable(context))
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Perfil")
        if (viewModel.uiState.isLoading) {
            br.com.serratech.ancora.clientes.ui.components.LoadingState("Carregando seu perfil...")
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(20.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                item {
                    AncoraCard {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(14.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                AsyncImage(
                                    model = viewModel.uiState.avatarUri ?: viewModel.uiState.user?.avatarUrl,
                                    contentDescription = "Foto do perfil",
                                    modifier = Modifier
                                        .size(72.dp)
                                        .clip(CircleShape)
                                        .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.08f)),
                                    contentScale = ContentScale.Crop,
                                )
                                androidx.compose.foundation.layout.Column(
                                    verticalArrangement = Arrangement.spacedBy(4.dp),
                                ) {
                                    Text(
                                        viewModel.uiState.user?.name ?: "Cliente",
                                        style = MaterialTheme.typography.headlineSmall,
                                    )
                                    Text(viewModel.uiState.user?.loginKey.orEmpty())
                                    Text(
                                        viewModel.uiState.user?.selectedCondominium?.name ?: "Sem condominio selecionado",
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                            IconButton(onClick = { avatarPicker.launch(arrayOf("image/*")) }) {
                                Icon(Icons.Outlined.PhotoCamera, contentDescription = "Alterar foto")
                            }
                        }
                        TextButton(onClick = { avatarPicker.launch(arrayOf("image/*")) }) {
                            Text(
                                if (viewModel.uiState.avatarUri == null) "Escolher foto"
                                else "Trocar foto selecionada (${context.resolveDisplayName(viewModel.uiState.avatarUri!!)})"
                            )
                        }
                    }
                }

                viewModel.uiState.error?.let { errorMessage ->
                    item {
                        AncoraCard {
                            Text("Atencao", style = MaterialTheme.typography.titleMedium)
                            Text(errorMessage, color = MaterialTheme.colorScheme.error)
                        }
                    }
                }

                viewModel.uiState.successMessage?.let { successMessage ->
                    item {
                        AncoraCard {
                            Text("Tudo certo", style = MaterialTheme.typography.titleMedium)
                            Text(successMessage, color = MaterialTheme.colorScheme.primary)
                        }
                    }
                }

                item {
                    AncoraCard {
                        Text("Seus dados", style = MaterialTheme.typography.titleMedium)
                        OutlinedTextField(
                            value = viewModel.uiState.email,
                            onValueChange = viewModel::updateEmail,
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("E-mail") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                            singleLine = true,
                        )
                        OutlinedTextField(
                            value = viewModel.uiState.phone,
                            onValueChange = viewModel::updatePhone,
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Telefone") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                            singleLine = true,
                        )
                        OutlinedTextField(
                            value = viewModel.uiState.birthDateInput,
                            onValueChange = viewModel::updateBirthDate,
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Data de nascimento") },
                            placeholder = { Text("dd/mm/aaaa") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            singleLine = true,
                        )
                        PrimaryButton(
                            text = "Salvar perfil",
                            loading = viewModel.uiState.isSavingProfile,
                            onClick = { viewModel.saveProfile(onProfileUpdated) },
                        )
                    }
                }

                item {
                    AncoraCard {
                        Text("Seguranca", style = MaterialTheme.typography.titleMedium)
                        Text(
                            if (viewModel.uiState.biometricAvailable) {
                                "Use biometria para desbloquear o app com mais rapidez."
                            } else {
                                "Este aparelho nao oferece biometria compativel no momento."
                            },
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        Switch(
                            checked = viewModel.uiState.biometricEnabled,
                            enabled = viewModel.uiState.biometricAvailable,
                            onCheckedChange = {
                                viewModel.updateBiometric(it)
                                onBiometricChanged(it)
                            },
                        )
                    }
                }

                item {
                    AncoraCard {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Icon(Icons.Outlined.Lock, contentDescription = null)
                            Text("Alterar senha", style = MaterialTheme.typography.titleMedium)
                        }
                        OutlinedTextField(
                            value = viewModel.uiState.currentPassword,
                            onValueChange = viewModel::updateCurrentPassword,
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Senha atual") },
                            visualTransformation = PasswordVisualTransformation(),
                            singleLine = true,
                        )
                        OutlinedTextField(
                            value = viewModel.uiState.newPassword,
                            onValueChange = viewModel::updateNewPassword,
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Nova senha") },
                            visualTransformation = PasswordVisualTransformation(),
                            singleLine = true,
                        )
                        OutlinedTextField(
                            value = viewModel.uiState.confirmPassword,
                            onValueChange = viewModel::updateConfirmPassword,
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Confirmar nova senha") },
                            visualTransformation = PasswordVisualTransformation(),
                            singleLine = true,
                        )
                        PrimaryButton(
                            text = "Atualizar senha",
                            loading = viewModel.uiState.isSavingPassword,
                            onClick = { viewModel.savePassword(onProfileUpdated) },
                        )
                    }
                }

                item {
                    AncoraCard {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Icon(Icons.Outlined.Settings, contentDescription = null)
                            Text("Endereco da instancia", style = MaterialTheme.typography.titleMedium)
                        }
                        Text(
                            viewModel.uiState.currentBaseUrl.ifBlank { "Nao configurado" },
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        TextButton(onClick = onOpenInstanceSettings) { Text("Alterar endereco do Ancora") }
                    }
                }

                if (!viewModel.uiState.user?.accessibleCondominiums.isNullOrEmpty()) {
                    item {
                        AncoraCard {
                            Text("Condominios vinculados", style = MaterialTheme.typography.titleMedium)
                        }
                    }
                    items(viewModel.uiState.user?.accessibleCondominiums.orEmpty()) { condominium ->
                        AncoraCard {
                            Text(condominium.name, style = MaterialTheme.typography.titleMedium)
                            condominium.syndicName?.let {
                                Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                    }
                }

                item {
                    AncoraCard {
                        Text("Versao do app", style = MaterialTheme.typography.titleMedium)
                        Text(BuildConfig.VERSION_NAME)
                    }
                }

                item {
                    AncoraCard {
                        TextButton(onClick = { openSupportWhatsapp(context) }) {
                            Icon(Icons.Outlined.SupportAgent, contentDescription = null)
                            Text(
                                "Falar com o suporte Serratech",
                                modifier = Modifier.padding(start = 8.dp),
                            )
                        }
                    }
                }

                item {
                    AncoraCard {
                        TextButton(onClick = onLogout) {
                            Icon(Icons.Outlined.Logout, contentDescription = null)
                            Text(
                                "Sair da conta",
                                modifier = Modifier.padding(start = 8.dp),
                            )
                        }
                    }
                }
            }
        }
    }
}

private fun persistReadPermission(context: Context, uri: Uri) {
    runCatching {
        context.contentResolver.takePersistableUriPermission(
            uri,
            Intent.FLAG_GRANT_READ_URI_PERMISSION,
        )
    }
}

private fun openSupportWhatsapp(context: Context) {
    val whatsappIntent = Intent(
        Intent.ACTION_VIEW,
        Uri.parse("https://wa.me/5527997232877"),
    ).apply {
        setPackage("com.whatsapp")
        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    }

    if (whatsappIntent.resolveActivity(context.packageManager) != null) {
        ContextCompat.startActivity(context, whatsappIntent, null)
    } else {
        Toast.makeText(
            context,
            "WhatsApp nao esta instalado neste aparelho.",
            Toast.LENGTH_LONG,
        ).show()
    }
}

private fun String?.toApiBirthDate(): String? {
    val value = this?.trim().orEmpty()
    if (value.isBlank()) {
        return ""
    }

    return try {
        LocalDate.parse(value, birthDateFormatter).toString()
    } catch (_: DateTimeParseException) {
        null
    }
}

private fun String.toDisplayBirthDate(): String = runCatching {
    LocalDate.parse(this).format(birthDateFormatter)
}.getOrDefault(this)

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
