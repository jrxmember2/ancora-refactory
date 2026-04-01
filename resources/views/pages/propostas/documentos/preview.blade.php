@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Preview da Proposta Premium" subtitle="Visualização completa do template premium antes da impressão ou PDF.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('propostas.document.edit', $proposta) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-pen"></i> Editar</a>
        <a href="{{ route('propostas.document.pdf', $proposta) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white"><i class="fa-solid fa-print"></i> PDF / Print</a>
    </div>
</x-ancora.section-header>
<link rel="stylesheet" href="{{ asset('assets/css/proposal-template-aquarela.css') }}">
<div class="proposal-premium-preview">
    @include('pages.propostas.documentos.templates.aquarela_master', ['renderData' => $render])
</div>
@endsection
