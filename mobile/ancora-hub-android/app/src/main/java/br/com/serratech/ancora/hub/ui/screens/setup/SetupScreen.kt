package br.com.serratech.ancora.hub.ui.screens.setup

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Language
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.R
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTextField
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing
import kotlinx.coroutines.launch

data class SetupUiState(
    val url: String = "",
    val isLoading: Boolean = false,
    val error: String? = null,
    val completed: Boolean = false,
)

class SetupViewModel(
    private val container: AppContainer,
    private val replaceCurrentInstance: Boolean,
) : ViewModel() {
    var uiState by mutableStateOf(SetupUiState())
        private set

    init {
        if (replaceCurrentInstance) {
            viewModelScope.launch {
                uiState = uiState.copy(url = container.instanceRepository.currentBaseUrl())
            }
        }
    }

    fun updateUrl(value: String) {
        uiState = uiState.copy(url = value, error = null)
    }

    fun submit() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            val result = container.validateInstanceUseCase(uiState.url)

            result.onSuccess { (normalized, _) ->
                if (replaceCurrentInstance) {
                    runCatching { container.authRepository.unregisterCurrentDeviceIfNeeded() }
                }
                container.instanceRepository.saveBaseUrl(normalized, clearSession = replaceCurrentInstance)
                uiState = uiState.copy(isLoading = false, completed = true)
            }.onFailure { error ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = error.message ?: "Não foi possível validar o endereço informado agora.",
                )
            }
        }
    }
}

@Composable
fun SetupScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    replaceCurrentInstance: Boolean,
    onConfigured: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    val viewModel: SetupViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return SetupViewModel(container, replaceCurrentInstance) as T
            }
        },
    )

    LaunchedEffect(viewModel.uiState.completed) {
        if (viewModel.uiState.completed) {
            onConfigured()
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = spacing.xl, vertical = spacing.xxl),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Image(
            painter = painterResource(R.drawable.logo_ancora_hub),
            contentDescription = null,
            modifier = Modifier.fillMaxWidth(0.42f),
        )
        Spacer(modifier = Modifier.height(spacing.xl))
        AncoraCard(
            modifier = Modifier.fillMaxWidth(),
            bordered = true,
        ) {
            AncoraStatusChip(
                label = if (replaceCurrentInstance) "Troca de instância" else "Configuração inicial",
                tone = if (replaceCurrentInstance) AncoraTone.Warning else AncoraTone.Brand,
            )
            Text(
                text = "Conectar ao Âncora",
                style = MaterialTheme.typography.headlineMedium,
            )
            Text(
                text = "Informe o endereço da sua instância para validar a conexão com o Âncora Hub.",
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            if (replaceCurrentInstance) {
                Text(
                    text = "Ao trocar o endereço do Âncora, sua sessão atual será encerrada neste aparelho.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error,
                )
            }

            AncoraTextField(
                value = viewModel.uiState.url,
                onValueChange = viewModel::updateUrl,
                label = "Endereço do Âncora",
                placeholder = "https://sua-instancia.com.br",
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                leadingIcon = Icons.Outlined.Language,
                isError = viewModel.uiState.error != null,
            )

            Text(
                text = "O aplicativo prioriza HTTPS, mas também aceita HTTP em ambientes locais ou de desenvolvimento.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            viewModel.uiState.error?.let {
                Text(
                    text = it,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.error,
                    textAlign = TextAlign.Start,
                )
            }

            AncoraButton(
                text = "Validar endereço",
                loading = viewModel.uiState.isLoading,
                onClick = viewModel::submit,
            )
        }
    }
}
