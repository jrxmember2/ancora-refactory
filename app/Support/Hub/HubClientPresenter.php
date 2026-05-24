<?php

namespace App\Support\Hub;

use App\Models\ClientAttachment;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientTimeline;
use App\Models\ClientUnit;
use App\Models\ClientUnitPartyHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HubClientPresenter
{
    public static function scopeOptions(): array
    {
        return [
            HubModulePresenter::statusOption('avulso', 'Cliente avulso'),
            HubModulePresenter::statusOption('condominium', 'Condomínio'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            HubModulePresenter::statusOption('active', 'Ativo'),
            HubModulePresenter::statusOption('inactive', 'Inativo'),
        ];
    }

    public static function clientSummary(ClientEntity $entity): array
    {
        $phones = self::contactList($entity->phones_json, 'number');
        $emails = self::contactList($entity->emails_json, 'email');
        $scope = $entity->profile_scope === 'avulso' ? 'avulso' : 'condominium';

        return [
            'id' => (int) $entity->id,
            'name' => (string) $entity->display_name,
            'legal_name' => $entity->legal_name ? (string) $entity->legal_name : null,
            'document' => $entity->cpf_cnpj ? (string) $entity->cpf_cnpj : null,
            'entity_type' => (string) ($entity->entity_type ?: 'pf'),
            'entity_type_label' => ($entity->entity_type === 'pj') ? 'Pessoa jurídica' : 'Pessoa física',
            'profile_scope' => (string) ($entity->profile_scope ?: 'avulso'),
            'profile_scope_label' => $scope === 'avulso' ? 'Cliente avulso' : 'Condomínio',
            'role_tag' => $entity->role_tag ? (string) $entity->role_tag : null,
            'role_label' => self::roleLabel($entity->role_tag),
            'primary_phone' => $phones[0]['value'] ?? null,
            'primary_email' => $emails[0]['value'] ?? null,
            'owned_units_count' => (int) ($entity->owned_units_count ?? 0),
            'rented_units_count' => (int) ($entity->rented_units_count ?? 0),
            'linked_units_count' => (int) (($entity->owned_units_count ?? 0) + ($entity->rented_units_count ?? 0)),
            'is_active' => (bool) $entity->is_active,
            'status_label' => $entity->is_active ? 'Ativo' : 'Inativo',
            'initials' => self::initials($entity->display_name),
            'created_at' => $entity->created_at?->toAtomString(),
            'created_at_br' => $entity->created_at?->format('d/m/Y H:i'),
            'updated_at' => $entity->updated_at?->toAtomString(),
            'updated_at_br' => $entity->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function clientDetail(
        ClientEntity $entity,
        array $documents = [],
        array $timeline = [],
        array $linkedUnits = [],
        array $linkedCondominiums = [],
    ): array {
        return array_merge(self::clientSummary($entity), [
            'gender' => $entity->gender ? (string) $entity->gender : null,
            'nationality' => $entity->nationality ? (string) $entity->nationality : null,
            'profession' => $entity->profession ? (string) $entity->profession : null,
            'marital_status' => $entity->marital_status ? (string) $entity->marital_status : null,
            'birth_date' => $entity->birth_date?->toDateString(),
            'birth_date_br' => $entity->birth_date?->format('d/m/Y'),
            'contract_end_date' => $entity->contract_end_date?->toDateString(),
            'contract_end_date_br' => $entity->contract_end_date?->format('d/m/Y'),
            'notes' => $entity->notes ? (string) $entity->notes : null,
            'description' => $entity->description ? (string) $entity->description : null,
            'inactive_reason' => $entity->inactive_reason ? (string) $entity->inactive_reason : null,
            'contacts' => [
                'phones' => self::contactList($entity->phones_json, 'number'),
                'emails' => self::contactList($entity->emails_json, 'email'),
                'billing_emails' => self::contactList($entity->cobranca_emails_json, 'email'),
            ],
            'addresses' => [
                'primary' => self::address($entity->primary_address_json),
                'billing' => self::address($entity->billing_address_json),
            ],
            'documents' => $documents,
            'document_groups' => self::documentGroups($documents),
            'timeline' => $timeline,
            'linked_units' => $linkedUnits,
            'linked_condominiums' => $linkedCondominiums,
        ]);
    }

    public static function condominiumSummary(ClientCondominium $condominium): array
    {
        $address = self::address($condominium->address_json);

        return [
            'id' => (int) $condominium->id,
            'name' => (string) $condominium->name,
            'cnpj' => $condominium->cnpj ? (string) $condominium->cnpj : null,
            'type_name' => $condominium->type?->name ? (string) $condominium->type->name : null,
            'syndic_name' => $condominium->syndic?->display_name,
            'administrator_name' => $condominium->administradora?->display_name,
            'city' => $address['city'] ?: null,
            'state' => $address['state'] ?: null,
            'has_blocks' => (bool) $condominium->has_blocks,
            'units_count' => (int) ($condominium->units_count ?? 0),
            'is_active' => (bool) $condominium->is_active,
            'status_label' => $condominium->is_active ? 'Ativo' : 'Inativo',
            'contract_end_date' => $condominium->contract_end_date?->toDateString(),
            'contract_end_date_br' => $condominium->contract_end_date?->format('d/m/Y'),
            'initials' => self::initials($condominium->name),
            'updated_at' => $condominium->updated_at?->toAtomString(),
            'updated_at_br' => $condominium->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function condominiumDetail(
        ClientCondominium $condominium,
        array $documents = [],
        array $units = [],
        array $timeline = [],
    ): array {
        $contacts = self::condominiumContacts($condominium);

        return array_merge(self::condominiumSummary($condominium), [
            'address' => self::address($condominium->address_json),
            'syndic' => self::entityReference($condominium->syndic),
            'administrator' => self::entityReference($condominium->administradora),
            'contacts' => $contacts,
            'quick_actions' => [
                'phone' => $contacts['phones'][0]['value'] ?? null,
                'whatsapp' => $contacts['phones'][0]['whatsapp_value'] ?? null,
                'email' => $contacts['emails'][0]['value'] ?? null,
            ],
            'bank_details' => $condominium->bank_details ? (string) $condominium->bank_details : null,
            'characteristics' => $condominium->characteristics ? (string) $condominium->characteristics : null,
            'inactive_reason' => $condominium->inactive_reason ? (string) $condominium->inactive_reason : null,
            'documents' => $documents,
            'document_groups' => self::documentGroups($documents),
            'units' => $units,
            'timeline' => $timeline,
        ]);
    }

    public static function unitSummary(ClientUnit $unit, ?string $relationshipLabel = null): array
    {
        $owner = self::entityReference($unit->owner);
        $tenant = self::entityReference($unit->tenant);
        $blockName = $unit->block?->name ? (string) $unit->block->name : null;
        $unitNumber = (string) $unit->unit_number;

        return [
            'id' => (int) $unit->id,
            'condominium_id' => (int) $unit->condominium_id,
            'condominium_name' => $unit->condominium?->name ? (string) $unit->condominium->name : null,
            'block_name' => $blockName,
            'unit_number' => $unitNumber,
            'unit_label' => $blockName ? "{$blockName} • {$unitNumber}" : $unitNumber,
            'type_name' => $unit->type?->name ? (string) $unit->type->name : null,
            'owner_name' => $owner['name'] ?? null,
            'tenant_name' => $tenant['name'] ?? null,
            'owner_phone' => $owner['primary_phone'] ?? null,
            'tenant_phone' => $tenant['primary_phone'] ?? null,
            'relationship_label' => $relationshipLabel,
            'updated_at' => $unit->updated_at?->toAtomString(),
            'updated_at_br' => $unit->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function unitDetail(
        ClientUnit $unit,
        array $documents = [],
        array $timeline = [],
        array $partyHistory = [],
    ): array {
        $owner = self::entityReference($unit->owner);
        $tenant = self::entityReference($unit->tenant);
        $billingAddress = self::address(
            $unit->owner?->billing_address_json
            ?: $unit->tenant?->billing_address_json
            ?: $unit->owner?->primary_address_json
            ?: $unit->tenant?->primary_address_json
        );

        return array_merge(self::unitSummary($unit), [
            'owner' => $owner,
            'tenant' => $tenant,
            'contacts' => [
                'owner_phones' => self::contactList($unit->owner?->phones_json, 'number'),
                'owner_emails' => self::contactList($unit->owner?->emails_json, 'email'),
                'tenant_phones' => self::contactList($unit->tenant?->phones_json, 'number'),
                'tenant_emails' => self::contactList($unit->tenant?->emails_json, 'email'),
            ],
            'billing_address' => $billingAddress,
            'owner_notes' => $unit->owner_notes ? (string) $unit->owner_notes : null,
            'tenant_notes' => $unit->tenant_notes ? (string) $unit->tenant_notes : null,
            'documents' => $documents,
            'document_groups' => self::documentGroups($documents),
            'timeline' => $timeline,
            'party_history' => $partyHistory,
        ]);
    }

    public static function document(ClientAttachment $attachment): array
    {
        $category = self::documentCategory($attachment);

        return [
            'id' => (int) $attachment->id,
            'name' => (string) $attachment->original_name,
            'category' => $category,
            'category_label' => self::documentCategoryLabel($category),
            'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : null,
            'file_size' => (int) ($attachment->file_size ?? 0),
            'document_date' => $attachment->document_date?->toDateString(),
            'document_date_br' => $attachment->document_date?->format('d/m/Y'),
            'uploaded_at' => $attachment->created_at?->toAtomString(),
            'uploaded_at_br' => $attachment->created_at?->format('d/m/Y H:i'),
            'download_path' => '/api/hub/v1/documents/' . $attachment->id . '/download',
        ];
    }

    public static function documentGroups(array $documents): array
    {
        return collect($documents)
            ->groupBy(fn (array $document) => $document['category'] ?? 'other')
            ->map(function (Collection $items, string $category) {
                return [
                    'key' => $category,
                    'label' => self::documentCategoryLabel($category),
                    'items' => $items->values()->all(),
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc(function (array $group) {
                return match ($group['key']) {
                    'convention' => 500,
                    'regiment' => 400,
                    'ata' => 300,
                    'contract' => 200,
                    default => 100,
                };
            })
            ->values()
            ->all();
    }

    public static function timelineItem(ClientTimeline $timeline): array
    {
        return [
            'id' => (int) $timeline->id,
            'note' => (string) $timeline->note,
            'user_email' => $timeline->user_email ? (string) $timeline->user_email : null,
            'created_at' => $timeline->created_at?->toAtomString(),
            'created_at_br' => $timeline->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function unitPartyHistory(ClientUnitPartyHistory $history): array
    {
        return [
            'id' => (int) $history->id,
            'party_type' => (string) $history->party_type,
            'party_type_label' => $history->party_type === 'tenant' ? 'Locatário' : 'Proprietário',
            'name' => $history->entity?->display_name ?: 'Não informado',
            'started_at' => $history->started_at?->toAtomString(),
            'started_at_br' => $history->started_at?->format('d/m/Y'),
            'ended_at' => $history->ended_at?->toAtomString(),
            'ended_at_br' => $history->ended_at?->format('d/m/Y'),
            'changed_by_name' => $history->changedBy?->name,
        ];
    }

    public static function entityReference(?ClientEntity $entity): ?array
    {
        if (!$entity) {
            return null;
        }

        $phones = self::contactList($entity->phones_json, 'number');
        $emails = self::contactList($entity->emails_json, 'email');

        return [
            'id' => (int) $entity->id,
            'name' => (string) $entity->display_name,
            'document' => $entity->cpf_cnpj ? (string) $entity->cpf_cnpj : null,
            'role_label' => self::roleLabel($entity->role_tag),
            'primary_phone' => $phones[0]['value'] ?? null,
            'primary_email' => $emails[0]['value'] ?? null,
            'phones' => $phones,
            'emails' => $emails,
        ];
    }

    public static function address(mixed $address): array
    {
        $address = is_array($address) ? $address : [];

        $street = trim((string) ($address['street'] ?? ''));
        $number = trim((string) ($address['number'] ?? ''));
        $complement = trim((string) ($address['complement'] ?? ''));
        $neighborhood = trim((string) ($address['neighborhood'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $state = trim((string) ($address['state'] ?? ''));
        $zip = trim((string) ($address['zip'] ?? ''));
        $notes = trim((string) ($address['notes'] ?? ''));

        $line = trim(collect([$street, $number])->filter()->implode(', '));
        $line2 = trim(collect([$complement, $neighborhood])->filter()->implode(' • '));
        $cityLine = trim(collect([collect([$city, $state])->filter()->implode(' - '), $zip])->filter()->implode(' • '));
        $full = collect([$line, $line2, $cityLine])->filter()->implode(' - ');

        return [
            'street' => $street !== '' ? $street : null,
            'number' => $number !== '' ? $number : null,
            'complement' => $complement !== '' ? $complement : null,
            'neighborhood' => $neighborhood !== '' ? $neighborhood : null,
            'city' => $city !== '' ? $city : null,
            'state' => $state !== '' ? $state : null,
            'zip' => $zip !== '' ? $zip : null,
            'notes' => $notes !== '' ? $notes : null,
            'formatted' => $full !== '' ? $full : null,
        ];
    }

    private static function condominiumContacts(ClientCondominium $condominium): array
    {
        $phones = [];
        $emails = [];

        foreach ([
            ['label' => 'Síndico', 'entity' => $condominium->syndic],
            ['label' => 'Administradora', 'entity' => $condominium->administradora],
        ] as $source) {
            $entity = $source['entity'];
            if (!$entity instanceof ClientEntity) {
                continue;
            }

            foreach (self::contactList($entity->phones_json, 'number') as $phone) {
                $phones[] = array_merge($phone, [
                    'source_label' => $source['label'],
                    'whatsapp_value' => self::digitsOnly($phone['value'] ?? null),
                ]);
            }

            foreach (self::contactList($entity->emails_json, 'email') as $email) {
                $emails[] = array_merge($email, [
                    'source_label' => $source['label'],
                ]);
            }
        }

        return [
            'phones' => $phones,
            'emails' => $emails,
        ];
    }

    private static function contactList(mixed $items, string $valueKey): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(function (mixed $item) use ($valueKey) {
                $item = is_array($item) ? $item : [];
                $value = trim((string) ($item[$valueKey] ?? ''));

                if ($value === '') {
                    return null;
                }

                $label = trim((string) ($item['label'] ?? ''));

                return [
                    'label' => $label !== '' ? $label : null,
                    'value' => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function documentCategory(ClientAttachment $attachment): string
    {
        $kind = $attachment->condominiumDocumentKind();
        if (in_array($kind, ['convention', 'regiment', 'ata'], true)) {
            return $kind;
        }

        $fileRole = Str::of((string) $attachment->file_role)->ascii()->lower()->toString();
        $name = Str::of((string) $attachment->original_name)->ascii()->lower()->toString();

        if ($fileRole === 'contrato' || str_contains($name, 'contrato')) {
            return 'contract';
        }

        return 'other';
    }

    private static function documentCategoryLabel(string $category): string
    {
        return match ($category) {
            'convention' => 'Convenção',
            'regiment' => 'Regimento Interno',
            'ata' => 'Ata',
            'contract' => 'Contrato',
            default => 'Outros documentos',
        };
    }

    private static function roleLabel(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = Str::of($value)->ascii()->lower()->replace('_', ' ')->replace('-', ' ')->squish()->toString();

        return match ($normalized) {
            'sindico' => 'Síndico',
            'sindica' => 'Síndica',
            'proprietario' => 'Proprietário',
            'locatario' => 'Locatário',
            'inquilino' => 'Locatário',
            'administradora' => 'Administradora',
            'imobiliaria' => 'Imobiliária',
            'corretor' => 'Corretor',
            'corretora' => 'Corretora',
            default => mb_convert_case($value, MB_CASE_TITLE, 'UTF-8'),
        };
    }

    private static function initials(?string $value): string
    {
        $parts = collect(explode(' ', trim((string) $value)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $parts !== '' ? $parts : 'C';
    }

    private static function digitsOnly(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        return $digits !== '' ? $digits : null;
    }
}
