package br.com.serratech.ancora.hub.ui.screens.login

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AlternateEmail
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.R
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.security.requestBiometricPrompt
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraPasswordField
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTextField
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val isLoading: Boolean = false,
    val error: String? = null,
    val biometricAvailable: Boolean = false,
    val biometricPrompted: Boolean = false,
    val biometricEnabled: Boolean = false,
    val showBiometricOptIn: Boolean = false,
    val pendingBiometricConfirmation: Boolean = false,
    val stagedUser: SessionUser? = null,
    val completedUser: SessionUser? = null,
    val feedbackMessage: String? = null,
)

class LoginViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(LoginUiState())
        private set

    fun loadBiometricState(isAvailable: Boolean) {
        viewModelScope.launch {
            uiState = uiState.copy(
                biometricAvailable = isAvailable,
                biometricPrompted = container.preferences.wasBiometricPrompted(),
                biometricEnabled = isAvailable && container.preferences.isBiometricEnabled(),
            )
        }
    }

    fun updateEmail(value: String) {
        uiState = uiState.copy(email = value, error = null)
    }

    fun updatePassword(value: String) {
        uiState = uiState.copy(password = value, error = null)
    }

    fun submit() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)

            runCatching {
                container.authRepository.login(
                    email = uiState.email,
                    password = uiState.password,
                    biometricEnabled = uiState.biometricEnabled,
                )
            }.onSuccess { user ->
                val shouldOfferBiometric = !uiState.biometricEnabled &&
                    uiState.biometricAvailable &&
                    !uiState.biometricPrompted

                if (shouldOfferBiometric) {
                    uiState = uiState.copy(
                        isLoading = false,
                        stagedUser = user,
                        showBiometricOptIn = true,
                    )
                } else {
                    if (uiState.biometricEnabled) {
                        container.authRepository.enableBiometric()
                    }
                    container.authRepository.requestPushRegistration()
                    uiState = uiState.copy(
                        isLoading = false,
                        completedUser = user,
                    )
                }
            }.onFailure { error ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = error.message ?: "Não foi possível entrar agora. Tente novamente.",
                )
            }
        }
    }

    fun requestBiometricActivation() {
        uiState = uiState.copy(
            showBiometricOptIn = false,
            pendingBiometricConfirmation = true,
            error = null,
        )
    }

    fun finalizeWithoutBiometric() {
        val stagedUser = uiState.stagedUser ?: return

        viewModelScope.launch {
            container.authRepository.disableBiometric()
            container.authRepository.requestPushRegistration()
            uiState = uiState.copy(
                isLoading = false,
                completedUser = stagedUser,
                stagedUser = null,
                showBiometricOptIn = false,
                pendingBiometricConfirmation = false,
                biometricEnabled = false,
                biometricPrompted = true,
            )
        }
    }

    fun confirmBiometricAuthenticated() {
        val stagedUser = uiState.stagedUser ?: return

        viewModelScope.launch {
            uiState = uiState.copy(
                isLoading = true,
                error = null,
                pendingBiometricConfirmation = false,
            )

            runCatching {
                container.authRepository.upgradeSessionWithBiometric(uiState.email, uiState.password)
            }.onSuccess { user ->
                container.authRepository.enableBiometric()
                container.authRepository.requestPushRegistration()
                uiState = uiState.copy(
                    isLoading = false,
                    completedUser = user,
                    stagedUser = null,
                    biometricEnabled = true,
                    biometricPrompted = true,
                    feedbackMessage = "Biometria ativada com sucesso.",
                )
            }.onFailure {
                container.authRepository.disableBiometric()
                container.authRepository.requestPushRegistration()
                uiState = uiState.copy(
                    isLoading = false,
                    completedUser = stagedUser,
                    stagedUser = null,
                    biometricEnabled = false,
                    biometricPrompted = true,
                )
            }
        }
    }

    fun setError(message: String) {
        uiState = uiState.copy(
            error = message,
            isLoading = false,
            pendingBiometricConfirmation = false,
            showBiometricOptIn = uiState.stagedUser != null,
        )
    }

    fun consumeFeedbackMessage() {
        uiState = uiState.copy(feedbackMessage = null)
    }
}

@Composable
fun LoginScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onLoginSuccess: (SessionUser) -> Unit,
    onOpenInstanceSettings: () -> Unit,
    onFeedbackMessage: (String) -> Unit,
) {
    val context = LocalContext.current
    val spacing = MaterialTheme.spacing
    val viewModel: LoginViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return LoginViewModel(container) as T
            }
        },
    )

    LaunchedEffect(Unit) {
        viewModel.loadBiometricState(
            container.biometricAuthenticator.isAvailable(context),
        )
    }

    LaunchedEffect(viewModel.uiState.feedbackMessage) {
        viewModel.uiState.feedbackMessage?.let { message ->
            onFeedbackMessage(message)
            viewModel.consumeFeedbackMessage()
        }
    }

    LaunchedEffect(viewModel.uiState.completedUser) {
        viewModel.uiState.completedUser?.let(onLoginSuccess)
    }

    LaunchedEffect(viewModel.uiState.pendingBiometricConfirmation) {
        if (!viewModel.uiState.pendingBiometricConfirmation) {
            return@LaunchedEffect
        }

        requestBiometricPrompt(
            context = context,
            title = context.getString(R.string.biometric_enable_title),
            subtitle = context.getString(R.string.biometric_enable_message),
            negativeButtonText = "Agora não",
            onAuthenticated = viewModel::confirmBiometricAuthenticated,
            onNegativeAction = viewModel::finalizeWithoutBiometric,
            onUnavailable = viewModel::finalizeWithoutBiometric,
            onError = viewModel::setError,
        )
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = spacing.xl, vertical = spacing.xxl),
        verticalArrangement = Arrangement.Center,
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.End,
        ) {
            AncoraGhostButton(
                text = "Alterar endereço do Âncora",
                onClick = onOpenInstanceSettings,
            )
        }

        Image(
            painter = painterResource(R.drawable.logo_ancora_hub),
            contentDescription = null,
            modifier = Modifier
                .fillMaxWidth(0.42f)
                .align(Alignment.CenterHorizontally),
        )

        AncoraCard(
            modifier = Modifier.padding(top = spacing.xl),
            bordered = true,
        ) {
            AncoraStatusChip(
                label = "Uso interno do escritório",
                tone = AncoraTone.Brand,
            )
            Text(
                text = "Entrar no Âncora Hub",
                style = MaterialTheme.typography.headlineMedium,
            )
            Text(
                text = "Use seu e-mail e sua senha do sistema interno para acessar o aplicativo do escritório.",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            AncoraTextField(
                value = viewModel.uiState.email,
                onValueChange = viewModel::updateEmail,
                label = "E-mail",
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                leadingIcon = Icons.Outlined.AlternateEmail,
            )
            AncoraPasswordField(
                value = viewModel.uiState.password,
                onValueChange = viewModel::updatePassword,
            )
            viewModel.uiState.error?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error,
                )
            }
            AncoraButton(
                text = "Entrar",
                loading = viewModel.uiState.isLoading,
                onClick = viewModel::submit,
            )
        }
    }

    if (viewModel.uiState.showBiometricOptIn) {
        AlertDialog(
            onDismissRequest = viewModel::finalizeWithoutBiometric,
            confirmButton = {
                TextButton(onClick = viewModel::requestBiometricActivation) {
                    Text("Ativar biometria")
                }
            },
            dismissButton = {
                TextButton(onClick = viewModel::finalizeWithoutBiometric) {
                    Text("Agora não")
                }
            },
            title = { Text(stringResource(R.string.biometric_enable_title)) },
            text = {
                Text(stringResource(R.string.biometric_enable_message))
            },
        )
    }
}
