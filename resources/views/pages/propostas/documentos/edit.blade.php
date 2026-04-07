@extends('layouts.app')

@section('content')
@php
    $documentData = $document ? $document->toArray() : [];
    $optionsData = $options->count() ? $options->map(fn($item) => $item->toArray())->all() : [[
        'title' => '', 'scope_title' => '', 'scope_html' => '', 'fee_label' => '', 'amount_value' => '', 'amount_text' => '', 'payment_terms' => '', 'is_recommended' => 0,
    ]];
    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500';
@endphp

<x-ancora.section-header title="Documento Premium" subtitle="Monte a proposta visual completa com base no template oficial.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('propostas.show', $proposta) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
        <a href="{{ route('propostas.document.preview', $proposta) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-eye"></i> Preview</a>
        <a href="{{ route('propostas.document.pdf', $proposta) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-print"></i> PDF / Print</a>
    </div>
</x-ancora.section-header>

<form method="post" action="{{ route('propostas.document.save', $proposta) }}" class="space-y-6" x-data="premiumOptionsForm()">
    @csrf
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Identificação</h3>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Template</label>
                <select name="template_id" class="{{ $fieldClass }}" required>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}" @selected((string) old('template_id', $documentData['template_id'] ?? '') === (string) $template->id)>{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Título do documento</label><input name="document_title" value="{{ old('document_title', $documentData['document_title'] ?? 'Proposta de Honorários') }}" class="{{ $fieldClass }}"></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo da proposta</label><input name="proposal_kind" value="{{ old('proposal_kind', $documentData['proposal_kind'] ?? '') }}" class="{{ $fieldClass }}"></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente / condomínio</label><input name="client_display_name" value="{{ old('client_display_name', $documentData['client_display_name'] ?? $proposta->client_name) }}" class="{{ $fieldClass }}"></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">A/C</label><input name="attention_to" value="{{ old('attention_to', $documentData['attention_to'] ?? $proposta->requester_name) }}" class="{{ $fieldClass }}"></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Cargo / função</label><input name="attention_role" value="{{ old('attention_role', $documentData['attention_role'] ?? '') }}" class="{{ $fieldClass }}"></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subtítulo da capa</label><input name="cover_subtitle" value="{{ old('cover_subtitle', $documentData['cover_subtitle'] ?? '') }}" class="{{ $fieldClass }}"></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Imagem da capa</label><input name="cover_image_path" value="{{ old('cover_image_path', $documentData['cover_image_path'] ?? '') }}" placeholder="/assets/uploads/capas/minha-capa.jpg" class="{{ $fieldClass }}"></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Validade (dias)</label><input type="number" min="1" max="365" name="validity_days" value="{{ old('validity_days', $documentData['validity_days'] ?? 30) }}" class="{{ $fieldClass }}"></div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contexto e fechamento</h3>
        <div class="mt-5 grid grid-cols-1 gap-4">
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Introdução do escopo</label><textarea name="scope_intro" rows="4" class="{{ $textareaClass }}">{{ old('scope_intro', $documentData['scope_intro'] ?? '') }}</textarea></div>
            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Mensagem final</label><textarea name="closing_message" rows="4" class="{{ $textareaClass }}">{{ old('closing_message', $documentData['closing_message'] ?? '') }}</textarea></div>
            <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 dark:text-gray-300 md:grid-cols-2 xl:grid-cols-4">
                <label class="flex items-center gap-2"><input type="checkbox" name="show_institutional" value="1" @checked(old('show_institutional', $documentData['show_institutional'] ?? true))> Mostrar institucional</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="show_services" value="1" @checked(old('show_services', $documentData['show_services'] ?? true))> Mostrar serviços</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="show_extra_services" value="1" @checked(old('show_extra_services', $documentData['show_extra_services'] ?? true))> Mostrar páginas extras</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="show_contacts_page" value="1" @checked(old('show_contacts_page', $documentData['show_contacts_page'] ?? true))> Mostrar contatos</label>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Opções comerciais</h3>
            <button type="button" @click="addOption()" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white"><i class="fa-solid fa-plus"></i> Adicionar opção</button>
        </div>
        <div class="mt-5 space-y-4">
            <template x-for="(option, index) in options" :key="index">
                <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <strong class="text-sm text-gray-900 dark:text-white" x-text="`Opção comercial ${index + 1}`"></strong>
                        <button type="button" @click="removeOption(index)" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Título da opção</label><input :name="`options[${index}][title]`" x-model="option.title" class="{{ $fieldClass }}"></div>
                            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Título do escopo</label><input :name="`options[${index}][scope_title]`" x-model="option.scope_title" class="{{ $fieldClass }}"></div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Escopo <small>(máx. 360 caracteres)</small></label>
                            <textarea :name="`options[${index}][scope_html]`" x-model="option.scope_html" maxlength="360" rows="4" class="{{ $textareaClass }}"></textarea>
                            <p class="mt-1 text-xs text-gray-500"><span x-text="(option.scope_html || '').length"></span>/360 caracteres</p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Rótulo do honorário</label><input :name="`options[${index}][fee_label]`" x-model="option.fee_label" class="{{ $fieldClass }}"></div>
                            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor</label><input :name="`options[${index}][amount_value]`" x-model="option.amount_value" placeholder="0,00" class="{{ $fieldClass }}" data-money-mask-br inputmode="decimal"></div>
                            <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor por extenso</label><input :name="`options[${index}][amount_text]`" x-model="option.amount_text" class="{{ $fieldClass }}"></div>
                        </div>
                        <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de pagamento</label><textarea :name="`options[${index}][payment_terms]`" x-model="option.payment_terms" rows="3" class="{{ $textareaClass }}"></textarea></div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" :name="`options[${index}][is_recommended]`" value="1" x-model="option.is_recommended"> Marcar como opção recomendada</label>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white"><i class="fa-solid fa-floppy-disk"></i> Salvar Documento Premium</button>
        <a href="{{ route('propostas.document.preview', $proposta) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-eye"></i> Visualizar</a>
        <a href="{{ route('propostas.document.pdf', $proposta) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-file-pdf"></i> PDF / Print</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
function formatMoneyBr(input) {
    let digits = String(input.value || '').replace(/\D/g, '');
    if (!digits) {
        input.value = '';
        return;
    }
    digits = digits.padStart(3, '0');
    const cents = digits.slice(-2);
    let integer = digits.slice(0, -2).replace(/^0+(?=\d)/, '');
    integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.value = `${integer},${cents}`;
}

function premiumOptionsForm() {
    return {
        options: (@json(old('options', $optionsData)) || []).map(option => ({
            ...option,
            amount_value: option.amount_value ? String(option.amount_value).replace('.', ',') : ''
        })),
        addOption() {
            this.options.push({ title: '', scope_title: '', scope_html: '', fee_label: '', amount_value: '', amount_text: '', payment_terms: '', is_recommended: false });
        },
        removeOption(index) {
            if (this.options.length === 1) {
                this.options[0] = { title: '', scope_title: '', scope_html: '', fee_label: '', amount_value: '', amount_text: '', payment_terms: '', is_recommended: false };
                return;
            }
            this.options.splice(index, 1);
        }
    };
}

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-money-mask-br]')) {
        formatMoneyBr(event.target);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-money-mask-br]').forEach((input) => formatMoneyBr(input));
});
</script>
@endpush
