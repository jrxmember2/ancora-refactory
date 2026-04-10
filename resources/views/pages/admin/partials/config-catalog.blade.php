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
