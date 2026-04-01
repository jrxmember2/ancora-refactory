@extends('layouts.app')

@section('content')
@php
    $blocksByCondominium = $condominiumsDropdown->mapWithKeys(fn ($condo) => [
        (string) $condo->id => $condo->blocks->map(fn ($block) => ['id' => $block->id, 'name' => $block->name])->values()->all(),
    ]);
    $selectedCondominium = (string) old('condominium_id', $item?->condominium_id);
    $selectedBlock = (string) old('block_id', $item?->block_id);
@endphp
<x-ancora.section-header :title="$title" subtitle="Cadastro de unidade com vínculo a condomínio, proprietário e locatário." />
@include('pages.clientes.partials.subnav')
<form method="post" action="{{ $mode === 'create' ? route('clientes.unidades.store') : route('clientes.unidades.update', $item) }}" enctype="multipart/form-data" class="space-y-6" x-data="{ blocksByCondo: @js($blocksByCondominium), condominiumId: '{{ $selectedCondominium }}', blockId: '{{ $selectedBlock }}', get blocks(){ return this.blocksByCondo[this.condominiumId] || []; }, syncBlock(){ if(!this.blocks.find(block => String(block.id) === String(this.blockId))){ this.blockId = ''; } } }" x-init="syncBlock()">
    @csrf
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Vínculos</h3>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Condomínio</label>
                        <select name="condominium_id" x-model="condominiumId" @change="syncBlock()" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
                            <option value="">Selecione</option>
                            @foreach($condominiumsDropdown as $condo)
                                <option value="{{ $condo->id }}">{{ $condo->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Bloco / torre</label>
                        <select name="block_id" x-model="blockId" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!condominiumId || blocks.length === 0">
                            <option value="">Selecione</option>
                            <template x-for="block in blocks" :key="block.id">
                                <option :value="block.id" x-text="block.name"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Os blocos/towers aparecem automaticamente conforme o condomínio selecionado.</p>
                    </div>
                    <div><label class="mb-1.5 block text-sm font-medium">Tipo de unidade</label><select name="unit_type_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="">Selecione</option>@foreach($unitTypes as $type)<option value="{{ $type->id }}" @selected((string)old('unit_type_id', $item?->unit_type_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Número da unidade</label><input name="unit_number" value="{{ old('unit_number', $item?->unit_number) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Proprietário</label><select name="owner_entity_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="">Selecione</option>@foreach($entitiesAll as $entity)<option value="{{ $entity->id }}" @selected((string)old('owner_entity_id', $item?->owner_entity_id)===(string)$entity->id)>{{ $entity->display_name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Locatário</label><select name="tenant_entity_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="">Selecione</option>@foreach($entitiesAll as $entity)<option value="{{ $entity->id }}" @selected((string)old('tenant_entity_id', $item?->tenant_entity_id)===(string)$entity->id)>{{ $entity->display_name }}</option>@endforeach</select></div>
                    <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-medium">Observações do proprietário</label><textarea name="owner_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('owner_notes', $item?->owner_notes) }}</textarea></div>
                    <div class="md:col-span-2"><label class="mb-1.5 block text-sm font-medium">Observações do locatário</label><textarea name="tenant_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('tenant_notes', $item?->tenant_notes) }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Anexos</h3>
                <div class="mt-4 space-y-4"><input type="file" name="attachments[]" multiple class="block w-full text-sm"><select name="attachment_role" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="documento">Documento</option><option value="contrato">Contrato</option><option value="outro">Outro</option></select></div>
            </div>
            @if($attachments->count())<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><h3 class="text-base font-semibold">Anexos</h3><div class="mt-4 space-y-3">@foreach($attachments as $attachment)<div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"><div class="text-sm font-medium">{{ $attachment->original_name }}</div><div class="mt-2 flex gap-2"><a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs text-white">Baixar</a><form method="post" action="{{ route('clientes.attachments.delete', $attachment) }}">@csrf<button class="rounded-lg border border-error-300 px-3 py-2 text-xs text-error-600">Excluir</button></form></div></div>@endforeach</div></div>@endif
        </div>
    </div>
    <div class="flex gap-3"><button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}</button>@if($mode === 'edit')<button formaction="{{ route('clientes.unidades.delete', $item) }}" formmethod="POST" onclick="return confirm('Excluir esta unidade?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600">Excluir</button>@endif</div>
</form>
@endsection
