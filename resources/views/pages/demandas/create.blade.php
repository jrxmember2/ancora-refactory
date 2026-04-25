@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

@section('content')
<x-ancora.section-header title="Nova demanda" subtitle="Cadastre uma demanda diretamente no backoffice, vinculando cliente, condominio, responsavel e visibilidade no portal quando fizer sentido.">
    <a href="{{ route('demandas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
</x-ancora.section-header>

@if($errors->any())
    <div class="mb-6 rounded-2xl border border-error-200 bg-error-50 p-4 text-sm text-error-700 dark:border-error-900/60 dark:bg-error-500/10 dark:text-error-200">
        Revise os campos destacados antes de salvar a demanda.
    </div>
@endif

<form method="post" action="{{ route('demandas.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,360px]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados da demanda</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Assunto, mensagem inicial, categoria e anexos que vao abrir a demanda.</p>
                    </div>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="publish_to_portal" value="1" @checked(old('publish_to_portal'))>
                        Mensagem inicial visivel no portal
                    </label>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Categoria</label>
                        <select name="category_id" required class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id') === (int) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Prioridade</label>
                        <select name="priority" required class="{{ $inputClass }}">
                            @foreach($priorityLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Assunto</label>
                        <input name="subject" value="{{ old('subject') }}" required maxlength="180" class="{{ $inputClass }}" placeholder="Resumo rapido da demanda">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Mensagem inicial</label>
                        <textarea name="description" rows="8" required class="{{ $textareaClass }}" placeholder="Descreva o contexto, o que precisa ser feito e as observacoes iniciais.">{{ old('description') }}</textarea>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Se a opcao de visibilidade estiver desmarcada, essa mensagem fica interna e nao aparece para o cliente no portal.</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Anexos iniciais</label>
                        <input type="file" name="files[]" multiple class="w-full rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm dark:border-gray-700">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Vinculos com o cliente</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use os vinculos para facilitar o filtro interno e, quando fizer sentido, permitir que a demanda apareca no Portal do Cliente.</p>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Usuario do portal</label>
                        <select name="client_portal_user_id" class="{{ $inputClass }}">
                            <option value="">Nao vincular usuario especifico</option>
                            @foreach($portalUsers as $portalUser)
                                <option value="{{ $portalUser->id }}" @selected((int) old('client_portal_user_id') === (int) $portalUser->id)>
                                    {{ $portalUser->name }} - {{ $portalUser->displayClientName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Condominio</label>
                        <select name="client_condominium_id" class="{{ $inputClass }}">
                            <option value="">Nao vincular condominio</option>
                            @foreach($condominiums as $condominium)
                                <option value="{{ $condominium->id }}" @selected((int) old('client_condominium_id') === (int) $condominium->id)>{{ $condominium->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente / entidade</label>
                        <select name="client_entity_id" class="{{ $inputClass }}">
                            <option value="">Nao vincular entidade</option>
                            @foreach($entities as $entity)
                                <option value="{{ $entity->id }}" @selected((int) old('client_entity_id') === (int) $entity->id)>{{ $entity->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Gestao inicial</h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tag inicial</label>
                        <select name="demand_tag_id" class="{{ $inputClass }}">
                            <option value="">Usar padrao do modulo</option>
                            @foreach($demandTags as $tag)
                                <option value="{{ $tag->id }}" @selected((int) old('demand_tag_id', $defaultTagId) === (int) $tag->id)>{{ $tag->name }}{{ $tag->sla_hours ? ' - '.$tag->sla_hours.'h' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">A tag define o status inicial da demanda e pode iniciar o SLA automaticamente.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Responsavel interno</label>
                        <select name="assigned_user_id" class="{{ $inputClass }}">
                            <option value="">Nao atribuir agora</option>
                            @foreach($users as $internalUser)
                                <option value="{{ $internalUser->id }}" @selected((int) old('assigned_user_id', $defaultAssignedUserId) === (int) $internalUser->id)>{{ $internalUser->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
                <div class="font-semibold text-gray-900 dark:text-white">Como o portal enxerga</div>
                <p class="mt-2">Demandas internas podem ser vinculadas a usuario do portal, condominio ou entidade cliente. Se a mensagem inicial for marcada como visivel, ela ja aparece no historico publico do cliente.</p>
                <p class="mt-2">Se voce preferir usar a demanda apenas internamente, deixe a mensagem inicial como interna e nao vincule nenhum acesso externo.</p>
            </div>
        </aside>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ route('demandas.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar demanda</button>
    </div>
</form>
@endsection
