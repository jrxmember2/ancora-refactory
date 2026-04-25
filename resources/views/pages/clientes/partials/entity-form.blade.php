@php
    $entity = $item;
    $primary = $entity?->primary_address_json ?? [];
    $billing = $entity?->billing_address_json ?? [];
    $selectedEntityType = old('entity_type', $entity?->entity_type ?? 'pf');
    $selectedInactive = old('is_inactive', ($entity && !$entity->is_active) ? 1 : 0);
    $addressesMatch = !empty($primary) && $primary === $billing;
    $selectedSameBilling = old('billing_same_as_primary', $addressesMatch ? 1 : 0);
    $roleOptions = $roleOptions ?? $entityRoles ?? collect();
    $maritalOptions = ['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Separado(a)', 'Viúvo(a)', 'União estável'];

    $phonesRows = old('phones');
    if (!is_array($phonesRows)) {
        $phonesRows = collect($entity?->phones_json ?? [])
            ->map(fn ($row) => ['number' => trim((string) ($row['number'] ?? ''))])
            ->filter(fn ($row) => $row['number'] !== '')
            ->values()
            ->all();
    }
    if ($phonesRows === []) {
        $phonesRows = [['number' => '']];
    }

    $emailsRows = old('emails');
    if (!is_array($emailsRows)) {
        $emailsRows = collect($entity?->emails_json ?? [])
            ->map(fn ($row) => ['email' => trim((string) ($row['email'] ?? ''))])
            ->filter(fn ($row) => $row['email'] !== '')
            ->values()
            ->all();
    }
    if ($emailsRows === []) {
        $emailsRows = [['email' => '']];
    }

    $cobrancaEmailsRows = old('cobranca_emails');
    if (!is_array($cobrancaEmailsRows)) {
        $cobrancaEmailsRows = collect($entity?->cobranca_emails_json ?? [])
            ->map(fn ($row) => ['email' => trim((string) ($row['email'] ?? ''))])
            ->filter(fn ($row) => $row['email'] !== '')
            ->values()
            ->all();
    }
    if ($cobrancaEmailsRows === []) {
        $cobrancaEmailsRows = [['email' => '']];
    }

    $shareholderRows = old('shareholders');
    if (!is_array($shareholderRows)) {
        $shareholderRows = collect($entity?->shareholders_json ?? [])
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'document' => trim((string) ($row['document'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
            ])
            ->filter(fn ($row) => collect($row)->filter(fn ($value) => $value !== '')->isNotEmpty())
            ->values()
            ->all();
    }
    if ($shareholderRows === []) {
        $shareholderRows = [['name' => '', 'document' => '', 'role' => '']];
    }

    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
    $selectClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
    $secondaryButtonClass = 'inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200 dark:hover:bg-gray-800';
@endphp

<div
    class="grid grid-cols-1 gap-6 xl:grid-cols-3"
    x-data="entityClientForm({
        entityType: '{{ $selectedEntityType }}',
        inactive: {{ $selectedInactive ? 'true' : 'false' }},
        sameBilling: {{ $selectedSameBilling ? 'true' : 'false' }},
        roleTag: @js(old('role_tag', $entity?->role_tag ?? $roleTag ?? '')),
        phones: @js(array_values($phonesRows)),
        emails: @js(array_values($emailsRows)),
        cobrancaEmails: @js(array_values($cobrancaEmailsRows)),
        shareholders: @js(array_values($shareholderRows)),
    })"
    x-init="init()"
>
    <div class="space-y-6 xl:col-span-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                    <select name="entity_type" x-model="entityType" @change="maskDocument()" class="{{ $selectClass }}">
                        <option value="pf">Pessoa física</option>
                        <option value="pj">Pessoa jurídica</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Perfil / papel</label>
                    <select name="role_tag" x-model="roleTag" class="{{ $selectClass }}" required>
                        <option value="">Selecione</option>
                        @foreach($roleOptions as $role)
                            <option value="{{ $role->name }}" @selected(old('role_tag', $entity?->role_tag ?? $roleTag) === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cadastre novos perfis/papéis em Configurações de clientes.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome principal</label>
                    <input name="display_name" value="{{ old('display_name', $entity?->display_name) }}" class="{{ $fieldClass }}" placeholder="Nome completo ou nome fantasia" required>
                </div>
                <div x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome jurídico / razão social</label>
                    <input name="legal_name" value="{{ old('legal_name', $entity?->legal_name) }}" class="{{ $fieldClass }}" placeholder="Razão social" :disabled="isPf">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                    <input name="cpf_cnpj" value="{{ old('cpf_cnpj', $entity?->cpf_cnpj) }}" class="{{ $fieldClass }}" x-ref="document" @input="maskDocument()" placeholder="CPF ou CNPJ" inputmode="numeric">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">RG / IE</label>
                    <input name="rg_ie" value="{{ old('rg_ie', $entity?->rg_ie) }}" class="{{ $fieldClass }}" placeholder="RG ou inscrição estadual" :disabled="isPj">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Profissão</label>
                    <input name="profession" value="{{ old('profession', $entity?->profession) }}" class="{{ $fieldClass }}" placeholder="Profissão" :disabled="isPj">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Estado civil</label>
                    <select name="marital_status" class="{{ $selectClass }}" :disabled="isPj">
                        <option value="">Selecione</option>
                        @foreach($maritalOptions as $status)
                            <option value="{{ $status }}" @selected(old('marital_status', $entity?->marital_status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de nascimento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $entity?->birth_date?->format('Y-m-d') ?? $entity?->birth_date) }}" class="{{ $fieldClass }}" :disabled="isPj">
                </div>
                <div x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Representante legal</label>
                    <input name="legal_representative" value="{{ old('legal_representative', $entity?->legal_representative) }}" class="{{ $fieldClass }}" placeholder="Representante legal" :disabled="isPf">
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contatos e observações</h3>
            <div class="mt-5 grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Telefones</label>
                        <button type="button" class="{{ $secondaryButtonClass }}" @click="addPhone()">
                            <i class="fa-solid fa-plus"></i>
                            <span>Adicionar</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <template x-for="(phone, index) in phones" :key="`phone-${index}`">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr,auto] sm:items-end">
                                <div>
                                    <input :name="`phones[${index}][number]`" x-model="phone.number" @input="maskPhone(index)" class="{{ $fieldClass }}" placeholder="(27) 99999-9999" inputmode="numeric">
                                </div>
                                <button type="button" class="{{ $secondaryButtonClass }}" @click="removePhone(index)" x-show="phones.length > 1" x-cloak>
                                    Remover
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">E-mails</label>
                        <button type="button" class="{{ $secondaryButtonClass }}" @click="addEmail()">
                            <i class="fa-solid fa-plus"></i>
                            <span>Adicionar</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <template x-for="(email, index) in emails" :key="`email-${index}`">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr,auto] sm:items-end">
                                <div>
                                    <input :name="`emails[${index}][email]`" x-model="email.email" class="{{ $fieldClass }}" placeholder="email@dominio.com" inputmode="email" autocomplete="off">
                                </div>
                                <button type="button" class="{{ $secondaryButtonClass }}" @click="removeEmail(index)" x-show="emails.length > 1" x-cloak>
                                    Remover
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="md:col-span-2" x-show="showCobrancaEmails" x-cloak>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">E-mails do setor de cobrança</label>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Usados na solicitação de boletos para a administradora.</p>
                        </div>
                        <button type="button" class="{{ $secondaryButtonClass }}" @click="addCobrancaEmail()">
                            <i class="fa-solid fa-plus"></i>
                            <span>Adicionar</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <template x-for="(email, index) in cobrancaEmails" :key="`cobranca-email-${index}`">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr,auto] sm:items-end">
                                <div>
                                    <input :name="`cobranca_emails[${index}][email]`" x-model="email.email" class="{{ $fieldClass }}" placeholder="cobranca@administradora.com" inputmode="email" autocomplete="off">
                                </div>
                                <button type="button" class="{{ $secondaryButtonClass }}" @click="removeCobrancaEmail(index)" x-show="cobrancaEmails.length > 1" x-cloak>
                                    Remover
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="md:col-span-2" x-bind:class="isPf ? 'opacity-60' : ''">
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sócios / acionistas</label>
                        <button type="button" class="{{ $secondaryButtonClass }}" @click="addShareholder()" :disabled="isPf">
                            <i class="fa-solid fa-plus"></i>
                            <span>Adicionar</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <template x-for="(shareholder, index) in shareholders" :key="`shareholder-${index}`">
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1.3fr,1fr,1fr,auto] lg:items-end">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                                        <input :name="`shareholders[${index}][name]`" x-model="shareholder.name" class="{{ $fieldClass }}" placeholder="Nome do sócio" :disabled="isPf">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                                        <input :name="`shareholders[${index}][document]`" x-model="shareholder.document" @input="maskFlexibleDocument(index)" class="{{ $fieldClass }}" placeholder="CPF ou CNPJ" inputmode="numeric" :disabled="isPf">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Função</label>
                                        <input :name="`shareholders[${index}][role]`" x-model="shareholder.role" class="{{ $fieldClass }}" placeholder="Ex.: Sócio administrador" :disabled="isPf">
                                    </div>
                                    <button type="button" class="{{ $secondaryButtonClass }}" @click="removeShareholder(index)" x-show="shareholders.length > 1" x-cloak :disabled="isPf">
                                        Remover
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notas</label>
                    <textarea name="notes" rows="3" class="{{ $textareaClass }}" placeholder="Informações relevantes para a equipe.">{{ old('notes', $entity?->notes) }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Descrição / observações livres</label>
                    <textarea name="description" rows="4" class="{{ $textareaClass }}" placeholder="Observações complementares.">{{ old('description', $entity?->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @include('pages.clientes.partials.address-fields', [
                'prefix' => 'primary_address',
                'address' => $primary,
                'title' => 'Endereço principal',
            ])

            <div class="space-y-3">
                <label class="inline-flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="billing_same_as_primary" value="1" x-model="sameBilling">
                    Endereço principal é o mesmo de cobrança
                </label>

                @include('pages.clientes.partials.address-fields', [
                    'prefix' => 'billing_address',
                    'address' => $billing,
                    'title' => 'Endereço de cobrança',
                    'disabledExpression' => 'sameBilling',
                ])
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Status</h3>
            <div class="mt-4 space-y-4">
                <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_inactive" value="1" x-model="inactive">
                    Inativo
                </label>
                <div x-bind:class="inactive ? '' : 'opacity-60'">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Motivo da inativação</label>
                    <input name="inactive_reason" value="{{ old('inactive_reason', $entity?->inactive_reason) }}" class="{{ $fieldClass }}" placeholder="Motivo" :disabled="!inactive">
                </div>
                <div x-bind:class="inactive ? '' : 'opacity-60'">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Fim do contrato</label>
                    <input type="date" name="contract_end_date" value="{{ old('contract_end_date', $entity?->contract_end_date?->format('Y-m-d') ?? $entity?->contract_end_date) }}" class="{{ $fieldClass }}" :disabled="!inactive">
                </div>

                <div data-attachment-manager>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Anexos</label>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-attachment-add>
                            <i class="fa-solid fa-plus"></i>
                            <span>Adicionar</span>
                        </button>
                    </div>

                    <div class="space-y-3" data-attachment-repeater>
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800" data-attachment-group>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr,auto] sm:items-end">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel do anexo</label>
                                    <select name="attachment_groups[0][role]" class="{{ $selectClass }}">
                                        <option value="documento">Documento</option>
                                        <option value="contrato">Contrato</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                                <button type="button" class="hidden rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200 dark:hover:bg-gray-800" data-attachment-remove>Remover</button>
                            </div>
                            <div class="mt-3" data-file-preview>
                                <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span>Escolher arquivo(s)</span>
                                    <input type="file" name="attachment_groups[0][files][]" multiple class="sr-only" data-file-input data-multiple>
                                </label>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>Nenhum arquivo selecionado</div>
                            </div>
                        </div>
                    </div>

                    <template data-attachment-template>
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800" data-attachment-group>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr,auto] sm:items-end">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel do anexo</label>
                                    <select name="attachment_groups[__INDEX__][role]" class="{{ $selectClass }}">
                                        <option value="documento">Documento</option>
                                        <option value="contrato">Contrato</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                                <button type="button" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200 dark:hover:bg-gray-800" data-attachment-remove>Remover</button>
                            </div>
                            <div class="mt-3" data-file-preview>
                                <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span>Escolher arquivo(s)</span>
                                    <input type="file" name="attachment_groups[__INDEX__][files][]" multiple class="sr-only" data-file-input data-multiple>
                                </label>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>Nenhum arquivo selecionado</div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function entityClientForm(initialState) {
                return {
                    entityType: initialState.entityType || 'pf',
                    inactive: !!initialState.inactive,
                    sameBilling: !!initialState.sameBilling,
                    roleTag: initialState.roleTag || '',
                    phones: Array.isArray(initialState.phones) && initialState.phones.length ? initialState.phones : [{ number: '' }],
                    emails: Array.isArray(initialState.emails) && initialState.emails.length ? initialState.emails : [{ email: '' }],
                    cobrancaEmails: Array.isArray(initialState.cobrancaEmails) && initialState.cobrancaEmails.length ? initialState.cobrancaEmails : [{ email: '' }],
                    shareholders: Array.isArray(initialState.shareholders) && initialState.shareholders.length ? initialState.shareholders : [{ name: '', document: '', role: '' }],
                    get isPf() { return this.entityType === 'pf'; },
                    get isPj() { return this.entityType === 'pj'; },
                    get normalizedRoleTag() {
                        return String(this.roleTag || '')
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .toLowerCase()
                            .trim();
                    },
                    get showCobrancaEmails() {
                        return this.normalizedRoleTag.includes('administradora');
                    },
                    init() {
                        this.maskDocument();
                        this.phones.forEach((_, index) => this.maskPhone(index));
                        this.shareholders.forEach((_, index) => this.maskFlexibleDocument(index));
                    },
                    addPhone() {
                        this.phones.push({ number: '' });
                    },
                    removePhone(index) {
                        if (this.phones.length === 1) {
                            this.phones[0].number = '';
                            return;
                        }
                        this.phones.splice(index, 1);
                    },
                    addEmail() {
                        this.emails.push({ email: '' });
                    },
                    removeEmail(index) {
                        if (this.emails.length === 1) {
                            this.emails[0].email = '';
                            return;
                        }
                        this.emails.splice(index, 1);
                    },
                    addCobrancaEmail() {
                        this.cobrancaEmails.push({ email: '' });
                    },
                    removeCobrancaEmail(index) {
                        if (this.cobrancaEmails.length === 1) {
                            this.cobrancaEmails[0].email = '';
                            return;
                        }
                        this.cobrancaEmails.splice(index, 1);
                    },
                    addShareholder() {
                        if (this.isPf) return;
                        this.shareholders.push({ name: '', document: '', role: '' });
                    },
                    removeShareholder(index) {
                        if (this.shareholders.length === 1) {
                            this.shareholders[0] = { name: '', document: '', role: '' };
                            return;
                        }
                        this.shareholders.splice(index, 1);
                    },
                    onlyDigits(value) {
                        return String(value || '').replace(/\D/g, '');
                    },
                    formatCpf(digits) {
                        return digits
                            .replace(/(\d{3})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    },
                    formatCnpj(digits) {
                        return digits
                            .replace(/(\d{2})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d)/, '$1/$2')
                            .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                    },
                    formatPhone(digits) {
                        if (digits.length <= 10) {
                            return digits
                                .replace(/(\d{2})(\d)/, '($1) $2')
                                .replace(/(\d{4})(\d)/, '$1-$2');
                        }

                        return digits
                            .replace(/(\d{2})(\d)/, '($1) $2')
                            .replace(/(\d{5})(\d)/, '$1-$2');
                    },
                    isValidCpf(digits) {
                        if (!/^\d{11}$/.test(digits) || /(\d)\1{10}/.test(digits)) return false;
                        let sum = 0;
                        for (let i = 0; i < 9; i += 1) sum += Number(digits[i]) * (10 - i);
                        let rest = (sum * 10) % 11;
                        if (rest === 10) rest = 0;
                        if (rest !== Number(digits[9])) return false;
                        sum = 0;
                        for (let i = 0; i < 10; i += 1) sum += Number(digits[i]) * (11 - i);
                        rest = (sum * 10) % 11;
                        if (rest === 10) rest = 0;
                        return rest === Number(digits[10]);
                    },
                    isValidCnpj(digits) {
                        if (!/^\d{14}$/.test(digits) || /(\d)\1{13}/.test(digits)) return false;
                        const calc = (base, factors) => {
                            const total = factors.reduce((sum, factor, index) => sum + Number(base[index]) * factor, 0);
                            const remainder = total % 11;
                            return remainder < 2 ? 0 : 11 - remainder;
                        };
                        const first = calc(digits, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
                        const second = calc(digits, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
                        return first === Number(digits[12]) && second === Number(digits[13]);
                    },
                    validateDocument() {
                        if (!this.$refs.document) return;
                        const digits = this.onlyDigits(this.$refs.document.value);
                        if (!digits) {
                            this.$refs.document.setCustomValidity('');
                            return;
                        }

                        if (this.isPf) {
                            this.$refs.document.setCustomValidity(digits.length === 11 && !this.isValidCpf(digits) ? 'Informe um CPF válido.' : '');
                            return;
                        }

                        this.$refs.document.setCustomValidity(digits.length === 14 && !this.isValidCnpj(digits) ? 'Informe um CNPJ válido.' : '');
                    },
                    maskDocument() {
                        if (!this.$refs.document) return;
                        let digits = this.onlyDigits(this.$refs.document.value);
                        if (this.isPf) {
                            digits = digits.slice(0, 11);
                            this.$refs.document.value = this.formatCpf(digits);
                        } else {
                            digits = digits.slice(0, 14);
                            this.$refs.document.value = this.formatCnpj(digits);
                        }
                        this.validateDocument();
                    },
                    maskPhone(index) {
                        if (!this.phones[index]) return;
                        const digits = this.onlyDigits(this.phones[index].number).slice(0, 11);
                        this.phones[index].number = this.formatPhone(digits);
                    },
                    maskFlexibleDocument(index) {
                        if (!this.shareholders[index]) return;
                        let digits = this.onlyDigits(this.shareholders[index].document);
                        digits = digits.slice(0, digits.length > 11 ? 14 : 11);
                        this.shareholders[index].document = digits.length > 11 ? this.formatCnpj(digits) : this.formatCpf(digits);
                    },
                }
            }

            function bindAttachmentRepeater(root) {
                if (!root || root.dataset.bound === '1') return;
                root.dataset.bound = '1';

                const container = root.querySelector('[data-attachment-repeater]');
                const template = root.querySelector('[data-attachment-template]');
                const addButton = root.querySelector('[data-attachment-add]');
                if (!container || !template || !addButton) return;

                const updateRemoveButtons = () => {
                    const groups = container.querySelectorAll('[data-attachment-group]');
                    groups.forEach((group, index) => {
                        const remove = group.querySelector('[data-attachment-remove]');
                        if (!remove) return;
                        remove.classList.toggle('hidden', index === 0 && groups.length === 1);
                    });
                };

                addButton.addEventListener('click', () => {
                    const index = container.querySelectorAll('[data-attachment-group]').length;
                    const html = template.innerHTML.replaceAll('__INDEX__', String(index));
                    container.insertAdjacentHTML('beforeend', html);
                    updateRemoveButtons();
                });

                root.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-attachment-remove]');
                    if (!removeButton) return;
                    const group = removeButton.closest('[data-attachment-group]');
                    if (group) {
                        group.remove();
                        updateRemoveButtons();
                    }
                });

                updateRemoveButtons();
            }

            document.addEventListener('change', (event) => {
                if (!event.target.matches('[data-file-input]')) return;
                const wrapper = event.target.closest('[data-file-preview]');
                const label = wrapper?.querySelector('[data-file-name]');
                if (!label) return;
                const files = Array.from(event.target.files || []);
                label.textContent = files.length ? files.map((file) => file.name).join(', ') : 'Nenhum arquivo selecionado';
            });

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-attachment-manager]').forEach((root) => bindAttachmentRepeater(root));
            });
        </script>
    @endpush
@endonce
