package br.com.serratech.ancora.hub.ui.screens.biometric

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.R
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.core.security.requestBiometricPrompt
import br.com.serratech.ancora.hub.data.repository.SessionValidationResult
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

data class BiometricUiState(
    val isValidatingSession: Boolean = false,
    val error: String? = null,
    val completedUser: SessionUser? = null,
)

class BiometricViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(BiometricUiState())
        private set

    fun validateSessionAfterUnlock(onSessionExpired: (String) -> Unit) {
        viewModelScope.launch {
            uiState = uiState.copy(isValidatingSession = true, error = null)

            when (val result = container.authRepository.validateSession()) {
                is SessionValidationResult.Success -> {
                    container.authRepository.requestPushRegistration()
                    uiState = uiState.copy(
                        isValidatingSession = false,
                        completedUser = result.user,
                    )
                }

                is SessionValidationResult.Expired -> {
                    container.sessionManager.clearSession(clearInstance = false)
                    uiState = uiState.copy(isValidatingSession = false)
                    onSessionExpired(result.message)
                }

                is SessionValidationResult.Unavailable -> {
                    uiState = uiState.copy(
                        isValidatingSession = false,
                        error = result.message,
                    )
                }
            }
        }
    }

    fun setPromptError(message: String) {
        uiState = uiState.copy(error = message, isValidatingSession = false)
    }
}

@Composable
fun BiometricScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onUnlocked: (SessionUser) -> Unit,
    onUsePassword: () -> Unit,
    onSessionExpired: (String) -> Unit,
    onBiometricUnavailable: () -> Unit,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val spacing = MaterialTheme.spacing
    val viewModel: BiometricViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return BiometricViewModel(container) as T
            }
        },
    )

    var promptRequested by remember { mutableStateOf(false) }
    var isResumed by remember { mutableStateOf(false) }

    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            isResumed = event == Lifecycle.Event.ON_RESUME
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    fun openPrompt() {
        requestBiometricPrompt(
            context = context,
            title = context.getString(R.string.biometric_title),
            subtitle = context.getString(R.string.biometric_subtitle),
            negativeButtonText = context.getString(R.string.biometric_negative),
            onAuthenticated = { viewModel.validateSessionAfterUnlock(onSessionExpired) },
            onNegativeAction = onUsePassword,
            onUnavailable = onBiometricUnavailable,
            onError = viewModel::setPromptError,
        )
    }

    LaunchedEffect(isResumed) {
        if (isResumed && !promptRequested) {
            promptRequested = true
            delay(350)
            if (isResumed) {
                openPrompt()
            }
        }
    }

    LaunchedEffect(viewModel.uiState.completedUser) {
        viewModel.uiState.completedUser?.let(onUnlocked)
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = spacing.xl, vertical = spacing.xxl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        AncoraCard(bordered = true) {
            AncoraStatusChip(
                label = "Acesso protegido",
                tone = AncoraTone.Info,
            )
            Icon(
                imageVector = Icons.Outlined.Fingerprint,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
            )
            Text(
                text = stringResource(R.string.biometric_title),
                style = MaterialTheme.typography.headlineMedium,
            )
            Text(
                text = stringResource(R.string.biometric_subtitle),
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(
                text = "Seu token continua protegido neste aparelho. A biometria apenas libera o acesso local.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            viewModel.uiState.error?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error,
                )
            }
            AncoraButton(
                text = if (viewModel.uiState.isValidatingSession) {
                    "Validando sessão..."
                } else {
                    "Usar biometria"
                },
                loading = viewModel.uiState.isValidatingSession,
                onClick = ::openPrompt,
            )
            AncoraSecondaryButton(
                text = stringResource(R.string.biometric_negative),
                onClick = onUsePassword,
            )
        }
    }
}
