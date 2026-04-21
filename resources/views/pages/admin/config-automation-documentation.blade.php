@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <x-ancora.section-header
        title="Documentacao da Automacao WhatsApp"
        subtitle="Referencia interna da API consumida pelo n8n para atendimento automatizado dentro do Ancora." />

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Arquivo fonte</div>
                <div class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $documentationPath }}</div>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('config.index') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">
                    Voltar para configuracoes
                </a>
                <a href="#documentacao" class="inline-flex items-center justify-center rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                    Ir para o conteudo
                </a>
            </div>
        </div>
    </div>

    <div id="documentacao" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <article class="space-y-4 text-sm leading-7 text-gray-700 dark:text-gray-200 [&_a]:text-brand-600 [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-brand-200 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-black/5 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-[0.95em] [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:text-gray-900 [&_h1]:dark:text-white [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-gray-900 [&_h2]:dark:text-white [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-gray-900 [&_h3]:dark:text-white [&_li]:ml-5 [&_li]:list-disc [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:text-sm [&_pre]:overflow-x-auto [&_pre]:rounded-2xl [&_pre]:bg-gray-950 [&_pre]:p-4 [&_pre]:text-gray-100 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-gray-200 [&_td]:p-2 [&_th]:border [&_th]:border-gray-200 [&_th]:bg-gray-50 [&_th]:p-2 [&_ul]:space-y-2">
            {!! $documentationHtml !!}
        </article>
    </div>
</div>
@endsection
