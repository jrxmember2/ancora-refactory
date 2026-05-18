@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $labelClass = 'mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300';
    $selectedCondominiums = collect(old('client_condominium_ids', $portalUser?->accessibleCondominiumIds() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $avatarUrl = $portalUser?->avatar_url;
@endphp

<dialog id="{{ $modalId }}" class="fixed inset-0 m-auto w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <form method="post" action="{{ $action }}" class="p-6" enctype="multipart/form-data">
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
            <div class="md:col-span-2 rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950/40">
                <div class="flex flex-col gap-4 md:flex-row md:items-center">
                    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-3xl bg-brand-500 text-xl font-semibold text-white">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $portalUser?->name ?: 'Avatar' }}" class="h-full w-full object-cover">
                        @else
                            {{ $portalUser?->initials ?? 'NC' }}
                        @endif
                    </div>
                    <div class="flex-1">
                        <label class="{{ $labelClass }}">Foto do usuario</label>
                        <input type="file" name="avatar" accept=".png,.jpg,.jpeg,.webp" class="{{ $inputClass }} h-auto py-3">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opcional. Se enviar uma nova imagem, ela substitui a foto atual do cadastro.</p>
                    </div>
                </div>
            </div>
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
                <label class="{{ $labelClass }}">Data de nascimento</label>
                <input type="date" name="birth_date" value="{{ old('birth_date', $portalUser?->birth_date?->format('Y-m-d')) }}" class="{{ $inputClass }}">
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

        <div class="mt-6 rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
            <div class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">IA do portal</div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="hidden" name="ai_enabled" value="0">
                        <input type="checkbox" name="ai_enabled" value="1" @checked((bool) old('ai_enabled', $portalUser?->ai_enabled ?? false))>
                        IA habilitada
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Libera o futuro Chat do Sindico para este usuario quando a IA global tambem estiver ativa.</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Limite mensal de perguntas</label>
                    <input type="number" name="ai_monthly_question_limit" min="0" value="{{ old('ai_monthly_question_limit', $portalUser?->ai_monthly_question_limit) }}" class="{{ $inputClass }}" placeholder="Em branco = ilimitado">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use zero para bloquear novas consultas mesmo com a IA habilitada.</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Perguntas utilizadas no mes atual</label>
                    <input type="number" name="ai_questions_used_current_month" min="0" value="{{ old('ai_questions_used_current_month', $portalUser?->ai_questions_used_current_month ?? 0) }}" class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Campo editavel pelo administrador para ajustes manuais.</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Data do ultimo reset</label>
                    <input type="date" name="ai_usage_reset_at" value="{{ old('ai_usage_reset_at', $portalUser?->ai_usage_reset_at?->format('Y-m-d')) }}" class="{{ $inputClass }}">
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $labelClass }}">Observacao interna</label>
                    <textarea name="ai_internal_note" rows="4" class="{{ $textareaClass }}" placeholder="Observacao visivel apenas no painel interno.">{{ old('ai_internal_note', $portalUser?->ai_internal_note) }}</textarea>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
            <button type="submit" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar</button>
        </div>
    </form>
</dialog>
