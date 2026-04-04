@php
    $entity = $item;
    $phonesText = collect($entity?->phones_json ?? [])->map(fn($row) => trim(($row['label'] ?? '').'|'.($row['number'] ?? '')))->implode(PHP_EOL);
    $emailsText = collect($entity?->emails_json ?? [])->map(fn($row) => trim(($row['label'] ?? '').'|'.($row['email'] ?? '')))->implode(PHP_EOL);
    $shareholdersText = collect($entity?->shareholders_json ?? [])->map(fn($row) => trim(($row['name'] ?? '').'|'.($row['document'] ?? '').'|'.($row['role'] ?? '')))->implode(PHP_EOL);
    $primary = $entity?->primary_address_json ?? [];
    $billing = $entity?->billing_address_json ?? [];
    $selectedEntityType = old('entity_type', $entity?->entity_type ?? 'pf');
    $selectedInactive = old('is_inactive', ($entity && !$entity->is_active) ? 1 : 0);
    $addressesMatch = !empty($primary) && $primary === $billing;
    $selectedSameBilling = old('billing_same_as_primary', $addressesMatch ? 1 : 0);
    $maritalOptions = ['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Separado(a)', 'Viúvo(a)', 'União estável'];
    $attachmentRoleLabels = ['documento' => 'Documentos', 'contrato' => 'Contratos', 'outro' => 'Outros'];
    $groupedAttachments = collect($attachments ?? [])->groupBy(fn ($attachment) => $attachment->file_role ?: 'documento');
@endphp

<div
    class="grid grid-cols-1 gap-6 xl:grid-cols-3"
    x-data="entityClientForm({ entityType: '{{ $selectedEntityType }}', inactive: {{ $selectedInactive ? 'true' : 'false' }}, sameBilling: {{ $selectedSameBilling ? 'true' : 'false' }} })"
    x-init="init()"
>
    <div class="space-y-6 xl:col-span-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Tipo</label>
                    <select name="entity_type" x-model="entityType" @change="maskDocument()" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                        <option value="pf">Pessoa física</option>
                        <option value="pj">Pessoa jurídica</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Perfil / papel</label>
                    <select name="role_tag" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
                        <option value="">Selecione</option>
                        @foreach(($entityRoles ?? collect()) as $role)
                            <option value="{{ $role->name }}" @selected(old('role_tag', $entity?->role_tag ?? $roleTag) === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Cadastre novos perfis/papéis em Configurações de clientes.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Nome principal</label>
                    <input name="display_name" value="{{ old('display_name', $entity?->display_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
                </div>
                <div x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Nome jurídico / razão social</label>
                    <input name="legal_name" value="{{ old('legal_name', $entity?->legal_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPf">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">CPF / CNPJ</label>
                    <input name="cpf_cnpj" value="{{ old('cpf_cnpj', $entity?->cpf_cnpj) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" x-ref="document" @input="maskDocument()" placeholder="CPF ou CNPJ" inputmode="numeric">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">RG / IE</label>
                    <input name="rg_ie" value="{{ old('rg_ie', $entity?->rg_ie) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Profissão</label>
                    <input name="profession" value="{{ old('profession', $entity?->profession) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Estado civil</label>
                    <select name="marital_status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                        <option value="">Selecione</option>
                        @foreach($maritalOptions as $status)
                            <option value="{{ $status }}" @selected(old('marital_status', $entity?->marital_status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Data de nascimento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $entity?->birth_date?->format('Y-m-d') ?? $entity?->birth_date) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                </div>
                <div x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Representante legal</label>
                    <input name="legal_representative" value="{{ old('legal_representative', $entity?->legal_representative) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPf">
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contatos e observações</h3>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Telefones</label>
                    <textarea name="phones_text" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="Principal|27999999999&#10;Financeiro|2733334444">{{ old('phones_text', $phonesText) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Um por linha, no formato rótulo|número.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">E-mails</label>
                    <textarea name="emails_text" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="Principal|email@dominio.com">{{ old('emails_text', $emailsText) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Um por linha, no formato rótulo|email.</p>
                </div>
                <div class="md:col-span-2" x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Sócios / acionistas</label>
                    <textarea name="shareholders_text" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="Nome do sócio|CPF/CNPJ|Função" :disabled="isPf">{{ old('shareholders_text', $shareholdersText) }}</textarea>
                </div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Notas</label><textarea name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('notes', $entity?->notes) }}</textarea></div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Descrição / observações livres</label><textarea name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('description', $entity?->description) }}</textarea></div>
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
                <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-200"><input type="checkbox" name="is_inactive" value="1" x-model="inactive"> Inativo</label>
                <div x-bind:class="inactive ? '' : 'opacity-60'">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Motivo da inativação</label>
                    <input name="inactive_reason" value="{{ old('inactive_reason', $entity?->inactive_reason) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!inactive">
                </div>
                <div x-bind:class="inactive ? '' : 'opacity-60'">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Fim do contrato</label>
                    <input type="date" name="contract_end_date" value="{{ old('contract_end_date', $entity?->contract_end_date?->format('Y-m-d') ?? $entity?->contract_end_date) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!inactive">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Anexos</label>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Adicione um ou mais arquivos e escolha a categoria de cada anexo.</p>
                    @include('pages.clientes.partials.attachment-uploader')
                </div>
            </div>
        </div>
        @if(isset($attachments) && $attachments->count())
            <div id="anexos" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos</h3>
                <div class="mt-4 space-y-4">
                    @foreach($groupedAttachments as $role => $roleAttachments)
                        <div>
                            <div class="mb-3 inline-flex items-center rounded-full border border-gray-200 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $attachmentRoleLabels[$role] ?? ucfirst($role) }}</div>
                            <div class="space-y-3">
                                @foreach($roleAttachments as $attachment)
                                    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-medium">{{ $attachment->original_name }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Categoria: {{ $attachmentRoleLabels[$attachment->file_role] ?? ucfirst($attachment->file_role ?? 'documento') }}</div>
                                            </div>
                                        </div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs text-white">Baixar</a>
                                            <button type="button" class="js-client-attachment-delete rounded-lg border border-error-300 px-3 py-2 text-xs text-error-600" data-delete-url="{{ route('clientes.attachments.delete', $attachment) }}" data-attachment-name="{{ $attachment->original_name }}">Excluir</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        @if(isset($timeline) && $timeline->count())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Timeline</h3>
                <div class="mt-4 space-y-3">
                    @foreach($timeline as $event)
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                            <div class="text-sm">{{ $event->note }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ optional($event->created_at)->format('d/m/Y H:i') }} · {{ $event->user_email }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
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
                    get isPf() { return this.entityType === 'pf'; },
                    get isPj() { return this.entityType === 'pj'; },
                    init() { this.maskDocument(); },
                    maskDocument() {
                        if (!this.$refs.document) return;
                        let digits = this.$refs.document.value.replace(/\D/g, '');
                        if (this.isPf) {
                            digits = digits.slice(0, 11);
                            digits = digits
                                .replace(/(\d{3})(\d)/, '$1.$2')
                                .replace(/(\d{3})(\d)/, '$1.$2')
                                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                        } else {
                            digits = digits.slice(0, 14);
                            digits = digits
                                .replace(/(\d{2})(\d)/, '$1.$2')
                                .replace(/(\d{3})(\d)/, '$1.$2')
                                .replace(/(\d{3})(\d)/, '$1/$2')
                                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                        }
                        this.$refs.document.value = digits;
                    },
                }
            }

            function ancoraAttachmentRepeater(initialRoles = ['documento']) {
                const roles = Array.isArray(initialRoles) && initialRoles.length ? initialRoles : ['documento'];
                return {
                    rows: roles.map((role, index) => ({ id: index + 1, role: role || 'documento', fileName: '' })),
                    nextId: roles.length + 1,
                    addRow(role = 'documento') {
                        this.rows.push({ id: this.nextId++, role, fileName: '' });
                    },
                    removeRow(index) {
                        if (this.rows.length === 1) {
                            this.rows[0].fileName = '';
                            return;
                        }
                        this.rows.splice(index, 1);
                    },
                    updateFile(index, event) {
                        const files = Array.from(event.target.files || []);
                        this.rows[index].fileName = files.map((file) => file.name).join(', ');
                    },
                }
            }

            function deleteClientAttachment(button) {
                const url = button.dataset.deleteUrl;
                const name = button.dataset.attachmentName || 'este anexo';
                if (!url || !confirm(`Excluir ${name}?`)) {
                    return;
                }

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.innerHTML = `
                    <input type="hidden" name="_token" value="${document.querySelector("meta[name='csrf-token']")?.content || ''}">
                    <input type="hidden" name="_method" value="DELETE">
                `;
                document.body.appendChild(form);
                form.submit();
            }

            document.addEventListener('click', (event) => {
                const button = event.target.closest('.js-client-attachment-delete');
                if (!button) return;
                deleteClientAttachment(button);
            });
        </script>
    @endpush
@endonce
