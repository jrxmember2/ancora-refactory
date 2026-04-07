@extends('layouts.app')

@section('content')
@php
    $documentData = $document ? $document->toArray() : [];
    $optionsData = $options->count() ? $options->map(fn($item) => $item->toArray())->all() : [[
        'title' => '', 'scope_title' => '', 'scope_html' => '', 'fee_label' => '', 'amount_value' => '', 'amount_text' => '', 'payment_terms' => '', 'is_recommended' => 0,
    ]];
    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-400 focus:ring-4 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-brand-500 dark:focus:ring-brand-500/20';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-400 focus:ring-4 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-brand-500 dark:focus:ring-brand-500/20';
    $labelClass = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200';
    $panelClass = 'rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
@endphp

<x-ancora.section-header title="Documento Premium" subtitle="Monte a proposta visual completa com base no template oficial.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('propostas.show', $proposta) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
        <a href="{{ route('propostas.document.preview', $proposta) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]"><i class="fa-solid fa-eye"></i> Preview</a>
        <a href="{{ route('propostas.document.pdf', $proposta) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]"><i class="fa-solid fa-print"></i> PDF / Print</a>
    </div>
</x-ancora.section-header>

<form method="post" action="{{ route('propostas.document.save', $proposta) }}" class="space-y-6" x-data="premiumOptionsForm()">
    @csrf
    <div class="{{ $panelClass }} text-gray-900 dark:text-gray-100">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Identificação</h3>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="{{ $labelClass }}">Template</label>
                <select name="template_id" class="{{ $fieldClass }}" required>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}" @selected((string) old('template_id', $documentData['template_id'] ?? '') === (string) $template->id)>{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="{{ $labelClass }}">Título do documento</label><input name="document_title" value="{{ old('document_title', $documentData['document_title'] ?? 'Proposta de Honorários') }}" class="{{ $fieldClass }}"></div>
            <div><label class="{{ $labelClass }}">Tipo da proposta</label><input name="proposal_kind" value="{{ old('proposal_kind', $documentData['proposal_kind'] ?? '') }}" class="{{ $fieldClass }}"></div>
            <div><label class="{{ $labelClass }}">Cliente / condomínio</label><input name="client_display_name" value="{{ old('client_display_name', $documentData['client_display_name'] ?? $proposta->client_name) }}" class="{{ $fieldClass }}"></div>
            <div><label class="{{ $labelClass }}">A/C</label><input name="attention_to" value="{{ old('attention_to', $documentData['attention_to'] ?? $proposta->requester_name) }}" class="{{ $fieldClass }}"></div>
            <div><label class="{{ $labelClass }}">Cargo / função</label><input name="attention_role" value="{{ old('attention_role', $documentData['attention_role'] ?? '') }}" class="{{ $fieldClass }}"></div>
            <div><label class="{{ $labelClass }}">Subtítulo da capa</label><input name="cover_subtitle" value="{{ old('cover_subtitle', $documentData['cover_subtitle'] ?? '') }}" class="{{ $fieldClass }}"></div>
            <div><label class="{{ $labelClass }}">Imagem da capa</label><input name="cover_image_path" value="{{ old('cover_image_path', $documentData['cover_image_path'] ?? '') }}" placeholder="/assets/uploads/capas/minha-capa.jpg" class="{{ $fieldClass }}"></div>
            <div>
                <label class="{{ $labelClass }}">Validade (dias)</label>
                <div class="relative">
                    <input type="number" name="validity_days" min="1" max="365" step="1" inputmode="numeric" value="{{ old('validity_days', $documentData['validity_days'] ?? 30) }}" class="{{ $fieldClass }} pr-16" placeholder="Ex.: 30">
                    <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-xs font-medium uppercase tracking-[0.2em] text-gray-400">dias</span>
                </div>
            </div>
        </div>
    </div>

    <div class="{{ $panelClass }} text-gray-900 dark:text-gray-100">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Escopo e fechamento</h3>
        <div class="mt-5 grid grid-cols-1 gap-4">
            <div><label class="{{ $labelClass }}">Introdução do escopo</label><textarea name="scope_intro" rows="4" class="{{ $textareaClass }}">{{ old('scope_intro', $documentData['scope_intro'] ?? '') }}</textarea></div>
            <div><label class="{{ $labelClass }}">Mensagem final</label><textarea name="closing_message" rows="4" class="{{ $textareaClass }}">{{ old('closing_message', $documentData['closing_message'] ?? '') }}</textarea></div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm text-gray-700 dark:text-gray-200">
                <label class="flex items-center gap-2"><input type="checkbox" name="show_institutional" value="1" @checked(old('show_institutional', $documentData['show_institutional'] ?? true))> Mostrar institucional</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="show_services" value="1" @checked(old('show_services', $documentData['show_services'] ?? true))> Mostrar serviços</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="show_extra_services" value="1" @checked(old('show_extra_services', $documentData['show_extra_services'] ?? true))> Mostrar páginas extras</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="show_contacts_page" value="1" @checked(old('show_contacts_page', $documentData['show_contacts_page'] ?? true))> Mostrar contatos</label>
            </div>
        </div>
    </div>

    <div class="{{ $panelClass }} text-gray-900 dark:text-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Opções comerciais</h3>
            <button type="button" @click="addOption()" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white"><i class="fa-solid fa-plus"></i> Adicionar opção</button>
        </div>
        <div class="mt-5 space-y-4">
            <template x-for="(option, index) in options" :key="index">
                <div class="rounded-2xl border border-gray-200 bg-gray-50/60 p-5 dark:border-gray-800 dark:bg-white/[0.02]">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <strong class="text-sm text-gray-900 dark:text-white" x-text="`Opção comercial ${index + 1}`"></strong>
                        <button type="button" @click="removeOption(index)" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 transition hover:bg-error-50 dark:border-error-800 dark:text-error-300 dark:hover:bg-error-950/30"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div><label class="{{ $labelClass }}">Título da opção</label><input :name="`options[${index}][title]`" x-model="option.title" class="{{ $fieldClass }}"></div>
                            <div><label class="{{ $labelClass }}">Título do escopo</label><input :name="`options[${index}][scope_title]`" x-model="option.scope_title" class="{{ $fieldClass }}"></div>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Escopo <small>(máx. 360 caracteres)</small></label>
                            <textarea :name="`options[${index}][scope_html]`" x-model="option.scope_html" maxlength="360" rows="4" class="{{ $textareaClass }}"></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span x-text="(option.scope_html || '').length"></span>/360 caracteres</p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <div><label class="{{ $labelClass }}">Rótulo do honorário</label><input :name="`options[${index}][fee_label]`" x-model="option.fee_label" class="{{ $fieldClass }}"></div>
                            <div><label class="{{ $labelClass }}">Valor</label><input :name="`options[${index}][amount_value]`" x-model="option.amount_value" placeholder="R$ 0,00" class="{{ $fieldClass }}" data-money-mask inputmode="decimal"></div>
                            <div><label class="{{ $labelClass }}">Valor por extenso</label><input :name="`options[${index}][amount_text]`" x-model="option.amount_text" class="{{ $fieldClass }}"></div>
                        </div>
                        <div><label class="{{ $labelClass }}">Forma de pagamento</label><textarea :name="`options[${index}][payment_terms]`" x-model="option.payment_terms" rows="3" class="{{ $textareaClass }}"></textarea></div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" :name="`options[${index}][is_recommended]`" value="1" x-model="option.is_recommended"> Marcar como opção recomendada</label>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white"><i class="fa-solid fa-floppy-disk"></i> Salvar Documento Premium</button>
        <a href="{{ route('propostas.document.preview', $proposta) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]"><i class="fa-solid fa-eye"></i> Visualizar</a>
        <a href="{{ route('propostas.document.pdf', $proposta) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]"><i class="fa-solid fa-file-pdf"></i> PDF / Print</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
function applyMoneyMask(input) {
    const digits = String(input.value || '').replace(/\D/g, '');
    if (!digits) {
        input.value = '';
        return;
    }

    const value = (Number(digits) / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    input.value = `R$ ${value}`;
}

function premiumOptionsForm() {
    return {
        options: @json(old('options', $optionsData)),
        addOption() {
            this.options.push({ title: '', scope_title: '', scope_html: '', fee_label: '', amount_value: '', amount_text: '', payment_terms: '', is_recommended: false });
            queueMicrotask(() => {
                document.querySelectorAll('[data-money-mask]').forEach(applyMoneyMask);
            });
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
    if (event.target.matches('[data-money-mask]')) {
        applyMoneyMask(event.target);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-money-mask]').forEach(applyMoneyMask);
});
</script>
@endpush
