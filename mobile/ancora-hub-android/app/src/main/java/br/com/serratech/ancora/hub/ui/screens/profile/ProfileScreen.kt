package br.com.serratech.ancora.hub.ui.screens.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.BuildConfig
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.domain.model.AppModule
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class ProfileUiState(
    val isLoading: Boolean = true,
    val baseUrl: String = "",
    val biometricEnabled: Boolean = false,
    val sessionRevoked: Boolean = false,
)

class ProfileViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(ProfileUiState())
        private set

    fun load() {
        viewModelScope.launch {
            uiState = uiState.copy(
                isLoading = false,
                baseUrl = container.preferences.instanceBaseUrl(),
                biometricEnabled = container.preferences.isBiometricEnabled(),
            )
        }
    }

    fun disableBiometric() {
        viewModelScope.launch {
            container.authRepository.disableBiometric()
            container.authRepository.logout()
            uiState = uiState.copy(
                biometricEnabled = false,
                sessionRevoked = true,
            )
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
    onBiometricDisabled: () -> Unit,
    onBack: (() -> Unit)? = null,
) {
    val spacing = MaterialTheme.spacing
    val viewModel: ProfileViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return ProfileViewModel(container) as T
            }
        },
    )

    LaunchedEffect(Unit) {
        viewModel.load()
    }

    LaunchedEffect(viewModel.uiState.sessionRevoked) {
        if (viewModel.uiState.sessionRevoked) {
            onBiometricDisabled()
        }
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = "Perfil",
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
            viewModel.uiState.isLoading -> AncoraLoadingState(
                label = "Carregando seu perfil...",
            )

            sessionUser == null -> AncoraEmptyState(
                title = "Usuário não encontrado.",
                message = "Não foi possível recuperar os dados da sua sessão.",
            )

            else -> LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(
                    horizontal = spacing.lg,
                    vertical = spacing.lg,
                ),
                verticalArrangement = Arrangement.spacedBy(spacing.md),
            ) {
                item {
                    AncoraCard(bordered = true) {
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(spacing.md),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(spacing.xxxl)
                                    .clip(CircleShape)
                                    .background(MaterialTheme.colorScheme.primaryContainer),
                                contentAlignment = Alignment.Center,
                            ) {
                                Text(
                                    text = sessionUser.initials.ifBlank { "AH" },
                                    style = MaterialTheme.typography.titleLarge,
                                    color = MaterialTheme.colorScheme.primary,
                                )
                            }
                            Column(
                                verticalArrangement = Arrangement.spacedBy(spacing.xs),
                            ) {
                                Text(
                                    text = sessionUser.name,
                                    style = MaterialTheme.typography.headlineSmall,
                                )
                                Text(
                                    text = sessionUser.email,
                                    style = MaterialTheme.typography.bodyLarge,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                                AncoraStatusChip(
                                    label = if (sessionUser.isSuperadmin) {
                                        "Acesso completo"
                                    } else {
                                        "Usuário interno"
                                    },
                                    tone = if (sessionUser.isSuperadmin) {
                                        AncoraTone.Brand
                                    } else {
                                        AncoraTone.Info
                                    },
                                )
                            }
                        }
                    }
                }

                item {
                    AncoraCard {
                        AncoraSectionTitle(title = "Dados básicos")
                        ProfileDetailRow(label = "E-mail", value = sessionUser.email)
                        ProfileDetailRow(label = "Perfil", value = sessionUser.role)
                        ProfileDetailRow(
                            label = "Permissões",
                            value = if (sessionUser.permissions.grantsAllRoutes) {
                                "Acesso completo a módulos e rotas"
                            } else {
                                "${sessionUser.permissions.groupKeys.size} grupos e ${sessionUser.permissions.routeNames.size} rotas liberadas"
                            },
                        )
                    }
                }

                item {
                    AncoraCard {
                        AncoraSectionTitle(title = "Módulos e permissões")
                        val enabledModules = sessionUser.modules.filter(AppModule::enabled)
                        if (enabledModules.isEmpty()) {
                            Text(
                                text = "Nenhum módulo interno foi carregado para esta conta.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        } else {
                            enabledModules.forEach { module ->
                                ProfileModuleRow(module = module)
                            }
                        }
                    }
                }

                item {
                    AncoraCard {
                        AncoraSectionTitle(title = "Biometria")
                        AncoraStatusChip(
                            label = if (viewModel.uiState.biometricEnabled) {
                                "Ativa neste aparelho"
                            } else {
                                "Desativada"
                            },
                            tone = if (viewModel.uiState.biometricEnabled) {
                                AncoraTone.Success
                            } else {
                                AncoraTone.Neutral
                            },
                        )
                        Text(
                            text = if (viewModel.uiState.biometricEnabled) {
                                "A biometria está protegendo o acesso local ao seu token neste aparelho."
                            } else {
                                "O aplicativo pedirá e-mail e senha quando a sessão local expirar."
                            },
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        if (viewModel.uiState.biometricEnabled) {
                            AncoraSecondaryButton(
                                text = "Desativar biometria",
                                onClick = viewModel::disableBiometric,
                            )
                        }
                    }
                }

                item {
                    AncoraCard {
                        AncoraSectionTitle(title = "Endereço do Âncora")
                        Text(
                            text = viewModel.uiState.baseUrl.ifBlank { "Ainda não configurado." },
                            style = MaterialTheme.typography.bodyLarge,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        AncoraSecondaryButton(
                            text = "Alterar endereço do Âncora",
                            onClick = onOpenInstanceSettings,
                        )
                    }
                }

                item {
                    AncoraCard {
                        AncoraSectionTitle(title = "Sessão")
                        Text(
                            text = "Janela de inatividade atual: ${sessionUser.sessionPolicy.inactiveExpiresInLabel}.",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        sessionUser.lastSeenAt?.let {
                            ProfileDetailRow(label = "Última atualização", value = it)
                        }
                        sessionUser.lastLoginAt?.let {
                            ProfileDetailRow(label = "Último login", value = it)
                        }
                    }
                }

                item {
                    AncoraCard {
                        AncoraSectionTitle(title = "Aplicativo")
                        ProfileDetailRow(label = "Versão", value = BuildConfig.VERSION_NAME)
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

@Composable
private fun ProfileDetailRow(
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
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium,
            fontWeight = FontWeight.Medium,
            color = MaterialTheme.colorScheme.onSurface,
        )
    }
}

@Composable
private fun ProfileModuleRow(module: AppModule) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(
            modifier = Modifier
                .weight(1f)
                .padding(end = MaterialTheme.spacing.sm),
        ) {
            Text(
                text = module.displayName,
                style = MaterialTheme.typography.titleSmall,
            )
            module.entryRouteName?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        AncoraStatusChip(
            label = "Liberado",
            tone = AncoraTone.Success,
        )
    }
}
