@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$title" subtitle="Cadastro de clientes avulsos PF/PJ com anexos e timeline." />
@include('pages.clientes.partials.subnav')

<form method="post" action="{{ $mode === 'create' ? route('clientes.avulsos.store') : route('clientes.avulsos.update', $item) }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    @include('pages.clientes.partials.entity-form', ['roleTag' => $roleTag])

    <div class="mt-8 flex flex-wrap gap-3" data-client-form-actions>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar</button>
        @if($mode === 'edit')
            <button type="submit" form="delete-avulso-form" onclick="return confirm('Excluir este cliente avulso?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600">Excluir</button>
        @endif
    </div>
</form>

@if($mode === 'edit')
    <form id="delete-avulso-form" method="post" action="{{ route('clientes.avulsos.delete', $item) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection
