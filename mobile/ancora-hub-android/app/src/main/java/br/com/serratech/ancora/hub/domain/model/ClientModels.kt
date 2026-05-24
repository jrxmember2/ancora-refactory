package br.com.serratech.ancora.hub.domain.model

import java.io.File

data class ClientListData(
    val items: List<ClientListItem>,
    val meta: PaginationMeta,
    val filters: ClientFilters,
)

data class ClientFilters(
    val scopes: List<FilterValueOption>,
    val statuses: List<FilterValueOption>,
)

data class ClientListItem(
    val id: Long,
    val name: String,
    val legalName: String?,
    val document: String?,
    val entityType: String,
    val entityTypeLabel: String,
    val profileScope: String,
    val profileScopeLabel: String,
    val roleTag: String?,
    val roleLabel: String?,
    val primaryPhone: String?,
    val primaryEmail: String?,
    val ownedUnitsCount: Int,
    val rentedUnitsCount: Int,
    val linkedUnitsCount: Int,
    val isActive: Boolean,
    val statusLabel: String,
    val initials: String,
    val createdAt: String?,
    val createdAtBr: String?,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class ClientDetail(
    val summary: ClientListItem,
    val gender: String?,
    val nationality: String?,
    val profession: String?,
    val maritalStatus: String?,
    val birthDate: String?,
    val birthDateBr: String?,
    val contractEndDate: String?,
    val contractEndDateBr: String?,
    val notes: String?,
    val description: String?,
    val inactiveReason: String?,
    val contacts: ClientContactGroups,
    val addresses: ClientAddressGroups,
    val documents: List<ClientDocument>,
    val documentGroups: List<DocumentGroup>,
    val timeline: List<ClientTimelineItem>,
    val linkedUnits: List<UnitListItem>,
    val linkedCondominiums: List<CondominiumListItem>,
)

data class CondominiumListData(
    val items: List<CondominiumListItem>,
    val meta: PaginationMeta,
    val filters: List<FilterValueOption>,
)

data class CondominiumListItem(
    val id: Long,
    val name: String,
    val cnpj: String?,
    val typeName: String?,
    val syndicName: String?,
    val administratorName: String?,
    val city: String?,
    val state: String?,
    val hasBlocks: Boolean,
    val unitsCount: Int,
    val isActive: Boolean,
    val statusLabel: String,
    val contractEndDate: String?,
    val contractEndDateBr: String?,
    val initials: String,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class CondominiumDetail(
    val summary: CondominiumListItem,
    val address: ClientAddress,
    val syndic: EntityReference?,
    val administrator: EntityReference?,
    val contacts: CondominiumContacts,
    val quickActions: CondominiumQuickActions,
    val bankDetails: String?,
    val characteristics: String?,
    val inactiveReason: String?,
    val documents: List<ClientDocument>,
    val documentGroups: List<DocumentGroup>,
    val units: List<UnitListItem>,
    val timeline: List<ClientTimelineItem>,
)

data class CondominiumUnitsPage(
    val items: List<UnitListItem>,
    val meta: PaginationMeta,
)

data class UnitListItem(
    val id: Long,
    val condominiumId: Long,
    val condominiumName: String?,
    val blockName: String?,
    val unitNumber: String,
    val unitLabel: String,
    val typeName: String?,
    val ownerName: String?,
    val tenantName: String?,
    val ownerPhone: String?,
    val tenantPhone: String?,
    val relationshipLabel: String?,
    val updatedAt: String?,
    val updatedAtBr: String?,
)

data class UnitDetail(
    val summary: UnitListItem,
    val owner: EntityReference?,
    val tenant: EntityReference?,
    val contacts: UnitContactGroups,
    val billingAddress: ClientAddress,
    val ownerNotes: String?,
    val tenantNotes: String?,
    val documents: List<ClientDocument>,
    val documentGroups: List<DocumentGroup>,
    val timeline: List<ClientTimelineItem>,
    val partyHistory: List<UnitPartyHistoryItem>,
)

data class ClientContactGroups(
    val phones: List<ContactValue>,
    val emails: List<ContactValue>,
    val billingEmails: List<ContactValue>,
)

data class CondominiumContacts(
    val phones: List<ContactAction>,
    val emails: List<ContactAction>,
)

data class CondominiumQuickActions(
    val phone: String?,
    val whatsapp: String?,
    val email: String?,
)

data class UnitContactGroups(
    val ownerPhones: List<ContactValue>,
    val ownerEmails: List<ContactValue>,
    val tenantPhones: List<ContactValue>,
    val tenantEmails: List<ContactValue>,
)

data class ContactValue(
    val label: String?,
    val value: String,
)

data class ContactAction(
    val label: String?,
    val value: String,
    val sourceLabel: String?,
    val whatsappValue: String?,
)

data class ClientAddressGroups(
    val primary: ClientAddress,
    val billing: ClientAddress,
)

data class ClientAddress(
    val street: String?,
    val number: String?,
    val complement: String?,
    val neighborhood: String?,
    val city: String?,
    val state: String?,
    val zip: String?,
    val notes: String?,
    val formatted: String?,
)

data class EntityReference(
    val id: Long,
    val name: String,
    val document: String?,
    val roleLabel: String?,
    val primaryPhone: String?,
    val primaryEmail: String?,
    val phones: List<ContactValue>,
    val emails: List<ContactValue>,
)

data class ClientDocument(
    val id: Long,
    val name: String,
    val category: String,
    val categoryLabel: String,
    val mimeType: String?,
    val fileSize: Int,
    val documentDate: String?,
    val documentDateBr: String?,
    val uploadedAt: String?,
    val uploadedAtBr: String?,
    val downloadPath: String?,
)

data class DocumentGroup(
    val key: String,
    val label: String,
    val items: List<ClientDocument>,
    val count: Int,
)

data class ClientTimelineItem(
    val id: Long,
    val note: String,
    val userEmail: String?,
    val createdAt: String?,
    val createdAtBr: String?,
)

data class UnitPartyHistoryItem(
    val id: Long,
    val partyType: String,
    val partyTypeLabel: String,
    val name: String,
    val startedAt: String?,
    val startedAtBr: String?,
    val endedAt: String?,
    val endedAtBr: String?,
    val changedByName: String?,
)

data class ClientDocumentsData(
    val items: List<ClientDocument>,
    val groups: List<DocumentGroup>,
)

data class DownloadedDocument(
    val file: File,
    val displayName: String,
    val mimeType: String,
)
