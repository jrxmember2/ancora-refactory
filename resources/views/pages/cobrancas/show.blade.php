@extends('layouts.app')

@section('content')
@php
    $monetaryUpdates = ($monetaryStorageReady ?? false) ? $case->monetaryUpdates : collect();
    $defaultMonetaryFinalDate = now()->endOfMonth()->format('Y-m-d');
@endphp
<x-ancora.section-header :title="'OS '.$case->os_number" subtitle="Acompanhe o histórico completo da cobrança, GED, quotas, parcelas e payload operacional.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.edit', $case) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Editar</a>
        @if(($monetaryStorageReady ?? false) && $case->quotas->isNotEmpty())
            <button type="button" id="open-monetary-update-modal" class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-800 hover:bg-warning-100 dark:border-warning-700/60 dark:bg-warning-500/10 dark:text-warning-200">Atualizar débitos TJES</button>
        @else
            <span title="{{ ($monetaryStorageReady ?? false) ? 'Cadastre quotas antes de calcular.' : 'Rode a migration de atualização monetária.' }}" class="rounded-xl border border-gray-200 bg-gray-100 px-4 py-3 text-sm font-medium text-gray-400 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-500">Atualizar débitos TJES</span>
        @endif
        @if($agreementPaymentError ?? null)
            <span title="{{ $agreementPaymentError }}" class="rounded-xl border border-gray-200 bg-gray-100 px-4 py-3 text-sm font-medium text-gray-400 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-500">Gerar termo de acordo</span>
        @else
            <a href="{{ route('cobrancas.agreement.edit', $case) }}" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Gerar termo de acordo</a>
        @endif
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
    <x-ancora.stat-card label="Situação da OS" :value="$stageLabels[$case->workflow_stage] ?? $case->workflow_stage" :hint="'Último andamento: ' . (optional($case->last_progress_at)->format('d/m/Y H:i') ?: '—')" icon="fa-solid fa-shoe-prints" />
    <x-ancora.stat-card label="Faturamento" :value="$billingLabels[$case->billing_status] ?? $case->billing_status" :hint="$case->billing_date ? 'Faturado em '.optional($case->billing_date)->format('d/m/Y') : 'Sem data de faturamento'" icon="fa-solid fa-file-invoice-dollar" />
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
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Atualização monetária TJES</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Histórico das memórias de cálculo salvas para esta OS.</p>
                </div>
                @if(($monetaryStorageReady ?? false) && $case->quotas->isNotEmpty())
                    <button type="button" data-open-monetary-update class="rounded-xl bg-warning-500 px-4 py-3 text-sm font-medium text-white hover:bg-warning-600">Novo cálculo</button>
                @endif
            </div>

            @if(!($monetaryStorageReady ?? false))
                <div class="mt-4 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
                    Rode a migration de atualização monetária para liberar a memória de cálculo TJES nesta OS.
                </div>
            @else
                <div class="mt-4 space-y-3">
                    @forelse($monetaryUpdates as $update)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-warning-200 bg-warning-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-warning-800 dark:border-warning-800/70 dark:bg-warning-500/10 dark:text-warning-200">TJES</span>
                                        @if($update->applied_to_case)
                                            <span class="rounded-full border border-success-200 bg-success-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300">Aplicado</span>
                                        @endif
                                    </div>
                                    <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                                        Base {{ optional($update->final_date)->format('d/m/Y') }} · {{ $update->items->count() }} quota(s) · Total geral <strong class="text-gray-900 dark:text-white">R$ {{ number_format((float) $update->grand_total, 2, ',', '.') }}</strong>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Débito atualizado: R$ {{ number_format((float) $update->debit_total, 2, ',', '.') }} · Honorários: R$ {{ number_format((float) $update->attorney_fee_amount, 2, ',', '.') }} · Criado em {{ optional($update->created_at)->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('cobrancas.monetary.pdf', [$case, $update]) }}" target="_blank" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">PDF</a>
                                    @if(!$update->applied_to_case)
                                        <form method="post" action="{{ route('cobrancas.monetary.apply', [$case, $update]) }}">
                                            @csrf
                                            <button onclick="return confirm('Aplicar esta memória na OS? O valor do acordo, honorários e valores atualizados das quotas serão alterados.')" class="rounded-lg border border-warning-300 px-3 py-2 text-xs font-medium text-warning-800 hover:bg-warning-50 dark:border-warning-700 dark:text-warning-200 dark:hover:bg-warning-500/10">Aplicar na OS</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-ancora.empty-state icon="fa-solid fa-scale-balanced" title="Sem memória de cálculo" subtitle="Use o botão Novo cálculo para simular a atualização TJES e salvar o histórico da OS." />
                    @endforelse
                </div>
            @endif
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

@if(($monetaryStorageReady ?? false) && $case->quotas->isNotEmpty())
<dialog id="monetary-update-modal" class="fixed inset-0 m-auto h-[92vh] w-[96vw] max-w-6xl overflow-hidden rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <form id="monetary-update-form" method="post" action="{{ route('cobrancas.monetary.store', $case) }}" class="flex h-full flex-col">
        @csrf
        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Atualização monetária TJES</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Simule, salve a memória e aplique na OS quando os números estiverem conferidos.</p>
                </div>
                <button type="button" id="monetary-update-close" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 overflow-y-auto lg:grid-cols-[0.9fr,1.1fr]">
            <div class="space-y-5 border-b border-gray-100 p-6 dark:border-gray-800 lg:border-b-0 lg:border-r">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data final do cálculo</label>
                        <input type="date" name="final_date" value="{{ $defaultMonetaryFinalDate }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Por padrão usamos o último dia do mês atual, como no relatório do TJES.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Índice</label>
                        <select name="index_code" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="ATM">Índice do TJES</option>
                        </select>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Quotas consideradas</h4>
                    <div class="mt-3 max-h-56 space-y-2 overflow-y-auto pr-1">
                        @foreach($case->quotas as $quota)
                            <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 px-3 py-2 text-sm dark:border-gray-800">
                                <span class="flex items-center gap-3">
                                    <input type="checkbox" name="quota_ids[]" value="{{ $quota->id }}" checked class="rounded border-gray-300 text-brand-500">
                                    <span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $quota->reference_label ?: optional($quota->due_date)->format('m/Y') }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ optional($quota->due_date)->format('d/m/Y') }}</span>
                                    </span>
                                </span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $quota->original_amount, 2, ',', '.') }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Juros moratórios</label>
                        <select name="interest_type" id="monetary-interest-type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="legal">Juros legais</option>
                            <option value="contractual">Juros contratuais</option>
                            <option value="none">Sem juros</option>
                        </select>
                    </div>
                    <div id="monetary-interest-rate-wrap" class="hidden">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Percentual mensal</label>
                        <input type="text" name="interest_rate_monthly" value="1,00" data-monetary-percent class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa (%)</label>
                        <input type="text" name="fine_percent" value="2,00" data-monetary-percent class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Honorários por</label>
                        <select name="attorney_fee_type" id="monetary-attorney-fee-type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="percent">Percentual (%)</option>
                            <option value="fixed">Valor fixo (R$)</option>
                            <option value="none">Sem honorários</option>
                        </select>
                    </div>
                    <div>
                        <label id="monetary-attorney-fee-label" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Honorários (%)</label>
                        <input type="text" name="attorney_fee_value" value="15,00" data-monetary-percent class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Custas/despesas (R$)</label>
                        <input type="text" name="costs_amount" value="" data-monetary-money class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data das custas</label>
                        <input type="date" name="costs_date" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Abatimento na data final (R$)</label>
                        <input type="text" name="abatement_amount" value="" data-monetary-money class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                </div>
            </div>

            <div class="flex min-h-0 flex-col p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Prévia da memória</h4>
                        <p id="monetary-preview-message" class="mt-1 text-sm text-gray-500 dark:text-gray-400">Clique em Simular cálculo para conferir a memória antes de salvar.</p>
                    </div>
                    <button type="button" id="monetary-preview-button" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Simular cálculo</button>
                </div>

                <div id="monetary-preview-empty" class="mt-6 rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    A prévia aparecerá aqui com fator TJES, juros, multa, honorários e total geral.
                </div>

                <div id="monetary-preview-content" class="mt-5 hidden min-h-0 flex-1 space-y-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Débito atualizado</div>
                            <div id="monetary-preview-debit-total" class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">R$ 0,00</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Honorários</div>
                            <div id="monetary-preview-attorney-fee" class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">R$ 0,00</div>
                        </div>
                        <div class="rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-800/70 dark:bg-warning-500/10">
                            <div class="text-xs uppercase tracking-[0.16em] text-warning-700 dark:text-warning-300">Total geral</div>
                            <div id="monetary-preview-grand-total" class="mt-2 text-lg font-semibold text-warning-900 dark:text-warning-100">R$ 0,00</div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-800">
                        <table class="min-w-full text-left text-xs">
                            <thead class="border-b border-gray-100 bg-gray-50 text-[11px] uppercase tracking-[0.14em] text-gray-500 dark:border-gray-800 dark:bg-gray-950/30 dark:text-gray-400">
                                <tr>
                                    <th class="px-3 py-3">Ref.</th>
                                    <th class="px-3 py-3">Venc.</th>
                                    <th class="px-3 py-3">Original</th>
                                    <th class="px-3 py-3">Fator</th>
                                    <th class="px-3 py-3">Corrigido</th>
                                    <th class="px-3 py-3">Juros</th>
                                    <th class="px-3 py-3">Multa</th>
                                    <th class="px-3 py-3">Total</th>
                                </tr>
                            </thead>
                            <tbody id="monetary-preview-items" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            <button type="button" id="monetary-update-cancel" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</button>
            <button type="submit" name="apply_to_case" value="0" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Salvar memória</button>
            <button type="submit" name="apply_to_case" value="1" onclick="return confirm('Salvar e aplicar o cálculo na OS? Isso altera valor do acordo, honorários e valores atualizados das quotas.')" class="rounded-xl bg-warning-500 px-4 py-3 text-sm font-medium text-white hover:bg-warning-600">Salvar e aplicar na OS</button>
        </div>
    </form>
</dialog>
@endif
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

(() => {
    const modal = document.getElementById('monetary-update-modal');
    const form = document.getElementById('monetary-update-form');
    if (!modal || !form) return;

    const previewUrl = @json(($monetaryStorageReady ?? false) ? route('cobrancas.monetary.preview', $case) : '');
    const openButtons = document.querySelectorAll('#open-monetary-update-modal, [data-open-monetary-update]');
    const closeButtons = [
        document.getElementById('monetary-update-close'),
        document.getElementById('monetary-update-cancel'),
    ].filter(Boolean);
    const previewButton = document.getElementById('monetary-preview-button');
    const message = document.getElementById('monetary-preview-message');
    const emptyState = document.getElementById('monetary-preview-empty');
    const content = document.getElementById('monetary-preview-content');
    const itemsBody = document.getElementById('monetary-preview-items');
    const debitTotal = document.getElementById('monetary-preview-debit-total');
    const attorneyFee = document.getElementById('monetary-preview-attorney-fee');
    const grandTotal = document.getElementById('monetary-preview-grand-total');
    const interestType = document.getElementById('monetary-interest-type');
    const interestRateWrap = document.getElementById('monetary-interest-rate-wrap');
    const attorneyFeeType = document.getElementById('monetary-attorney-fee-type');
    const attorneyFeeLabel = document.getElementById('monetary-attorney-fee-label');
    const attorneyFeeInput = form.querySelector('[name="attorney_fee_value"]');

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function formatDecimalValue(value) {
        const digits = onlyDigits(value);
        if (!digits) return '';
        return (Number(digits) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bindDecimalMasks() {
        form.querySelectorAll('[data-monetary-money], [data-monetary-percent]').forEach((input) => {
            if (input.dataset.monetaryBound === '1') return;
            input.dataset.monetaryBound = '1';
            input.value = formatDecimalValue(input.value);
            input.addEventListener('input', () => {
                input.value = formatDecimalValue(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = formatDecimalValue(input.value);
            });
        });
    }

    function updateConditionalFields() {
        if (interestRateWrap && interestType) {
            interestRateWrap.classList.toggle('hidden', interestType.value !== 'contractual');
        }

        if (!attorneyFeeType || !attorneyFeeInput || !attorneyFeeLabel) return;
        if (attorneyFeeType.value === 'fixed') {
            attorneyFeeLabel.textContent = 'Honorários (R$)';
            attorneyFeeInput.disabled = false;
            attorneyFeeInput.placeholder = '0,00';
            if (!attorneyFeeInput.value) attorneyFeeInput.value = '0,00';
            return;
        }
        if (attorneyFeeType.value === 'none') {
            attorneyFeeLabel.textContent = 'Honorários';
            attorneyFeeInput.value = '';
            attorneyFeeInput.placeholder = 'Sem honorários';
            attorneyFeeInput.disabled = true;
            return;
        }

        attorneyFeeLabel.textContent = 'Honorários (%)';
        attorneyFeeInput.disabled = false;
        attorneyFeeInput.placeholder = '0,00';
        if (!attorneyFeeInput.value) attorneyFeeInput.value = '15,00';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function renderPreview(data) {
        emptyState?.classList.add('hidden');
        content?.classList.remove('hidden');
        if (message) {
            message.textContent = `${data.settings.index_label} · ${data.settings.interest_label} · base ${data.settings.final_date}`;
            message.className = 'mt-1 text-sm text-success-600 dark:text-success-400';
        }
        if (debitTotal) debitTotal.textContent = data.totals.debit_total;
        if (attorneyFee) attorneyFee.textContent = data.totals.attorney_fee;
        if (grandTotal) grandTotal.textContent = data.totals.grand_total;
        if (itemsBody) {
            itemsBody.innerHTML = (data.items || []).map((item) => `
                <tr>
                    <td class="px-3 py-3 font-medium text-gray-900 dark:text-white">${escapeHtml(item.reference_label)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.due_date)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.original)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.factor)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.corrected)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.interest)} <span class="text-gray-400">(${escapeHtml(item.interest_percent)})</span></td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.fine)}</td>
                    <td class="px-3 py-3 font-semibold text-gray-900 dark:text-white">${escapeHtml(item.total)}</td>
                </tr>
            `).join('');
        }
    }

    async function previewCalculation() {
        if (!previewUrl || !previewButton) return;

        previewButton.disabled = true;
        previewButton.textContent = 'Calculando...';
        if (message) {
            message.textContent = 'Consultando fatores e calculando a memória...';
            message.className = 'mt-1 text-sm text-gray-500 dark:text-gray-400';
        }

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                },
                body: new FormData(form),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Não foi possível simular o cálculo.');
            }
            renderPreview(data);
        } catch (error) {
            if (message) {
                message.textContent = error.message || 'Não foi possível simular o cálculo.';
                message.className = 'mt-1 text-sm text-error-600 dark:text-error-400';
            }
        } finally {
            previewButton.disabled = false;
            previewButton.textContent = 'Simular cálculo';
        }
    }

    openButtons.forEach((button) => button.addEventListener('click', () => modal.showModal()));
    closeButtons.forEach((button) => button.addEventListener('click', () => modal.close()));
    previewButton?.addEventListener('click', previewCalculation);
    interestType?.addEventListener('change', updateConditionalFields);
    attorneyFeeType?.addEventListener('change', updateConditionalFields);

    bindDecimalMasks();
    updateConditionalFields();
})();
</script>
@endpush
