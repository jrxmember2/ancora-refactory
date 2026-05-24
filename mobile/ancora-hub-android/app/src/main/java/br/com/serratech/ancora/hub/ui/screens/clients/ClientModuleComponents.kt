package br.com.serratech.ancora.hub.ui.screens.clients

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import br.com.serratech.ancora.hub.domain.model.ClientDocument
import br.com.serratech.ancora.hub.domain.model.ClientTimelineItem
import br.com.serratech.ancora.hub.domain.model.DocumentGroup
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraEmptyState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraStatusChip
import br.com.serratech.ancora.hub.ui.theme.AncoraTone
import br.com.serratech.ancora.hub.ui.theme.spacing

@Composable
internal fun InfoLine(
    label: String,
    value: String?,
    modifier: Modifier = Modifier,
) {
    if (value.isNullOrBlank()) {
        return
    }

    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.xs),
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyLarge,
        )
    }
}

@Composable
internal fun DocumentGroupsSection(
    title: String,
    groups: List<DocumentGroup>,
    openingDocumentId: Long?,
    emptyTitle: String,
    onOpenDocument: (ClientDocument) -> Unit,
) {
    Column(
        verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.md),
    ) {
        AncoraSectionTitle(title = title)

        if (groups.isEmpty()) {
            AncoraEmptyState(
                title = emptyTitle,
                message = "Os documentos desta área aparecerão aqui assim que estiverem disponíveis.",
            )
            return
        }

        groups.forEach { group ->
            AncoraCard(bordered = true) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                ) {
                    Text(
                        text = group.label,
                        style = MaterialTheme.typography.titleMedium,
                    )
                    AncoraStatusChip(
                        label = "${group.count}",
                        tone = AncoraTone.Info,
                    )
                }

                group.items.forEach { document ->
                    AncoraCard(bordered = true) {
                        Text(
                            text = document.name,
                            style = MaterialTheme.typography.titleSmall,
                        )
                        document.documentDateBr?.let {
                            Text(
                                text = "Data do documento: $it",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        Text(
                            text = "Enviado em ${document.uploadedAtBr ?: "data não informada"}",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        AncoraGhostButton(
                            text = if (openingDocumentId == document.id) "Abrindo..." else "Abrir documento",
                            enabled = openingDocumentId != document.id,
                            onClick = { onOpenDocument(document) },
                        )
                    }
                }
            }
        }
    }
}

@Composable
internal fun TimelineSection(
    title: String,
    items: List<ClientTimelineItem>,
    emptyTitle: String,
) {
    Column(
        verticalArrangement = Arrangement.spacedBy(MaterialTheme.spacing.md),
    ) {
        AncoraSectionTitle(title = title)

        if (items.isEmpty()) {
            AncoraEmptyState(
                title = emptyTitle,
                message = "As observações e movimentações aparecerão aqui quando existirem registros.",
            )
            return
        }

        items.forEach { item ->
            AncoraCard(bordered = true) {
                Text(
                    text = item.note,
                    style = MaterialTheme.typography.bodyLarge,
                )
                item.userEmail?.let {
                    Text(
                        text = it,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                Text(
                    text = item.createdAtBr ?: "Agora mesmo",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
internal fun ActionErrorDialog(
    message: String,
    onDismiss: () -> Unit,
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Não foi possível concluir a ação.") },
        text = { Text(message) },
        confirmButton = {
            AncoraGhostButton(
                text = "Entendi",
                onClick = onDismiss,
            )
        },
    )
}
