package br.com.serratech.ancora.hub.ui.screens.collections

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.DeleteOutline
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import br.com.serratech.ancora.hub.core.AppContainer
import br.com.serratech.ancora.hub.data.dto.CollectionCreateRequestDto
import br.com.serratech.ancora.hub.data.dto.CollectionQuotaInputDto
import br.com.serratech.ancora.hub.domain.model.CollectionDetail
import br.com.serratech.ancora.hub.domain.model.CondominiumListItem
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.UnitListItem
import br.com.serratech.ancora.hub.ui.components.AncoraButton
import br.com.serratech.ancora.hub.ui.components.AncoraCard
import br.com.serratech.ancora.hub.ui.components.AncoraErrorState
import br.com.serratech.ancora.hub.ui.components.AncoraGhostButton
import br.com.serratech.ancora.hub.ui.components.AncoraLoadingState
import br.com.serratech.ancora.hub.ui.components.AncoraSecondaryButton
import br.com.serratech.ancora.hub.ui.components.AncoraSectionTitle
import br.com.serratech.ancora.hub.ui.components.AncoraTextField
import br.com.serratech.ancora.hub.ui.components.AncoraTopBar
import br.com.serratech.ancora.hub.ui.theme.spacing
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import kotlinx.coroutines.launch

private data class CollectionQuotaFormState(
    val referenceLabel: String = "",
    val dueDate: String = "",
    val originalAmount: String = "",
    val updatedAmount: String = "",
    val status: String = "pendente",
)

private data class CollectionEditorUiState(
    val isLoading: Boolean = true,
    val isSaving: Boolean = false,
    val error: String? = null,
    val condominiums: List<CondominiumListItem> = emptyList(),
    val units: List<UnitListItem> = emptyList(),
    val item: CollectionDetail? = null,
    val selectedCondominiumId: Long? = null,
    val selectedUnitId: Long? = null,
    val chargeType: String = "extrajudicial",
    val workflowStage: String = "triagem",
    val billingStatus: String = "a_faturar",
    val agreementTotal: String = "",
    val billingDate: String = "",
    val alertMessage: String = "",
    val notes: String = "",
    val entryStatus: String = "",
    val entryDueDate: String = "",
    val entryAmount: String = "",
    val feesAmount: String = "",
    val judicialCaseNumber: String = "",
    val calcBaseDate: String = "",
    val quotas: List<CollectionQuotaFormState> = listOf(CollectionQuotaFormState()),
    val chargeTypeOptions: List<FilterValueOption> = defaultChargeTypeOptions(),
    val workflowStageOptions: List<FilterValueOption> = defaultWorkflowStageOptions(),
    val billingStatusOptions: List<FilterValueOption> = defaultBillingStatusOptions(),
)

private class CollectionEditorViewModel(
    private val container: AppContainer,
    private val collectionId: Long?,
) : ViewModel() {
    var uiState by mutableStateOf(CollectionEditorUiState())
        private set

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            uiState = uiState.copy(isLoading = true, error = null)

            runCatching {
                val condominiums = container.clientRepository.listCondominiums(page = 1, perPage = 100).items
                val detail = collectionId?.let { container.collectionRepository.detail(it) }
                condominiums to detail
            }.onSuccess { (condominiums, detail) ->
                val nextState = if (detail != null) {
                    uiState.copy(
                        isLoading = false,
                        condominiums = condominiums,
                        item = detail,
                        selectedCondominiumId = detail.summary.condominiumId,
                        selectedUnitId = detail.summary.unitId,
                        chargeType = detail.chargeType ?: "extrajudicial",
                        workflowStage = detail.summary.workflowStage,
                        billingStatus = detail.summary.billingStatus ?: "a_faturar",
                        agreementTotal = detail.summary.agreementTotal?.toString().orEmpty(),
                        billingDate = detail.billingDate.orEmpty(),
                        alertMessage = "",
                        notes = "",
                        entryStatus = "",
                        entryDueDate = "",
                        entryAmount = detail.entryAmount?.toString().orEmpty(),
                        feesAmount = detail.feesAmount?.toString().orEmpty(),
                        judicialCaseNumber = detail.judicialCaseNumber.orEmpty(),
                        calcBaseDate = "",
                        quotas = if (detail.quotas.isNotEmpty()) {
                            detail.quotas.map { quota ->
                                CollectionQuotaFormState(
                                    referenceLabel = quota.referenceLabel,
                                    dueDate = quota.dueDate.orEmpty(),
                                    originalAmount = quota.originalAmount?.toString().orEmpty(),
                                    updatedAmount = quota.updatedAmount?.toString().orEmpty(),
                                    status = quota.status.orEmpty(),
                                )
                            }
                        } else {
                            listOf(CollectionQuotaFormState())
                        },
                        chargeTypeOptions = if (detail.options.chargeTypes.isNotEmpty()) detail.options.chargeTypes else defaultChargeTypeOptions(),
                        workflowStageOptions = if (detail.options.workflowStages.isNotEmpty()) detail.options.workflowStages else defaultWorkflowStageOptions(),
                        billingStatusOptions = if (detail.options.billingStatuses.isNotEmpty()) detail.options.billingStatuses else defaultBillingStatusOptions(),
                    )
                } else {
                    uiState.copy(
                        isLoading = false,
                        condominiums = condominiums,
                    )
                }

                uiState = nextState

                val condominiumId = nextState.selectedCondominiumId
                if (condominiumId != null) {
                    loadUnits(condominiumId)
                }
            }.onFailure {
                uiState = uiState.copy(
                    isLoading = false,
                    error = it.message ?: "Não foi possível preparar a edição da OS agora.",
                )
            }
        }
    }

    fun selectCondominium(condominiumId: Long?) {
        uiState = uiState.copy(
            selectedCondominiumId = condominiumId,
            selectedUnitId = null,
            units = emptyList(),
        )

        if (condominiumId != null) {
            viewModelScope.launch { loadUnits(condominiumId) }
        }
    }

    fun selectUnit(unitId: Long?) {
        uiState = uiState.copy(selectedUnitId = unitId)
    }

    fun updateChargeType(value: String) {
        uiState = uiState.copy(chargeType = value)
    }

    fun updateWorkflowStage(value: String) {
        uiState = uiState.copy(workflowStage = value)
    }

    fun updateBillingStatus(value: String) {
        uiState = uiState.copy(billingStatus = value)
    }

    fun updateAgreementTotal(value: String) {
        uiState = uiState.copy(agreementTotal = value)
    }

    fun updateBillingDate(value: String) {
        uiState = uiState.copy(billingDate = value)
    }

    fun updateAlertMessage(value: String) {
        uiState = uiState.copy(alertMessage = value)
    }

    fun updateNotes(value: String) {
        uiState = uiState.copy(notes = value)
    }

    fun updateEntryStatus(value: String) {
        uiState = uiState.copy(entryStatus = value)
    }

    fun updateEntryDueDate(value: String) {
        uiState = uiState.copy(entryDueDate = value)
    }

    fun updateEntryAmount(value: String) {
        uiState = uiState.copy(entryAmount = value)
    }

    fun updateFeesAmount(value: String) {
        uiState = uiState.copy(feesAmount = value)
    }

    fun updateJudicialCaseNumber(value: String) {
        uiState = uiState.copy(judicialCaseNumber = value)
    }

    fun updateCalcBaseDate(value: String) {
        uiState = uiState.copy(calcBaseDate = value)
    }

    fun updateQuota(index: Int, transform: (CollectionQuotaFormState) -> CollectionQuotaFormState) {
        val quotas = uiState.quotas.toMutableList()
        quotas[index] = transform(quotas[index])
        uiState = uiState.copy(quotas = quotas)
    }

    fun addQuota() {
        uiState = uiState.copy(quotas = uiState.quotas + CollectionQuotaFormState())
    }

    fun removeQuota(index: Int) {
        if (uiState.quotas.size <= 1) {
            return
        }

        val quotas = uiState.quotas.toMutableList().also { it.removeAt(index) }
        uiState = uiState.copy(quotas = quotas)
    }

    fun save(onSaved: (Long) -> Unit) {
        val selectedUnitId = uiState.selectedUnitId
        if (selectedUnitId == null) {
            uiState = uiState.copy(error = "Selecione a unidade vinculada à OS.")
            return
        }

        if (uiState.quotas.any { it.referenceLabel.isBlank() || it.dueDate.isBlank() || it.originalAmount.isBlank() }) {
            uiState = uiState.copy(error = "Preencha referência, vencimento e valor original de todas as cotas.")
            return
        }

        viewModelScope.launch {
            uiState = uiState.copy(isSaving = true, error = null)

            runCatching {
                val payload = CollectionCreateRequestDto(
                    unitId = selectedUnitId,
                    chargeType = uiState.chargeType,
                    workflowStage = uiState.workflowStage,
                    billingStatus = uiState.billingStatus,
                    agreementTotal = normalizeMoney(uiState.agreementTotal),
                    billingDate = normalizeDate(uiState.billingDate),
                    alertMessage = uiState.alertMessage.trim().takeIf { it.isNotBlank() },
                    notes = uiState.notes.trim().takeIf { it.isNotBlank() },
                    entryStatus = uiState.entryStatus.trim().takeIf { it.isNotBlank() },
                    entryDueDate = normalizeDate(uiState.entryDueDate),
                    entryAmount = normalizeMoney(uiState.entryAmount),
                    feesAmount = normalizeMoney(uiState.feesAmount),
                    judicialCaseNumber = uiState.judicialCaseNumber.trim().takeIf { it.isNotBlank() },
                    calcBaseDate = normalizeDate(uiState.calcBaseDate),
                    quotas = uiState.quotas.map { quota ->
                        CollectionQuotaInputDto(
                            referenceLabel = quota.referenceLabel.trim(),
                            dueDate = normalizeDate(quota.dueDate) ?: quota.dueDate.trim(),
                            originalAmount = normalizeMoney(quota.originalAmount) ?: 0.0,
                            updatedAmount = normalizeMoney(quota.updatedAmount),
                            status = quota.status.trim().takeIf { it.isNotBlank() },
                        )
                    },
                )

                if (collectionId == null) {
                    container.collectionRepository.create(payload)
                } else {
                    container.collectionRepository.update(collectionId, payload)
                }
            }.onSuccess { detail ->
                uiState = uiState.copy(isSaving = false)
                onSaved(detail.summary.id)
            }.onFailure {
                uiState = uiState.copy(
                    isSaving = false,
                    error = it.message ?: "Não foi possível salvar a OS agora.",
                )
            }
        }
    }

    private suspend fun loadUnits(condominiumId: Long) {
        runCatching {
            container.clientRepository.condominiumUnits(
                condominiumId = condominiumId,
                page = 1,
                perPage = 100,
            ).items
        }.onSuccess { units ->
            uiState = uiState.copy(units = units)
        }.onFailure {
            uiState = uiState.copy(
                error = it.message ?: "Não foi possível carregar as unidades deste condomínio.",
            )
        }
    }
}

@Composable
fun CollectionEditorScreen(
    modifier: Modifier = Modifier,
    container: AppContainer,
    collectionId: Long?,
    onSaved: (Long) -> Unit,
    onBack: () -> Unit,
) {
    val spacing = MaterialTheme.spacing
    val viewModel: CollectionEditorViewModel = viewModel(
        key = "collection-editor-${collectionId ?: "new"}",
        factory = object : ViewModelProvider.Factory {
            override fun <T : ViewModel> create(modelClass: Class<T>): T {
                @Suppress("UNCHECKED_CAST")
                return CollectionEditorViewModel(container, collectionId) as T
            }
        },
    )

    Column(modifier = modifier.fillMaxSize()) {
        AncoraTopBar(
            title = if (collectionId == null) "Nova OS" else "Editar cobrança",
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
                AncoraLoadingState(label = "Preparando a OS...")
            }

            viewModel.uiState.error != null &&
                viewModel.uiState.condominiums.isEmpty() &&
                viewModel.uiState.item == null -> {
                AncoraErrorState(
                    title = "Não foi possível abrir a tela agora.",
                    message = viewModel.uiState.error.orEmpty(),
                    onRetry = viewModel::refresh,
                )
            }

            else -> {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = spacing.lg, vertical = spacing.lg),
                    verticalArrangement = Arrangement.spacedBy(spacing.md),
                ) {
                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Vinculação")
                            SelectionField(
                                label = "Condomínio",
                                value = viewModel.uiState.condominiums.firstOrNull { it.id == viewModel.uiState.selectedCondominiumId }?.name.orEmpty(),
                                options = viewModel.uiState.condominiums.map { it.id to it.name },
                                onSelected = { viewModel.selectCondominium(it) },
                            )
                            SelectionField(
                                label = "Unidade",
                                value = viewModel.uiState.units.firstOrNull { it.id == viewModel.uiState.selectedUnitId }?.unitLabel.orEmpty(),
                                options = viewModel.uiState.units.map { it.id to "${it.unitLabel} · ${it.ownerName ?: "Sem proprietário"}" },
                                enabled = viewModel.uiState.selectedCondominiumId != null,
                                onSelected = { viewModel.selectUnit(it) },
                            )
                        }
                    }

                    item {
                        AncoraCard {
                            AncoraSectionTitle(title = "Dados principais")
                            SelectionField(
                                label = "Tipo de cobrança",
                                value = viewModel.uiState.chargeTypeOptions.firstOrNull { it.value == viewModel.uiState.chargeType }?.label.orEmpty(),
                                options = viewModel.uiState.chargeTypeOptions.map { it.value to it.label },
                                onSelected = { selected -> selected?.let(viewModel::updateChargeType) },
                            )
                            SelectionField(
                                label = "Etapa",
                                value = viewModel.uiState.workflowStageOptions.firstOrNull { it.value == viewModel.uiState.workflowStage }?.label.orEmpty(),
                                options = viewModel.uiState.workflowStageOptions.map { it.value to it.label },
                                onSelected = { selected -> selected?.let(viewModel::updateWorkflowStage) },
                            )
                            SelectionField(
                                label = "Status de faturamento",
                                value = viewModel.uiState.billingStatusOptions.firstOrNull { it.value == viewModel.uiState.billingStatus }?.label.orEmpty(),
                                options = viewModel.uiState.billingStatusOptions.map { it.value to it.label },
                                onSelected = { selected -> selected?.let(viewModel::updateBillingStatus) },
                            )
                            AncoraTextField(
                                value = viewModel.uiState.agreementTotal,
                                onValueChange = viewModel::updateAgreementTotal,
                                label = "Valor do acordo",
                                placeholder = "0,00",
                            )
                            AncoraTextField(
                                value = viewModel.uiState.entryAmount,
                                onValueChange = viewModel::updateEntryAmount,
                                label = "Entrada",
                                placeholder = "0,00",
                            )
                            AncoraTextField(
                                value = viewModel.uiState.feesAmount,
                                onValueChange = viewModel::updateFeesAmount,
                                label = "Honorários",
                                placeholder = "0,00",
                            )
                            AncoraTextField(
                                value = viewModel.uiState.billingDate,
                                onValueChange = viewModel::updateBillingDate,
                                label = "Data de faturamento",
                                placeholder = "AAAA-MM-DD",
                            )
                            AncoraTextField(
                                value = viewModel.uiState.calcBaseDate,
                                onValueChange = viewModel::updateCalcBaseDate,
                                label = "Base do cálculo",
                                placeholder = "AAAA-MM-DD",
                            )
                            AncoraTextField(
                                value = viewModel.uiState.entryDueDate,
                                onValueChange = viewModel::updateEntryDueDate,
                                label = "Vencimento da entrada",
                                placeholder = "AAAA-MM-DD",
                            )
                            AncoraTextField(
                                value = viewModel.uiState.entryStatus,
                                onValueChange = viewModel::updateEntryStatus,
                                label = "Status da entrada",
                                placeholder = "Ex.: pendente",
                            )
                            if (viewModel.uiState.chargeType == "judicial") {
                                AncoraTextField(
                                    value = viewModel.uiState.judicialCaseNumber,
                                    onValueChange = viewModel::updateJudicialCaseNumber,
                                    label = "Número do processo",
                                    placeholder = "0000000-00.0000.0.00.0000",
                                )
                            }
                            AncoraTextField(
                                value = viewModel.uiState.alertMessage,
                                onValueChange = viewModel::updateAlertMessage,
                                label = "Alerta interno",
                                placeholder = "Mensagem opcional para a equipe",
                                singleLine = false,
                            )
                            AncoraTextField(
                                value = viewModel.uiState.notes,
                                onValueChange = viewModel::updateNotes,
                                label = "Observações",
                                placeholder = "Observações internas da OS",
                                singleLine = false,
                            )
                        }
                    }

                    item {
                        AncoraSectionTitle(title = "Cotas")
                    }

                    itemsIndexed(viewModel.uiState.quotas) { index, quota ->
                        AncoraCard(bordered = true) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                Text(
                                    text = "Cota ${index + 1}",
                                    style = MaterialTheme.typography.titleMedium,
                                )
                                if (viewModel.uiState.quotas.size > 1) {
                                    IconButton(onClick = { viewModel.removeQuota(index) }) {
                                        Icon(
                                            imageVector = Icons.Outlined.DeleteOutline,
                                            contentDescription = "Remover cota",
                                        )
                                    }
                                }
                            }
                            AncoraTextField(
                                value = quota.referenceLabel,
                                onValueChange = { value ->
                                    viewModel.updateQuota(index) { it.copy(referenceLabel = value) }
                                },
                                label = "Referência",
                                placeholder = "Ex.: 05/2026",
                            )
                            AncoraTextField(
                                value = quota.dueDate,
                                onValueChange = { value ->
                                    viewModel.updateQuota(index) { it.copy(dueDate = value) }
                                },
                                label = "Vencimento",
                                placeholder = "AAAA-MM-DD",
                            )
                            AncoraTextField(
                                value = quota.originalAmount,
                                onValueChange = { value ->
                                    viewModel.updateQuota(index) { it.copy(originalAmount = value) }
                                },
                                label = "Valor original",
                                placeholder = "0,00",
                            )
                            AncoraTextField(
                                value = quota.updatedAmount,
                                onValueChange = { value ->
                                    viewModel.updateQuota(index) { it.copy(updatedAmount = value) }
                                },
                                label = "Valor atualizado",
                                placeholder = "Opcional",
                            )
                            AncoraTextField(
                                value = quota.status,
                                onValueChange = { value ->
                                    viewModel.updateQuota(index) { it.copy(status = value) }
                                },
                                label = "Status da cota",
                                placeholder = "Ex.: pendente",
                            )
                        }
                    }

                    item {
                        AncoraSecondaryButton(
                            text = "Adicionar cota",
                            onClick = viewModel::addQuota,
                        )
                    }

                    viewModel.uiState.error?.let { message ->
                        item {
                            AncoraCard(bordered = true) {
                                Text(
                                    text = message,
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.error,
                                )
                            }
                        }
                    }

                    item {
                        AncoraButton(
                            text = if (viewModel.uiState.isSaving) "Salvando..." else "Salvar OS",
                            enabled = !viewModel.uiState.isSaving,
                            onClick = { viewModel.save(onSaved) },
                        )
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun <T> SelectionField(
    label: String,
    value: String,
    options: List<Pair<T, String>>,
    enabled: Boolean = true,
    onSelected: (T?) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }

    ExposedDropdownMenuBox(
        expanded = expanded,
        onExpandedChange = { if (enabled) expanded = !expanded },
    ) {
        OutlinedTextField(
            value = value,
            onValueChange = {},
            readOnly = true,
            enabled = enabled,
            label = { Text(label) },
            modifier = Modifier
                .fillMaxWidth()
                .menuAnchor(),
            trailingIcon = {
                ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded)
            },
        )
        ExposedDropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false },
        ) {
            options.forEach { (id, text) ->
                DropdownMenuItem(
                    text = { Text(text) },
                    onClick = {
                        expanded = false
                        onSelected(id)
                    },
                )
            }
        }
    }
}

private fun normalizeMoney(value: String): Double? {
    val raw = value.trim()
    if (raw.isBlank()) {
        return null
    }

    val normalized = raw
        .replace(".", "")
        .replace(",", ".")

    return normalized.toDoubleOrNull()
}

private fun normalizeDate(value: String): String? {
    val raw = value.trim()
    if (raw.isBlank()) {
        return null
    }

    return runCatching {
        if (raw.contains("/")) {
            LocalDate.parse(raw, DateTimeFormatter.ofPattern("dd/MM/yyyy")).toString()
        } else {
            LocalDate.parse(raw).toString()
        }
    }.getOrNull() ?: raw
}

private fun defaultChargeTypeOptions(): List<FilterValueOption> = listOf(
    FilterValueOption("extrajudicial", "Cobrança extrajudicial"),
    FilterValueOption("judicial", "Cobrança judicial"),
)

private fun defaultWorkflowStageOptions(): List<FilterValueOption> = listOf(
    FilterValueOption("triagem", "Triagem"),
    FilterValueOption("apto_notificar", "Apto para notificar"),
    FilterValueOption("em_negociacao", "Em negociação"),
    FilterValueOption("aguardando_assinatura", "Aguardando assinatura"),
    FilterValueOption("acordo_ativo", "Acordo ativo"),
    FilterValueOption("acordo_inadimplido", "Acordo inadimplido"),
    FilterValueOption("apto_judicializar", "Apto para judicialização"),
    FilterValueOption("judicializado", "Judicializado"),
    FilterValueOption("pago_encerrado", "Pago / encerrado"),
    FilterValueOption("cancelado", "Cancelado"),
)

private fun defaultBillingStatusOptions(): List<FilterValueOption> = listOf(
    FilterValueOption("a_faturar", "A faturar"),
    FilterValueOption("faturado", "Faturado"),
    FilterValueOption("contrato_fixo", "Contrato fixo"),
    FilterValueOption("cancelado", "Cancelado"),
)
