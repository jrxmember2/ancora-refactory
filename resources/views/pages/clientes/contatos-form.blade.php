@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$title" subtitle="Cadastro de síndicos, administradoras, parceiros e fornecedores reutilizáveis." />
@include('pages.clientes.partials.subnav')

<form id="contato-form" method="post" action="{{ $mode === 'create' ? route('clientes.contatos.store') : route('clientes.contatos.update', $item) }}" enctype="multipart/form-data" class="space-y-6" data-clientes-form>
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    @include('pages.clientes.partials.entity-form', ['roleTag' => $item?->role_tag ?? 'administradora'])
</form>

<div class="mt-3 flex flex-wrap gap-3">
    <button type="submit" form="contato-form" class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">
        {{ $mode === 'create' ? 'Cadastrar' : 'Salvar' }}
    </button>

    @if($mode === 'edit')
        <form method="post" action="{{ route('clientes.contatos.delete', $item) }}">
            @csrf
            @method('DELETE')
            <button onclick="return confirm('Excluir este cadastro?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600 dark:text-error-300">Excluir</button>
        </form>
    @endif
</div>

@if($mode === 'edit')
    @include('pages.clientes.partials.entity-related-panels')
@endif
@endsection
