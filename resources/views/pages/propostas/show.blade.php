@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="'Proposta '.$proposal->proposal_code" subtitle="Visualização consolidada da proposta, anexos, histórico e documento premium.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('propostas.edit', $proposal) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600"><i class="fa-solid fa-pen"></i> Editar</a>
        <a href="{{ route('propostas.print', $proposal) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800"><i class="fa-solid fa-print"></i> Imprimir</a>
        <a href="{{ route('propostas.document.edit', $proposal) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800"><i class="fa-solid fa-file-pdf"></i> Documento premium</a>
        <form method="post" action="{{ route('propostas.delete', $proposal) }}" onsubmit="return confirm('Deseja excluir esta proposta?');">@csrf<button class="inline-flex items-center gap-2 rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600 dark:border-error-800 dark:text-error-400"><i class="fa-solid fa-trash"></i> Excluir</button></form>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr,1fr]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Cliente</p><p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $proposal->client_name }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Solicitante</p><p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $proposal->requester_name }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Administradora</p><p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $proposal->administradora->name ?? '—' }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Serviço</p><p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $proposal->servico->name ?? '—' }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Forma de envio</p><p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $proposal->formaEnvio->name ?? '—' }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Status</p><p class="mt-2"><span class="inline-flex rounded-full px-3 py-1 text-xs font-medium" style="background-color: {{ ($proposal->statusRetorno->color_hex ?? '#999999') }}20; color: {{ $proposal->statusRetorno->color_hex ?? '#999999' }}">{{ $proposal->statusRetorno->name ?? '—' }}</span></p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Valor da proposta</p><p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">R$ {{ number_format((float) $proposal->proposal_total, 2, ',', '.') }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Valor fechado</p><p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">R$ {{ number_format((float) ($proposal->closed_total ?? 0), 2, ',', '.') }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Telefone</p><p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $proposal->requester_phone ?: '—' }}</p></div>
                <div><p class="text-xs uppercase tracking-[0.2em] text-gray-400">E-mail</p><p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $proposal->contact_email ?: '—' }}</p></div>
            </div>
            @if($proposal->notes)<div class="mt-6 border-t border-gray-100 pt-6 dark:border-gray-800"><p class="text-xs uppercase tracking-[0.2em] text-gray-400">Observações</p><p class="mt-3 whitespace-pre-line text-sm leading-7 text-gray-600 dark:text-gray-300">{{ $proposal->notes }}</p></div>@endif
            @if($proposal->refusal_reason)<div class="mt-6 rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/30 dark:text-error-300"><strong>Motivo da recusa:</strong> {{ $proposal->refusal_reason }}</div>@endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos PDF</h3>
                <form method="post" action="{{ route('propostas.attachments.upload', $proposal) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="attachment_pdf" accept="application/pdf,.pdf" class="block text-sm">
                    <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Enviar PDF</button>
                </form>
            </div>
            @if($proposal->attachments->count())
                <div class="mt-4 space-y-3">
                    @foreach($proposal->attachments as $attachment)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $attachment->original_name }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ number_format(($attachment->file_size ?? 0)/1024, 1, ',', '.') }} KB</div>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('propostas.attachments.download', [$proposal, $attachment]) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-800">Baixar</a>
                                <form method="post" action="{{ route('propostas.attachments.delete', [$proposal, $attachment]) }}">@csrf<button class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600">Excluir</button></form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4"><x-ancora.empty-state icon="fa-solid fa-paperclip" title="Sem anexos" subtitle="Envie PDFs relacionados a esta proposta." /></div>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Documento Premium</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Monte a proposta visual completa, visualize o preview e gere o PDF/print como era na base anterior.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('propostas.document.edit', $proposal) }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Editar</a>
                <a href="{{ route('propostas.document.preview', $proposal) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">Preview</a>
                <a href="{{ route('propostas.document.pdf', $proposal) }}" target="_blank" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">PDF / Print</a>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Histórico</h3>
            @if($proposal->history->count())<div class="mt-4 space-y-4">@foreach($proposal->history->sortByDesc('created_at') as $event)<div class="relative pl-5"><div class="absolute top-1 left-0 h-2.5 w-2.5 rounded-full bg-brand-500"></div><div class="text-sm font-medium text-gray-800 dark:text-white">{{ $event->summary }}</div><div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($event->created_at)->format('d/m/Y H:i') }} · {{ $event->user_email }}</div></div>@endforeach</div>@else<div class="mt-4"><x-ancora.empty-state icon="fa-solid fa-clock-rotate-left" title="Sem histórico" subtitle="O histórico desta proposta ainda está vazio." /></div>@endif
        </div>
    </div>
</div>
@endsection
