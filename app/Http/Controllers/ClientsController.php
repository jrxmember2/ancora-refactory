<?php

namespace App\Http\Controllers;

use App\Models\ClientAttachment;
use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientTimeline;
use App\Models\ClientType;
use App\Models\ClientUnit;
use App\Support\AncoraAuth;
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
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        return $values->map(fn ($value, $index) => [
            'label' => $labelPrefix . ' ' . ($index + 1),
            $fieldKey => $value,
        ])->all();
    }

    private function addressFromRequest(Request $request, string $prefix): array
    {
        return [
            'street' => trim((string) $request->input($prefix . '_street', '')),
            'number' => trim((string) $request->input($prefix . '_number', '')),
            'complement' => trim((string) $request->input($prefix . '_complement', '')),
            'neighborhood' => trim((string) $request->input($prefix . '_neighborhood', '')),
            'city' => trim((string) $request->input($prefix . '_city', '')),
            'state' => trim((string) $request->input($prefix . '_state', '')),
            'zip' => trim((string) $request->input($prefix . '_zip', '')),
            'notes' => trim((string) $request->input($prefix . '_notes', '')),
        ];
    }

    private function entityPayloadFromRequest(Request $request, string $scope, string $defaultRole = 'outro'): array
    {
        $entityType = $request->input('entity_type', 'pf') === 'pj' ? 'pj' : 'pf';
        $isInactive = $request->boolean('is_inactive');

        $payload = [
            'entity_type' => $entityType,
            'profile_scope' => $scope,
            'role_tag' => trim((string) $request->input('role_tag', $defaultRole)) ?: $defaultRole,
            'display_name' => trim((string) $request->input('display_name', '')),
            'legal_name' => trim((string) $request->input('legal_name', '')) ?: null,
            'cpf_cnpj' => trim((string) $request->input('cpf_cnpj', '')) ?: null,
            'rg_ie' => trim((string) $request->input('rg_ie', '')) ?: null,
            'gender' => trim((string) $request->input('gender', '')) ?: null,
            'nationality' => trim((string) $request->input('nationality', '')) ?: null,
            'birth_date' => trim((string) $request->input('birth_date', '')) ?: null,
            'profession' => trim((string) $request->input('profession', '')) ?: null,
            'marital_status' => trim((string) $request->input('marital_status', '')) ?: null,
            'pis' => trim((string) $request->input('pis', '')) ?: null,
            'spouse_name' => trim((string) $request->input('spouse_name', '')) ?: null,
            'father_name' => trim((string) $request->input('father_name', '')) ?: null,
            'mother_name' => trim((string) $request->input('mother_name', '')) ?: null,
            'children_info' => trim((string) $request->input('children_info', '')) ?: null,
            'ctps' => trim((string) $request->input('ctps', '')) ?: null,
            'cnae' => trim((string) $request->input('cnae', '')) ?: null,
            'state_registration' => trim((string) $request->input('state_registration', '')) ?: null,
            'municipal_registration' => trim((string) $request->input('municipal_registration', '')) ?: null,
            'opening_date' => null,
            'legal_representative' => trim((string) $request->input('legal_representative', '')) ?: null,
            'phones_json' => $this->parseLines($request->input('phones_text'), ['label', 'number']),
            'emails_json' => $this->parseLines($request->input('emails_text'), ['label', 'email']),
            'primary_address_json' => $this->addressFromRequest($request, 'primary_address'),
            'billing_address_json' => $this->addressFromRequest($request, 'billing_address'),
            'shareholders_json' => $this->parseLines($request->input('shareholders_text'), ['name', 'document', 'role']),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'is_active' => !$isInactive,
            'inactive_reason' => $isInactive ? (trim((string) $request->input('inactive_reason', '')) ?: null) : null,
            'contract_end_date' => $isInactive ? (trim((string) $request->input('contract_end_date', '')) ?: null) : null,
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
        if (empty($data['is_active']) && empty($data['inactive_reason'])) {
            $errors[] = 'Informe o motivo da inativação.';
        }
        return $errors;
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

            $ext = strtolower($file->getClientOriginalExtension());
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
            $fileSize = (int) ($file->getSize() ?: 0);

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
        $files = $request->file('attachments');
        if ($files) {
            $files = is_array($files) ? $files : [$files];
            $role = in_array($request->input('attachment_role', 'documento'), ['documento', 'contrato', 'outro'], true)
                ? $request->input('attachment_role')
                : 'documento';
            $this->storeAttachmentFiles($relatedType, $relatedId, $files, $role, $request);
        }
    }

    private function uploadCondominiumDocuments(int $condominiumId, Request $request): void
    {
        if ($request->hasFile('document_convention')) {
            $this->storeAttachmentFiles('condominium', $condominiumId, [$request->file('document_convention')], 'documento', $request, 'Convenção condominial');
        }
        if ($request->hasFile('document_regiment')) {
            $this->storeAttachmentFiles('condominium', $condominiumId, [$request->file('document_regiment')], 'documento', $request, 'Regimento interno');
        }
        if ($request->hasFile('document_atas')) {
            $files = $request->file('document_atas');
            $files = is_array($files) ? $files : [$files];
            $this->storeAttachmentFiles('condominium', $condominiumId, $files, 'documento', $request, 'ATA');
        }
    }

    private function syncPartyEntityFromRequest(Request $request, string $prefix, string $roleTag, ?int $existingId = null): ?int
    {
        $name = trim((string) $request->input($prefix . '_name', ''));
        $document = trim((string) $request->input($prefix . '_cpf_cnpj', ''));
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
            'display_name' => $name !== '' ? $name : ($existingId ? ClientEntity::query()->find($existingId)?->display_name : $roleTag),
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
        $query = ClientEntity::query()->where('profile_scope', 'avulso')->orderBy('display_name');
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

        return view('pages.clientes.avulsos', array_merge([
            'title' => 'Clientes avulsos',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
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
        $query = ClientEntity::query()->where('profile_scope', 'contato')->orderBy('display_name');
        if ($term = trim((string) $request->input('q'))) {
            $query->where(fn ($sub) => $sub->where('display_name', 'like', "%{$term}%")->orWhere('cpf_cnpj', 'like', "%{$term}%"));
        }
        if ($request->filled('role_tag')) {
            $query->where('role_tag', $request->input('role_tag'));
        }

        return view('pages.clientes.contatos', array_merge([
            'title' => 'Parceiros e fornecedores',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
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

        return view('pages.clientes.contatos-form', array_merge([
            'title' => 'Editar parceiro / fornecedor',
            'item' => $contato,
            'mode' => 'edit',
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
        $contato->delete();
        return redirect()->route('clientes.contatos')->with('success', 'Cadastro excluído.');
    }

    public function condominios(Request $request): View
    {
        $query = ClientCondominium::query()->with(['type', 'syndic', 'administradora'])->orderBy('name');
        if ($term = trim((string) $request->input('q'))) {
            $query->where('name', 'like', "%{$term}%");
        }

        return view('pages.clientes.condominios', [
            'title' => 'Condomínios',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
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
        $condominio->delete();
        return redirect()->route('clientes.condominios')->with('success', 'Condomínio excluído.');
    }

    public function unidades(Request $request): View
    {
        $query = ClientUnit::query()->with(['condominium', 'block', 'type', 'owner', 'tenant'])->orderByDesc('id');
        if ($term = trim((string) $request->input('q'))) {
            $query->where('unit_number', 'like', "%{$term}%");
        }
        if ($request->filled('condominium_id')) {
            $query->where('condominium_id', (int) $request->input('condominium_id'));
        }

        return view('pages.clientes.unidades', array_merge([
            'title' => 'Unidades',
            'items' => $query->paginate(15)->withQueryString(),
            'filters' => $request->all(),
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
        $ownerId = $this->syncPartyEntityFromRequest($request, 'owner', 'proprietario');
        $tenantId = $this->syncPartyEntityFromRequest($request, 'tenant', 'locatario');
        $payload = $this->unitPayload($request, $ownerId, $tenantId);
        $errors = $this->validateUnit($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        try {
            $unit = ClientUnit::query()->create($payload);
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
        $ownerId = $this->syncPartyEntityFromRequest($request, 'owner', 'proprietario', $unidade->owner_entity_id);
        $tenantId = $this->syncPartyEntityFromRequest($request, 'tenant', 'locatario', $unidade->tenant_entity_id);
        $payload = $this->unitPayload($request, $ownerId, $tenantId);
        $payload['created_by'] = $unidade->created_by;
        $errors = $this->validateUnit($payload);
        if ($errors) {
            return back()->withInput()->with('errors_list', $errors);
        }

        try {
            $unidade->update($payload);
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
        $path = public_path(ltrim($attachment->relative_path, '/'));
        if (is_file($path)) {
            @unlink($path);
        }
        $attachment->delete();
        return back()->with('success', 'Anexo removido com sucesso.');
    }

    private function condominioPayload(Request $request): array
    {
        $isInactive = $request->boolean('is_inactive');

        return [
            'name' => trim((string) $request->input('name', '')),
            'condominium_type_id' => $request->integer('condominium_type_id') ?: null,
            'has_blocks' => $request->boolean('has_blocks'),
            'cnpj' => trim((string) $request->input('cnpj', '')) ?: null,
            'cnae' => trim((string) $request->input('cnae', '')) ?: null,
            'state_registration' => trim((string) $request->input('state_registration', '')) ?: null,
            'municipal_registration' => trim((string) $request->input('municipal_registration', '')) ?: null,
            'address_json' => $this->addressFromRequest($request, 'address'),
            'syndico_entity_id' => $request->integer('syndico_entity_id') ?: null,
            'administradora_entity_id' => $request->integer('administradora_entity_id') ?: null,
            'bank_details' => trim((string) $request->input('bank_details', '')) ?: null,
            'characteristics' => trim((string) $request->input('characteristics', '')) ?: null,
            'is_active' => !$isInactive,
            'inactive_reason' => $isInactive ? (trim((string) $request->input('inactive_reason', '')) ?: null) : null,
            'contract_end_date' => $isInactive ? (trim((string) $request->input('contract_end_date', '')) ?: null) : null,
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

        $condo->blocks()->delete();
        foreach ($names as $index => $name) {
            ClientBlock::query()->create([
                'condominium_id' => $condo->id,
                'name' => $name,
                'sort_order' => $index,
            ]);
        }
    }

    private function unitPayload(Request $request, ?int $ownerId, ?int $tenantId): array
    {
        return [
            'condominium_id' => $request->integer('condominium_id') ?: null,
            'block_id' => $request->integer('block_id') ?: null,
            'unit_type_id' => $request->integer('unit_type_id') ?: null,
            'unit_number' => trim((string) $request->input('unit_number', '')),
            'owner_entity_id' => $ownerId,
            'tenant_entity_id' => $tenantId,
            'owner_notes' => trim((string) $request->input('owner_notes', '')) ?: null,
            'tenant_notes' => trim((string) $request->input('tenant_notes', '')) ?: null,
            'created_by' => AncoraAuth::user($request)?->id,
            'updated_by' => AncoraAuth::user($request)?->id,
        ];
    }

    private function validateUnit(array $payload): array
    {
        $errors = [];
        if (empty($payload['condominium_id'])) {
            $errors[] = 'Selecione o condomínio.';
        }
        if (($payload['unit_number'] ?? '') === '') {
            $errors[] = 'Informe o número da unidade.';
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

        $block = ClientBlock::query()->firstOrCreate(
            ['condominium_id' => $condominium->id, 'name' => $blockName],
            ['sort_order' => 999]
        );

        return (int) $block->id;
    }

    private function syncPartyEntityFromArray(array $data, string $roleTag, int $userId): ?int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $document = trim((string) ($data['document'] ?? ''));
        $phones = collect((array) ($data['phones'] ?? []))->map(fn ($value) => trim((string) $value))->filter()->values()->all();
        $emails = collect((array) ($data['emails'] ?? []))->map(fn ($value) => trim((string) $value))->filter()->values()->all();
        $address = (array) ($data['address'] ?? []);

        if ($name === '' && $document === '' && empty($phones) && empty($emails) && collect($address)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $document);
        $entityType = strlen($digits) > 11 ? 'pj' : 'pf';

        $payload = [
            'entity_type' => $entityType,
            'profile_scope' => 'contato',
            'role_tag' => $roleTag,
            'display_name' => $name !== '' ? $name : ucfirst($roleTag),
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

    public function unidadesImport(Request $request): RedirectResponse
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
            $header = fgetcsv($handle, 0, ',');
            if (!$header) {
                return back()->with('error', 'A planilha CSV está vazia.');
            }

            $header = array_map(fn ($value) => $this->normalizeCsvHeader((string) $value), $header);
            $created = 0;
            $updated = 0;
            $userId = (int) (AncoraAuth::user($request)?->id ?? 0);

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
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
                    'unit_number' => $unitNumber,
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
