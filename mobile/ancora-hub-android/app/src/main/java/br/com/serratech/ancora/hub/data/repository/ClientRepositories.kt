package br.com.serratech.ancora.hub.data.repository

import android.app.Application
import br.com.serratech.ancora.hub.data.api.HubApiService
import br.com.serratech.ancora.hub.data.dto.ClientAddressDto
import br.com.serratech.ancora.hub.data.dto.ClientAddressGroupsDto
import br.com.serratech.ancora.hub.data.dto.ClientContactGroupsDto
import br.com.serratech.ancora.hub.data.dto.ClientDetailDto
import br.com.serratech.ancora.hub.data.dto.ClientDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.ClientDocumentDto
import br.com.serratech.ancora.hub.data.dto.ClientDocumentsResponseDto
import br.com.serratech.ancora.hub.data.dto.ClientFiltersDto
import br.com.serratech.ancora.hub.data.dto.ClientListResponseDto
import br.com.serratech.ancora.hub.data.dto.ClientSummaryDto
import br.com.serratech.ancora.hub.data.dto.ClientTimelineDto
import br.com.serratech.ancora.hub.data.dto.CondominiumContactsDto
import br.com.serratech.ancora.hub.data.dto.CondominiumDetailDto
import br.com.serratech.ancora.hub.data.dto.CondominiumDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.CondominiumFiltersDto
import br.com.serratech.ancora.hub.data.dto.CondominiumListResponseDto
import br.com.serratech.ancora.hub.data.dto.CondominiumQuickActionsDto
import br.com.serratech.ancora.hub.data.dto.CondominiumSummaryDto
import br.com.serratech.ancora.hub.data.dto.CondominiumUnitsResponseDto
import br.com.serratech.ancora.hub.data.dto.ContactActionDto
import br.com.serratech.ancora.hub.data.dto.ContactValueDto
import br.com.serratech.ancora.hub.data.dto.DocumentGroupDto
import br.com.serratech.ancora.hub.data.dto.EntityReferenceDto
import br.com.serratech.ancora.hub.data.dto.PaginationDto
import br.com.serratech.ancora.hub.data.dto.UnitContactGroupsDto
import br.com.serratech.ancora.hub.data.dto.UnitDetailDto
import br.com.serratech.ancora.hub.data.dto.UnitDetailResponseDto
import br.com.serratech.ancora.hub.data.dto.UnitPartyHistoryDto
import br.com.serratech.ancora.hub.data.dto.UnitSummaryDto
import br.com.serratech.ancora.hub.data.dto.ValueLabelDto
import br.com.serratech.ancora.hub.domain.model.ClientAddress
import br.com.serratech.ancora.hub.domain.model.ClientAddressGroups
import br.com.serratech.ancora.hub.domain.model.ClientContactGroups
import br.com.serratech.ancora.hub.domain.model.ClientDetail
import br.com.serratech.ancora.hub.domain.model.ClientDocument
import br.com.serratech.ancora.hub.domain.model.ClientDocumentsData
import br.com.serratech.ancora.hub.domain.model.ClientFilters
import br.com.serratech.ancora.hub.domain.model.ClientListData
import br.com.serratech.ancora.hub.domain.model.ClientListItem
import br.com.serratech.ancora.hub.domain.model.ClientTimelineItem
import br.com.serratech.ancora.hub.domain.model.CondominiumContacts
import br.com.serratech.ancora.hub.domain.model.CondominiumDetail
import br.com.serratech.ancora.hub.domain.model.CondominiumListData
import br.com.serratech.ancora.hub.domain.model.CondominiumListItem
import br.com.serratech.ancora.hub.domain.model.CondominiumQuickActions
import br.com.serratech.ancora.hub.domain.model.CondominiumUnitsPage
import br.com.serratech.ancora.hub.domain.model.ContactAction
import br.com.serratech.ancora.hub.domain.model.ContactValue
import br.com.serratech.ancora.hub.domain.model.DocumentGroup
import br.com.serratech.ancora.hub.domain.model.DownloadedDocument
import br.com.serratech.ancora.hub.domain.model.EntityReference
import br.com.serratech.ancora.hub.domain.model.FilterValueOption
import br.com.serratech.ancora.hub.domain.model.PaginationMeta
import br.com.serratech.ancora.hub.domain.model.UnitContactGroups
import br.com.serratech.ancora.hub.domain.model.UnitDetail
import br.com.serratech.ancora.hub.domain.model.UnitListItem
import br.com.serratech.ancora.hub.domain.model.UnitPartyHistoryItem
import java.io.File
import java.net.URLConnection
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import retrofit2.HttpException

class ClientRepository(
    application: Application,
    private val api: HubApiService,
    private val json: Json,
) {
    private val cacheDirectory = File(application.cacheDir, "hub-documents").apply {
        mkdirs()
    }

    suspend fun listClients(
        page: Int = 1,
        perPage: Int = 20,
        scope: String? = null,
        status: String? = null,
        query: String? = null,
    ): ClientListData = try {
        api.clients(
            page = page,
            perPage = perPage,
            scope = scope,
            status = status,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os clientes agora.",
            ),
            throwable,
        )
    }

    suspend fun clientDetail(clientId: Long): ClientDetail = try {
        api.client(clientId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o cliente agora.",
            ),
            throwable,
        )
    }

    suspend fun listCondominiums(
        page: Int = 1,
        perPage: Int = 20,
        status: String? = null,
        query: String? = null,
    ): CondominiumListData = try {
        api.condominiums(
            page = page,
            perPage = perPage,
            status = status,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os condomínios agora.",
            ),
            throwable,
        )
    }

    suspend fun condominiumDetail(condominiumId: Long): CondominiumDetail = try {
        api.condominium(condominiumId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar o condomínio agora.",
            ),
            throwable,
        )
    }

    suspend fun condominiumUnits(
        condominiumId: Long,
        page: Int = 1,
        perPage: Int = 20,
        query: String? = null,
    ): CondominiumUnitsPage = try {
        api.condominiumUnits(
            condominiumId = condominiumId,
            page = page,
            perPage = perPage,
            query = query?.takeIf { it.isNotBlank() },
        ).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar as unidades agora.",
            ),
            throwable,
        )
    }

    suspend fun unitDetail(unitId: Long): UnitDetail = try {
        api.unit(unitId).item.toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar a unidade agora.",
            ),
            throwable,
        )
    }

    suspend fun condominiumDocuments(condominiumId: Long): ClientDocumentsData = try {
        api.condominiumDocuments(condominiumId).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os documentos agora.",
            ),
            throwable,
        )
    }

    suspend fun unitDocuments(unitId: Long): ClientDocumentsData = try {
        api.unitDocuments(unitId).toDomain()
    } catch (throwable: Throwable) {
        throw UserFacingException(
            throwable.toClientFacingMessage(
                json = json,
                defaultMessage = "Não foi possível carregar os documentos agora.",
            ),
            throwable,
        )
    }

    suspend fun downloadDocument(documentId: Long, displayName: String): DownloadedDocument = withContext(Dispatchers.IO) {
        try {
            val response = api.downloadDocument(documentId)
            if (!response.isSuccessful) {
                throw HttpException(response)
            }

            val body = response.body()
                ?: throw UserFacingException("Documento indisponível no momento.")

            val safeName = sanitizeFileName(displayName)
            val targetFile = File(cacheDirectory, safeName)
            body.byteStream().use { input ->
                targetFile.outputStream().use { output ->
                    input.copyTo(output)
                }
            }

            val mimeType = response.headers()["Content-Type"]
                ?: URLConnection.guessContentTypeFromName(safeName)
                ?: "application/octet-stream"

            DownloadedDocument(
                file = targetFile,
                displayName = displayName,
                mimeType = mimeType,
            )
        } catch (throwable: Throwable) {
            throw UserFacingException(
                throwable.toClientFacingMessage(
                    json = json,
                    defaultMessage = "Não foi possível abrir o documento agora.",
                ),
                throwable,
            )
        }
    }

    private fun sanitizeFileName(name: String): String {
        val trimmed = name.trim().ifBlank { "documento" }
        return trimmed.replace(Regex("[^A-Za-z0-9._-]"), "_")
    }
}

private fun ClientListResponseDto.toDomain(): ClientListData = ClientListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.toDomain(),
)

private fun ClientFiltersDto.toDomain(): ClientFilters = ClientFilters(
    scopes = scopes.map { it.toDomain() },
    statuses = statuses.map { it.toDomain() },
)

private fun ClientSummaryDto.toDomain(): ClientListItem = ClientListItem(
    id = id,
    name = name,
    legalName = legalName,
    document = document,
    entityType = entityType,
    entityTypeLabel = entityTypeLabel,
    profileScope = profileScope,
    profileScopeLabel = profileScopeLabel,
    roleTag = roleTag,
    roleLabel = roleLabel,
    primaryPhone = primaryPhone,
    primaryEmail = primaryEmail,
    ownedUnitsCount = ownedUnitsCount,
    rentedUnitsCount = rentedUnitsCount,
    linkedUnitsCount = linkedUnitsCount,
    isActive = isActive,
    statusLabel = statusLabel,
    initials = initials,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ClientDetailDto.toDomain(): ClientDetail = ClientDetail(
    summary = toSummary(),
    gender = gender,
    nationality = nationality,
    profession = profession,
    maritalStatus = maritalStatus,
    birthDate = birthDate,
    birthDateBr = birthDateBr,
    contractEndDate = contractEndDate,
    contractEndDateBr = contractEndDateBr,
    notes = notes,
    description = description,
    inactiveReason = inactiveReason,
    contacts = contacts.toDomain(),
    addresses = addresses.toDomain(),
    documents = documents.map { it.toDomain() },
    documentGroups = documentGroups.map { it.toDomain() },
    timeline = timeline.map { it.toDomain() },
    linkedUnits = linkedUnits.map { it.toDomain() },
    linkedCondominiums = linkedCondominiums.map { it.toDomain() },
)

private fun ClientDetailDto.toSummary(): ClientListItem = ClientListItem(
    id = id,
    name = name,
    legalName = legalName,
    document = document,
    entityType = entityType,
    entityTypeLabel = entityTypeLabel,
    profileScope = profileScope,
    profileScopeLabel = profileScopeLabel,
    roleTag = roleTag,
    roleLabel = roleLabel,
    primaryPhone = primaryPhone,
    primaryEmail = primaryEmail,
    ownedUnitsCount = ownedUnitsCount,
    rentedUnitsCount = rentedUnitsCount,
    linkedUnitsCount = linkedUnitsCount,
    isActive = isActive,
    statusLabel = statusLabel,
    initials = initials,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun CondominiumListResponseDto.toDomain(): CondominiumListData = CondominiumListData(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
    filters = filters.statuses.map { it.toDomain() },
)

private fun CondominiumSummaryDto.toDomain(): CondominiumListItem = CondominiumListItem(
    id = id,
    name = name,
    cnpj = cnpj,
    typeName = typeName,
    syndicName = syndicName,
    administratorName = administratorName,
    city = city,
    state = state,
    hasBlocks = hasBlocks,
    unitsCount = unitsCount,
    isActive = isActive,
    statusLabel = statusLabel,
    contractEndDate = contractEndDate,
    contractEndDateBr = contractEndDateBr,
    initials = initials,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun CondominiumDetailDto.toDomain(): CondominiumDetail = CondominiumDetail(
    summary = toSummary(),
    address = address.toDomain(),
    syndic = syndic?.toDomain(),
    administrator = administrator?.toDomain(),
    contacts = contacts.toDomain(),
    quickActions = quickActions.toDomain(),
    bankDetails = bankDetails,
    characteristics = characteristics,
    inactiveReason = inactiveReason,
    documents = documents.map { it.toDomain() },
    documentGroups = documentGroups.map { it.toDomain() },
    units = units.map { it.toDomain() },
    timeline = timeline.map { it.toDomain() },
)

private fun CondominiumDetailDto.toSummary(): CondominiumListItem = CondominiumListItem(
    id = id,
    name = name,
    cnpj = cnpj,
    typeName = typeName,
    syndicName = syndicName,
    administratorName = administratorName,
    city = city,
    state = state,
    hasBlocks = hasBlocks,
    unitsCount = unitsCount,
    isActive = isActive,
    statusLabel = statusLabel,
    contractEndDate = contractEndDate,
    contractEndDateBr = contractEndDateBr,
    initials = initials,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun CondominiumUnitsResponseDto.toDomain(): CondominiumUnitsPage = CondominiumUnitsPage(
    items = items.map { it.toDomain() },
    meta = meta.toDomain(),
)

private fun UnitSummaryDto.toDomain(): UnitListItem = UnitListItem(
    id = id,
    condominiumId = condominiumId,
    condominiumName = condominiumName,
    blockName = blockName,
    unitNumber = unitNumber,
    unitLabel = unitLabel,
    typeName = typeName,
    ownerName = ownerName,
    tenantName = tenantName,
    ownerPhone = ownerPhone,
    tenantPhone = tenantPhone,
    relationshipLabel = relationshipLabel,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun UnitDetailDto.toDomain(): UnitDetail = UnitDetail(
    summary = toSummary(),
    owner = owner?.toDomain(),
    tenant = tenant?.toDomain(),
    contacts = contacts.toDomain(),
    billingAddress = billingAddress.toDomain(),
    ownerNotes = ownerNotes,
    tenantNotes = tenantNotes,
    documents = documents.map { it.toDomain() },
    documentGroups = documentGroups.map { it.toDomain() },
    timeline = timeline.map { it.toDomain() },
    partyHistory = partyHistory.map { it.toDomain() },
)

private fun UnitDetailDto.toSummary(): UnitListItem = UnitListItem(
    id = id,
    condominiumId = condominiumId,
    condominiumName = condominiumName,
    blockName = blockName,
    unitNumber = unitNumber,
    unitLabel = unitLabel,
    typeName = typeName,
    ownerName = ownerName,
    tenantName = tenantName,
    ownerPhone = ownerPhone,
    tenantPhone = tenantPhone,
    relationshipLabel = relationshipLabel,
    updatedAt = updatedAt,
    updatedAtBr = updatedAtBr,
)

private fun ClientDocumentsResponseDto.toDomain(): ClientDocumentsData = ClientDocumentsData(
    items = items.map { it.toDomain() },
    groups = groups.map { it.toDomain() },
)

private fun ClientContactGroupsDto.toDomain(): ClientContactGroups = ClientContactGroups(
    phones = phones.map { it.toDomain() },
    emails = emails.map { it.toDomain() },
    billingEmails = billingEmails.map { it.toDomain() },
)

private fun CondominiumContactsDto.toDomain(): CondominiumContacts = CondominiumContacts(
    phones = phones.map { it.toDomain() },
    emails = emails.map { it.toDomain() },
)

private fun CondominiumQuickActionsDto.toDomain(): CondominiumQuickActions = CondominiumQuickActions(
    phone = phone,
    whatsapp = whatsapp,
    email = email,
)

private fun UnitContactGroupsDto.toDomain(): UnitContactGroups = UnitContactGroups(
    ownerPhones = ownerPhones.map { it.toDomain() },
    ownerEmails = ownerEmails.map { it.toDomain() },
    tenantPhones = tenantPhones.map { it.toDomain() },
    tenantEmails = tenantEmails.map { it.toDomain() },
)

private fun ContactValueDto.toDomain(): ContactValue = ContactValue(
    label = label,
    value = value,
)

private fun ContactActionDto.toDomain(): ContactAction = ContactAction(
    label = label,
    value = value,
    sourceLabel = sourceLabel,
    whatsappValue = whatsappValue,
)

private fun ClientAddressGroupsDto.toDomain(): ClientAddressGroups = ClientAddressGroups(
    primary = primary.toDomain(),
    billing = billing.toDomain(),
)

private fun ClientAddressDto.toDomain(): ClientAddress = ClientAddress(
    street = street,
    number = number,
    complement = complement,
    neighborhood = neighborhood,
    city = city,
    state = state,
    zip = zip,
    notes = notes,
    formatted = formatted,
)

private fun EntityReferenceDto.toDomain(): EntityReference = EntityReference(
    id = id,
    name = name,
    document = document,
    roleLabel = roleLabel,
    primaryPhone = primaryPhone,
    primaryEmail = primaryEmail,
    phones = phones.map { it.toDomain() },
    emails = emails.map { it.toDomain() },
)

private fun ClientDocumentDto.toDomain(): ClientDocument = ClientDocument(
    id = id,
    name = name,
    category = category,
    categoryLabel = categoryLabel,
    mimeType = mimeType,
    fileSize = fileSize,
    documentDate = documentDate,
    documentDateBr = documentDateBr,
    uploadedAt = uploadedAt,
    uploadedAtBr = uploadedAtBr,
    downloadPath = downloadPath,
)

private fun DocumentGroupDto.toDomain(): DocumentGroup = DocumentGroup(
    key = key,
    label = label,
    items = items.map { it.toDomain() },
    count = count,
)

private fun ClientTimelineDto.toDomain(): ClientTimelineItem = ClientTimelineItem(
    id = id,
    note = note,
    userEmail = userEmail,
    createdAt = createdAt,
    createdAtBr = createdAtBr,
)

private fun UnitPartyHistoryDto.toDomain(): UnitPartyHistoryItem = UnitPartyHistoryItem(
    id = id,
    partyType = partyType,
    partyTypeLabel = partyTypeLabel,
    name = name,
    startedAt = startedAt,
    startedAtBr = startedAtBr,
    endedAt = endedAt,
    endedAtBr = endedAtBr,
    changedByName = changedByName,
)

private fun PaginationDto.toDomain(): PaginationMeta = PaginationMeta(
    currentPage = currentPage,
    lastPage = lastPage,
    perPage = perPage,
    total = total,
)

private fun ValueLabelDto.toDomain(): FilterValueOption = FilterValueOption(
    value = value,
    label = label,
    color = color,
)

private fun Throwable.toClientFacingMessage(
    json: Json,
    defaultMessage: String,
): String {
    clientApiMessageOrNull(json)?.let { message ->
        if (message.isNotBlank()) {
            return message
        }
    }

    return when {
        this is HttpException && code() == 401 -> "Sessão expirada. Entre novamente."
        this is HttpException && code() == 403 -> "Você não possui permissão para acessar este recurso."
        else -> defaultMessage
    }
}

private fun Throwable.clientApiMessageOrNull(json: Json): String? {
    val exception = this as? HttpException ?: return null
    val rawBody = runCatching { exception.response()?.errorBody()?.string() }.getOrNull().orEmpty()
    if (rawBody.isBlank()) {
        return null
    }

    return runCatching {
        json.decodeFromString(ClientApiMessagePayloadDto.serializer(), rawBody).message
    }.getOrNull()
}

@Serializable
private data class ClientApiMessagePayloadDto(
    val message: String? = null,
)
