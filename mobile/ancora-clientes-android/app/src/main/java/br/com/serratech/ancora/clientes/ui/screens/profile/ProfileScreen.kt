package br.com.serratech.ancora.clientes.ui.screens.profile

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import br.com.serratech.ancora.clientes.core.AppContainer
import br.com.serratech.ancora.clientes.domain.model.SessionUser
import br.com.serratech.ancora.clientes.ui.components.AncoraCard
import br.com.serratech.ancora.clientes.ui.components.AncoraTopBar

@Composable
fun ProfileScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    sessionUser: SessionUser?,
    onLogout: () -> Unit,
    onOpenInstanceSettings: () -> Unit,
    onBiometricChanged: (Boolean) -> Unit,
) {
    val context = LocalContext.current
    var biometricEnabled by mutableStateOf(false)
    var biometricAvailable by mutableStateOf(false)
    var currentBaseUrl by mutableStateOf("")
    var selectedCondominiumName by mutableStateOf(sessionUser?.selectedCondominium?.name ?: "Sem condominio selecionado")

    LaunchedEffect(Unit) {
        biometricEnabled = container.preferences.isBiometricEnabled()
        biometricAvailable = container.biometricAuthenticator.isAvailable(context)
        currentBaseUrl = container.preferences.instanceBaseUrl()
        runCatching { container.condominiumRepository.list() }
            .getOrNull()
            ?.let { selectedCondominiumName = it.selected?.name ?: "Sem condominio selecionado" }
    }

    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item { AncoraTopBar(title = "Perfil") }
        item {
            AncoraCard {
                Text(
                    sessionUser?.name ?: "Cliente",
                    style = MaterialTheme.typography.headlineSmall,
                )
                Text(sessionUser?.email ?: sessionUser?.loginKey ?: "")
                Text(
                    selectedCondominiumName,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        if (!sessionUser?.accessibleCondominiums.isNullOrEmpty()) {
            item {
                AncoraCard {
                    Text("Condominios vinculados", style = MaterialTheme.typography.titleMedium)
                }
            }
            items(sessionUser?.accessibleCondominiums.orEmpty()) { condominium ->
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
                Text("Biometria", style = MaterialTheme.typography.titleMedium)
                Text(
                    if (biometricAvailable) {
                        "Desbloqueie o app com biometria nas proximas aberturas."
                    } else {
                        "Este aparelho nao oferece biometria compativel para o app."
                    },
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Switch(
                    checked = biometricEnabled,
                    enabled = biometricAvailable,
                    onCheckedChange = {
                        biometricEnabled = it
                        onBiometricChanged(it)
                    },
                )
            }
        }
        item {
            AncoraCard {
                Text("Endereco da instancia", style = MaterialTheme.typography.titleMedium)
                Text(
                    currentBaseUrl.ifBlank { "Nao configurado" },
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                TextButton(onClick = onOpenInstanceSettings) { Text("Alterar endereco do Ancora") }
            }
        }
        item {
            AncoraCard {
                Text("Versao do app", style = MaterialTheme.typography.titleMedium)
                Text(br.com.serratech.ancora.clientes.BuildConfig.VERSION_NAME)
            }
        }
        item {
            AncoraCard {
                TextButton(onClick = onLogout) { Text("Sair da conta") }
            }
        }
    }
}
