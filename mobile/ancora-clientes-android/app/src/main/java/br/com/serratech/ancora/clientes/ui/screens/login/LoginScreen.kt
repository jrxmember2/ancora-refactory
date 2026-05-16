package br.com.serratech.ancora.clientes.ui.screens.login

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.clientes.R
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.SessionUser
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.PrimaryButton
import kotlinx.coroutines.launch

data class LoginUiState(
    val login: String = "",
    val password: String = "",
    val isLoading: Boolean = false,
    val error: String? = null,
    val user: SessionUser? = null,
)

class LoginViewModel(
    private val container: AppContainer,
) : ViewModel() {
    var uiState by mutableStateOf(LoginUiState())
        private set

    fun updateLogin(value: String) {
        uiState = uiState.copy(login = value, error = null)
    }

    fun updatePassword(value: String) {
        uiState = uiState.copy(password = value, error = null)
    }

    fun submit() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)
            runCatching {
                container.authRepository.login(uiState.login, uiState.password)
            }.onSuccess { user ->
                container.pushNotifier.registerCurrentDevice()
                uiState = uiState.copy(isLoading = false, user = user)
            }.onFailure { error ->
                uiState = uiState.copy(
                    isLoading = false,
                    error = error.message ?: "Não foi possível entrar agora. Tente novamente.",
                )
            }
        }
    }
}

@Composable
fun LoginScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    onLoginSuccess: (SessionUser) -> Unit,
    onOpenInstanceSettings: () -> Unit,
) {
    val viewModel: LoginViewModel = viewModel(
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return LoginViewModel(container) as T
            }
        }
    )

    LaunchedEffect(viewModel.uiState.user) {
        viewModel.uiState.user?.let { user ->
            onLoginSuccess(user)
        }
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(24.dp),
        verticalArrangement = Arrangement.Center,
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.End,
        ) {
            IconButton(onClick = onOpenInstanceSettings) {
                Icon(Icons.Outlined.Settings, contentDescription = "Alterar instância")
            }
        }
        Image(
            painter = painterResource(R.drawable.logo_ancora_clientes),
            contentDescription = null,
            modifier = Modifier
                .fillMaxWidth(0.52f)
                .align(Alignment.CenterHorizontally),
        )
        AncoraCard(modifier = Modifier.padding(top = 24.dp)) {
            Text("Entrar", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Acesse seu portal com a chave de acesso, login ou e-mail configurado pela equipe.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            OutlinedTextField(
                value = viewModel.uiState.login,
                onValueChange = viewModel::updateLogin,
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Login, chave ou e-mail") },
                singleLine = true,
            )
            OutlinedTextField(
                value = viewModel.uiState.password,
                onValueChange = viewModel::updatePassword,
                modifier = Modifier.fillMaxWidth(),
                label = { Text("Senha") },
                visualTransformation = PasswordVisualTransformation(),
                singleLine = true,
            )
            viewModel.uiState.error?.let {
                Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
            }
            PrimaryButton(
                text = "Entrar",
                loading = viewModel.uiState.isLoading,
                onClick = viewModel::submit,
            )
        }
    }
}
