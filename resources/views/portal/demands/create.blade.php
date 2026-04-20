@extends('portal.layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('portal.demands.index') }}" class="text-sm font-semibold text-[#941415]">Voltar às solicitações</a>
    <div class="mt-4 rounded-3xl border border-[#eadfd5] bg-white p-8 shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Nova solicitação</p>
        <h1 class="mt-2 text-3xl font-semibold text-gray-950">Como podemos ajudar?</h1>
        <form method="post" action="{{ route('portal.demands.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
            @csrf
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Categoria</label>
                <select name="category_id" required class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
                    <option value="">Selecione</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id') === (int) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Assunto</label>
                <input name="subject" value="{{ old('subject') }}" required maxlength="180" class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]" placeholder="Resumo da solicitação">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Descrição</label>
                <textarea name="description" rows="7" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#941415]" placeholder="Descreva o contexto, prazo e documentos relacionados">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Anexos</label>
                <input type="file" name="files[]" multiple class="w-full rounded-2xl border border-dashed border-gray-300 bg-[#f7f2ec] px-4 py-4 text-sm">
                <p class="mt-2 text-xs text-gray-500">PDF, imagens, Word, Excel, CSV ou TXT até 20 MB por arquivo.</p>
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('portal.demands.index') }}" class="rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600">Cancelar</a>
                <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Abrir solicitação</button>
            </div>
        </form>
    </div>
</div>
@endsection
