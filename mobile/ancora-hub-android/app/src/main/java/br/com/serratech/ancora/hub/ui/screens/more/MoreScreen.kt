package br.com.serratech.ancora.hub.ui.screens.more

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.GridItemSpan
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Article
import androidx.compose.material.icons.outlined.AccountCircle
import androidx.compose.material.icons.outlined.AutoAwesome
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Groups
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.PieChartOutline
import androidx.compose.material.icons.outlined.Settings
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import br.com.serratech.ancora.hub.domain.model.SessionUser
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraModuleCard
import br.com.serratech.ancora.hub.ui.components.AncoraSearchBar
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing

private data class MoreMenuItem(
    val title: String,
    val description: String,
    val tone: AncoraTone,
    val icon: ImageVector,
    val enabled: Boolean,
    val statusLabel: String? = null,
    val onClick: () -> Unit,
)

@Composable
fun MoreScreen(
    modifier: Modifier = Modifier,
    sessionUser: SessionUser?,
    unreadNotifications: Int,
    onOpenClients: () -> Unit,
    onOpenProposals: () -> Unit,
    onOpenContracts: () -> Unit,
    onOpenSigner: () -> Unit,
    onOpenFinance: () -> Unit,
    onOpenLemeIa: () -> Unit,
    onOpenNotifications: () -> Unit,
    onOpenProfile: () -> Unit,
    onOpenSettings: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    var query by rememberSaveable { mutableStateOf("") }

    fun isModuleEnabled(vararg aliases: String): Boolean {
        if (sessionUser?.isSuperadmin == true) {
            return true
        }

        val moduleSlugs = sessionUser
            ?.modules
            ?.filter { it.enabled }
            ?.map { it.slug.lowercase() }
            ?.toSet()
            .orEmpty()

        if (moduleSlugs.isEmpty()) {
            return true
        }

        return aliases.any { alias -> moduleSlugs.contains(alias.lowercase()) }
    }

    val items = listOf(
        MoreMenuItem(
            title = "Clientes",
            description = "Acesse o relacionamento com clientes e o histórico essencial.",
            tone = AncoraTone.Success,
            icon = Icons.Outlined.Groups,
            enabled = isModuleEnabled("clientes", "cliente"),
            onClick = onOpenClients,
        ),
        MoreMenuItem(
            title = "Propostas",
            description = "Consulte propostas em andamento e próximos passos comerciais.",
            tone = AncoraTone.Info,
            icon = Icons.Outlined.Description,
            enabled = isModuleEnabled("propostas", "proposta"),
            onClick = onOpenProposals,
        ),
        MoreMenuItem(
            title = "Contratos",
            description = "Acompanhe contratos ativos com foco nas ações do dia.",
            tone = AncoraTone.Brand,
            icon = Icons.AutoMirrored.Outlined.Article,
            enabled = isModuleEnabled("contratos", "contrato"),
            onClick = onOpenContracts,
        ),
        MoreMenuItem(
            title = "Assinador",
            description = "Organize assinaturas pendentes com fluxo mobile mais objetivo.",
            tone = AncoraTone.Warning,
            icon = Icons.Outlined.Edit,
            enabled = isModuleEnabled("assinador", "assinaturas", "assinatura"),
            onClick = onOpenSigner,
        ),
        MoreMenuItem(
            title = "Financeiro 360",
            description = "Veja a visão financeira do escritório com acesso rápido.",
            tone = AncoraTone.Warning,
            icon = Icons.Outlined.PieChartOutline,
            enabled = isModuleEnabled("financeiro", "financeiro360"),
            onClick = onOpenFinance,
        ),
        MoreMenuItem(
            title = "Leme IA",
            description = "Abra os recursos inteligentes preparados para produtividade.",
            tone = AncoraTone.Info,
            icon = Icons.Outlined.AutoAwesome,
            enabled = isModuleEnabled("leme-ia", "leme_ia", "ia"),
            onClick = onOpenLemeIa,
        ),
        MoreMenuItem(
            title = "Notificações",
            description = "Veja seus avisos e marque rapidamente o que já leu.",
            tone = AncoraTone.Brand,
            icon = Icons.Outlined.Notifications,
            enabled = true,
            statusLabel = if (unreadNotifications > 0) {
                "$unreadNotifications não lidas"
            } else {
                "Em dia"
            },
            onClick = onOpenNotifications,
        ),
        MoreMenuItem(
            title = "Perfil",
            description = "Revise biometria, permissões, sessão e endereço da instância.",
            tone = AncoraTone.Neutral,
            icon = Icons.Outlined.AccountCircle,
            enabled = true,
            onClick = onOpenProfile,
        ),
        MoreMenuItem(
            title = "Configurações",
            description = "Ajuste preferências do aparelho e a forma de uso do aplicativo.",
            tone = AncoraTone.Neutral,
            icon = Icons.Outlined.Settings,
            enabled = true,
            onClick = onOpenSettings,
        ),
    )

    val filteredItems = items.filter { item ->
        if (query.isBlank()) {
            true
        } else {
            val normalizedQuery = query.trim().lowercase()
            item.title.lowercase().contains(normalizedQuery) ||
                item.description.lowercase().contains(normalizedQuery)
        }
    }

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(title = "Mais")
        LazyVerticalGrid(
            columns = GridCells.Adaptive(minSize = 168.dp),
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(
                horizontal = spacing.lg,
                vertical = spacing.lg,
            ),
            horizontalArrangement = Arrangement.spacedBy(spacing.md),
            verticalArrangement = Arrangement.spacedBy(spacing.md),
        ) {
            item(span = { GridItemSpan(maxLineSpan) }) {
                AncoraCard(bordered = true) {
                    AncoraStatusChip(
                        label = "Explorar módulos",
                        tone = AncoraTone.Brand,
                    )
                    Text(
                        text = "Acesse áreas importantes do Âncora com navegação pensada para celular.",
                        style = MaterialTheme.typography.headlineSmall,
                    )
                    Text(
                        text = "O menu “Mais” reúne acessos internos, atalhos úteis e telas de apoio para o uso diário do escritório.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    AncoraSearchBar(
                        query = query,
                        onQueryChange = { query = it },
                        placeholder = "Buscar módulo ou recurso",
                    )
                }
            }

            if (filteredItems.isEmpty()) {
                item(span = { GridItemSpan(maxLineSpan) }) {
                    AncoraEmptyState(
                        title = "Nenhum registro encontrado.",
                        message = "Tente buscar por outro módulo, atalho ou recurso interno.",
                    )
                }
            } else {
                items(filteredItems) { item ->
                    AncoraModuleCard(
                        title = item.title,
                        description = item.description,
                        tone = item.tone,
                        enabled = item.enabled,
                        icon = item.icon,
                        statusLabel = item.statusLabel,
                        onClick = item.onClick,
                    )
                }
            }
        }
    }
}
