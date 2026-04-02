@extends('layouts.app')

@section('content')
@php
    $address = $item?->address_json ?? [];
    $selectedInactive = old('is_inactive', ($item && !$item->is_active) ? 1 : 0);
@endphp

<x-ancora.section-header :title="$title" subtitle="Cadastro de condomínio com vínculo obrigatório de síndico, blocos/towers, documentos e anexos." />
@include('pages.clientes.partials.subnav')

<form method="post" action="{{ $mode === 'create' ? route('clientes.condominios.store') : route('clientes.condominios.update', $item) }}" enctype="multipart/form-data" class="space-y-6" x-data="{ inactive: {{ $selectedInactive ? 'true' : 'false' }} }">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Dados principais</h3>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium">Nome do condomínio</label><input name="name" value="{{ old('name', $item?->name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Tipo</label><select name="condominium_type_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="">Selecione</option>@foreach($condominiumTypes as $type)<option value="{{ $type->id }}" @selected((string)old('condominium_type_id', $item?->condominium_type_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium">CNPJ</label><input name="cnpj" value="{{ old('cnpj', $item?->cnpj) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="00.000.000/0000-00"></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Síndico vinculado</label><select name="syndico_entity_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required><option value="">Selecione</option>@foreach($syndics as $syndic)<option value="{{ $syndic->id }}" @selected((string)old('syndico_entity_id', $item?->syndico_entity_id)===(string)$syndic->id)>{{ $syndic->display_name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Administradora</label><select name="administradora_entity_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="">Selecione</option>@foreach($administradorasList as $admin)<option value="{{ $admin->id }}" @selected((string)old('administradora_entity_id', $item?->administradora_entity_id)===(string)$admin->id)>{{ $admin->display_name }}</option>@endforeach</select></div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300"><input type="checkbox" name="has_blocks" value="1" @checked(old('has_blocks', $item?->has_blocks))> Possui blocos / torres</label>
                    <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-medium">Blocos / torres</label><textarea name="blocks_text" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="Um bloco por linha">{{ old('blocks_text', $blocksText) }}</textarea><p class="mt-1 text-xs text-gray-500">Caso o condomínio tenha múltiplos blocos, informe um por linha.</p></div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Endereço</h3>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach(['street'=>'Rua','number'=>'Número','complement'=>'Complemento','neighborhood'=>'Bairro','city'=>'Cidade','state'=>'UF','zip'=>'CEP','notes'=>'Observações'] as $key => $label)
                        @if($key === 'notes')
                            <div class="md:col-span-2"><textarea name="address_{{ $key }}" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700" placeholder="{{ $label }}">{{ old('address_'.$key, $address[$key] ?? '') }}</textarea></div>
                        @else
                            <div><input name="address_{{ $key }}" value="{{ old('address_'.$key, $address[$key] ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="{{ $label }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Documentos</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Convenção condominial</label>
                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            <span>Selecionar arquivo</span>
                            <input type="file" name="document_convention" class="sr-only">
                        </label>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Regimento interno</label>
                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            <span>Selecionar arquivo</span>
                            <input type="file" name="document_regiment" class="sr-only">
                        </label>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">ATAs</label>
                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-files"></i>
                            <span>Selecionar um ou mais arquivos</span>
                            <input type="file" name="document_atas[]" multiple class="sr-only">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Status</h3>
                <div class="mt-4 space-y-4">
                    <label class="flex items-center gap-3 text-sm font-medium"><input type="checkbox" name="is_inactive" value="1" x-model="inactive"> Inativo</label>
                    <div x-bind:class="inactive ? '' : 'opacity-60'"><label class="mb-1.5 block text-sm font-medium">Motivo da inativação</label><input name="inactive_reason" value="{{ old('inactive_reason', $item?->inactive_reason) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!inactive"></div>
                    <div x-bind:class="inactive ? '' : 'opacity-60'"><label class="mb-1.5 block text-sm font-medium">Fim do contrato</label><input type="date" name="contract_end_date" value="{{ old('contract_end_date', $item?->contract_end_date?->format('Y-m-d') ?? $item?->contract_end_date) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!inactive"></div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Anexos adicionais</h3>
                <div class="mt-4 space-y-4">
                    <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                        <i class="fa-solid fa-paperclip"></i>
                        <span>Escolher arquivos para anexar</span>
                        <input type="file" name="attachments[]" multiple class="sr-only">
                    </label>
                    <div><label class="mb-1.5 block text-sm font-medium">Papel dos anexos</label><select name="attachment_role" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="documento">Documento</option><option value="contrato">Contrato</option><option value="outro">Outro</option></select></div>
                </div>
            </div>

            @if($attachments->count())
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold">Documentos e anexos</h3>
                    <div class="mt-4 space-y-3">
                        @foreach($attachments as $attachment)
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <div class="text-sm font-medium">{{ $attachment->original_name }}</div>
                                <div class="mt-2 flex gap-2">
                                    <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs text-white">Baixar</a>
                                    <form method="post" action="{{ route('clientes.attachments.delete', $attachment) }}">@csrf @method('DELETE')<button class="rounded-lg border border-error-300 px-3 py-2 text-xs text-error-600">Excluir</button></form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="flex gap-3">
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}</button>
    </div>
</form>

@if($mode === 'edit')
    <form method="post" action="{{ route('clientes.condominios.delete', $item) }}" class="mt-3">
        @csrf
        @method('DELETE')
        <button onclick="return confirm('Excluir este condomínio?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600">Excluir</button>
    </form>
@endif
@endsection
