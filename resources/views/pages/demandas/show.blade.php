@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

@section('content')
<x-ancora.section-header :title="$demand->protocol" :subtitle="$demand->subject">
    <a href="{{ route('demandas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,360px]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Timeline</h3>
            <div class="mt-5 space-y-4">
                @foreach($demand->messages as $message)
                    <div class="rounded-2xl border {{ $message->is_internal ? 'border-warning-200 bg-warning-50 dark:border-warning-900/60 dark:bg-warning-500/10' : 'border-gray-200 dark:border-gray-800' }} p-5">
                        <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $message->senderName() }} @if($message->is_internal)<span class="ml-2 rounded-full bg-warning-100 px-2 py-0.5 text-xs text-warning-700">Interno</span>@endif</div>
                            <div class="text-xs text-gray-500">{{ $message->created_at?->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $message->message }}</div>
                        @if($message->attachments->count())
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($message->attachments as $attachment)
                                    <a href="{{ route('demandas.attachments.download', [$demand, $attachment]) }}" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ $attachment->original_name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Responder</h3>
            <form method="post" action="{{ route('demandas.reply', $demand) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <textarea name="message" rows="5" required class="{{ $textareaClass }}" placeholder="Escreva a resposta"></textarea>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <select name="status" class="{{ $inputClass }}">
                        <option value="">Status automático</option>
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="is_internal" value="1">
                        Observação interna (cliente não vê)
                    </label>
                </div>
                <input type="file" name="files[]" multiple class="w-full rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm dark:border-gray-700">
                <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Enviar resposta</button>
            </form>
        </div>
    </div>

    <aside class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Gestão</h3>
            <form method="post" action="{{ route('demandas.update', $demand) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" class="{{ $inputClass }}">
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected($demand->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Prioridade</label>
                    <select name="priority" class="{{ $inputClass }}">
                        @foreach($priorityLabels as $key => $label)
                            <option value="{{ $key }}" @selected($demand->priority === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Categoria</label>
                    <select name="category_id" class="{{ $inputClass }}">
                        <option value="">Sem categoria</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) $demand->category_id === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Responsável</label>
                    <select name="assigned_user_id" class="{{ $inputClass }}">
                        <option value="">Não atribuído</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((int) $demand->assigned_user_id === (int) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar gestão</button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-sm shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Cliente</h3>
            <div class="mt-4 space-y-3 text-gray-700 dark:text-gray-200">
                <div><span class="text-gray-500">Nome:</span> {{ $demand->clientName() }}</div>
                <div><span class="text-gray-500">Usuário portal:</span> {{ $demand->portalUser?->name ?: 'Não informado' }}</div>
                <div><span class="text-gray-500">Origem:</span> {{ ucfirst($demand->origin) }}</div>
                <div><span class="text-gray-500">Aberta em:</span> {{ $demand->created_at?->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </aside>
</div>
@endsection
