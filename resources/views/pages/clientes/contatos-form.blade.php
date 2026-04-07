@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$title" subtitle="Cadastro de síndicos, administradoras, parceiros e fornecedores reutilizáveis." />
@include('pages.clientes.partials.subnav')

<form method="post" action="{{ $mode === 'create' ? route('clientes.contatos.store') : route('clientes.contatos.update', $item) }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    @include('pages.clientes.partials.entity-form', ['roleTag' => $item?->role_tag ?? 'administradora'])

    <div class="flex gap-3">
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Cadastrar' : 'Salvar' }}</button>
    </div>
</form>

@if($mode === 'edit')
    <div class="mt-3 flex flex-wrap gap-3">
        <form method="post" action="{{ route('clientes.contatos.delete', $item) }}">
            @csrf
            @method('DELETE')
            <button onclick="return confirm('Excluir este cadastro?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600 dark:text-error-300">Excluir</button>
        </form>
    </div>

    @include('pages.clientes.partials.entity-related-panels')
@endif
@endsection
