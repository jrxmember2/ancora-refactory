package br.com.serratech.ancora.hub.ui.screens.settings

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.NotificationsActive
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.BuildConfig
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.data.repository.SessionValidationResult
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import kotlinx.coroutines.launch

private enum class ConnectionState {
    Idle,
    Success,
    Error,
}

private data class SettingsUiState(
    val isLoading: Boolean = true,
    val baseUrl: String = "",
    val biometricEnabled: Boolean = false,
    val hasRegisteredDevice: Boolean = false,
    val lastValidatedAt: String? = null,
    val isTestingConnection: Boolean = false,
    val isRefreshingUser: Boolean = false,
    val connectionState: ConnectionState = ConnectionState.Idle,
    val connectionMessage: String? = null,
    val feedbackMessage: String? = null,
)

private class SettingsViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(SettingsUiState())
        private set

    fun load() {
        viewModelScope.launch {
            uiState = uiState.copy(
                isLoading = false,
                baseUrl = container.preferences.instanceBaseUrl(),
                biometricEnabled = container.preferences.isBiometricEnabled(),
                hasRegisteredDevice = container.preferences.fcmToken().isNotBlank(),
                lastValidatedAt = container.sessionManager.cachedLastValidatedAt()
                    .takeIf { it.isNotBlank() },
            )
        }
    }

    fun testConnection() {
        val currentBaseUrl = uiState.baseUrl.trim()
        if (currentBaseUrl.isBlank()) {
            uiState = uiState.copy(
                connectionState = ConnectionState.Error,
                connectionMessage = "Informe um endereço do Âncora antes de testar a conexão.",
            )
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(
                isTestingConnection = true,
                connectionMessage = null,
            )

            val result = container.instanceRepository.validate(currentBaseUrl)
            result.onSuccess {
                uiState = uiState.copy(
                    isTestingConnection = false,
                    connectionState = ConnectionState.Success,
                    connectionMessage = "Conexão validada com sucesso.",
                )
            }.onFailure { error ->
                uiState = uiState.copy(
                    isTestingConnection = false,
                    connectionState = ConnectionState.Error,
                    connectionMessage = error.message
                        ?: "Não foi possível validar a conexão com esta instância agora.",
                )
            }
        }
    }

    fun refreshUserData(
        onUserUpdated: (SessionUser) -> Unit,
        onSessionExpired: (String) -> Unit,
    ) {
        viewModelScope.launch {
            uiState = uiState.copy(
                isRefreshingUser = true,
                feedbackMessage = null,
            )

            when (val result = container.authRepository.validateSession()) {
                is SessionValidationResult.Success -> {
                    onUserUpdated(result.user)
                    uiState = uiState.copy(
                        isRefreshingUser = false,
                        lastValidatedAt = container.sessionManager.cachedLastValidatedAt()
                            .takeIf { it.isNotBlank() },
                        feedbackMessage = "Dados do usuário atualizados com sucesso.",
                    )
                }

                is SessionValidationResult.Expired -> {
                    uiState = uiState.copy(isRefreshingUser = false)
                    onSessionExpired(result.message)
                }

                is SessionValidationResult.Unavailable -> {
                    uiState = uiState.copy(
                        isRefreshingUser = false,
                        feedbackMessage = result.message,
                    )
                }
            }
        }
    }

    fun registerNotifications(notificationsGranted: Boolean) {
        viewModelScope.launch {
            if (!notificationsGranted) {
                uiState = uiState.copy(
                    feedbackMessage = "Permita as notificações neste aparelho para receber avisos do Âncora Hub.",
                )
                return@launch
            }

            container.authRepository.requestPushRegistration()
            uiState = uiState.copy(
                hasRegisteredDevice = true,
                feedbackMessage = "Solicitação de registro do dispositivo enviada.",
            )
        }
    }

    fun disableBiometric(onCompleted: () -> Unit) {
        viewModelScope.launch {
            container.authRepository.disableBiometric()
            container.authRepository.logout()
            uiState = uiState.copy(
                biometricEnabled = false,
                hasRegisteredDevice = false,
                feedbackMessage = "Biometria desativada.",
            )
            onCompleted()
        }
    }
}

@Composable
fun SettingsScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    sessionUser: SessionUser?,
    onUserUpdated: (SessionUser) -> Unit,
    onOpenInstanceSettings: () -> Unit,
    onLogout: () -> Unit,
    onBiometricDisabled: () -> Unit,
    onSessionExpired: (String) -> Unit,
    onBack: () -> Unit,
) {
    val context = LocalContext.current
    val spacing = MaterialTheme.spacing
    val viewModel: SettingsViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return SettingsViewModel(container) as T
            }
        },
    )

    var notificationsGranted by rememberSaveable {
        mutableStateOf(context.notificationsPermissionGranted())
    }
    var showAboutDialog by rememberSaveable { mutableStateOf(false) }

    val notificationsPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { granted ->
        notificationsGranted = granted || context.notificationsPermissionGranted()
        if (notificationsGranted) {
            viewModel.registerNotifications(notificationsGranted = true)
        }
    }

    LaunchedEffect(Unit) {
        viewModel.load()
    }

    if (showAboutDialog) {
        AboutDialog(
            versionName = BuildConfig.VERSION_NAME,
            onDismiss = { showAboutDialog = false },
            onOpenWebsite = {
                showAboutDialog = false
                context.openExternalLink("https://www.serratech.tec.br")
            },
        )
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Configurações",
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
                AncoraLoadingState(label = "Carregando configurações...")
            }

            sessionUser == null -> {
                AncoraEmptyState(
                    title = "Usuário não encontrado.",
                    message = "Não foi possível recuperar os dados da sua sessão para abrir as configurações.",
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
                        AncoraCard(bordered = true) {
                            AncoraStatusChip(
                                label = "Ajustes do aplicativo",
                                tone = AncoraTone.Brand,
                            )
                            Text(
                                text = "Configure o Âncora Hub para o uso diário do escritório.",
                                style = MaterialTheme.typography.headlineSmall,
                            )
                            Text(
                                text = "Você pode validar a instância, revisar biometria, conferir notificações e manter seus dados atualizados sem sair do app.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                            viewModel.uiState.feedbackMessage?.let { message ->
                                Text(
                                    text = message,
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                            }
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Endereço da instância")
                            SettingsDetailRow(
                                label = "Endereço do Âncora",
                                value = viewModel.uiState.baseUrl.ifBlank { "Ainda não configurado." },
                            )
                            viewModel.uiState.connectionMessage?.let { message ->
                                AncoraStatusChip(
                                    label = when (viewModel.uiState.connectionState) {
                                        ConnectionState.Success -> "Conexão validada"
                                        ConnectionState.Error -> "Falha na conexão"
                                        ConnectionState.Idle -> "Sem teste recente"
                                    },
                                    tone = when (viewModel.uiState.connectionState) {
                                        ConnectionState.Success -> AncoraTone.Success
                                        ConnectionState.Error -> AncoraTone.Error
                                        ConnectionState.Idle -> AncoraTone.Neutral
                                    },
                                )
                                Text(
                                    text = message,
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = if (viewModel.uiState.connectionState == ConnectionState.Error) {
                                        MaterialTheme.colorScheme.error
                                    } else {
                                        MaterialTheme.colorScheme.onSurfaceVariant
                                    },
                                )
                            }
                            AncoraSecondaryButton(
                                text = "Testar conexão com a instância",
                                enabled = !viewModel.uiState.isTestingConnection,
                                onClick = viewModel::testConnection,
                            )
                            AncoraGhostButton(
                                text = "Alterar endereço do Âncora",
                                onClick = onOpenInstanceSettings,
                            )
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Segurança e sessão")
                            AncoraStatusChip(
                                label = if (viewModel.uiState.biometricEnabled) {
                                    "Biometria ativa"
                                } else {
                                    "Biometria inativa"
                                },
                                tone = if (viewModel.uiState.biometricEnabled) {
                                    AncoraTone.Success
                                } else {
                                    AncoraTone.Neutral
                                },
                            )
                            SettingsDetailRow(
                                label = "Política de sessão",
                                value = "Renovável por uso com janela de ${sessionUser.sessionPolicy.inactiveExpiresInLabel}.",
                            )
                            SettingsDetailRow(
                                label = "Proteção local",
                                value = if (viewModel.uiState.biometricEnabled) {
                                    "Token protegido por biometria neste aparelho."
                                } else {
                                    "O acesso volta para login quando a sessão expira."
                                },
                            )
                            viewModel.uiState.lastValidatedAt?.let {
                                SettingsDetailRow(
                                    label = "Última validação",
                                    value = it.prettyDateTime(),
                                )
                            }
                            if (viewModel.uiState.biometricEnabled) {
                                AncoraSecondaryButton(
                                    text = "Desativar biometria",
                                    onClick = {
                                        viewModel.disableBiometric(
                                            onCompleted = onBiometricDisabled,
                                        )
                                    },
                                )
                            }
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Notificações")
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(spacing.sm),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Icon(
                                    imageVector = Icons.Outlined.NotificationsActive,
                                    contentDescription = null,
                                    tint = MaterialTheme.colorScheme.primary,
                                )
                                AncoraStatusChip(
                                    label = if (notificationsGranted) {
                                        "Ativas no aparelho"
                                    } else {
                                        "Permissão pendente"
                                    },
                                    tone = if (notificationsGranted) {
                                        AncoraTone.Success
                                    } else {
                                        AncoraTone.Warning
                                    },
                                )
                            }
                            SettingsDetailRow(
                                label = "Registro do dispositivo",
                                value = if (viewModel.uiState.hasRegisteredDevice) {
                                    "Dispositivo já sinalizado para receber push."
                                } else {
                                    "O dispositivo ainda não confirmou o registro para push."
                                },
                            )
                            AncoraSecondaryButton(
                                text = if (notificationsGranted) {
                                    "Atualizar registro do dispositivo"
                                } else {
                                    "Ativar notificações"
                                },
                                onClick = {
                                    if (
                                        Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
                                        !notificationsGranted
                                    ) {
                                        notificationsPermissionLauncher.launch(
                                            Manifest.permission.POST_NOTIFICATIONS,
                                        )
                                    } else {
                                        notificationsGranted = true
                                        viewModel.registerNotifications(notificationsGranted = true)
                                    }
                                },
                            )
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Dados do usuário")
                            SettingsDetailRow(label = "Usuário", value = sessionUser.name)
                            SettingsDetailRow(label = "E-mail", value = sessionUser.email)
                            SettingsDetailRow(
                                label = "Permissão",
                                value = if (sessionUser.isSuperadmin) {
                                    "Acesso completo"
                                } else {
                                    "${sessionUser.modules.count { it.enabled }} módulos liberados"
                                },
                            )
                            AncoraSecondaryButton(
                                text = if (viewModel.uiState.isRefreshingUser) {
                                    "Atualizando..."
                                } else {
                                    "Atualizar dados do usuário"
                                },
                                enabled = !viewModel.uiState.isRefreshingUser,
                                onClick = {
                                    viewModel.refreshUserData(
                                        onUserUpdated = onUserUpdated,
                                        onSessionExpired = onSessionExpired,
                                    )
                                },
                            )
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Dados do app")
                            SettingsDetailRow(label = "Aplicativo", value = "Âncora Hub")
                            SettingsDetailRow(label = "Versão", value = BuildConfig.VERSION_NAME)
                            SettingsDetailRow(label = "Pacote", value = BuildConfig.APPLICATION_ID)
                            SettingsDetailRow(label = "API", value = "/api/hub/v1")
                            SettingsDetailRow(label = "Site", value = "www.serratech.tec.br")
                            AncoraSecondaryButton(
                                text = "Sobre",
                                onClick = { showAboutDialog = true },
                            )
                        }
                    }

                    item {
                        AncoraButton(
                            text = "Sair",
                            onClick = onLogout,
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun AboutDialog(
    versionName: String,
    onDismiss: () -> Unit,
    onOpenWebsite: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                text = "Sobre",
                style = MaterialTheme.typography.titleLarge,
            )
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.sm)) {
                Text(
                    text = "Âncora Hub",
                    style = MaterialTheme.typography.headlineSmall,
                )
                Text(
                    text = "Versão $versionName",
                    style = MaterialTheme.typography.bodyMedium,
                )
                Text(
                    text = "Desenvolvido por Serratech.",
                    style = MaterialTheme.typography.bodyMedium,
                )
                Text(
                    text = "Produto nativo Android para a operação mobile do escritório.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Text(
                    text = "Site: www.serratech.tec.br",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
        },
        confirmButton = {
            TextButton(onClick = onOpenWebsite) {
                Text("Abrir site")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Fechar")
            }
        },
    )
}

@Composable
private fun SettingsDetailRow(
    label: String,
    value: String,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.Top,
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.weight(0.42f),
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier
                .weight(0.58f)
                .padding(start = MaterialTheme.spacing.sm),
        )
    }
}

private fun Context.notificationsPermissionGranted(): Boolean {
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
        return true
    }

    return ContextCompat.checkSelfPermission(
        this,
        Manifest.permission.POST_NOTIFICATIONS,
    ) == PackageManager.PERMISSION_GRANTED
}

private fun Context.openExternalLink(url: String) {
    runCatching {
        startActivity(
            Intent(Intent.ACTION_VIEW, Uri.parse(url)).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            },
        )
    }
}

private fun String.prettyDateTime(): String = runCatching {
    Instant.parse(this).atZone(ZoneId.systemDefault()).format(
        DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm"),
    )
}.getOrElse { this }
