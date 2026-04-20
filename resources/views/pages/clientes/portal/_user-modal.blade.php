@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $labelClass = 'mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300';
    $selectedCondominiums = collect(old('client_condominium_ids', $portalUser?->accessibleCondominiumIds() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<dialog id="{{ $modalId }}" class="fixed inset-0 m-auto w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <form method="post" action="{{ $action }}" class="p-6">
        @csrf
        <input type="hidden" name="_portal_user_modal" value="{{ $modalId }}">
        @if($method !== 'POST')
            @method($method)
        @endif

        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $portalUser ? 'Editar usuario do portal' : 'Novo usuario do portal' }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Defina vinculo, chave de acesso e permissoes externas.</p>
            </div>
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs dark:border-gray-700">Fechar</button>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="{{ $labelClass }}">Nome</label>
                <input name="name" value="{{ old('name', $portalUser?->name) }}" required class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Chave de acesso</label>
                <input name="login_key" value="{{ old('login_key', $portalUser?->login_key) }}" required class="{{ $inputClass }}" placeholder="Ex: DOVER2026">
            </div>
            <div>
                <label class="{{ $labelClass }}">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $portalUser?->email) }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Telefone</label>
                <input name="phone" value="{{ old('phone', $portalUser?->phone) }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Perfil</label>
                <select name="portal_role" class="{{ $inputClass }}">
                    @foreach($roles as $key => $label)
                        <option value="{{ $key }}" @selected(old('portal_role', $portalUser?->portal_role ?? 'sindico') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $labelClass }}">Senha {{ $portalUser ? '(preencha apenas se quiser alterar)' : '' }}</label>
                <input type="password" name="password" @required(!$portalUser) class="{{ $inputClass }}" minlength="8">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimo de 8 caracteres.</p>
            </div>

            <div class="md:col-span-2">
                <label class="{{ $labelClass }}">Condominios vinculados</label>
                <div class="max-h-56 overflow-y-auto rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950/40">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach($condominiums as $condominium)
                            <label class="flex items-start gap-2 rounded-xl bg-white px-3 py-2 text-sm text-gray-700 shadow-theme-xs dark:bg-gray-900 dark:text-gray-200">
                                <input type="checkbox" name="client_condominium_ids[]" value="{{ $condominium->id }}" class="mt-1" @checked(in_array((int) $condominium->id, $selectedCondominiums, true))>
                                <span>{{ $condominium->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pode selecionar mais de um condominio para sindicos profissionais ou administradoras.</p>
            </div>

            <div class="md:col-span-2">
                <label class="{{ $labelClass }}">Cliente avulso / entidade</label>
                <select name="client_entity_id" class="{{ $inputClass }}">
                    <option value="">Sem vinculo</option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) old('client_entity_id', $portalUser?->client_entity_id) === (int) $entity->id)>{{ $entity->display_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
            <div class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Permissoes</div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach([
                    'is_active' => 'Acesso ativo',
                    'must_change_password' => 'Forcar troca de senha',
                    'can_view_processes' => 'Ver processos',
                    'can_view_cobrancas' => 'Ver cobrancas',
                    'can_open_demands' => 'Abrir demandas',
                    'can_view_demands' => 'Ver demandas',
                    'can_view_documents' => 'Ver documentos',
                    'can_view_financial_summary' => 'Resumo financeiro',
                ] as $field => $label)
                    @php($default = in_array($field, ['is_active', 'must_change_password', 'can_view_processes', 'can_view_cobrancas', 'can_open_demands', 'can_view_demands'], true))
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="hidden" name="{{ $field }}" value="0">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked((bool) old($field, $portalUser?->{$field} ?? $default))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
            <button type="submit" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar</button>
        </div>
    </form>
</dialog>
