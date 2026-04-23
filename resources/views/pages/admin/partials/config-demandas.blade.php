<div class="grid grid-cols-1 gap-6 xl:grid-cols-3" id="demand-catalog-section">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-2">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Configuracao de Demandas</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Crie tags do kanban, defina cores, SLA e o que pode aparecer no Portal do Cliente.</p>
            </div>
            <a href="{{ route('demandas.kanban') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
                <i class="fa-solid fa-table-columns"></i>
                <span>Abrir kanban</span>
            </a>
        </div>

        <form method="post" action="{{ route('config.demand-tags.store') }}" class="mt-6 rounded-2xl border border-dashed border-brand-300 bg-brand-50/40 p-4 dark:border-brand-800 dark:bg-brand-500/5">
            @csrf
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
                <input name="name" placeholder="Nome da tag" class="{{ $inputClass }} xl:col-span-2" required>
                <input name="slug" placeholder="Identificador opcional" class="{{ $inputClass }}">
                <select name="status_key" class="{{ $inputClass }}">
                    @foreach($demandStatusLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select name="sla_hours" class="{{ $inputClass }}">
                    @foreach($demandTagSlaOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900">
                    <input type="color" name="color_hex" value="#2563EB" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Cor</span>
                </div>
                <input name="portal_label" placeholder="Nome exibido no portal" class="{{ $inputClass }} xl:col-span-2">
                <input type="number" name="sort_order" value="0" placeholder="Ordem" class="{{ $inputClass }}">
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    <input type="checkbox" name="show_on_portal" value="1" checked>
                    Mostrar no portal
                </label>
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    <input type="checkbox" name="is_default" value="1">
                    Padrao inicial
                </label>
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    <input type="checkbox" name="is_closing" value="1">
                    Encerra demanda
                </label>
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Ativa
                </label>
            </div>
            <button class="{{ $buttonClass }} mt-4">Cadastrar tag</button>
        </form>

        <div class="mt-6 max-h-[34rem] space-y-4 overflow-y-auto pr-2">
            @forelse($demandTags as $tag)
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="h-4 w-4 rounded-full" style="background-color: {{ $tag->color_hex }}"></span>
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $tag->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $tag->slug }} · {{ $tag->demands_count }} demanda(s)</div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $demandStatusLabels[$tag->status_key] ?? $tag->status_key }}</span>
                            <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $tag->sla_hours ? $tag->sla_hours.'h SLA' : 'Sem SLA' }}</span>
                            <span class="rounded-full px-3 py-1 font-semibold {{ $tag->show_on_portal ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' }}">{{ $tag->show_on_portal ? 'Portal visivel' : 'Interna' }}</span>
                        </div>
                    </div>

                    <form method="post" action="{{ route('config.demand-tags.update', $tag) }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $tag->name }}" class="{{ $inputClass }} xl:col-span-2" required>
                        <input name="slug" value="{{ $tag->slug }}" class="{{ $inputClass }}">
                        <select name="status_key" class="{{ $inputClass }}">
                            @foreach($demandStatusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($tag->status_key === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="sla_hours" class="{{ $inputClass }}">
                            @foreach($demandTagSlaOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) ($tag->sla_hours ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-3 dark:border-gray-700 dark:bg-gray-900">
                            <input type="color" name="color_hex" value="{{ $tag->color_hex }}" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $tag->color_hex }}</span>
                        </div>
                        <input name="portal_label" value="{{ $tag->portal_label }}" placeholder="Nome exibido no portal" class="{{ $inputClass }} xl:col-span-2">
                        <input type="number" name="sort_order" value="{{ $tag->sort_order }}" class="{{ $inputClass }}">
                        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="show_on_portal" value="1" @checked($tag->show_on_portal)>
                            Mostrar no portal
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="is_default" value="1" @checked($tag->is_default)>
                            Padrao inicial
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="is_closing" value="1" @checked($tag->is_closing)>
                            Encerra demanda
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="is_active" value="1" @checked($tag->is_active)>
                            Ativa
                        </label>
                        <div class="flex gap-2 xl:col-span-6">
                            <button class="{{ $buttonClass }}">Salvar tag</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('config.demand-tags.delete', $tag) }}" class="mt-3">
                        @csrf
                        <button class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600" onclick="return confirm('Excluir esta tag de demanda?')">Excluir tag</button>
                    </form>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nenhuma tag cadastrada.</div>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Como o SLA funciona</h3>
        <div class="mt-4 space-y-4 text-sm leading-6 text-gray-600 dark:text-gray-300">
            <p>Cada tag pode recalcular o prazo da demanda em horas corridas. Se nao houver SLA, a demanda fica fora dos alertas.</p>
            <p>O alerta "a vencer" aparece quando resta menos de 10% do tempo total da tag. Demandas concluidas ou canceladas saem da contagem de SLA.</p>
            <p>Quando a tag esta marcada como visivel no portal, o cliente ve o nome publico configurado. Tags internas continuam atualizando o fluxo interno sem expor detalhes estrategicos.</p>
        </div>
        <div class="mt-5 rounded-2xl border border-dashed border-[#941415]/30 bg-[#941415]/5 p-4 text-sm text-[#941415] dark:text-red-200">
            Sugestao pratica: use cores fortes para gargalos, como amarelo para triagem, vermelho para risco e verde para concluido.
        </div>
    </div>
</div>
