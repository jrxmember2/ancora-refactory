package br.com.serratech.ancora.hub.data.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ClientListResponseDto(
    val items: List<ClientSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: ClientFiltersDto = ClientFiltersDto(),
)

@Serializable
data class ClientFiltersDto(
    val scopes: List<ValueLabelDto> = emptyList(),
    val statuses: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class ClientSummaryDto(
    val id: Long,
    val name: String,
    @SerialName("legal_name") val legalName: String? = null,
    val document: String? = null,
    @SerialName("entity_type") val entityType: String = "pf",
    @SerialName("entity_type_label") val entityTypeLabel: String = "Pessoa física",
    @SerialName("profile_scope") val profileScope: String = "avulso",
    @SerialName("profile_scope_label") val profileScopeLabel: String = "Cliente avulso",
    @SerialName("role_tag") val roleTag: String? = null,
    @SerialName("role_label") val roleLabel: String? = null,
    @SerialName("primary_phone") val primaryPhone: String? = null,
    @SerialName("primary_email") val primaryEmail: String? = null,
    @SerialName("owned_units_count") val ownedUnitsCount: Int = 0,
    @SerialName("rented_units_count") val rentedUnitsCount: Int = 0,
    @SerialName("linked_units_count") val linkedUnitsCount: Int = 0,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("status_label") val statusLabel: String = "Ativo",
    val initials: String = "C",
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class ClientDetailResponseDto(
    val item: ClientDetailDto,
)

@Serializable
data class ClientDetailDto(
    val id: Long,
    val name: String,
    @SerialName("legal_name") val legalName: String? = null,
    val document: String? = null,
    @SerialName("entity_type") val entityType: String = "pf",
    @SerialName("entity_type_label") val entityTypeLabel: String = "Pessoa física",
    @SerialName("profile_scope") val profileScope: String = "avulso",
    @SerialName("profile_scope_label") val profileScopeLabel: String = "Cliente avulso",
    @SerialName("role_tag") val roleTag: String? = null,
    @SerialName("role_label") val roleLabel: String? = null,
    @SerialName("primary_phone") val primaryPhone: String? = null,
    @SerialName("primary_email") val primaryEmail: String? = null,
    @SerialName("owned_units_count") val ownedUnitsCount: Int = 0,
    @SerialName("rented_units_count") val rentedUnitsCount: Int = 0,
    @SerialName("linked_units_count") val linkedUnitsCount: Int = 0,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("status_label") val statusLabel: String = "Ativo",
    val initials: String = "C",
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    val gender: String? = null,
    val nationality: String? = null,
    val profession: String? = null,
    @SerialName("marital_status") val maritalStatus: String? = null,
    @SerialName("birth_date") val birthDate: String? = null,
    @SerialName("birth_date_br") val birthDateBr: String? = null,
    @SerialName("contract_end_date") val contractEndDate: String? = null,
    @SerialName("contract_end_date_br") val contractEndDateBr: String? = null,
    val notes: String? = null,
    val description: String? = null,
    @SerialName("inactive_reason") val inactiveReason: String? = null,
    val contacts: ClientContactGroupsDto = ClientContactGroupsDto(),
    val addresses: ClientAddressGroupsDto = ClientAddressGroupsDto(),
    val documents: List<ClientDocumentDto> = emptyList(),
    @SerialName("document_groups") val documentGroups: List<DocumentGroupDto> = emptyList(),
    val timeline: List<ClientTimelineDto> = emptyList(),
    @SerialName("linked_units") val linkedUnits: List<UnitSummaryDto> = emptyList(),
    @SerialName("linked_condominiums") val linkedCondominiums: List<CondominiumSummaryDto> = emptyList(),
)

@Serializable
data class CondominiumListResponseDto(
    val items: List<CondominiumSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
    val filters: CondominiumFiltersDto = CondominiumFiltersDto(),
)

@Serializable
data class CondominiumFiltersDto(
    val statuses: List<ValueLabelDto> = emptyList(),
)

@Serializable
data class CondominiumSummaryDto(
    val id: Long,
    val name: String,
    val cnpj: String? = null,
    @SerialName("type_name") val typeName: String? = null,
    @SerialName("syndic_name") val syndicName: String? = null,
    @SerialName("administrator_name") val administratorName: String? = null,
    val city: String? = null,
    val state: String? = null,
    @SerialName("has_blocks") val hasBlocks: Boolean = false,
    @SerialName("units_count") val unitsCount: Int = 0,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("status_label") val statusLabel: String = "Ativo",
    @SerialName("contract_end_date") val contractEndDate: String? = null,
    @SerialName("contract_end_date_br") val contractEndDateBr: String? = null,
    val initials: String = "C",
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class CondominiumDetailResponseDto(
    val item: CondominiumDetailDto,
)

@Serializable
data class CondominiumDetailDto(
    val id: Long,
    val name: String,
    val cnpj: String? = null,
    @SerialName("type_name") val typeName: String? = null,
    @SerialName("syndic_name") val syndicName: String? = null,
    @SerialName("administrator_name") val administratorName: String? = null,
    val city: String? = null,
    val state: String? = null,
    @SerialName("has_blocks") val hasBlocks: Boolean = false,
    @SerialName("units_count") val unitsCount: Int = 0,
    @SerialName("is_active") val isActive: Boolean = true,
    @SerialName("status_label") val statusLabel: String = "Ativo",
    @SerialName("contract_end_date") val contractEndDate: String? = null,
    @SerialName("contract_end_date_br") val contractEndDateBr: String? = null,
    val initials: String = "C",
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    val address: ClientAddressDto = ClientAddressDto(),
    val syndic: EntityReferenceDto? = null,
    val administrator: EntityReferenceDto? = null,
    val contacts: CondominiumContactsDto = CondominiumContactsDto(),
    @SerialName("quick_actions") val quickActions: CondominiumQuickActionsDto = CondominiumQuickActionsDto(),
    @SerialName("bank_details") val bankDetails: String? = null,
    val characteristics: String? = null,
    @SerialName("inactive_reason") val inactiveReason: String? = null,
    val documents: List<ClientDocumentDto> = emptyList(),
    @SerialName("document_groups") val documentGroups: List<DocumentGroupDto> = emptyList(),
    val units: List<UnitSummaryDto> = emptyList(),
    val timeline: List<ClientTimelineDto> = emptyList(),
)

@Serializable
data class CondominiumUnitsResponseDto(
    val items: List<UnitSummaryDto> = emptyList(),
    val meta: PaginationDto = PaginationDto(),
)

@Serializable
data class UnitSummaryDto(
    val id: Long,
    @SerialName("condominium_id") val condominiumId: Long,
    @SerialName("condominium_name") val condominiumName: String? = null,
    @SerialName("block_name") val blockName: String? = null,
    @SerialName("unit_number") val unitNumber: String,
    @SerialName("unit_label") val unitLabel: String,
    @SerialName("type_name") val typeName: String? = null,
    @SerialName("owner_name") val ownerName: String? = null,
    @SerialName("tenant_name") val tenantName: String? = null,
    @SerialName("owner_phone") val ownerPhone: String? = null,
    @SerialName("tenant_phone") val tenantPhone: String? = null,
    @SerialName("relationship_label") val relationshipLabel: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
)

@Serializable
data class UnitDetailResponseDto(
    val item: UnitDetailDto,
)

@Serializable
data class UnitDetailDto(
    val id: Long,
    @SerialName("condominium_id") val condominiumId: Long,
    @SerialName("condominium_name") val condominiumName: String? = null,
    @SerialName("block_name") val blockName: String? = null,
    @SerialName("unit_number") val unitNumber: String,
    @SerialName("unit_label") val unitLabel: String,
    @SerialName("type_name") val typeName: String? = null,
    @SerialName("owner_name") val ownerName: String? = null,
    @SerialName("tenant_name") val tenantName: String? = null,
    @SerialName("owner_phone") val ownerPhone: String? = null,
    @SerialName("tenant_phone") val tenantPhone: String? = null,
    @SerialName("relationship_label") val relationshipLabel: String? = null,
    @SerialName("updated_at") val updatedAt: String? = null,
    @SerialName("updated_at_br") val updatedAtBr: String? = null,
    val owner: EntityReferenceDto? = null,
    val tenant: EntityReferenceDto? = null,
    val contacts: UnitContactGroupsDto = UnitContactGroupsDto(),
    @SerialName("billing_address") val billingAddress: ClientAddressDto = ClientAddressDto(),
    @SerialName("owner_notes") val ownerNotes: String? = null,
    @SerialName("tenant_notes") val tenantNotes: String? = null,
    val documents: List<ClientDocumentDto> = emptyList(),
    @SerialName("document_groups") val documentGroups: List<DocumentGroupDto> = emptyList(),
    val timeline: List<ClientTimelineDto> = emptyList(),
    @SerialName("party_history") val partyHistory: List<UnitPartyHistoryDto> = emptyList(),
)

@Serializable
data class ClientDocumentsResponseDto(
    val items: List<ClientDocumentDto> = emptyList(),
    val groups: List<DocumentGroupDto> = emptyList(),
)

@Serializable
data class ClientContactGroupsDto(
    val phones: List<ContactValueDto> = emptyList(),
    val emails: List<ContactValueDto> = emptyList(),
    @SerialName("billing_emails") val billingEmails: List<ContactValueDto> = emptyList(),
)

@Serializable
data class CondominiumContactsDto(
    val phones: List<ContactActionDto> = emptyList(),
    val emails: List<ContactActionDto> = emptyList(),
)

@Serializable
data class CondominiumQuickActionsDto(
    val phone: String? = null,
    val whatsapp: String? = null,
    val email: String? = null,
)

@Serializable
data class UnitContactGroupsDto(
    @SerialName("owner_phones") val ownerPhones: List<ContactValueDto> = emptyList(),
    @SerialName("owner_emails") val ownerEmails: List<ContactValueDto> = emptyList(),
    @SerialName("tenant_phones") val tenantPhones: List<ContactValueDto> = emptyList(),
    @SerialName("tenant_emails") val tenantEmails: List<ContactValueDto> = emptyList(),
)

@Serializable
data class ContactValueDto(
    val label: String? = null,
    val value: String,
)

@Serializable
data class ContactActionDto(
    val label: String? = null,
    val value: String,
    @SerialName("source_label") val sourceLabel: String? = null,
    @SerialName("whatsapp_value") val whatsappValue: String? = null,
)

@Serializable
data class ClientAddressGroupsDto(
    val primary: ClientAddressDto = ClientAddressDto(),
    val billing: ClientAddressDto = ClientAddressDto(),
)

@Serializable
data class ClientAddressDto(
    val street: String? = null,
    val number: String? = null,
    val complement: String? = null,
    val neighborhood: String? = null,
    val city: String? = null,
    val state: String? = null,
    val zip: String? = null,
    val notes: String? = null,
    val formatted: String? = null,
)

@Serializable
data class EntityReferenceDto(
    val id: Long,
    val name: String,
    val document: String? = null,
    @SerialName("role_label") val roleLabel: String? = null,
    @SerialName("primary_phone") val primaryPhone: String? = null,
    @SerialName("primary_email") val primaryEmail: String? = null,
    val phones: List<ContactValueDto> = emptyList(),
    val emails: List<ContactValueDto> = emptyList(),
)

@Serializable
data class ClientDocumentDto(
    val id: Long,
    val name: String,
    val category: String = "other",
    @SerialName("category_label") val categoryLabel: String = "Outros documentos",
    @SerialName("mime_type") val mimeType: String? = null,
    @SerialName("file_size") val fileSize: Int = 0,
    @SerialName("document_date") val documentDate: String? = null,
    @SerialName("document_date_br") val documentDateBr: String? = null,
    @SerialName("uploaded_at") val uploadedAt: String? = null,
    @SerialName("uploaded_at_br") val uploadedAtBr: String? = null,
    @SerialName("download_path") val downloadPath: String? = null,
)

@Serializable
data class DocumentGroupDto(
    val key: String,
    val label: String,
    val items: List<ClientDocumentDto> = emptyList(),
    val count: Int = 0,
)

@Serializable
data class ClientTimelineDto(
    val id: Long,
    val note: String,
    @SerialName("user_email") val userEmail: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("created_at_br") val createdAtBr: String? = null,
)

@Serializable
data class UnitPartyHistoryDto(
    val id: Long,
    @SerialName("party_type") val partyType: String,
    @SerialName("party_type_label") val partyTypeLabel: String,
    val name: String,
    @SerialName("started_at") val startedAt: String? = null,
    @SerialName("started_at_br") val startedAtBr: String? = null,
    @SerialName("ended_at") val endedAt: String? = null,
    @SerialName("ended_at_br") val endedAtBr: String? = null,
    @SerialName("changed_by_name") val changedByName: String? = null,
)
