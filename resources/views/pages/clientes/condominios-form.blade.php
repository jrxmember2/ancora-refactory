@extends('layouts.app')

@section('content')
@php
    $item = $item ?? $condominio ?? null;
    $address = $item?->address_json ?? [];
    $selectedInactive = old('is_inactive', ($item && !$item->is_active) ? 1 : 0);
    $blocksText = old('blocks_text', isset($blocksText) ? $blocksText : '');
    $attachments = $attachments ?? collect();
    $condominioRouteParam = $item?->getRouteKey()
        ?? request()->route('condominio')?->getRouteKey()
        ?? request()->route('condominio');

    $groupedAttachments = [
        'convention' => $attachments->filter(fn ($attachment) => str_starts_with($attachment->original_name, 'Convenção condominial -')),
        'regiment' => $attachments->filter(fn ($attachment) => str_starts_with($attachment->original_name, 'Regimento interno -')),
        'atas' => $attachments->filter(fn ($attachment) => str_starts_with($attachment->original_name, 'ATA -')),
        'others' => $attachments->reject(fn ($attachment) =>
            str_starts_with($attachment->original_name, 'Convenção condominial -')
            || str_starts_with($attachment->original_name, 'Regimento interno -')
            || str_starts_with($attachment->original_name, 'ATA -')
        ),
    ];

    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
    $selectClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
@endphp

<x-ancora.section-header
    :title="$mode === 'create' ? 'Novo condomínio' : 'Editar condomínio'"
    subtitle="Cadastro da área condominial com tipo, endereço, síndico, documentos e anexos."
/>

<form
    id="condominio-form"
    method="post"
    action="{{ $mode === 'create' ? route('clientes.condominios.store') : route('clientes.condominios.update', ['condominio' => $condominioRouteParam]) }}"
    enctype="multipart/form-data"
    class="space-y-6"
    x-data="condominiumForm({ inactive: {{ $selectedInactive ? 'true' : 'false' }} })"
    x-init="init()"
>
    @csrf
    @if($mode === 'edit')
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome do condomínio</label>
                        <input name="name" value="{{ old('name', $item?->name) }}" class="{{ $fieldClass }}" placeholder="Nome do condomínio" required>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                        <select name="condominium_type_id" class="{{ $selectClass }}">
                            <option value="">Selecione</option>
                            @foreach($condominiumTypes as $type)
                                <option value="{{ $type->id }}" @selected((string) old('condominium_type_id', $item?->condominium_type_id) === (string) $type->id)>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CNPJ</label>
                        <input
                            name="cnpj"
                            value="{{ old('cnpj', $item?->cnpj) }}"
                            class="{{ $fieldClass }}"
                            placeholder="00.000.000/0000-00"
                            inputmode="numeric"
                            maxlength="18"
                            x-ref="cnpj"
                            @input="maskCnpj()"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Síndico vinculado</label>
                        <select name="syndico_entity_id" class="{{ $selectClass }}" required>
                            <option value="">Selecione</option>
                            @foreach($syndics as $syndic)
                                <option value="{{ $syndic->id }}" @selected((string) old('syndico_entity_id', $item?->syndico_entity_id) === (string) $syndic->id)>
                                    {{ $syndic->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Administradora</label>
                        <select name="administradora_entity_id" class="{{ $selectClass }}">
                            <option value="">Selecione</option>
                            @foreach($administradorasList as $admin)
                                <option value="{{ $admin->id }}" @selected((string) old('administradora_entity_id', $item?->administradora_entity_id) === (string) $admin->id)>
                                    {{ $admin->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="has_blocks" value="1" @checked(old('has_blocks', $item?->has_blocks))>
                        Possui blocos / torres
                    </label>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Blocos / torres</label>
                        <textarea name="blocks_text" rows="5" class="{{ $textareaClass }}" placeholder="Um bloco por linha">{{ $blocksText }}</textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Caso o condomínio tenha múltiplos blocos, informe um por linha.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor do boleto</label>
                        <input
                            name="boleto_fee_amount"
                            value="{{ old('boleto_fee_amount', isset($item?->boleto_fee_amount) ? number_format((float) $item->boleto_fee_amount, 2, ',', '.') : '') }}"
                            class="{{ $fieldClass }}"
                            placeholder="R$ 0,00"
                            inputmode="decimal"
                            x-ref="boletoFeeAmount"
                            @input="maskMoney('boletoFeeAmount')"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor de cancelamento de boleto</label>
                        <input
                            name="boleto_cancellation_fee_amount"
                            value="{{ old('boleto_cancellation_fee_amount', isset($item?->boleto_cancellation_fee_amount) ? number_format((float) $item->boleto_cancellation_fee_amount, 2, ',', '.') : '') }}"
                            class="{{ $fieldClass }}"
                            placeholder="R$ 0,00"
                            inputmode="decimal"
                            x-ref="boletoCancellationFeeAmount"
                            @input="maskMoney('boletoCancellationFeeAmount')"
                        >
                    </div>
                </div>
            </div>

            @include('pages.clientes.partials.address-fields', [
                'prefix' => 'address',
                'address' => $address,
                'title' => 'Endereço',
            ])

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Documentos</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Suba a convenção, o regimento interno e quantas ATAs forem necessárias.
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Limite recomendado: até 20 MB por arquivo e 72 MB por envio.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div data-file-preview>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Convenção condominial</label>
                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            <span>Selecionar arquivo</span>
                            <input type="file" name="document_convention" class="sr-only" data-file-input>
                        </label>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>
                            {{ $groupedAttachments['convention']->pluck('original_name')->implode(', ') ?: 'Nenhum arquivo selecionado' }}
                        </div>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">PDF, imagem ou Word até 20 MB.</p>

                        @if($groupedAttachments['convention']->count())
                            <div class="mt-3 space-y-2">
                                @foreach($groupedAttachments['convention'] as $attachment)
                                    <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $attachment->original_name }}</div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                            <button type="submit" form="attachment-delete-{{ $attachment->id }}" onclick="return confirm('Excluir este anexo?')" class="rounded-md border border-error-300 px-2 py-1 text-error-600 dark:text-error-300">Excluir</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div data-file-preview>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Regimento interno</label>
                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            <span>Selecionar arquivo</span>
                            <input type="file" name="document_regiment" class="sr-only" data-file-input>
                        </label>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>
                            {{ $groupedAttachments['regiment']->pluck('original_name')->implode(', ') ?: 'Nenhum arquivo selecionado' }}
                        </div>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">PDF, imagem ou Word até 20 MB.</p>

                        @if($groupedAttachments['regiment']->count())
                            <div class="mt-3 space-y-2">
                                @foreach($groupedAttachments['regiment'] as $attachment)
                                    <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $attachment->original_name }}</div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                            <button type="submit" form="attachment-delete-{{ $attachment->id }}" onclick="return confirm('Excluir este anexo?')" class="rounded-md border border-error-300 px-2 py-1 text-error-600 dark:text-error-300">Excluir</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div data-file-preview>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">ATAs</label>
                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            <span>Selecionar um ou mais arquivos</span>
                            <input type="file" name="document_atas[]" multiple class="sr-only" data-file-input data-multiple>
                        </label>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>
                            {{ $groupedAttachments['atas']->pluck('original_name')->implode(', ') ?: 'Nenhum arquivo selecionado' }}
                        </div>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">PDF, imagem ou Word até 20 MB por arquivo.</p>

                        @if($groupedAttachments['atas']->count())
                            <div class="mt-3 space-y-2">
                                @foreach($groupedAttachments['atas'] as $attachment)
                                    <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $attachment->original_name }}</div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                            <button type="submit" form="attachment-delete-{{ $attachment->id }}" onclick="return confirm('Excluir este anexo?')" class="rounded-md border border-error-300 px-2 py-1 text-error-600 dark:text-error-300">Excluir</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
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
                        <input name="inactive_reason" value="{{ old('inactive_reason', $item?->inactive_reason) }}" class="{{ $fieldClass }}" placeholder="Motivo" :disabled="!inactive">
                    </div>

                    <div x-bind:class="inactive ? '' : 'opacity-60'">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Fim do contrato</label>
                        <input type="date" name="contract_end_date" value="{{ old('contract_end_date', $item?->contract_end_date?->format('Y-m-d') ?? $item?->contract_end_date) }}" class="{{ $fieldClass }}" :disabled="!inactive">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos adicionais</h3>

                <div class="mt-4 space-y-4" data-file-preview>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel dos anexos</label>
                        <select name="attachment_role" class="{{ $selectClass }}">
                            <option value="documento">Documento</option>
                            <option value="contrato">Contrato</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>

                    <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                        <i class="fa-solid fa-paperclip"></i>
                        <span>Escolher arquivos para anexar</span>
                        <input type="file" name="attachments[]" multiple class="sr-only" data-file-input data-multiple>
                    </label>

                    <div class="text-xs text-gray-500 dark:text-gray-400" data-file-name>
                        {{ $groupedAttachments['others']->pluck('original_name')->implode(', ') ?: 'Nenhum anexo adicional' }}
                    </div>

                    @if($groupedAttachments['others']->count())
                        <div class="space-y-2">
                            @foreach($groupedAttachments['others'] as $attachment)
                                <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                    <div class="font-medium text-gray-800 dark:text-gray-100">{{ $attachment->original_name }}</div>
                                    <div class="mt-2 flex gap-2">
                                        <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                        <button type="submit" form="attachment-delete-{{ $attachment->id }}" onclick="return confirm('Excluir este anexo?')" class="rounded-md border border-error-300 px-2 py-1 text-error-600 dark:text-error-300">Excluir</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if($mode === 'edit' && isset($relatedContracts))
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contratos relacionados</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $relatedContracts->count() }} contrato(s)</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($relatedContracts as $contract)
                            <a href="{{ route('contratos.show', $contract) }}" class="block rounded-xl border border-gray-200 p-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $contract->code ?: ('Contrato #' . $contract->id) }}</div>
                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $contract->title }}</div>
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $contract->type }}
                                            @if($contract->client?->display_name)
                                                · {{ $contract->client->display_name }}
                                            @endif
                                            @if($contract->unit?->unit_number)
                                                · {{ $contract->unit->block?->name ? $contract->unit->block->name.' · ' : '' }}Unidade {{ $contract->unit->unit_number }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                                        <div>{{ \App\Support\Contracts\ContractCatalog::statuses()[$contract->status] ?? $contract->status }}</div>
                                        <div class="mt-1">R$ {{ number_format((float) ($contract->contract_value ?? $contract->monthly_value ?? $contract->total_value ?? 0), 2, ',', '.') }}</div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nao ha contratos vinculados a este condominio ate o momento.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            @if($attachments->count())
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo de documentos cadastrados</h3>
                    <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Total anexado: {{ $attachments->count() }} arquivo(s)
                    </div>
                </div>
            @endif
        </div>
    </div>
</form>

@foreach($attachments as $attachment)
    <form id="attachment-delete-{{ $attachment->id }}" method="post" action="{{ route('clientes.attachments.delete', $attachment) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endforeach

<div class="mt-3 flex flex-wrap gap-3">
    <button type="submit" form="condominio-form" class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">
        {{ $mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}
    </button>

    @if($mode === 'edit')
        <form method="post" action="{{ route('clientes.condominios.delete', $item) }}">
            @csrf
            @method('DELETE')
            <button onclick="return confirm('Excluir este condomínio?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600 dark:text-error-300">
                Excluir
            </button>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
function condominiumForm(initialState) {
    return {
        inactive: !!initialState.inactive,
        init() {
            this.maskCnpj();
            this.maskMoney('boletoFeeAmount');
            this.maskMoney('boletoCancellationFeeAmount');
        },
        onlyDigits(value) {
            return String(value || '').replace(/\D/g, '');
        },
        formatMoneyValue(value) {
            const digits = this.onlyDigits(value);
            if (!digits) return '';

            const amount = Number(digits) / 100;
            return `R$ ${amount.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },
        maskMoney(refName) {
            const input = this.$refs[refName];
            if (!input) return;
            input.value = this.formatMoneyValue(input.value);
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
        maskCnpj() {
            if (!this.$refs.cnpj) return;
            let digits = this.onlyDigits(this.$refs.cnpj.value).slice(0, 14);
            digits = digits
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
            this.$refs.cnpj.value = digits;

            const raw = this.onlyDigits(digits);
            this.$refs.cnpj.setCustomValidity(raw.length === 14 && !this.isValidCnpj(raw) ? 'Informe um CNPJ válido.' : '');
        },
    }
}

document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-file-input]')) return;

    const wrapper = event.target.closest('[data-file-preview]');
    const label = wrapper?.querySelector('[data-file-name]');
    if (!label) return;

    const files = Array.from(event.target.files || []);
    label.textContent = files.length ? files.map((file) => file.name).join(', ') : 'Nenhum arquivo selecionado';
});
</script>
@endpush
