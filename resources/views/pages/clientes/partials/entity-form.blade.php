@php
    $entity = $item;
    $phonesText = collect($entity?->phones_json ?? [])->map(fn($row) => trim(($row['label'] ?? '').'|'.($row['number'] ?? '')))->implode(PHP_EOL);
    $emailsText = collect($entity?->emails_json ?? [])->map(fn($row) => trim(($row['label'] ?? '').'|'.($row['email'] ?? '')))->implode(PHP_EOL);
    $shareholdersText = collect($entity?->shareholders_json ?? [])->map(fn($row) => trim(($row['name'] ?? '').'|'.($row['document'] ?? '').'|'.($row['role'] ?? '')))->implode(PHP_EOL);
    $primary = $entity?->primary_address_json ?? [];
    $billing = $entity?->billing_address_json ?? [];
    $selectedEntityType = old('entity_type', $entity?->entity_type ?? 'pf');
    $selectedInactive = old('is_inactive', ($entity && !$entity->is_active) ? 1 : 0);
    $maritalOptions = ['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Separado(a)', 'Viúvo(a)', 'União estável'];
@endphp

<div
    class="grid grid-cols-1 gap-6 xl:grid-cols-3"
    x-data="entityClientForm({ entityType: '{{ $selectedEntityType }}', inactive: {{ $selectedInactive ? 'true' : 'false' }} })"
>
    <div class="space-y-6 xl:col-span-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Tipo</label>
                    <select name="entity_type" x-model="entityType" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                        <option value="pf">Pessoa física</option>
                        <option value="pj">Pessoa jurídica</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Perfil / papel</label>
                    <select name="role_tag" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
                        <option value="">Selecione</option>
                        @foreach(($entityRoles ?? collect()) as $role)
                            <option value="{{ $role->name }}" @selected(old('role_tag', $entity?->role_tag ?? $roleTag) === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Cadastre novos perfis/papéis em Configurações de clientes.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Nome principal</label>
                    <input name="display_name" value="{{ old('display_name', $entity?->display_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
                </div>
                <div x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium">Nome jurídico / razão social</label>
                    <input name="legal_name" value="{{ old('legal_name', $entity?->legal_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPf">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">CPF / CNPJ</label>
                    <input name="cpf_cnpj" value="{{ old('cpf_cnpj', $entity?->cpf_cnpj) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" x-ref="document" @input="maskDocument()" placeholder="CPF ou CNPJ">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium">RG / IE</label>
                    <input name="rg_ie" value="{{ old('rg_ie', $entity?->rg_ie) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium">Profissão</label>
                    <input name="profession" value="{{ old('profession', $entity?->profession) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium">Estado civil</label>
                    <select name="marital_status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                        <option value="">Selecione</option>
                        @foreach($maritalOptions as $status)
                            <option value="{{ $status }}" @selected(old('marital_status', $entity?->marital_status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-bind:class="isPj ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium">Data de nascimento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $entity?->birth_date?->format('Y-m-d') ?? $entity?->birth_date) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPj">
                </div>
                <div x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium">Representante legal</label>
                    <input name="legal_representative" value="{{ old('legal_representative', $entity?->legal_representative) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="isPf">
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contatos e observações</h3>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Telefones</label>
                    <textarea name="phones_text" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="Principal|27999999999&#10;Financeiro|2733334444">{{ old('phones_text', $phonesText) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Um por linha, no formato rótulo|número.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">E-mails</label>
                    <textarea name="emails_text" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="Principal|email@dominio.com">{{ old('emails_text', $emailsText) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Um por linha, no formato rótulo|email.</p>
                </div>
                <div class="md:col-span-2" x-bind:class="isPf ? 'opacity-60' : ''">
                    <label class="mb-1.5 block text-sm font-medium">Sócios / acionistas</label>
                    <textarea name="shareholders_text" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="Nome do sócio|CPF/CNPJ|Função" :disabled="isPf">{{ old('shareholders_text', $shareholdersText) }}</textarea>
                </div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-medium">Notas</label><textarea name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('notes', $entity?->notes) }}</textarea></div>
                <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-medium">Descrição / observações livres</label><textarea name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('description', $entity?->description) }}</textarea></div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @include('pages.clientes.partials.address-fields', [
                'prefix' => 'primary_address',
                'address' => $primary,
                'title' => 'Endereço principal',
            ])

            @include('pages.clientes.partials.address-fields', [
                'prefix' => 'billing_address',
                'address' => $billing,
                'title' => 'Endereço de cobrança',
            ])
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Status</h3>
            <div class="mt-4 space-y-4">
                <label class="flex items-center gap-3 text-sm font-medium"><input type="checkbox" name="is_inactive" value="1" x-model="inactive"> Inativo</label>
                <div x-bind:class="inactive ? '' : 'opacity-60'">
                    <label class="mb-1.5 block text-sm font-medium">Motivo da inativação</label>
                    <input name="inactive_reason" value="{{ old('inactive_reason', $entity?->inactive_reason) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!inactive">
                </div>
                <div x-bind:class="inactive ? '' : 'opacity-60'">
                    <label class="mb-1.5 block text-sm font-medium">Fim do contrato</label>
                    <input type="date" name="contract_end_date" value="{{ old('contract_end_date', $entity?->contract_end_date?->format('Y-m-d') ?? $entity?->contract_end_date) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!inactive">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Anexos</label>
                    <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                        <i class="fa-solid fa-paperclip"></i>
                        <span>Escolher arquivos para anexar</span>
                        <input type="file" name="attachments[]" multiple class="sr-only">
                    </label>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Papel dos anexos</label>
                    <select name="attachment_role" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="documento">Documento</option><option value="contrato">Contrato</option><option value="outro">Outro</option></select>
                </div>
            </div>
        </div>
        @if(isset($attachments) && $attachments->count())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Anexos</h3>
                <div class="mt-4 space-y-3">
                    @foreach($attachments as $attachment)
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                            <div class="text-sm font-medium">{{ $attachment->original_name }}</div>
                            <div class="mt-2 flex gap-2">
                                <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs text-white">Baixar</a>
                                <form method="post" action="{{ route('clientes.attachments.delete', $attachment) }}">@csrf @method('DELETE')<button class="rounded-lg border border-error-300 px-3 py-2 text-xs text-error-600">Excluir</button></form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        @if(isset($timeline) && $timeline->count())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Timeline</h3>
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
                    get isPf() { return this.entityType === 'pf'; },
                    get isPj() { return this.entityType === 'pj'; },
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
        </script>
    @endpush
@endonce
