<div class="grid grid-cols-1 gap-6 xl:grid-cols-3" id="catalog-section">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Serviços</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cadastro sem recarregar a página inteira.</p>
        <form method="post" action="{{ route('config.servicos.store') }}" class="mt-5 space-y-3 js-async-form" data-refresh-target="#catalog-section">
            @csrf
            <input name="name" placeholder="Nome do serviço" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
            <input name="description" placeholder="Descrição" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
            <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} w-full">Adicionar serviço</button>
        </form>
        <div class="mt-5 space-y-3">
            @foreach($servicos as $item)
                <form method="post" action="{{ route('config.servicos.update', $item) }}" class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 js-async-form" data-refresh-target="#catalog-section">
                    @csrf
                    <div class="space-y-3">
                        <input name="name" value="{{ $item->name }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
                        <input name="description" value="{{ $item->description }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} flex-1">Salvar</button>
                        <button formaction="{{ route('config.servicos.delete', $item) }}" formmethod="POST" class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600">Excluir</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Status</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Respostas, recusas e fechamento comercial.</p>
        <form method="post" action="{{ route('config.status.store') }}" class="mt-5 space-y-3 js-async-form" data-refresh-target="#catalog-section">
            @csrf
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <input name="system_key" placeholder="Chave" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
                <input name="name" placeholder="Nome" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
            </div>
            <input name="color_hex" value="#999999" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
            <div class="grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-300">
                <label class="flex items-center gap-2"><input type="checkbox" name="requires_closed_value" value="1"> Exige valor fechado</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="requires_refusal_reason" value="1"> Exige motivo de recusa</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="stop_followup_alert" value="1"> Interrompe follow-up</label>
            </div>
            <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} w-full">Adicionar status</button>
        </form>
        <div class="mt-5 space-y-3">
            @foreach($statusRetorno as $item)
                <form method="post" action="{{ route('config.status.update', $item) }}" class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 js-async-form" data-refresh-target="#catalog-section">
                    @csrf
                    <div class="mb-3 flex items-center gap-3">
                        <span class="h-4 w-4 rounded-full border border-white/50" style="background-color: {{ $item->color_hex }}"></span>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $item->name }}</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input name="system_key" value="{{ $item->system_key }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                        <input name="name" value="{{ $item->name }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                    </div>
                    <input name="color_hex" value="{{ $item->color_hex }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }} mt-3">
                    <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <label class="flex items-center gap-2"><input type="checkbox" name="requires_closed_value" value="1" @checked($item->requires_closed_value)> Exige valor fechado</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="requires_refusal_reason" value="1" @checked($item->requires_refusal_reason)> Exige motivo de recusa</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="stop_followup_alert" value="1" @checked($item->stop_followup_alert)> Interrompe follow-up</label>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} flex-1">Salvar</button>
                        <button formaction="{{ route('config.status.delete', $item) }}" formmethod="POST" class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600">Excluir</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Formas de envio</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Com preview imediato do ícone escolhido.</p>
        <form method="post" action="{{ route('config.formas.store') }}" class="mt-5 space-y-3 js-async-form" data-refresh-target="#catalog-section" x-data="iconPreview('fa-solid fa-envelope', '#2563EB')">
            @csrf
            <input name="name" placeholder="Forma de envio" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
                <input name="icon_class" x-model="icon" placeholder="fa-solid fa-envelope" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
                <div class="flex items-center justify-center rounded-xl border border-gray-200 px-4 text-xl dark:border-gray-700" :style="`color:${color}`"><i :class="icon"></i></div>
            </div>
            <input name="color_hex" x-model="color" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" value="#2563EB">
            <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} w-full">Adicionar forma</button>
        </form>
        <div class="mt-5 space-y-3">
            @foreach($formasEnvio as $item)
                <form method="post" action="{{ route('config.formas.update', $item) }}" class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 js-async-form" data-refresh-target="#catalog-section" x-data="iconPreview('{{ $item->icon_class }}', '{{ $item->color_hex }}')">
                    @csrf
                    <div class="mb-3 flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl border border-gray-200 dark:border-gray-700" :style="`color:${color}`"><i :class="icon"></i></span>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $item->name }}</span>
                    </div>
                    <input name="name" value="{{ $item->name }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
                        <input name="icon_class" x-model="icon" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                        <div class="flex items-center justify-center rounded-xl border border-gray-200 px-4 text-xl dark:border-gray-700" :style="`color:${color}`"><i :class="icon"></i></div>
                    </div>
                    <input name="color_hex" x-model="color" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }} mt-3">
                    <div class="mt-3 flex gap-2">
                        <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} flex-1">Salvar</button>
                        <button formaction="{{ route('config.formas.delete', $item) }}" formmethod="POST" class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600">Excluir</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" id="process-catalog-section">
    <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Processos</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cadastros auxiliares usados no modulo Processos.</p>
        </div>
        <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-xs text-brand-700 dark:border-brand-900/60 dark:bg-brand-500/10 dark:text-brand-200">
            O campo Tribunal DataJud usa o alias oficial, por exemplo <strong>api_publica_tjes</strong>.
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
        @foreach(($processOptionLabels ?? []) as $groupKey => $groupLabel)
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $groupLabel }}</h4>
                <form method="post" action="{{ route('config.process-options.store') }}" class="mt-4 space-y-3 js-async-form" data-refresh-target="#process-catalog-section">
                    @csrf
                    <input type="hidden" name="group_key" value="{{ $groupKey }}">
                    <input name="name" placeholder="Nome" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
                    <input name="slug" placeholder="{{ $groupKey === 'datajud_court' ? 'Alias DataJud' : 'Slug automatico' }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                    <div class="grid grid-cols-2 gap-3">
                        <input name="color_hex" placeholder="#941415" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                        <input type="number" name="sort_order" placeholder="Ordem" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="is_active" value="1" checked> Ativo</label>
                    <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} w-full">Adicionar</button>
                </form>

                <div class="mt-4 max-h-80 space-y-3 overflow-auto pr-1">
                    @foreach(($processOptions[$groupKey] ?? collect()) as $item)
                        <form method="post" action="{{ route('config.process-options.update', $item) }}" class="rounded-2xl border border-gray-100 p-3 dark:border-gray-800 js-async-form" data-refresh-target="#process-catalog-section">
                            @csrf
                            <input type="hidden" name="group_key" value="{{ $groupKey }}">
                            <div class="mb-2 flex items-center gap-2">
                                @if($item->color_hex)
                                    <span class="h-3 w-3 rounded-full" style="background-color: {{ $item->color_hex }}"></span>
                                @endif
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $item->slug }}</span>
                            </div>
                            <input name="name" value="{{ $item->name }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
                            <input name="slug" value="{{ $item->slug }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }} mt-2">
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <input name="color_hex" value="{{ $item->color_hex }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                                <input type="number" name="sort_order" value="{{ $item->sort_order }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                            </div>
                            <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Ativo</label>
                            <div class="mt-3 flex gap-2">
                                <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} flex-1">Salvar</button>
                                <button formaction="{{ route('config.process-options.delete', $item) }}" formmethod="POST" class="rounded-xl border border-error-300 px-3 py-2 text-xs font-medium text-error-600">Excluir</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

@php
    $latestTjesFactor = ($tjesIndexFactors ?? collect())->first();
    $openTjesModal = session('open_tjes_indices')
        || $errors->has('year')
        || $errors->has('month')
        || $errors->has('factor')
        || $errors->has('source_label');
@endphp

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ tjesOpen: {{ $openTjesModal ? 'true' : 'false' }} }">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Atualizacao monetaria</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Gerencie os fatores mensais usados na memoria de calculo TJES das OS de cobranca.</p>
        </div>
        <button type="button" @click="tjesOpen = true" class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }}">
            INDICES TJES
        </button>
    </div>

    @if(!($tjesIndexStorageReady ?? false))
        <div class="mt-4 rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
            Rode as migrations para habilitar o cadastro dos indices TJES nesta tela.
        </div>
    @else
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Competencias</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ ($tjesIndexFactors ?? collect())->count() }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Total de fatores cadastrados no banco.</div>
            </div>
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Ultima competencia</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ $latestTjesFactor ? sprintf('%02d/%04d', (int) $latestTjesFactor->month, (int) $latestTjesFactor->year) : '--/----' }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use o modal para incluir os proximos meses.</div>
            </div>
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Fonte atual</div>
                <div class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $latestTjesFactor?->source_label ?: 'Sem observacao cadastrada' }}</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Duplicar uma competencia atualiza o fator ja existente.</div>
            </div>
        </div>
    @endif

    <div x-show="tjesOpen" class="fixed inset-0 z-[999999] flex items-center justify-center px-4 py-6" style="display: none;">
        <div class="absolute inset-0 bg-gray-950/70" @click="tjesOpen = false"></div>
        <div class="relative z-10 flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900" @click.away="tjesOpen = false">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Indices TJES</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cadastre a nova competencia e consulte todos os fatores ATM ja gravados no sistema.</p>
                </div>
                <button type="button" @click="tjesOpen = false" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                    Fechar
                </button>
            </div>

            <div class="grid flex-1 grid-cols-1 gap-0 lg:grid-cols-[340px_minmax(0,1fr)]">
                <div class="border-b border-gray-200 p-6 dark:border-gray-800 lg:border-r lg:border-b-0">
                    <div class="rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 text-xs text-brand-700 dark:border-brand-900/60 dark:bg-brand-500/10 dark:text-brand-200">
                        O codigo usado na cobranca e o <strong>ATM</strong>. Se voce informar uma competencia ja existente, o sistema substitui o fator anterior por este novo valor.
                    </div>

                    @if($errors->has('year') || $errors->has('month') || $errors->has('factor') || $errors->has('source_label'))
                        <div class="mt-4 rounded-2xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-500/10 dark:text-error-200">
                            @foreach(['year', 'month', 'factor', 'source_label'] as $field)
                                @error($field)
                                    <div>{{ $message }}</div>
                                @enderror
                            @endforeach
                        </div>
                    @endif

                    <form method="post" action="{{ route('config.tjes-factors.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Mes</label>
                                <select name="month" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                                    @foreach(range(1, 12) as $month)
                                        <option value="{{ $month }}" @selected((int) old('month', now()->month) === $month)>{{ sprintf('%02d', $month) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Ano</label>
                                <input type="number" name="year" value="{{ old('year', now()->year) }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" min="1969" max="2100" required>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Fator</label>
                            <input name="factor" value="{{ old('factor') }}" placeholder="Ex.: 0,9234567890" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}" required>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aceita ponto ou virgula como separador decimal.</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Fonte / observacao</label>
                            <input name="source_label" value="{{ old('source_label', 'Atualizado manualmente pela tela de configuracoes') }}" class="{{ $inputClass ?? 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90' }}">
                        </div>

                        <button class="{{ $buttonClass ?? 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600' }} w-full">
                            Salvar indice
                        </button>
                    </form>
                </div>

                <div class="flex min-h-0 flex-col">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ ($tjesIndexFactors ?? collect())->count() }} competencia(s) cadastrada(s)</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Listagem em ordem decrescente de competencia.</div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto px-6 py-4">
                        <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800">
                            <table class="min-w-full text-left text-sm">
                                <thead class="sticky top-0 border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-[0.16em] text-gray-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3">Competencia</th>
                                        <th class="px-4 py-3">Codigo</th>
                                        <th class="px-4 py-3">Fator</th>
                                        <th class="px-4 py-3">Fonte</th>
                                        <th class="px-4 py-3">Atualizado em</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse(($tjesIndexFactors ?? collect()) as $factor)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ sprintf('%02d/%04d', (int) $factor->month, (int) $factor->year) }}</td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $factor->index_code }}</td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ number_format((float) $factor->factor, 10, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $factor->source_label ?: '-' }}</td>
                                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ optional($factor->updated_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-10">
                                                <x-ancora.empty-state icon="fa-solid fa-scale-balanced" title="Sem indices cadastrados" subtitle="Cadastre a primeira competencia ATM para liberar a atualizacao TJES." />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
