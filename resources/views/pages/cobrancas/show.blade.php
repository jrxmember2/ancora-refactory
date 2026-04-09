@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="'OS '.$case->os_number" subtitle="Acompanhe o histórico completo da cobrança, GED, quotas, parcelas e payload operacional.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.edit', $case) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Editar</a>
        <button type="button" disabled aria-disabled="true" title="Em breve será possível gerar o termo automaticamente por aqui." class="rounded-xl border border-brand-200 bg-brand-50/70 px-4 py-3 text-sm font-medium text-brand-400 opacity-70 dark:border-brand-900/60 dark:bg-brand-500/10 dark:text-brand-300/70">Gerar termo de acordo</button>
        <a href="{{ route('cobrancas.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova OS</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

@if($case->alert_message)
    <div class="mb-6 rounded-2xl border border-warning-300 bg-warning-50 px-5 py-4 text-sm text-warning-800 dark:border-warning-700/60 dark:bg-warning-500/10 dark:text-warning-200">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <div>
                <div class="font-semibold">Alerta da OS</div>
                <div class="mt-1">{{ $case->alert_message }}</div>
            </div>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 gap-6 xl:grid-cols-4">
    <x-ancora.stat-card label="Etapa" :value="$stageLabels[$case->workflow_stage] ?? $case->workflow_stage" :hint="'Último andamento: ' . (optional($case->last_progress_at)->format('d/m/Y H:i') ?: '—')" icon="fa-solid fa-shoe-prints" />
    <x-ancora.stat-card label="Situação" :value="$situationLabels[$case->situation] ?? $case->situation" :hint="$billingLabels[$case->billing_status] ?? $case->billing_status" icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Valor do acordo" :value="$case->agreement_total ? 'R$ '.number_format((float) $case->agreement_total, 2, ',', '.') : 'Não definido'" :hint="'Honorários: R$ '.number_format((float) ($case->fees_amount ?? 0), 2, ',', '.')" icon="fa-solid fa-money-bill-wave" />
    <x-ancora.stat-card label="Entrada" :value="$case->entry_amount ? 'R$ '.number_format((float) $case->entry_amount, 2, ',', '.') : 'Não definida'" :hint="$entryStatusLabels[$case->entry_status] ?? ($case->entry_status ?: 'Sem status')" icon="fa-solid fa-receipt" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr,1fr]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Condomínio / unidade</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $case->condominium?->name ?? 'Condomínio não vinculado' }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $case->block?->name ? $case->block->name.' · ' : '' }}Unidade {{ $case->unit?->unit_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Condômino / devedor</div>
                    <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $case->debtor_name_snapshot }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $case->debtor_document_snapshot ?: 'Documento não informado' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Tipo de cobrança</div>
                    <div class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $case->charge_type === 'judicial' ? 'Cobrança judicial' : 'Cobrança extrajudicial' }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Base do cálculo: {{ optional($case->calc_base_date)->format('d/m/Y') ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Processo judicial</div>
                    <div class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $case->judicial_case_number ?: 'Ainda não vinculado' }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Faturamento: {{ $billingLabels[$case->billing_status] ?? $case->billing_status }}</div>
                </div>
            </div>

            @if($case->notes)
                <div class="mt-5 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    {!! nl2br(e($case->notes)) !!}
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contatos</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $case->contacts->count() }} registro(s)</span>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @forelse($case->contacts as $contact)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ $contact->contact_type === 'email' ? 'E-mail' : 'Telefone' }}</div>
                        <div class="mt-2 font-medium text-gray-900 dark:text-white">{{ $contact->value }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $contact->label ?: 'Sem rótulo' }}{{ $contact->is_whatsapp ? ' · WhatsApp' : '' }}</div>
                    </div>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-address-book" title="Sem contatos" subtitle="Cadastre e-mails e telefones na edição da OS." />
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Quotas em aberto</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs uppercase tracking-[0.16em] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-3 pr-4">Referência</th>
                            <th class="py-3 pr-4">Vencimento</th>
                            <th class="py-3 pr-4">Valor original</th>
                            <th class="py-3 pr-4">Valor atualizado</th>
                            <th class="py-3 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($case->quotas as $quota)
                            <tr>
                                <td class="py-3 pr-4 text-gray-900 dark:text-white">{{ $quota->reference_label ?: '—' }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ optional($quota->due_date)->format('d/m/Y') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $quota->original_amount, 2, ',', '.') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) ($quota->updated_amount ?? $quota->original_amount), 2, ',', '.') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ ucfirst(str_replace('_', ' ', $quota->status)) }}</td>
                            </tr>
                            @if($quota->notes)
                                <tr>
                                    <td colspan="5" class="pb-4 text-xs text-gray-500 dark:text-gray-400">{{ $quota->notes }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="py-6"><x-ancora.empty-state icon="fa-solid fa-list-check" title="Sem quotas" subtitle="Ainda não há quotas registradas nesta OS." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Parcelas / vencimentos</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $case->installments->count() }} registro(s)</span>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs uppercase tracking-[0.16em] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-3 pr-4">Descrição</th>
                            <th class="py-3 pr-4">Tipo</th>
                            <th class="py-3 pr-4">Vencimento</th>
                            <th class="py-3 pr-4">Valor</th>
                            <th class="py-3 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($case->installments as $parcel)
                            <tr>
                                <td class="py-3 pr-4 text-gray-900 dark:text-white">{{ $parcel->label ?: 'Parcela '.$parcel->installment_number }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ ucfirst($parcel->installment_type) }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ optional($parcel->due_date)->format('d/m/Y') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $parcel->amount, 2, ',', '.') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ ucfirst(str_replace('_', ' ', $parcel->status)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6"><x-ancora.empty-state icon="fa-solid fa-calendar-days" title="Sem parcelas" subtitle="Cadastre os vencimentos do acordo na edição da OS." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Andamentos</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $case->timeline->count() }} evento(s)</span>
            </div>
            <form method="post" action="{{ route('cobrancas.timeline.store', $case) }}" class="mt-4 space-y-3">
                @csrf
                <select name="event_type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="manual">Andamento manual</option>
                    <option value="notificacao">Notificação</option>
                    <option value="negociacao">Negociação</option>
                    <option value="termo">Termo / assinatura</option>
                    <option value="boleto">Boleto / pagamento</option>
                    <option value="judicializacao">Judicialização</option>
                </select>
                <textarea name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Descreva o andamento realizado."></textarea>
                <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Adicionar andamento</button>
            </form>
            <div class="mt-5 space-y-3">
                @forelse($case->timeline as $event)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full border border-gray-200 px-3 py-1 text-[11px] uppercase tracking-[0.16em] text-gray-500 dark:border-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', $event->event_type) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ optional($event->created_at)->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">{!! nl2br(e($event->description)) !!}</div>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $event->user_email ?: 'Sistema' }}</div>
                    </div>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-clock-rotate-left" title="Sem andamentos" subtitle="Registre o primeiro histórico desta OS." />
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">GED / documentos</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $case->attachments->count() }} arquivo(s)</span>
            </div>
            <form method="post" action="{{ route('cobrancas.attachments.upload', $case) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <select name="file_role" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="documento">Documento</option>
                    <option value="relatorio">Relatório de inadimplência</option>
                    <option value="termo">Termo de acordo</option>
                    <option value="boleto">Boleto</option>
                    <option value="comprovante">Comprovante</option>
                    <option value="identificacao">Documento de identificação</option>
                </select>
                <input type="file" name="files[]" multiple class="block w-full rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm text-gray-700 dark:border-brand-700 dark:text-gray-200">
                <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Enviar arquivos</button>
            </form>
            <div class="mt-5 space-y-3">
                @forelse($case->attachments as $attachment)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $attachment->original_name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ ucfirst($attachment->file_role) }} · {{ optional($attachment->created_at)->format('d/m/Y H:i') }}</div>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('cobrancas.attachments.download', [$case, $attachment]) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">Baixar</a>
                                <form method="post" action="{{ route('cobrancas.attachments.delete', [$case, $attachment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirm('Excluir este arquivo do GED?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-folder-open" title="GED vazio" subtitle="Envie o relatório, termo, boletos e comprovantes por aqui." />
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Payload n8n</h3>
                <button type="button" id="copy-n8n-payload" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Copiar JSON</button>
            </div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Use este payload como base para o gatilho do fluxo de notificação no n8n.</p>
            <pre id="n8n-payload" class="mt-4 overflow-x-auto rounded-2xl bg-gray-950/95 p-4 text-xs text-emerald-200">{{ json_encode($n8nPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const button = document.getElementById('copy-n8n-payload');
    const source = document.getElementById('n8n-payload');
    if (!button || !source || !navigator.clipboard) return;
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(source.textContent || '');
            button.textContent = 'Copiado';
            setTimeout(() => button.textContent = 'Copiar JSON', 1600);
        } catch (error) {
            button.textContent = 'Falhou ao copiar';
            setTimeout(() => button.textContent = 'Copiar JSON', 1600);
        }
    });
})();
</script>
@endpush
