@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="'Termo de acordo · OS '.$case->os_number" subtitle="Revise o rascunho automático, ajuste o texto quando necessário e gere o PDF final.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.show', $case) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar para OS</a>
        <a href="{{ route('cobrancas.agreement.pdf', $case) }}" target="_blank" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Gerar PDF</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <form method="post" action="{{ route('cobrancas.agreement.save', $case) }}" class="space-y-6">
        @csrf
        @if(!($termStorageReady ?? true))
            <div class="rounded-2xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-200">
                A tabela de termos ainda não foi criada no banco. Você pode abrir o PDF do rascunho automático, mas para salvar customizações é necessário rodar a migration ou aplicar o SQL incremental.
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Título do documento</label>
                    <input type="text" name="title" value="{{ $formData['title'] }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Modelo aplicado</label>
                    <div class="flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        {{ $draft['template_type'] === 'judicial' ? 'Unidade ajuizada' : 'Unidade não ajuizada' }}
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Texto do termo</label>
                <textarea name="body_text" rows="34" class="w-full rounded-2xl border border-gray-300 bg-gray-50 px-5 py-4 font-serif text-sm leading-7 text-gray-900 shadow-inner outline-none focus:border-brand-400 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-100">{{ $formData['body_text'] }}</textarea>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Você pode alterar cláusulas, nomes, datas e condições antes de gerar o PDF. Ao salvar, o texto customizado passa a ser usado no documento final.</p>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <button @disabled(!($termStorageReady ?? true)) class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">Salvar customização</button>
                <button type="button" id="reload-agreement-draft" class="rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Recarregar rascunho automático</button>
                <a href="{{ route('cobrancas.agreement.pdf', $case) }}" target="_blank" class="rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Abrir PDF</a>
            </div>
        </div>
    </form>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados usados</h3>
            <div class="mt-4 space-y-3 text-sm">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Condomínio</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $draft['payload']['condominium_name'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $draft['payload']['unit_label'] }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Devedor</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $draft['payload']['debtor_name'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $draft['payload']['debtor_label'] }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Dívida</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $draft['payload']['agreement_amount_money'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $draft['payload']['quota_period'] }}</div>
                </div>
                @if($draft['template_type'] === 'judicial')
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Processo</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $draft['payload']['judicial_case_number'] ?: 'Não informado' }}</div>
                    </div>
                @endif
            </div>
        </div>

        @if($draft['warnings'])
            <div class="rounded-2xl border border-warning-200 bg-warning-50 p-6 text-warning-800 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-200">
                <h3 class="text-base font-semibold">Pontos para revisar</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach($draft['warnings'] as $warning)
                        <li class="flex gap-2"><i class="fa-solid fa-triangle-exclamation mt-1"></i><span>{{ $warning }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-sm leading-6 text-gray-600 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Como usar</h3>
            <p class="mt-3">O sistema escolhe automaticamente o modelo ajuizado quando a OS está como cobrança judicial ou possui número de processo. Depois de revisar, salve o texto e gere o PDF final.</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('reload-agreement-draft')?.addEventListener('click', function () {
    if (!confirm('Substituir o texto atual pelo rascunho automático gerado a partir da OS?')) {
        return;
    }

    document.querySelector('[name="title"]').value = @json($draft['title']);
    document.querySelector('[name="body_text"]').value = @json($draft['body_text']);
});
</script>
@endpush
