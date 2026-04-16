<?php

namespace App\Http\Controllers;

use App\Models\ClientAttachment;
use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientTimeline;
use App\Models\ClientType;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;
use App\Models\AuditLog;
use App\Support\AncoraAuth;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientsController extends Controller
{
    private function commonViewData(): array
    {
        return [
            'condominiumTypes' => ClientType::query()->where('scope', 'condominium')->where('is_active', 1)->orderBy('sort_order')->get(),
            'unitTypes' => ClientType::query()->where('scope', 'unit')->where('is_active', 1)->orderBy('sort_order')->get(),
            'entityRoles' => ClientType::query()->where('scope', 'entity_role')->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->get(),
            'entitiesAll' => ClientEntity::query()->orderBy('display_name')->get(),
            'syndics' => ClientEntity::query()->whereIn('role_tag', ['sindico', 'Síndico', 'síndico'])->orderBy('display_name')->get(),
            'administradorasList' => ClientEntity::query()->whereIn('role_tag', ['administradora', 'Administradora'])->orderBy('display_name')->get(),
            'condominiumsDropdown' => ClientCondominium::query()->with('blocks')->orderBy('name')->get(),
        ];
    }

    private function partnerEntitiesQuery()
    {
        return ClientEntity::query()
            ->where('profile_scope', 'contato')
            ->whereNotIn('id', ClientUnit::query()->select('owner_entity_id')->whereNotNull('owner_entity_id'))
            ->whereNotIn('id', ClientUnit::query()->select('tenant_entity_id')->whereNotNull('tenant_entity_id'));
    }

    private function condominoEntitiesQuery()
    {
        return ClientEntity::query()
            ->where('profile_scope', 'contato')
            ->where(function ($query) {
                $query->whereIn('id', ClientUnit::query()->select('owner_entity_id')->whereNotNull('owner_entity_id'))
                    ->orWhereIn('id', ClientUnit::query()->select('tenant_entity_id')->whereNotNull('tenant_entity_id'));
            });
    }

    private function entityUnitLinkCounts(ClientEntity $entity): array
    {
        return [
            'owner' => ClientUnit::query()->where('owner_entity_id', $entity->id)->count(),
            'tenant' => ClientUnit::query()->where('tenant_entity_id', $entity->id)->count(),
        ];
    }

    private function isCondominoEntity(ClientEntity $entity): bool
    {
        $counts = $this->entityUnitLinkCounts($entity);

        return ($counts['owner'] + $counts['tenant']) > 0;
    }

    private function blockNameKey(string $name): string
    {
        return Str::of($name)->squish()->lower()->toString();
    }

    private function parseLines(?string $text, array $keys): array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }

        $rows = [];
        foreach (preg_split('/\R+/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $row = [];
            foreach ($keys as $index => $key) {
                $row[$key] = $parts[$index] ?? '';
            }
            if (collect($row)->filter(fn ($value) => $value !== '')->isNotEmpty()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function parseSimpleValues(array $values, string $fieldKey, string $labelPrefix): array
    {
        $values = collect($values)
            ->map(function ($value) use ($fieldKey) {
                $value = match ($fieldKey) {
                    'email' => $this->normalizeEmail($value),
                    'number' => $this->normalizePhone($value),
                    default => $this->normalizeWhitespace($value),
                };

                return $value;
            })
            ->filter()
            ->values();

        return $values->map(fn ($value, $index) => [
            'label' => $labelPrefix . ' ' . ($index + 1),
            $fieldKey => $value,
        ])->all();
    }

    private function parseRepeaterRows(array $rows, array $fields): array
    {
        return collect($rows)
            ->map(function ($row) use ($fields) {
                $row = is_array($row) ? $row : [];
                $normalized = [];
                foreach ($fields as $field) {
                    $normalized[$field] = trim((string) ($row[$field] ?? ''));
                }
                return $normalized;
            })
            ->filter(fn ($row) => collect($row)->filter(fn ($value) => $value !== '')->isNotEmpty())
            ->values()
            ->all();
    }

    private function parsePhoneRows(array $rows): array
    {
        return collect($this->parseRepeaterRows($rows, ['number']))
            ->map(fn ($row, $index) => [
                'label' => 'Telefone ' . ($index + 1),
                'number' => $this->normalizePhone($row['number']),
            ])
            ->filter(fn ($row) => $row['number'] !== '')
            ->values()
            ->all();
    }

    private function parseEmailRows(array $rows): array
    {
        return collect($this->parseRepeaterRows($rows, ['email']))
            ->map(fn ($row, $index) => [
                'label' => 'E-mail ' . ($index + 1),
                'email' => $this->normalizeEmail($row['email']),
            ])
            ->filter(fn ($row) => $row['email'] !== '')
            ->values()
            ->all();
    }

    private function parseShareholderRows(array $rows): array
    {
        return collect($this->parseRepeaterRows($rows, ['name', 'document', 'role']))
            ->map(fn ($row) => [
                'name' => $this->normalizeTitleCase($row['name']),
                'document' => $this->formatCpfCnpj($row['document']),
                'role' => $this->normalizeTitleCase($row['role']),
            ])
            ->filter(fn ($row) => collect($row)->filter(fn ($value) => $value !== '')->isNotEmpty())
            ->values()
            ->all();
    }

    private function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normalizeWhitespace(?string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }

    private function normalizeLower(?string $value): string
    {
        return mb_strtolower($this->normalizeWhitespace($value), 'UTF-8');
    }

    private function normalizeUpper(?string $value): string
    {
        return mb_strtoupper($this->normalizeWhitespace($value), 'UTF-8');
    }

    private function normalizeTitleCase(?string $value): string
    {
        $value = $this->normalizeWhitespace($value);
        if ($value === '') {
            return '';
        }

        $minorWords = ['da', 'das', 'de', 'do', 'dos', 'e'];
        $chunks = preg_split('/(\s+)/u', mb_strtolower($value, 'UTF-8'), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $result = '';
        $isFirstWord = true;

        foreach ($chunks as $chunk) {
            if (preg_match('/^\s+$/u', $chunk)) {
                $result .= $chunk;
                continue;
            }

            $parts = preg_split('/([\-\/])/u', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$chunk];
            $rebuilt = '';

            foreach ($parts as $part) {
                if ($part === '-' || $part === '/') {
                    $rebuilt .= $part;
                    continue;
                }

                if ($part === '') {
                    continue;
                }

                if (!$isFirstWord && in_array($part, $minorWords, true)) {
                    $rebuilt .= $part;
                } elseif (preg_match('/^[ivxlcdm]+$/u', $part)) {
                    $rebuilt .= mb_strtoupper($part, 'UTF-8');
                } else {
                    $rebuilt .= mb_convert_case($part, MB_CASE_TITLE, 'UTF-8');
                }
            }

            $result .= $rebuilt;
            $isFirstWord = false;
        }

        return $result;
    }

    private function formatCpfCnpj(?string $value): string
    {
        $digits = $this->digitsOnly($value);

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $digits;
        }

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
        }

        return $this->normalizeUpper($value);
    }

    private function formatZip(?string $value): string
    {
        $digits = $this->digitsOnly($value);
        if (strlen($digits) !== 8) {
            return $this->normalizeWhitespace($value);
        }

        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? $digits;
    }

    private function normalizePhone(?string $value): string
    {
        $digits = $this->digitsOnly($value);

        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?? $digits;
        }

        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?? $digits;
        }

        return $this->normalizeWhitespace($value);
    }

    private function normalizeEmail(?string $value): string
    {
        return $this->normalizeLower($value);
    }

    private function normalizeAddress(array $address): array
    {
        return [
            'street' => $this->normalizeTitleCase($address['street'] ?? ''),
            'number' => $this->normalizeWhitespace($address['number'] ?? ''),
            'complement' => $this->normalizeTitleCase($address['complement'] ?? ''),
            'neighborhood' => $this->normalizeTitleCase($address['neighborhood'] ?? ''),
            'city' => $this->normalizeTitleCase($address['city'] ?? ''),
            'state' => $this->normalizeUpper($address['state'] ?? ''),
            'zip' => $this->formatZip($address['zip'] ?? ''),
            'notes' => $this->normalizeWhitespace($address['notes'] ?? ''),
        ];
    }

    private function isValidCpf(string $digits): bool
    {
        if (!preg_match('/^\d{11}$/', $digits) || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $digits[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $digits[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $digits): bool
    {
        if (!preg_match('/^\d{14}$/', $digits) || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        $calculate = static function (string $base, array $weights): int {
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += ((int) $base[$index]) * $weight;
            }
            $remainder = $sum % 11;
            return $remainder < 2 ? 0 : 11 - $remainder;
        };

        $first = $calculate($digits, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = $calculate($digits, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $first === (int) $digits[12] && $second === (int) $digits[13];
    }

    private function isValidCpfCnpj(?string $document): bool
    {
        $digits = $this->digitsOnly($document);
        if ($digits === '') {
            return true;
        }

        return match (strlen($digits)) {
            11 => $this->isValidCpf($digits),
            14 => $this->isValidCnpj($digits),
            default => false,
        };
    }

    private function addressFromRequest(Request $request, string $prefix): array
    {
        return $this->normalizeAddress([
            'street' => $request->input($prefix . '_street', ''),
            'number' => $request->input($prefix . '_number', ''),
            'complement' => $request->input($prefix . '_complement', ''),
            'neighborhood' => $request->input($prefix . '_neighborhood', ''),
            'city' => $request->input($prefix . '_city', ''),
            'state' => $request->input($prefix . '_state', ''),
            'zip' => $request->input($prefix . '_zip', ''),
            'notes' => $request->input($prefix . '_notes', ''),
        ]);
    }

    private function entityPayloadFromRequest(Request $request, string $scope, string $defaultRole = 'outro'): array
    {
        $entityType = $request->input('entity_type', 'pf') === 'pj' ? 'pj' : 'pf';
        $isInactive = $request->boolean('is_inactive');

        $payload = [
            'entity_type' => $entityType,
            'profile_scope' => $scope,
            'role_tag' => $this->normalizeWhitespace($request->input('role_tag', $defaultRole)) ?: $defaultRole,
            'display_name' => $this->normalizeTitleCase($request->input('display_name', '')),
            'legal_name' => ($value = $this->normalizeTitleCase($request->input('legal_name', ''))) !== '' ? $value : null,
            'cpf_cnpj' => ($value = $this->formatCpfCnpj($request->input('cpf_cnpj', ''))) !== '' ? $value : null,
            'rg_ie' => ($value = $this->normalizeUpper($request->input('rg_ie', ''))) !== '' ? $value : null,
            'gender' => ($value = $this->normalizeTitleCase($request->input('gender', ''))) !== '' ? $value : null,
            'nationality' => ($value = $this->normalizeTitleCase($request->input('nationality', ''))) !== '' ? $value : null,
            'birth_date' => ($value = $this->normalizeWhitespace($request->input('birth_date', ''))) !== '' ? $value : null,
            'profession' => ($value = $this->normalizeTitleCase($request->input('profession', ''))) !== '' ? $value : null,
            'marital_status' => ($value = $this->normalizeTitleCase($request->input('marital_status', ''))) !== '' ? $value : null,
            'pis' => ($value = $this->normalizeUpper($request->input('pis', ''))) !== '' ? $value : null,
            'spouse_name' => ($value = $this->normalizeTitleCase($request->input('spouse_name', ''))) !== '' ? $value : null,
            'father_name' => ($value = $this->normalizeTitleCase($request->input('father_name', ''))) !== '' ? $value : null,
            'mother_name' => ($value = $this->normalizeTitleCase($request->input('mother_name', ''))) !== '' ? $value : null,
            'children_info' => ($value = $this->normalizeWhitespace($request->input('children_info', ''))) !== '' ? $value : null,
            'ctps' => ($value = $this->normalizeUpper($request->input('ctps', ''))) !== '' ? $value : null,
            'cnae' => ($value = $this->normalizeUpper($request->input('cnae', ''))) !== '' ? $value : null,
            'state_registration' => ($value = $this->normalizeUpper($request->input('state_registration', ''))) !== '' ? $value : null,
            'municipal_registration' => ($value = $this->normalizeUpper($request->input('municipal_registration', ''))) !== '' ? $value : null,
            'opening_date' => null,
            'legal_representative' => ($value = $this->normalizeTitleCase($request->input('legal_representative', ''))) !== '' ? $value : null,
            'phones_json' => $this->parsePhoneRows((array) $request->input('phones', [])),
            'emails_json' => $this->parseEmailRows((array) $request->input('emails', [])),
            'primary_address_json' => $this->addressFromRequest($request, 'primary_address'),
            'billing_address_json' => $request->boolean('billing_same_as_primary')
                ? $this->addressFromRequest($request, 'primary_address')
                : $this->addressFromRequest($request, 'billing_address'),
            'shareholders_json' => $this->parseShareholderRows((array) $request->input('shareholders', [])),
            'notes' => ($value = $this->normalizeWhitespace($request->input('notes', ''))) !== '' ? $value : null,
            'description' => ($value = $this->normalizeWhitespace($request->input('description', ''))) !== '' ? $value : null,
            'is_active' => !$isInactive,
            'inactive_reason' => $isInactive ? (($value = $this->normalizeWhitespace($request->input('inactive_reason', ''))) !== '' ? $value : null) : null,
            'contract_end_date' => $isInactive ? (($value = $this->normalizeWhitespace($request->input('contract_end_date', ''))) !== '' ? $value : null) : null,
            'created_by' => AncoraAuth::user($request)?->id,
            'updated_by' => AncoraAuth::user($request)?->id,
        ];

        if ($entityType === 'pf') {
            $payload['legal_name'] = null;
            $payload['legal_representative'] = null;
            $payload['shareholders_json'] = [];
            $payload['cnae'] = null;
            $payload['state_registration'] = null;
            $payload['municipal_registration'] = null;
        } else {
            $payload['rg_ie'] = null;
            $payload['profession'] = null;
            $payload['marital_status'] = null;
            $payload['birth_date'] = null;
            $payload['gender'] = null;
            $payload['nationality'] = null;
            $payload['pis'] = null;
            $payload['spouse_name'] = null;
            $payload['father_name'] = null;
            $payload['mother_name'] = null;
            $payload['children_info'] = null;
            $payload['ctps'] = null;
        }

        return $payload;
    }

    private function validateEntity(array $data): array
    {
        $errors = [];
        if (($data['display_name'] ?? '') === '') {
            $errors[] = 'Informe o nome principal.';
        }
        if (($data['role_tag'] ?? '') === '') {
            $errors[] = 'Selecione o perfil / papel.';
        }
        if (($data['profile_scope'] ?? '') === 'avulso' && empty($data['cpf_cnpj'])) {
            $errors[] = 'Para cliente avulso, informe CPF/CNPJ.';
        }
        if (!empty($data['cpf_cnpj']) && !$this->isValidCpfCnpj($data['cpf_cnpj'])) {
            $errors[] = 'Informe um CPF/CNPJ válido.';
        }
        if (collect($data['emails_json'] ?? [])->pluck('email')->filter(fn ($email) => $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))->isNotEmpty()) {
            $errors[] = 'Revise os e-mails informados.';
        }
        if (empty($data['is_active']) && empty($data['inactive_reason'])) {
            $errors[] = 'Informe o motivo da inativação.';
        }
        return $errors;
    }

    private function unitAlreadyExists(int $condominiumId, ?int $blockId, string $unitNumber, ?int $ignoreUnitId = null): bool
    {
        $unitNumber = $this->normalizeUpper($unitNumber);
        if ($unitNumber === '') {
            return false;
        }

        return ClientUnit::query()
            ->where('condominium_id', $condominiumId)
            ->where('unit_number', $unitNumber)
            ->when($ignoreUnitId, fn ($query) => $query->where('id', '<>', $ignoreUnitId))
            ->where(function ($query) use ($blockId) {
                if ($blockId) {
                    $query->where('block_id', $blockId);
                } else {
                    $query->whereNull('block_id');
                }
            })
            ->exists();
    }

    private function recordTimeline(string $relatedType, int $relatedId, string $note, Request $request): void
    {
        $user = AncoraAuth::user($request);
        if (!$user) {
            return;
        }

        ClientTimeline::query()->create([
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'note' => $note,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);
    }

    private function normalizeUploadedFiles(mixed $value): array
    {
        if ($value instanceof UploadedFile) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $files = [];

        array_walk_recursive($value, function ($item) use (&$files) {
            if ($item instanceof UploadedFile) {
                $files[] = $item;
            }
        });

        return $files;
    }

    private function safeUploadedFileSize(UploadedFile $file): int
    {
        $realPath = $file->getRealPath();

        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
            return (int) (@filesize($realPath) ?: 0);
        }

        try {
            return (int) ($file->getSize() ?: 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function storeAttachmentFiles(string $relatedType, int $relatedId, array $files, string $role, Request $request, ?string $labelPrefix = null): void
    {
        $dir = public_path('uploads/clientes/' . $relatedType . '/' . $relatedId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx'], true)) {
                continue;
            }
            if ($role === 'contrato' && $ext !== 'pdf') {
                continue;
            }

            $stored = now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
            $originalName = $file->getClientOriginalName();
            if ($labelPrefix) {
                $originalName = $labelPrefix . ' - ' . $originalName;
            }
            $originalName = Str::limit($originalName, 250, '');
            $mimeType = Str::limit((string) $file->getClientMimeType(), 120, '');
            $fileSize = $this->safeUploadedFileSize($file);

            $file->move($dir, $stored);

            ClientAttachment::query()->create([
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'file_role' => $role,
                'original_name' => $originalName,
                'stored_name' => $stored,
                'relative_path' => '/uploads/clientes/' . $relatedType . '/' . $relatedId . '/' . $stored,
                'mime_type' => $mimeType ?: null,
                'file_size' => $fileSize,
                'uploaded_by' => AncoraAuth::user($request)?->id,
            ]);
        }
    }

    private function uploadAttachments(string $relatedType, int $relatedId, Request $request): void
    {
        $request->validate([
            'attachment_groups.*.files.*' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx', 'max:20480'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx', 'max:20480'],
        ]);

        $groupInputs = $request->input('attachment_groups', []);
        $groupFiles = $request->allFiles()['attachment_groups'] ?? [];
        $hasGroupedUpload = false;

        if (is_array($groupFiles)) {
            foreach ($groupFiles as $index => $group) {
                $files = $this->normalizeUploadedFiles($group['files'] ?? []);
                if (empty($files)) {
                    continue;
                }

                $roleInput = $groupInputs[$index]['role'] ?? 'documento';
                $role = in_array($roleInput, ['documento', 'contrato', 'outro'], true) ? $roleInput : 'documento';
                $this->storeAttachmentFiles($relatedType, $relatedId, $files, $role, $request);
                $hasGroupedUpload = true;
            }
        }

        if ($hasGroupedUpload) {
            return;
        }

        $files = $this->normalizeUploadedFiles($request->file('attachments'));
        if (empty($files)) {
            return;
        }

        $role = in_array($request->input('attachment_role', 'documento'), ['documento', 'contrato', 'outro'], true)
            ? $request->input('attachment_role')
            : 'documento';

        $this->storeAttachmentFiles($relatedType, $relatedId, $files, $role, $request);
    }

    private function uploadCondominiumDocuments(int $condominiumId, Request $request): void
    {
        $request->validate([
            'document_convention' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx', 'max:20480'],
            'document_regiment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx', 'max:20480'],
            'document_atas.*' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx', 'max:20480'],
        ]);

        $conventionFiles = $this->normalizeUploadedFiles($request->file('document_convention'));
        if (!empty($conventionFiles)) {
            $this->storeAttachmentFiles('condominium', $condominiumId, $conventionFiles, 'documento', $request, 'Convenção condominial');
        }

        $regimentFiles = $this->normalizeUploadedFiles($request->file('document_regiment'));
        if (!empty($regimentFiles)) {
            $this->storeAttachmentFiles('condominium', $condominiumId, $regimentFiles, 'documento', $request, 'Regimento interno');
        }

        $ataFiles = $this->normalizeUploadedFiles($request->file('document_atas'));
        if (!empty($ataFiles)) {
            $this->storeAttachmentFiles('condominium', $condominiumId, $ataFiles, 'documento', $request, 'ATA');
        }
    }

    private function validatePartyRequest(Request $request, string $prefix, string $label): array
    {
        $errors = [];
        $document = $this->formatCpfCnpj($request->input($prefix . '_cpf_cnpj', ''));
        $emails = collect((array) $request->input($prefix . '_emails', []))
            ->map(fn ($value) => $this->normalizeEmail($value))
            ->filter();

        if ($document !== '' && !$this->isValidCpfCnpj($document)) {
            $errors[] = 'Informe um CPF/CNPJ válido para ' . $label . '.';
        }

        if ($emails->filter(fn ($email) => !filter_var($email, FILTER_VALIDATE_EMAIL))->isNotEmpty()) {
            $errors[] = 'Revise os e-mails informados em ' . $label . '.';
        }

        return $errors;
    }

    private function syncPartyEntityFromRequest(Request $request, string $prefix, string $roleTag, ?int $existingId = null): ?int
    {
        $name = $this->normalizeTitleCase($request->input($prefix . '_name', ''));
        $document = $this->formatCpfCnpj($request->input($prefix . '_cpf_cnpj', ''));
        $phones = $this->parseSimpleValues((array) $request->input($prefix . '_phones', []), 'number', 'Telefone');
        $emails = $this->parseSimpleValues((array) $request->input($prefix . '_emails', []), 'email', 'E-mail');
        $address = $this->addressFromRequest($request, $prefix . '_address');

        $hasAddress = collect($address)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty();
        if ($name === '' && $document === '' && empty($phones) && empty($emails) && !$hasAddress) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $document);
        $entityType = strlen($digits) > 11 ? 'pj' : 'pf';
        $userId = AncoraAuth::user($request)?->id;

        $payload = [
            'entity_type' => $entityType,
            'profile_scope' => 'contato',
            'role_tag' => $roleTag,
            'display_name' => $name !== '' ? $name : ($existingId ? ClientEntity::query()->find($existingId)?->display_name : $this->normalizeTitleCase($roleTag)),
            'legal_name' => $entityType === 'pj' ? ($name !== '' ? $name : null) : null,
            'cpf_cnpj' => $document !== '' ? $document : null,
            'phones_json' => $phones,
            'emails_json' => $emails,
            'primary_address_json' => $address,
            'billing_address_json' => $address,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        $entity = $existingId ? ClientEntity::query()->find($existingId) : null;
        if ($entity) {
            $payload['created_by'] = $entity->created_by;
            $entity->update($payload);
            return (int) $entity->id;
        }

        return (int) ClientEntity::query()->create($payload)->id;
    }

    private function saveClientEntity(Request $request, array $payload, ?ClientEntity $existing, string $successMessage, string $timelineMessage, string $redirectRoute): RedirectResponse
    {
        try {
            $entity = DB::transaction(function () use ($payload, $existing) {
                if ($existing) {
                    $payload['created_by'] = $existing->created_by;
                    $existing->update($payload);
                    return $existing->fresh();
                }

                return ClientEntity::query()->create($payload);
            });

            $this->uploadAttachments('entity', (int) $entity->id, $request);
            $this->recordTimeline('entity', (int) $entity->id, $timelineMessage, $request);

            return $existing
                ? back()->with('success', $successMessage)
                : redirect()->route($redirectRoute, $entity)->with('success', $successMessage);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Não foi possível salvar este cadastro agora. Revise os dados e tente novamente.');
        }
    }

    private function saveCondominium(Request $request, array $payload, ?ClientCondominium $existing, string $successMessage, string $timelineMessage): RedirectResponse
    {
        try {
            $condo = DB::transaction(function () use ($payload, $existing, $request) {
                if ($existing) {
                    $payload['created_by'] = $existing->created_by;
                    $existing->update($payload);
                    $this->syncBlocks($existing, $request->input('blocks_text'));
                    return $existing->fresh();
                }

                $condo = ClientCondominium::query()->create($payload);
                $this->syncBlocks($condo, $request->input('blocks_text'));
                return $condo;
            });

            $this->uploadAttachments('condominium', (int) $condo->id, $request);
            $this->uploadCondominiumDocuments((int) $condo->id, $request);
            $this->recordTimeline('condominium', (int) $condo->id, $timelineMessage, $request);

            return $existing
                ? back()->with('success', $successMessage)
                : redirect()->route('clientes.condominios.edit', $condo)->with('success', $successMessage);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Não foi possível salvar o condomínio agora. Revise os dados e tente novamente.');
        }
    }

    public function index(): View
    {
        return view('pages.clientes.index', array_merge([
            'title' => 'Clientes',
            'entityCounts' => [
                'total' => ClientEntity::query()->count(),
                'avulsos_total' => ClientEntity::query()->where('profile_scope', 'avulso')->count(),
                'contatos_total' => $this->partnerEntitiesQuery()->count(),
                'condominos_total' => $this->condominoEntitiesQuery()->count(),
            ],
            'condominiumCounts' => [
                'total' => ClientCondominium::query()->count(),
                'with_blocks_total' => ClientCondominium::query()->where('has_blocks', 1)->count(),
            ],
            'unitCounts' => [
                'total' => ClientUnit::query()->count(),
                'rented_total' => ClientUnit::query()->whereNotNull('tenant_entity_id')->count(),
            ],
            'recentEntities' => ClientEntity::query()->latest('id')->limit(8)->get(),
            'recentCondominiums' => ClientCondominium::query()->latest('id')->limit(6)->get(),
        ], $this->commonViewData()));
    }

    public function avulsos(Request $request): View
    {
        $query = ClientEntity::query()->where('profile_scope', 'avulso');
        if ($term = trim((string) $request->input('q'))) {
            $query->where(fn ($sub) => $sub
                ->where('display_name', 'like', "%{$term}%")
                ->orWhere('legal_name', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%"));
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->input('is_active'));
        }
        if ($request->filled('role_tag')) {
            $query->where('role_tag', $request->input('role_tag'));
        }

        $sortState = SortableQuery::apply($query, $request, [
            'name' => 'display_name',
            'role' => 'role_tag',
            'type' => 'entity_type',
            'document' => 'cpf_cnpj',
            'status' => 'is_active',
            'created_at' => 'created_at',
        ], 'name');

        return view('pages.clientes.avulsos', array_merge([
            'title' => 'Clientes avulsos',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
            'sortState' => $sortState,
        ], $this->commonViewData()));
    }

    public function avulsoCreate(): View
    {
        return view('pages.clientes.avulsos-form', array_merge([
            'title' => 'Novo cliente avulso',
            'item' => null,
            'mode' => 'create',
            'scope' => 'avulso',
            'roleTag' => 'outro',
            'attachments' => collect(),
            'timeline' => collect(),
        ], $this->commonViewData()));
    }

    public function avulsoStore(Request $request): RedirectResponse
    {
        $payload = $this->entityPayloadFromRequest($request, 'avulso', 'outro');
        $errors = $this->validateEntity($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        return $this->saveClientEntity(
            $request,
            $payload,
            null,
            'Cliente avulso cadastrado.',
            'Cliente avulso cadastrado.',
            'clientes.avulsos.edit'
        );
    }

    public function avulsoEdit(ClientEntity $avulso): View
    {
        abort_if($avulso->profile_scope !== 'avulso', 404);

        return view('pages.clientes.avulsos-form', array_merge([
            'title' => 'Editar cliente avulso',
            'item' => $avulso,
            'mode' => 'edit',
            'scope' => 'avulso',
            'roleTag' => $avulso->role_tag,
            'attachments' => ClientAttachment::query()->where('related_type', 'entity')->where('related_id', $avulso->id)->latest('id')->get(),
            'timeline' => ClientTimeline::query()->where('related_type', 'entity')->where('related_id', $avulso->id)->latest('id')->get(),
        ], $this->commonViewData()));
    }

    public function avulsoUpdate(Request $request, ClientEntity $avulso): RedirectResponse
    {
        abort_if($avulso->profile_scope !== 'avulso', 404);

        $payload = $this->entityPayloadFromRequest($request, 'avulso', $avulso->role_tag ?: 'outro');
        $errors = $this->validateEntity($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        return $this->saveClientEntity(
            $request,
            $payload,
            $avulso,
            'Cliente avulso atualizado.',
            'Cliente avulso atualizado.',
            'clientes.avulsos.edit'
        );
    }

    public function avulsoDelete(ClientEntity $avulso): RedirectResponse
    {
        abort_if($avulso->profile_scope !== 'avulso', 404);
        $avulso->delete();
        return redirect()->route('clientes.avulsos')->with('success', 'Cliente avulso excluído.');
    }

    public function contatos(Request $request): View
    {
        $query = $this->partnerEntitiesQuery();
        if ($term = trim((string) $request->input('q'))) {
            $query->where(fn ($sub) => $sub->where('display_name', 'like', "%{$term}%")->orWhere('cpf_cnpj', 'like', "%{$term}%"));
        }
        if ($request->filled('role_tag')) {
            $query->where('role_tag', $request->input('role_tag'));
        }

        $sortState = SortableQuery::apply($query, $request, [
            'name' => 'display_name',
            'role' => 'role_tag',
            'type' => 'entity_type',
            'document' => 'cpf_cnpj',
            'created_at' => 'created_at',
        ], 'name');

        return view('pages.clientes.contatos', array_merge([
            'title' => 'Parceiros e fornecedores',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
            'sortState' => $sortState,
        ], $this->commonViewData()));
    }

    public function condominos(Request $request): View
    {
        $query = $this->condominoEntitiesQuery()
            ->withCount(['ownedUnits', 'rentedUnits'])
            ->with([
                'ownedUnits.condominium',
                'ownedUnits.block',
                'rentedUnits.condominium',
                'rentedUnits.block',
            ]);

        if ($term = trim((string) $request->input('q'))) {
            $query->where(fn ($sub) => $sub
                ->where('display_name', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%"));
        }

        if ($request->input('vinculo') === 'proprietario') {
            $query->whereIn('id', ClientUnit::query()->select('owner_entity_id')->whereNotNull('owner_entity_id'));
        } elseif ($request->input('vinculo') === 'locatario') {
            $query->whereIn('id', ClientUnit::query()->select('tenant_entity_id')->whereNotNull('tenant_entity_id'));
        }

        $sortState = SortableQuery::apply($query, $request, [
            'name' => 'display_name',
            'role' => 'role_tag',
            'type' => 'entity_type',
            'document' => 'cpf_cnpj',
            'created_at' => 'created_at',
        ], 'name');

        return view('pages.clientes.condominos', array_merge([
            'title' => 'Condôminos',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
            'sortState' => $sortState,
        ], $this->commonViewData()));
    }

    public function contatoCreate(): View
    {
        return view('pages.clientes.contatos-form', array_merge([
            'title' => 'Novo parceiro / fornecedor',
            'item' => null,
            'mode' => 'create',
            'attachments' => collect(),
            'timeline' => collect(),
        ], $this->commonViewData()));
    }

    public function contatoStore(Request $request): RedirectResponse
    {
        $payload = $this->entityPayloadFromRequest($request, 'contato', 'administradora');
        $errors = $this->validateEntity($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        return $this->saveClientEntity(
            $request,
            $payload,
            null,
            'Cadastro concluído com sucesso.',
            'Contato cadastrado.',
            'clientes.contatos.edit'
        );
    }

    public function contatoEdit(ClientEntity $contato): View
    {
        abort_if($contato->profile_scope !== 'contato', 404);
        $isCondomino = $this->isCondominoEntity($contato);

        return view('pages.clientes.contatos-form', array_merge([
            'title' => $isCondomino ? 'Editar condômino' : 'Editar parceiro / fornecedor',
            'formSubtitle' => $isCondomino
                ? 'Cadastro de proprietário ou locatário vinculado a uma ou mais unidades.'
                : 'Cadastro de síndicos, administradoras, parceiros e fornecedores reutilizáveis.',
            'item' => $contato,
            'mode' => 'edit',
            'isCondomino' => $isCondomino,
            'attachments' => ClientAttachment::query()->where('related_type', 'entity')->where('related_id', $contato->id)->latest('id')->get(),
            'timeline' => ClientTimeline::query()->where('related_type', 'entity')->where('related_id', $contato->id)->latest('id')->get(),
        ], $this->commonViewData()));
    }

    public function contatoUpdate(Request $request, ClientEntity $contato): RedirectResponse
    {
        abort_if($contato->profile_scope !== 'contato', 404);

        $payload = $this->entityPayloadFromRequest($request, 'contato', $contato->role_tag ?: 'administradora');
        $errors = $this->validateEntity($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        return $this->saveClientEntity(
            $request,
            $payload,
            $contato,
            'Cadastro atualizado com sucesso.',
            'Contato atualizado.',
            'clientes.contatos.edit'
        );
    }

    public function contatoDelete(ClientEntity $contato): RedirectResponse
    {
        abort_if($contato->profile_scope !== 'contato', 404);
        $linkCounts = $this->entityUnitLinkCounts($contato);
        if (($linkCounts['owner'] + $linkCounts['tenant']) > 0) {
            return back()->with('error', 'Não é possível excluir este condômino porque ele está vinculado a unidade(s). Remova ou altere o vínculo na unidade antes de excluir o cadastro.');
        }

        $contato->delete();
        return redirect()->route('clientes.contatos')->with('success', 'Cadastro excluído.');
    }

    public function condominios(Request $request): View
    {
        $query = ClientCondominium::query()
            ->select('client_condominiums.*')
            ->leftJoin('client_types as condominium_type_sort', 'condominium_type_sort.id', '=', 'client_condominiums.condominium_type_id')
            ->leftJoin('client_entities as condominium_syndic_sort', 'condominium_syndic_sort.id', '=', 'client_condominiums.syndico_entity_id')
            ->with(['type', 'syndic', 'administradora']);
        if ($term = trim((string) $request->input('q'))) {
            $query->where('client_condominiums.name', 'like', "%{$term}%");
        }

        $sortState = SortableQuery::apply($query, $request, [
            'name' => 'client_condominiums.name',
            'type' => 'condominium_type_sort.name',
            'syndic' => 'condominium_syndic_sort.display_name',
            'blocks' => 'client_condominiums.has_blocks',
            'created_at' => 'client_condominiums.created_at',
        ], 'name');

        return view('pages.clientes.condominios', [
            'title' => 'Condomínios',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
            'sortState' => $sortState,
        ]);
    }

    public function condominioCreate(): View
    {
        return view('pages.clientes.condominios-form', array_merge([
            'title' => 'Novo condomínio',
            'item' => null,
            'mode' => 'create',
            'attachments' => collect(),
            'timeline' => collect(),
            'blocksText' => '',
        ], $this->commonViewData()));
    }

    public function condominioStore(Request $request): RedirectResponse
    {
        $payload = $this->condominioPayload($request);
        $errors = $this->validateCondominium($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        return $this->saveCondominium(
            $request,
            $payload,
            null,
            'Condomínio cadastrado.',
            'Condomínio cadastrado.'
        );
    }

    public function condominioEdit(ClientCondominium $condominio): View
    {
        return view('pages.clientes.condominios-form', array_merge([
            'title' => 'Editar condomínio',
            'item' => $condominio->load(['blocks', 'syndic', 'administradora', 'type']),
            'mode' => 'edit',
            'attachments' => ClientAttachment::query()->where('related_type', 'condominium')->where('related_id', $condominio->id)->latest('id')->get(),
            'timeline' => ClientTimeline::query()->where('related_type', 'condominium')->where('related_id', $condominio->id)->latest('id')->get(),
            'blocksText' => $condominio->blocks->pluck('name')->implode(PHP_EOL),
        ], $this->commonViewData()));
    }

    public function condominioUpdate(Request $request, ClientCondominium $condominio): RedirectResponse
    {
        $payload = $this->condominioPayload($request);
        $errors = $this->validateCondominium($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        return $this->saveCondominium(
            $request,
            $payload,
            $condominio,
            'Condomínio atualizado.',
            'Condomínio atualizado.'
        );
    }

    public function condominioDelete(ClientCondominium $condominio): RedirectResponse
    {
        $caseCount = CobrancaCase::query()->where('condominium_id', $condominio->id)->count();
        if ($caseCount > 0) {
            return back()->with('error', "Não é possível excluir este condomínio porque existem {$caseCount} OS vinculada(s). Exclua as OS antes de remover o condomínio.");
        }

        $unitCount = ClientUnit::query()->where('condominium_id', $condominio->id)->count();
        if ($unitCount > 0) {
            return back()->with('error', "Não é possível excluir este condomínio porque existem {$unitCount} unidade(s) cadastrada(s). A ordem segura é: excluir OS, depois unidades, depois blocos e por último o condomínio.");
        }

        $blockCount = ClientBlock::query()->where('condominium_id', $condominio->id)->count();
        if ($blockCount > 0) {
            return back()->with('error', "Não é possível excluir este condomínio porque ainda existem {$blockCount} bloco(s). Remova os blocos no cadastro do condomínio antes de excluir.");
        }

        $condominio->delete();
        return redirect()->route('clientes.condominios')->with('success', 'Condomínio excluído.');
    }

    public function unidades(Request $request): View
    {
        $query = ClientUnit::query()
            ->select('client_units.*')
            ->leftJoin('client_condominiums as unit_condominium_sort', 'unit_condominium_sort.id', '=', 'client_units.condominium_id')
            ->leftJoin('client_condominium_blocks as unit_block_sort', 'unit_block_sort.id', '=', 'client_units.block_id')
            ->leftJoin('client_entities as unit_owner_sort', 'unit_owner_sort.id', '=', 'client_units.owner_entity_id')
            ->leftJoin('client_entities as unit_tenant_sort', 'unit_tenant_sort.id', '=', 'client_units.tenant_entity_id')
            ->with(['condominium', 'block', 'type', 'owner', 'tenant']);
        if ($term = trim((string) $request->input('q'))) {
            $query->where('client_units.unit_number', 'like', "%{$term}%");
        }
        if ($request->filled('condominium_id')) {
            $query->where('client_units.condominium_id', (int) $request->input('condominium_id'));
        }

        $sortState = SortableQuery::apply($query, $request, [
            'condominium' => 'unit_condominium_sort.name',
            'block' => 'unit_block_sort.name',
            'unit' => 'client_units.unit_number',
            'owner' => 'unit_owner_sort.display_name',
            'tenant' => 'unit_tenant_sort.display_name',
            'created_at' => 'client_units.created_at',
        ], 'created_at', 'desc');

        $importPreviewToken = (string) $request->session()->get('unit_import_preview_token', '');
        $importPreview = $importPreviewToken !== ''
            ? $request->session()->get("client_unit_import_previews.{$importPreviewToken}")
            : null;

        return view('pages.clientes.unidades', array_merge([
            'title' => 'Unidades',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
            'sortState' => $sortState,
            'importPreviewToken' => $importPreviewToken,
            'importPreview' => $importPreview,
        ], $this->commonViewData()));
    }

    public function unidadeCreate(): View
    {
        return view('pages.clientes.unidades-form', array_merge([
            'title' => 'Nova unidade',
            'item' => null,
            'mode' => 'create',
            'attachments' => collect(),
            'timeline' => collect(),
        ], $this->commonViewData()));
    }

    public function unidadeStore(Request $request): RedirectResponse
    {
        $errors = array_merge(
            $this->validatePartyRequest($request, 'owner', 'o proprietário'),
            $this->validatePartyRequest($request, 'tenant', 'o locatário')
        );

        $payload = $this->unitPayload($request, null, null);
        $errors = array_merge($errors, $this->validateUnit($payload));
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        try {
            $unit = DB::transaction(function () use ($request, $payload) {
                $ownerId = $this->syncPartyEntityFromRequest($request, 'owner', 'proprietario');
                $tenantId = $this->syncPartyEntityFromRequest($request, 'tenant', 'locatario');
                $payload['owner_entity_id'] = $ownerId;
                $payload['tenant_entity_id'] = $tenantId;

                return ClientUnit::query()->create($payload);
            });

            $this->uploadAttachments('unit', (int) $unit->id, $request);
            $this->recordTimeline('unit', (int) $unit->id, 'Unidade cadastrada.', $request);

            return redirect()->route('clientes.unidades.edit', $unit)->with('success', 'Unidade cadastrada.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Não foi possível salvar a unidade agora. Revise os dados e tente novamente.');
        }
    }

    public function unidadeEdit(ClientUnit $unidade): View
    {
        return view('pages.clientes.unidades-form', array_merge([
            'title' => 'Editar unidade',
            'item' => $unidade->load(['condominium', 'block', 'type', 'owner', 'tenant']),
            'mode' => 'edit',
            'attachments' => ClientAttachment::query()->where('related_type', 'unit')->where('related_id', $unidade->id)->latest('id')->get(),
            'timeline' => ClientTimeline::query()->where('related_type', 'unit')->where('related_id', $unidade->id)->latest('id')->get(),
        ], $this->commonViewData()));
    }

    public function unidadeUpdate(Request $request, ClientUnit $unidade): RedirectResponse
    {
        $errors = array_merge(
            $this->validatePartyRequest($request, 'owner', 'o proprietário'),
            $this->validatePartyRequest($request, 'tenant', 'o locatário')
        );

        $payload = $this->unitPayload($request, $unidade->owner_entity_id, $unidade->tenant_entity_id);
        $payload['created_by'] = $unidade->created_by;
        $errors = array_merge($errors, $this->validateUnit($payload, (int) $unidade->id));
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        try {
            DB::transaction(function () use ($request, $unidade, $payload) {
                $ownerId = $this->syncPartyEntityFromRequest($request, 'owner', 'proprietario', $unidade->owner_entity_id);
                $tenantId = $this->syncPartyEntityFromRequest($request, 'tenant', 'locatario', $unidade->tenant_entity_id);
                $payload['owner_entity_id'] = $ownerId;
                $payload['tenant_entity_id'] = $tenantId;

                $unidade->update($payload);
            });

            $this->uploadAttachments('unit', (int) $unidade->id, $request);
            $this->recordTimeline('unit', (int) $unidade->id, 'Unidade atualizada.', $request);

            return back()->with('success', 'Unidade atualizada.');
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Não foi possível salvar a unidade agora. Revise os dados e tente novamente.');
        }
    }

    public function unidadeDelete(ClientUnit $unidade): RedirectResponse
    {
        $caseCount = CobrancaCase::query()->where('unit_id', $unidade->id)->count();
        if ($caseCount > 0) {
            $examples = CobrancaCase::query()
                ->where('unit_id', $unidade->id)
                ->orderByDesc('id')
                ->limit(3)
                ->pluck('os_number')
                ->filter()
                ->implode(', ');

            $suffix = $examples !== '' ? " OS: {$examples}." : '';
            return back()->with('error', "Não é possível excluir esta unidade porque existem {$caseCount} OS vinculada(s). Exclua as OS primeiro e depois tente remover a unidade.{$suffix}");
        }

        $unidade->delete();
        return redirect()->route('clientes.unidades')->with('success', 'Unidade excluída.');
    }

    public function config(): View
    {
        return view('pages.clientes.config', [
            'title' => 'Configurações de clientes',
            'types' => ClientType::query()->orderBy('scope')->orderBy('sort_order')->get(),
            'scopeOptions' => [
                'entity_role' => 'Perfil / papel',
                'condominium' => 'Condomínio',
                'unit' => 'Unidade',
            ],
        ]);
    }

    public function configTypeStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:entity_role,condominium,unit'],
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['is_active'] = true;
        $data['sort_order'] = (int) $request->integer('sort_order', 999);
        ClientType::query()->updateOrCreate(['scope' => $data['scope'], 'name' => $data['name']], $data);
        return back()->with('success', 'Tipo salvo com sucesso.');
    }

    public function attachmentDownload(ClientAttachment $attachment): BinaryFileResponse
    {
        $path = public_path(ltrim($attachment->relative_path, '/'));
        abort_unless(is_file($path), 404, 'Arquivo não encontrado.');
        return response()->download($path, $attachment->original_name);
    }

    public function attachmentDelete(ClientAttachment $attachment): RedirectResponse
    {
        $redirect = $this->attachmentReturnRedirect($attachment);
        $path = public_path(ltrim($attachment->relative_path, '/'));
        if (is_file($path)) {
            @unlink($path);
        }
        $attachment->delete();
        return $redirect->with('success', 'Anexo removido com sucesso.');
    }

    private function attachmentReturnRedirect(ClientAttachment $attachment): RedirectResponse
    {
        if ($attachment->related_type === 'entity') {
            $entity = ClientEntity::query()->find($attachment->related_id);
            if ($entity) {
                return match ($entity->profile_scope) {
                    'avulso' => redirect()->route('clientes.avulsos.edit', $entity),
                    'contato' => redirect()->route('clientes.contatos.edit', $entity),
                    default => redirect()->back(),
                };
            }
        }

        if ($attachment->related_type === 'condominium') {
            $condominium = ClientCondominium::query()->find($attachment->related_id);
            if ($condominium) {
                return redirect()->route('clientes.condominios.edit', $condominium);
            }
        }

        if ($attachment->related_type === 'unit') {
            $unit = ClientUnit::query()->find($attachment->related_id);
            if ($unit) {
                return redirect()->route('clientes.unidades.edit', $unit);
            }
        }

        return redirect()->back();
    }

    private function condominioPayload(Request $request): array
    {
        $isInactive = $request->boolean('is_inactive');

        return [
            'name' => $this->normalizeTitleCase($request->input('name', '')),
            'condominium_type_id' => $request->integer('condominium_type_id') ?: null,
            'has_blocks' => $request->boolean('has_blocks'),
            'cnpj' => ($value = $this->formatCpfCnpj($request->input('cnpj', ''))) !== '' ? $value : null,
            'cnae' => ($value = $this->normalizeUpper($request->input('cnae', ''))) !== '' ? $value : null,
            'state_registration' => ($value = $this->normalizeUpper($request->input('state_registration', ''))) !== '' ? $value : null,
            'municipal_registration' => ($value = $this->normalizeUpper($request->input('municipal_registration', ''))) !== '' ? $value : null,
            'address_json' => $this->addressFromRequest($request, 'address'),
            'syndico_entity_id' => $request->integer('syndico_entity_id') ?: null,
            'administradora_entity_id' => $request->integer('administradora_entity_id') ?: null,
            'bank_details' => ($value = $this->normalizeWhitespace($request->input('bank_details', ''))) !== '' ? $value : null,
            'characteristics' => ($value = $this->normalizeWhitespace($request->input('characteristics', ''))) !== '' ? $value : null,
            'is_active' => !$isInactive,
            'inactive_reason' => $isInactive ? (($value = $this->normalizeWhitespace($request->input('inactive_reason', ''))) !== '' ? $value : null) : null,
            'contract_end_date' => $isInactive ? (($value = $this->normalizeWhitespace($request->input('contract_end_date', ''))) !== '' ? $value : null) : null,
            'created_by' => AncoraAuth::user($request)?->id,
            'updated_by' => AncoraAuth::user($request)?->id,
        ];
    }

    private function validateCondominium(array $payload): array
    {
        $errors = [];
        if (($payload['name'] ?? '') === '') {
            $errors[] = 'Informe o nome do condomínio.';
        }
        if (empty($payload['syndico_entity_id'])) {
            $errors[] = 'Vincule um síndico ao condomínio.';
        }
        if (!empty($payload['cnpj'])) {
            $digits = $this->digitsOnly($payload['cnpj']);
            if (strlen($digits) !== 14 || !$this->isValidCnpj($digits)) {
                $errors[] = 'Informe um CNPJ válido para o condomínio.';
            }
        }
        if (empty($payload['is_active']) && empty($payload['inactive_reason'])) {
            $errors[] = 'Informe o motivo da inativação.';
        }
        return $errors;
    }

    private function syncBlocks(ClientCondominium $condo, ?string $blocksText): void
    {
        $names = collect(preg_split('/\R+/', trim((string) $blocksText)) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        $desired = [];
        foreach ($names as $index => $name) {
            $desired[$this->blockNameKey($name)] = [
                'name' => $name,
                'sort_order' => $index,
            ];
        }

        $existingBlocks = ClientBlock::query()
            ->where('condominium_id', $condo->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($existingBlocks as $block) {
            $key = $this->blockNameKey((string) $block->name);
            if (array_key_exists($key, $desired)) {
                $block->update($desired[$key]);
                unset($desired[$key]);
                continue;
            }

            $unitCount = ClientUnit::query()->where('block_id', $block->id)->count();
            $caseCount = CobrancaCase::query()->where('block_id', $block->id)->count();
            if (($unitCount + $caseCount) > 0) {
                throw new \RuntimeException("Não é possível remover o bloco {$block->name} porque ele possui {$unitCount} unidade(s) e {$caseCount} OS vinculada(s). Remova as OS e as unidades antes de apagar o bloco.");
            }

            $block->delete();
        }

        foreach ($desired as $block) {
            ClientBlock::query()->create([
                'condominium_id' => $condo->id,
                'name' => $block['name'],
                'sort_order' => $block['sort_order'],
            ]);
        }
    }

    private function unitPayload(Request $request, ?int $ownerId, ?int $tenantId): array
    {
        return [
            'condominium_id' => $request->integer('condominium_id') ?: null,
            'block_id' => $request->integer('block_id') ?: null,
            'unit_type_id' => $request->integer('unit_type_id') ?: null,
            'unit_number' => $this->normalizeUpper($request->input('unit_number', '')),
            'owner_entity_id' => $ownerId,
            'tenant_entity_id' => $tenantId,
            'owner_notes' => ($value = $this->normalizeWhitespace($request->input('owner_notes', ''))) !== '' ? $value : null,
            'tenant_notes' => ($value = $this->normalizeWhitespace($request->input('tenant_notes', ''))) !== '' ? $value : null,
            'created_by' => AncoraAuth::user($request)?->id,
            'updated_by' => AncoraAuth::user($request)?->id,
        ];
    }

    private function validateUnit(array $payload, ?int $ignoreUnitId = null): array
    {
        $errors = [];
        if (empty($payload['condominium_id'])) {
            $errors[] = 'Selecione o condomínio.';
        }
        if (($payload['unit_number'] ?? '') === '') {
            $errors[] = 'Informe o número da unidade.';
        }
        if (!empty($payload['condominium_id']) && ($payload['unit_number'] ?? '') !== '' && $this->unitAlreadyExists(
            (int) $payload['condominium_id'],
            $payload['block_id'] ? (int) $payload['block_id'] : null,
            (string) $payload['unit_number'],
            $ignoreUnitId
        )) {
            $errors[] = 'Já existe uma unidade com este mesmo condomínio, bloco e número. Quando não houver bloco, o número da unidade também não pode se repetir no condomínio.';
        }

        return $errors;
    }

    private function normalizeCsvHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function csvField(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function resolveUnitTypeId(?string $unitTypeName): ?int
    {
        $unitTypeName = trim((string) $unitTypeName);
        if ($unitTypeName === '') {
            return null;
        }

        $type = ClientType::query()->firstOrCreate(
            ['scope' => 'unit', 'name' => $unitTypeName],
            ['is_active' => 1, 'sort_order' => 999]
        );

        return (int) $type->id;
    }

    private function resolveBlockId(ClientCondominium $condominium, ?string $blockName): ?int
    {
        $blockName = trim((string) $blockName);
        if ($blockName === '') {
            return null;
        }

        $existing = $this->findBlockByName($condominium, $blockName);
        if ($existing) {
            return (int) $existing->id;
        }

        $block = ClientBlock::query()->firstOrCreate(
            ['condominium_id' => $condominium->id, 'name' => $blockName],
            ['sort_order' => 999]
        );

        return (int) $block->id;
    }

    private function findBlockByName(ClientCondominium $condominium, ?string $blockName): ?ClientBlock
    {
        $key = $this->blockNameKey((string) $blockName);
        if ($key === '') {
            return null;
        }

        return ClientBlock::query()
            ->where('condominium_id', $condominium->id)
            ->get()
            ->first(fn (ClientBlock $block) => $this->blockNameKey((string) $block->name) === $key);
    }

    private function unitNumberKey(string $unitNumber): string
    {
        return Str::of($this->normalizeUpper($unitNumber))->squish()->toString();
    }

    private function readUnitImportCsv(ClientCondominium $condominium, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \RuntimeException('Não foi possível abrir a planilha CSV para importação.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new \RuntimeException('A planilha CSV está vazia.');
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $header = fgetcsv($handle, 0, $delimiter);
            if (!$header) {
                throw new \RuntimeException('A planilha CSV está vazia.');
            }

            $header[0] = preg_replace("/^\xEF\xBB\xBF/", '', (string) ($header[0] ?? '')) ?? (string) ($header[0] ?? '');
            $header = array_map(fn ($value) => $this->normalizeCsvHeader((string) $value), $header);
            $rows = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;
                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $data = [];
                foreach ($header as $index => $column) {
                    $data[$column] = $row[$index] ?? '';
                }

                $rows[] = [
                    'row_number' => $rowNumber,
                    'condominium_id' => (int) $condominium->id,
                    'condominium_name' => (string) $condominium->name,
                    'block_name' => $this->normalizeWhitespace($this->csvField($data, ['block', 'bloco', 'torre'])),
                    'unit_number' => $this->normalizeUpper($this->csvField($data, ['unit_number', 'unidade', 'numero_unidade', 'numero'])),
                    'unit_type_name' => $this->normalizeWhitespace($this->csvField($data, ['unit_type', 'tipo_unidade', 'tipo'])),
                    'owner_name' => $this->normalizeTitleCase($this->csvField($data, ['owner_name', 'proprietario_nome', 'proprietario'])),
                    'owner_document' => $this->formatCpfCnpj($this->csvField($data, ['owner_document', 'owner_cpf_cnpj', 'proprietario_documento', 'proprietario_cpf_cnpj'])),
                    'owner_phone' => $this->normalizePhone($this->csvField($data, ['owner_phone', 'proprietario_telefone'])),
                    'owner_email' => $this->normalizeEmail($this->csvField($data, ['owner_email', 'proprietario_email'])),
                    'tenant_name' => $this->normalizeTitleCase($this->csvField($data, ['tenant_name', 'locatario_nome', 'locatario'])),
                    'tenant_document' => $this->formatCpfCnpj($this->csvField($data, ['tenant_document', 'tenant_cpf_cnpj', 'locatario_documento', 'locatario_cpf_cnpj'])),
                    'tenant_phone' => $this->normalizePhone($this->csvField($data, ['tenant_phone', 'locatario_telefone'])),
                    'tenant_email' => $this->normalizeEmail($this->csvField($data, ['tenant_email', 'locatario_email'])),
                ];
            }

            if (!$rows) {
                throw new \RuntimeException('Nenhuma unidade válida foi encontrada no CSV.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function prepareUnitImportPreview(ClientCondominium $condominium, array $rows): array
    {
        $seen = [];
        $preparedRows = [];
        $summary = [
            'total' => 0,
            'ready' => 0,
            'errors' => 0,
            'new_blocks' => 0,
        ];
        $newBlockKeys = [];

        foreach ($rows as $row) {
            $row['condominium_id'] = (int) $condominium->id;
            $row['condominium_name'] = (string) $condominium->name;
            $row['block_name'] = $this->normalizeWhitespace((string) ($row['block_name'] ?? ''));
            $row['unit_number'] = $this->normalizeUpper((string) ($row['unit_number'] ?? ''));
            $row['unit_type_name'] = $this->normalizeWhitespace((string) ($row['unit_type_name'] ?? ''));
            $messages = [];

            if ($row['unit_number'] === '') {
                $messages[] = 'Número da unidade não informado.';
            }

            $block = $this->findBlockByName($condominium, $row['block_name']);
            $row['block_id'] = $block?->id;
            $row['block_status'] = $row['block_name'] === '' ? 'none' : ($block ? 'existing' : 'new');

            $blockKey = $row['block_name'] === '' ? '__sem_bloco__' : $this->blockNameKey($row['block_name']);
            $unitKey = $this->unitNumberKey($row['unit_number']);
            $duplicateKey = $condominium->id . '|' . $blockKey . '|' . $unitKey;

            if ($unitKey !== '' && isset($seen[$duplicateKey])) {
                $messages[] = 'Duplicada dentro da própria planilha com a linha ' . $seen[$duplicateKey] . '.';
            } elseif ($unitKey !== '') {
                $seen[$duplicateKey] = (int) ($row['row_number'] ?? 0);
            }

            if ($unitKey !== '') {
                $alreadyExists = $row['block_name'] === ''
                    ? $this->unitAlreadyExists($condominium->id, null, $row['unit_number'])
                    : ($block && $this->unitAlreadyExists($condominium->id, (int) $block->id, $row['unit_number']));

                if ($alreadyExists) {
                    $messages[] = 'Já existe unidade cadastrada para este condomínio, bloco e número.';
                }
            }

            if ($row['block_status'] === 'new') {
                $newBlockKeys[$blockKey] = true;
            }

            $row['status'] = $messages ? 'error' : 'ready';
            $row['messages'] = $messages;
            $preparedRows[] = $row;
            $summary['total']++;
            $summary[$row['status'] === 'ready' ? 'ready' : 'errors']++;
        }

        $summary['new_blocks'] = count($newBlockKeys);

        return [
            'condominium_id' => (int) $condominium->id,
            'condominium_name' => (string) $condominium->name,
            'rows' => $preparedRows,
            'summary' => $summary,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    private function syncPartyEntityFromArray(array $data, string $roleTag, int $userId): ?int
    {
        $name = $this->normalizeTitleCase((string) ($data['name'] ?? ''));
        $document = $this->formatCpfCnpj((string) ($data['document'] ?? ''));
        $phones = collect((array) ($data['phones'] ?? []))->map(fn ($value) => $this->normalizePhone($value))->filter()->values()->all();
        $emails = collect((array) ($data['emails'] ?? []))->map(fn ($value) => $this->normalizeEmail($value))->filter()->values()->all();
        $address = $this->normalizeAddress((array) ($data['address'] ?? []));

        if ($name === '' && $document === '' && empty($phones) && empty($emails) && collect($address)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $document);
        $entityType = strlen($digits) > 11 ? 'pj' : 'pf';

        $payload = [
            'entity_type' => $entityType,
            'profile_scope' => 'contato',
            'role_tag' => $roleTag,
            'display_name' => $name !== '' ? $name : $this->normalizeTitleCase($roleTag),
            'legal_name' => $entityType === 'pj' ? ($name !== '' ? $name : null) : null,
            'cpf_cnpj' => $document !== '' ? $document : null,
            'phones_json' => $this->parseSimpleValues($phones, 'number', 'Telefone'),
            'emails_json' => $this->parseSimpleValues($emails, 'email', 'E-mail'),
            'primary_address_json' => $address,
            'billing_address_json' => $address,
            'is_active' => true,
            'updated_by' => $userId ?: null,
        ];

        $query = ClientEntity::query()->where('profile_scope', 'contato')->where('role_tag', $roleTag);
        if ($document !== '') {
            $query->where('cpf_cnpj', $document);
        } else {
            $query->where('display_name', $payload['display_name']);
        }

        $entity = $query->first();
        if ($entity) {
            $payload['created_by'] = $entity->created_by;
            $entity->update($payload);
            return (int) $entity->id;
        }

        $payload['created_by'] = $userId ?: null;
        return (int) ClientEntity::query()->create($payload)->id;
    }

    public function unidadesImportPreview(Request $request): RedirectResponse
    {
        $request->validate([
            'import_condominium_id' => ['required', 'integer'],
            'import_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $condominium = ClientCondominium::query()->findOrFail((int) $request->input('import_condominium_id'));

        try {
            $rows = $this->readUnitImportCsv($condominium, $request->file('import_file'));
            $preview = $this->prepareUnitImportPreview($condominium, $rows);
            $token = (string) Str::uuid();

            $request->session()->put("client_unit_import_previews.{$token}", $preview);
            $this->logClientAction($request, 'clientes.unidades.import.preview', 'client_units', null, "Prévia de importação de {$preview['summary']['total']} unidade(s) para {$condominium->name}.");

            $message = $preview['summary']['errors'] > 0
                ? "Prévia gerada com {$preview['summary']['errors']} pendência(s). Corrija as linhas sinalizadas antes de executar."
                : "Prévia gerada com {$preview['summary']['ready']} unidade(s) pronta(s) para criação. Confira e clique em executar.";

            return redirect()->route('clientes.unidades')
                ->with('unit_import_preview_token', $token)
                ->with($preview['summary']['errors'] > 0 ? 'error' : 'success', $message);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Não foi possível gerar a prévia da importação agora. Revise o CSV e tente novamente.');
        }
    }

    public function unidadesImportExecute(Request $request): RedirectResponse
    {
        $token = (string) $request->input('import_token', '');
        $preview = $token !== '' ? $request->session()->get("client_unit_import_previews.{$token}") : null;

        if (!$preview || empty($preview['condominium_id']) || empty($preview['rows'])) {
            return redirect()->route('clientes.unidades')->with('error', 'A prévia da importação expirou. Envie o CSV novamente.');
        }

        $condominium = ClientCondominium::query()->findOrFail((int) $preview['condominium_id']);
        $preview = $this->prepareUnitImportPreview($condominium, (array) $preview['rows']);
        $request->session()->put("client_unit_import_previews.{$token}", $preview);

        if (($preview['summary']['errors'] ?? 0) > 0) {
            return redirect()->route('clientes.unidades')
                ->with('unit_import_preview_token', $token)
                ->with('error', 'A importação não foi executada porque ainda existem linhas duplicadas ou inválidas na prévia.');
        }

        $userId = (int) (AncoraAuth::user($request)?->id ?? 0);

        try {
            $created = DB::transaction(function () use ($preview, $condominium, $userId) {
                $created = 0;

                foreach ($preview['rows'] as $row) {
                    if (($row['status'] ?? '') !== 'ready') {
                        continue;
                    }

                    $blockId = $this->resolveBlockId($condominium, $row['block_name'] ?? '');
                    if ($this->unitAlreadyExists($condominium->id, $blockId, (string) $row['unit_number'])) {
                        throw new \RuntimeException("A unidade {$row['unit_number']} já existe para este condomínio e bloco.");
                    }

                    $ownerId = $this->syncPartyEntityFromArray([
                        'name' => $row['owner_name'] ?? '',
                        'document' => $row['owner_document'] ?? '',
                        'phones' => [$row['owner_phone'] ?? ''],
                        'emails' => [$row['owner_email'] ?? ''],
                    ], 'proprietario', $userId);

                    $tenantId = $this->syncPartyEntityFromArray([
                        'name' => $row['tenant_name'] ?? '',
                        'document' => $row['tenant_document'] ?? '',
                        'phones' => [$row['tenant_phone'] ?? ''],
                        'emails' => [$row['tenant_email'] ?? ''],
                    ], 'locatario', $userId);

                    ClientUnit::query()->create([
                        'condominium_id' => $condominium->id,
                        'block_id' => $blockId,
                        'unit_type_id' => $this->resolveUnitTypeId($row['unit_type_name'] ?? ''),
                        'unit_number' => $this->normalizeUpper($row['unit_number'] ?? ''),
                        'owner_entity_id' => $ownerId,
                        'tenant_entity_id' => $tenantId,
                        'owner_notes' => null,
                        'tenant_notes' => null,
                        'created_by' => $userId ?: null,
                        'updated_by' => $userId ?: null,
                    ]);

                    $created++;
                }

                return $created;
            });

            $request->session()->forget("client_unit_import_previews.{$token}");
            $this->logClientAction($request, 'clientes.unidades.import.execute', 'client_units', null, "Importação executada: {$created} unidade(s) criada(s) para {$condominium->name}.");

            return redirect()->route('clientes.unidades')->with('success', "Importação executada. {$created} unidade(s) criada(s).");
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('clientes.unidades')
                ->with('unit_import_preview_token', $token)
                ->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível executar a importação agora. Tente novamente.');
        }
    }

    public function unidadesBulkDelete(Request $request): RedirectResponse
    {
        $ids = collect((array) $request->input('unit_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Selecione ao menos uma unidade para excluir.');
        }

        $caseCount = CobrancaCase::query()->whereIn('unit_id', $ids)->count();
        if ($caseCount > 0) {
            return back()->with('error', "Não foi possível excluir em massa: existem {$caseCount} OS vinculada(s) às unidades selecionadas. Exclua as OS primeiro.");
        }

        $units = ClientUnit::query()
            ->with(['condominium', 'block'])
            ->whereIn('id', $ids)
            ->get();

        if ($units->isEmpty()) {
            return back()->with('error', 'Nenhuma unidade válida foi encontrada para exclusão.');
        }

        $deleted = DB::transaction(function () use ($units) {
            $count = $units->count();
            ClientUnit::query()->whereIn('id', $units->pluck('id'))->delete();

            return $count;
        });

        $this->logClientAction($request, 'clientes.unidades.bulk-delete', 'client_units', null, "Exclusão em massa de {$deleted} unidade(s).");

        return redirect()->route('clientes.unidades')->with('success', "{$deleted} unidade(s) excluída(s) com sucesso.");
    }

    private function logClientAction(Request $request, string $action, ?string $entityType, ?int $entityId, string $details): void
    {
        try {
            $user = AncoraAuth::user($request);
            AuditLog::query()->create([
                'user_id' => $user?->id,
                'user_email' => $user?->email ?? 'desconhecido',
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            //
        }
    }

    private function unidadesImportLegacy(Request $request): RedirectResponse
    {
        $request->validate([
            'import_condominium_id' => ['required', 'integer'],
            'import_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $condominium = ClientCondominium::query()->findOrFail((int) $request->input('import_condominium_id'));
        $file = $request->file('import_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            return back()->with('error', 'Não foi possível abrir a planilha CSV para importação.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return back()->with('error', 'A planilha CSV está vazia.');
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $header = fgetcsv($handle, 0, $delimiter);
            if (!$header) {
                return back()->with('error', 'A planilha CSV está vazia.');
            }

            $header[0] = preg_replace('/^ï»¿/', '', (string) ($header[0] ?? '')) ?? (string) ($header[0] ?? '');
            $header = array_map(fn ($value) => $this->normalizeCsvHeader((string) $value), $header);
            $created = 0;
            $updated = 0;
            $userId = (int) (AncoraAuth::user($request)?->id ?? 0);

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $data = [];
                foreach ($header as $index => $column) {
                    $data[$column] = $row[$index] ?? '';
                }

                $unitNumber = $this->csvField($data, ['unit_number', 'unidade', 'numero_unidade', 'numero']);
                if ($unitNumber === '') {
                    continue;
                }

                $blockName = $this->csvField($data, ['block', 'bloco', 'torre']);
                $unitTypeName = $this->csvField($data, ['unit_type', 'tipo_unidade', 'tipo']);

                $ownerId = $this->syncPartyEntityFromArray([
                    'name' => $this->csvField($data, ['owner_name', 'proprietario_nome', 'proprietario']),
                    'document' => $this->csvField($data, ['owner_document', 'owner_cpf_cnpj', 'proprietario_documento', 'proprietario_cpf_cnpj']),
                    'phones' => [$this->csvField($data, ['owner_phone', 'proprietario_telefone'])],
                    'emails' => [$this->csvField($data, ['owner_email', 'proprietario_email'])],
                ], 'proprietario', $userId);

                $tenantId = $this->syncPartyEntityFromArray([
                    'name' => $this->csvField($data, ['tenant_name', 'locatario_nome', 'locatario']),
                    'document' => $this->csvField($data, ['tenant_document', 'tenant_cpf_cnpj', 'locatario_documento', 'locatario_cpf_cnpj']),
                    'phones' => [$this->csvField($data, ['tenant_phone', 'locatario_telefone'])],
                    'emails' => [$this->csvField($data, ['tenant_email', 'locatario_email'])],
                ], 'locatario', $userId);

                $blockId = $this->resolveBlockId($condominium, $blockName);
                $unitTypeId = $this->resolveUnitTypeId($unitTypeName);

                $existing = ClientUnit::query()
                    ->where('condominium_id', $condominium->id)
                    ->where('unit_number', $unitNumber)
                    ->where(function ($query) use ($blockId) {
                        if ($blockId) {
                            $query->where('block_id', $blockId);
                        } else {
                            $query->whereNull('block_id');
                        }
                    })
                    ->first();

                $payload = [
                    'condominium_id' => $condominium->id,
                    'block_id' => $blockId,
                    'unit_type_id' => $unitTypeId,
                    'unit_number' => $this->normalizeUpper($unitNumber),
                    'owner_entity_id' => $ownerId,
                    'tenant_entity_id' => $tenantId,
                    'owner_notes' => null,
                    'tenant_notes' => null,
                    'updated_by' => $userId ?: null,
                ];

                if ($existing) {
                    $payload['created_by'] = $existing->created_by;
                    $existing->update($payload);
                    $updated++;
                } else {
                    $payload['created_by'] = $userId ?: null;
                    ClientUnit::query()->create($payload);
                    $created++;
                }
            }

            return back()->with('success', "Importação concluída. {$created} unidade(s) criada(s) e {$updated} atualizada(s).");
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Não foi possível concluir a importação em massa agora. Revise o CSV e tente novamente.');
        } finally {
            fclose($handle);
        }
    }
}
