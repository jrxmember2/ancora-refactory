package br.com.serratech.ancora.clientes.ui.screens.setup

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.clientes.R
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.UrlSetupForm
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
                    error = error.message ?: "Não conseguimos conectar ao endereço informado. Verifique se o endereço está correto e tente novamente.",
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
    val viewModel: SetupViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return SetupViewModel(container, replaceCurrentInstance) as T
            }
        }
    )
    var showHelp by mutableStateOf(false)

    LaunchedEffect(viewModel.uiState.completed) {
        if (viewModel.uiState.completed) {
            onConfigured()
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Image(
            painter = painterResource(R.drawable.logo_ancora_clientes),
            contentDescription = null,
            modifier = Modifier.fillMaxWidth(0.52f),
        )
        AncoraCard(
            modifier = Modifier
                .padding(top = 24.dp)
                .fillMaxWidth(),
        ) {
            Text("Conectar ao Âncora", style = MaterialTheme.typography.headlineSmall)
            if (replaceCurrentInstance) {
                Text(
                    "Ao trocar o endereço do Âncora, sua sessão atual será encerrada.",
                    color = MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
            UrlSetupForm(
                url = viewModel.uiState.url,
                loading = viewModel.uiState.isLoading,
                error = viewModel.uiState.error,
                onUrlChanged = viewModel::updateUrl,
                onSubmit = viewModel::submit,
                onHelpClick = { showHelp = true },
            )
        }
    }

    if (showHelp) {
        AlertDialog(
            onDismissRequest = { showHelp = false },
            confirmButton = {
                TextButton(onClick = { showHelp = false }) { Text("Entendi") }
            },
            title = { Text("Onde encontro esse endereço?") },
            text = {
                Text("Peça à administradora, ao escritório ou ao condomínio o domínio do Portal do Cliente. O aplicativo valida o acesso em /api/mobile/v1/health antes de prosseguir.")
            },
        )
    }
}
