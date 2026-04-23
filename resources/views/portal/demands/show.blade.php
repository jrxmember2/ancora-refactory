@extends('portal.layouts.app')

@section('content')
@php
    $canManageDemand = $canManageDemand ?? false;
@endphp

<div x-data="{ editOpen: false, cancelOpen: false }">
<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        <a href="{{ route('portal.demands.index') }}" class="text-sm font-semibold text-[#941415]">Voltar às solicitações</a>
        <h1 class="mt-2 text-3xl font-semibold text-gray-950">{{ $demand->subject }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ $demand->protocol }} · {{ $demand->category?->name ?: 'Sem categoria' }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <span class="w-fit rounded-full bg-[#f7f2ec] px-4 py-2 text-sm font-semibold text-[#941415]">{{ $statusLabels[$demand->status] ?? $demand->status }}</span>
        @if($canManageDemand)
            <button type="button" @click="editOpen = true" class="rounded-2xl border border-[#eadfd5] bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:border-[#941415] hover:text-[#941415]">Editar</button>
            <button type="button" @click="cancelOpen = true" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Cancelar</button>
        @endif
    </div>
</div>

<section class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-gray-950">Conversa</h2>
    <div class="mt-5 space-y-4">
        @foreach($demand->publicMessages as $message)
            <div class="rounded-2xl {{ $message->sender_type === 'client' ? 'bg-[#f7f2ec]' : 'border border-[#eadfd5] bg-white' }} p-5">
                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                    <div class="font-semibold text-gray-950">{{ $message->senderName() }}</div>
                    <div class="text-xs text-gray-500">{{ $message->created_at?->format('d/m/Y H:i') }}</div>
                </div>
                <div class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $message->message }}</div>
                @if($message->attachments->count())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($message->attachments as $attachment)
                            <a href="{{ route('portal.demands.attachments.download', [$demand, $attachment]) }}" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600">{{ $attachment->original_name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</section>

@if($clientPortalUser->can_open_demands && !in_array($demand->status, ['concluida', 'cancelada'], true))
    <section class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-950">Responder</h2>
        <form method="post" action="{{ route('portal.demands.reply', $demand) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <textarea name="message" rows="5" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#941415]" placeholder="Escreva sua resposta"></textarea>
            <input type="file" name="files[]" multiple class="w-full rounded-2xl border border-dashed border-gray-300 bg-[#f7f2ec] px-4 py-4 text-sm">
            <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Enviar resposta</button>
        </form>
    </section>
@endif

@if($canManageDemand)
    <div x-cloak x-show="editOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 px-4 py-6">
        <div @click.outside="editOpen = false" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950">Editar solicitacao</h2>
                    <p class="mt-1 text-sm text-gray-500">A alteracao ficara registrada no historico com data, hora e usuario.</p>
                </div>
                <button type="button" @click="editOpen = false" class="rounded-full border border-gray-200 px-3 py-1 text-sm text-gray-500">Fechar</button>
            </div>
            <form method="post" action="{{ route('portal.demands.update', $demand) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Categoria</label>
                    <select name="category_id" required class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', $demand->category_id) === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($condominiums->count() > 1)
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Condominio relacionado</label>
                        <select name="client_condominium_id" required class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
                            <option value="">Selecione</option>
                            @foreach($condominiums as $condominium)
                                <option value="{{ $condominium->id }}" @selected((int) old('client_condominium_id', $demand->client_condominium_id) === (int) $condominium->id)>{{ $condominium->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($condominiums->count() === 1)
                    <input type="hidden" name="client_condominium_id" value="{{ $condominiums->first()->id }}">
                @endif
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Assunto</label>
                    <input name="subject" value="{{ old('subject', $demand->subject) }}" required maxlength="180" class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Descricao</label>
                    <textarea name="description" rows="7" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#941415]">{{ old('description', $demand->description) }}</textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="editOpen = false" class="rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600">Voltar</button>
                    <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Salvar edicao</button>
                </div>
            </form>
        </div>
    </div>

    <div x-cloak x-show="cancelOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 px-4 py-6">
        <div @click.outside="cancelOpen = false" class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
            <h2 class="text-xl font-semibold text-gray-950">Cancelar solicitacao?</h2>
            <p class="mt-2 text-sm text-gray-500">Essa acao encerrara a solicitacao e tambem ficara registrada no historico.</p>
            <form method="post" action="{{ route('portal.demands.cancel', $demand) }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Motivo do cancelamento (opcional)</label>
                    <textarea name="cancel_reason" rows="4" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#941415]" placeholder="Se quiser, informe o motivo."></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="cancelOpen = false" class="rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600">Voltar</button>
                    <button class="rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white">Confirmar cancelamento</button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
@endsection
