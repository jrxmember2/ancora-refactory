@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$title" subtitle="Cadastro de clientes avulsos PF/PJ com anexos e timeline." />
@include('pages.clientes.partials.subnav')

<form method="post" action="{{ $mode === 'create' ? route('clientes.avulsos.store') : route('clientes.avulsos.update', $item) }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    @include('pages.clientes.partials.entity-form', ['roleTag' => $roleTag])

    <div class="flex gap-3">
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}</button>
    </div>
</form>

@if($mode === 'edit')
    <form method="post" action="{{ route('clientes.avulsos.delete', $item) }}" class="mt-3">
        @csrf
        @method('DELETE')
        <button onclick="return confirm('Excluir este cliente avulso?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600">Excluir</button>
    </form>
@endif
@endsection
