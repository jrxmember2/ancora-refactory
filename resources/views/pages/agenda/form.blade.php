@extends('layouts.app')

@php
    $item = $item ?? null;
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $valueOf = fn ($key, $fallback = null) => old($key, $item->{$key} ?? $draft[$key] ?? $fallback);
    $boolOf = fn ($key, $fallback = false) => (bool) old($key, $item->{$key} ?? $draft[$key] ?? $fallback);
    $dtLocal = function ($key) use ($item, $draft) {
        $v = old($key, $item->{$key} ?? $draft[$key] ?? null);
        if ($v instanceof \Carbon\CarbonInterface) {
            return $v->format('Y-m-d\TH:i');
        }
        if (is_string($v) && trim($v) !== '') {
            try { return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i'); } catch (\Throwable) { return ''; }
        }
        return '';
    };
@endphp

@section('content')
<x-ancora.section-header :title="$title" subtitle="Defina o tipo, a data/hora, o responsavel e o vinculo do compromisso.">
    <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
</x-ancora.section-header>

@if(session('error'))
    <div class="mb-4 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if(!empty($parentProcess ?? null))
    <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
        Prazo vinculado ao processo <strong>{{ $parentProcess->process_number }}</strong>.
    </div>
@endif

<form method="post" action="{{ $mode === 'create' ? route('agenda.store') : route('agenda.update', $item) }}" class="space-y-6">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Titulo *</label>
                <input name="title" value="{{ $valueOf('title') }}" required class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo *</label>
                <select name="type" class="{{ $inputClass }}">
                    @foreach($typeOptions as $key => $label)
                        <option value="{{ $key }}" @selected($valueOf('type', 'compromisso') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Prioridade</label>
                <select name="priority" class="{{ $inputClass }}">
                    <option value="">Normal</option>
                    @foreach($priorityOptions as $key => $label)
                        <option value="{{ $key }}" @selected($valueOf('priority') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cor de fundo</label>
                <div class="flex h-11 items-center gap-3">
                    <input type="color" name="color" value="{{ $valueOf('color') ?: '#3b82f6' }}" class="h-9 w-14 cursor-pointer rounded-lg border border-gray-300 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="apply_color" value="1" @checked(!empty($valueOf('color')))> Aplicar cor</label>
                </div>
                <span class="mt-1 block text-xs text-gray-400">A cor da letra ajusta-se automaticamente para contrastar.</span>
            </div>
            @if($mode === 'edit')
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="status" class="{{ $inputClass }}">
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($valueOf('status', 'aberto') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Inicio *</label>
                <input type="datetime-local" name="start_at" value="{{ $dtLocal('start_at') }}" required class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Termino</label>
                <input type="datetime-local" name="end_at" value="{{ $dtLocal('end_at') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Lembrete</label>
                <select name="reminder_minutes" class="{{ $inputClass }}">
                    @foreach($reminderOptions as $key => $label)
                        <option value="{{ $key }}" @selected((string) $valueOf('reminder_minutes', '') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if(($mode ?? 'create') !== 'edit')
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Repeticao</label>
                <select name="recurrence" class="{{ $inputClass }}">
                    @foreach(\App\Support\Agenda\AgendaCatalog::recurrences() as $key => $label)
                        <option value="{{ $key }}" @selected((string) $valueOf('recurrence', '') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Repetir ate</label>
                <input type="date" name="recurrence_until" value="{{ $valueOf('recurrence_until') }}" class="{{ $inputClass }}">
                <span class="mt-1 block text-xs text-gray-400">Cria varios compromissos da serie ate esta data.</span>
            </div>
            @endif
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Local / vara / link</label>
                <input name="location" value="{{ $valueOf('location') }}" class="{{ $inputClass }}">
            </div>
            <div class="flex items-end gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="is_fatal" value="1" @checked($boolOf('is_fatal'))> Prazo fatal</label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="all_day" value="1" @checked($boolOf('all_day'))> Dia inteiro</label>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Responsaveis e vinculos</h3>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Responsavel</label>
                <select name="responsible_user_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string) $valueOf('responsible_user_id') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Solicitante</label>
                <select name="requester_user_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string) $valueOf('requester_user_id') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                @php $selectedParticipants = collect(old('participants', $selectedParticipants ?? []))->map(fn ($id) => (int) $id)->all(); @endphp
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Participantes</label>
                <select name="participants[]" multiple size="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(in_array((int) $u->id, $selectedParticipants, true))>{{ $u->name }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-gray-400">Segure Ctrl/Cmd para selecionar varios. Eles recebem o lembrete por e-mail.</span>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Processo</label>
                <select name="process_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach($processes as $p)
                        <option value="{{ $p->id }}" @selected((string) $valueOf('process_id') === (string) $p->id)>{{ $p->process_number }} - {{ $p->client_name_snapshot }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Demanda</label>
                <select name="demand_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach($demands as $d)
                        <option value="{{ $d->id }}" @selected((string) $valueOf('demand_id') === (string) $d->id)>{{ $d->protocol }} - {{ \Illuminate\Support\Str::limit($d->subject, 40) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
                <select name="client_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected((string) $valueOf('client_id') === (string) $c->id)>{{ $c->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Contrato</label>
                <select name="contract_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach($contracts as $c)
                        <option value="{{ $c->id }}" @selected((string) $valueOf('contract_id') === (string) $c->id)>{{ $c->code ?: $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Descricao</label>
                <textarea name="description" rows="4" class="{{ $textareaClass }}">{{ $valueOf('description') }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Criar compromisso' : 'Salvar alteracoes' }}</button>
    </div>
</form>
@endsection
