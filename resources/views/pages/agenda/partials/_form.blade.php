@php
    // Partial do formulario de compromisso, reutilizado pela pagina (form.blade.php) e pelo modal
    // do calendario. Recebe: $mode ('create'|'edit'), $item, $draft, $inModal (bool) e as
    // opcoes vindas de formOptions() do controller.
    $item = $item ?? null;
    $inModal = $inModal ?? false;
    $draft = $draft ?? [];
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
    $startId = 'agenda-start-' . ($inModal ? 'modal' : 'page');

    $reminderChoices = \App\Support\Agenda\AgendaCatalog::reminderChoices();
    $initialReminders = $mode === 'edit' && $item
        ? $item->reminders->pluck('minutes_before')->map(fn ($m) => (int) $m)->values()->all()
        : collect(old('reminders', []))->map(fn ($m) => (int) $m)->filter()->values()->all();
    $remEmail = (bool) old('remind_email', $item->remind_email ?? true);
    $remWhats = (bool) old('remind_whatsapp', $item->remind_whatsapp ?? true);
    $copyOn = (bool) old('copy_enabled', $item->copy_enabled ?? false);
    $selectedParticipants = collect(old('participants', $selectedParticipants ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<style>[x-cloak]{display:none !important;}</style>

<form method="post" action="{{ $mode === 'create' ? route('agenda.store') : route('agenda.update', $item) }}" class="space-y-6">
    @csrf
    @if($mode === 'edit')@method('PUT')@endif
    @if($inModal)<input type="hidden" name="_modal" value="1">@endif

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
                <input id="{{ $startId }}" type="datetime-local" name="start_at" value="{{ $dtLocal('start_at') }}" required class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Termino</label>
                <input type="datetime-local" name="end_at" value="{{ $dtLocal('end_at') }}" class="{{ $inputClass }}">
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

    {{-- Lembretes e notificacoes --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"
        x-data="{
            reminders: @js(array_values($initialReminders)),
            pick: '',
            copyOn: {{ $copyOn ? 'true' : 'false' }},
            labels: @js($reminderChoices),
            addVal(v){ v = parseInt(v); if(!v) return; if(!this.reminders.includes(v)){ this.reminders.push(v); this.reminders.sort((a,b)=>a-b); } },
            add(){ this.addVal(this.pick); this.pick=''; },
            remove(v){ this.reminders = this.reminders.filter(i => i !== v); },
            label(v){ return this.labels[v] || (v + ' min'); },
            mask(v){ v = v.replace(/\D/g,'').slice(0,11); if(v.length>10){return v.replace(/(\d{2})(\d{5})(\d{0,4}).*/,'($1) $2-$3');} if(v.length>6){return v.replace(/(\d{2})(\d{4})(\d{0,4}).*/,'($1) $2-$3');} if(v.length>2){return v.replace(/(\d{2})(\d{0,5}).*/,'($1) $2');} return v; }
        }">
        <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Lembretes e notificacoes</h3>

        <div class="mb-4 flex flex-wrap items-center gap-5">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="remind_email" value="1" @checked($remEmail)> Avisar por e-mail</label>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="remind_whatsapp" value="1" @checked($remWhats)> Avisar por WhatsApp</label>
        </div>

        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Lembretes (antecedencia)</label>
        <div class="mb-3 flex flex-wrap gap-2" x-show="reminders.length">
            <template x-for="m in reminders" :key="m">
                <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-200">
                    <span x-text="label(m)"></span>
                    <button type="button" class="text-brand-400 hover:text-error-500" @click="remove(m)">&times;</button>
                    <input type="hidden" name="reminders[]" :value="m">
                </span>
            </template>
        </div>
        <p class="mb-2 text-xs text-gray-400" x-show="!reminders.length">Nenhum lembrete. Adicione abaixo.</p>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="addVal(5)" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">+5 min</button>
            <button type="button" @click="addVal(10)" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">+10 min</button>
            <button type="button" @click="addVal(15)" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">+15 min</button>
            <select x-model="pick" class="{{ $inputClass }} max-w-[220px]">
                <option value="">Outra antecedencia...</option>
                @foreach($reminderChoices as $min => $lbl)
                    <option value="{{ $min }}">{{ $lbl }}</option>
                @endforeach
            </select>
            <button type="button" @click="add()" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white hover:bg-brand-600">Adicionar</button>
        </div>

        <div class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-800">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="copy_enabled" value="1" x-model="copyOn"> Enviar copia do lembrete para outra pessoa
            </label>
            <div x-show="copyOn" x-cloak class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                <input name="copy_name" value="{{ old('copy_name', $item->copy_name ?? '') }}" placeholder="Nome" class="{{ $inputClass }}">
                <input name="copy_phone" value="{{ old('copy_phone', $item->copy_phone ?? '') }}" placeholder="(27) 99999-9999" class="{{ $inputClass }}" x-on:input="$event.target.value = mask($event.target.value)">
                <input type="email" name="copy_email" value="{{ old('copy_email', $item->copy_email ?? '') }}" placeholder="email@dominio.com" class="{{ $inputClass }}">
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
                <span class="mt-1 block text-xs text-gray-400">Se vazio, assume voce. Os lembretes vao para o responsavel.</span>
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
        @if($inModal)
            <button type="button" @click="close()" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
        @else
            <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        @endif
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Criar compromisso' : 'Salvar alteracoes' }}</button>
    </div>
</form>
