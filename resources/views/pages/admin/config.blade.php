@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
@endphp

@section('content')
<div class="space-y-8" x-data="configPage()">
    <x-ancora.section-header title="Configurações" subtitle="Branding, módulos, catálogos auxiliares, usuários, perfis de acesso e SMTP do sistema." />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3" id="branding-section">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Branding</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Nome, slogan, contatos e logos do sistema.</p>
                    <p class="mt-2 text-xs text-amber-600 dark:text-amber-300">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        No EasyPanel, mantenha um volume persistente em
                        <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">/var/www/html/public/assets/uploads/branding</code>
                        para não perder as imagens ao redeploy.
                    </p>
                </div>
            </div>

            <form method="post" action="{{ route('config.branding.save') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Empresa</label>
                        <input name="company_name" value="{{ $branding['company_name'] }}" class="{{ $inputClass }}">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Slogan</label>
                        <input name="app_slogan" value="{{ $branding['app_slogan'] }}" class="{{ $inputClass }}" placeholder="Seu slogan institucional">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone</label>
                        <input name="company_phone" value="{{ $branding['company_phone'] }}" class="{{ $inputClass }}" data-phone-mask placeholder="(27) 99999-9999">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                        <input type="email" name="company_email" value="{{ $branding['company_email'] }}" class="{{ $inputClass }}">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Endereço</label>
                        <input name="company_address" value="{{ $branding['company_address'] }}" class="{{ $inputClass }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800" data-file-preview>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">Logo clara</div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Exibida no tema claro.</p>
                            </div>
                            <img src="{{ $branding['logo_light_url'] }}" alt="Logo clara atual" class="h-14 w-auto rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Alterar logo clara</span>
                                <input type="file" name="branding_logo_light" class="sr-only" data-file-input>
                            </label>
                            <span class="text-xs text-gray-500 dark:text-gray-400" data-file-name>{{ basename($branding['logo_light_path']) }}</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800" data-file-preview>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">Logo escura</div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Exibida no tema escuro.</p>
                            </div>
                            <img src="{{ $branding['logo_dark_url'] }}" alt="Logo escura atual" class="h-14 w-auto rounded-xl border border-gray-200 bg-gray-900 p-2 dark:border-gray-700 dark:bg-gray-800">
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Alterar logo escura</span>
                                <input type="file" name="branding_logo_dark" class="sr-only" data-file-input>
                            </label>
                            <span class="text-xs text-gray-500 dark:text-gray-400" data-file-name>{{ basename($branding['logo_dark_path']) }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Altura desktop</label>
                        <input type="number" name="logo_height_desktop" value="{{ $branding['logo_height_desktop'] }}" class="{{ $inputClass }}">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Altura mobile</label>
                        <input type="number" name="logo_height_mobile" value="{{ $branding['logo_height_mobile'] }}" class="{{ $inputClass }}">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Altura login</label>
                        <input type="number" name="logo_height_login" value="{{ $branding['logo_height_login'] }}" class="{{ $inputClass }}">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Logo premium</label>
                        <select name="premium_logo_variant" class="{{ $inputClass }}">
                            <option value="light" @selected($branding['premium_logo_variant'] === 'light')>Logo clara</option>
                            <option value="dark" @selected($branding['premium_logo_variant'] === 'dark')>Logo escura</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="{{ $buttonClass }}">Salvar branding</button>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" data-file-preview>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Favicon</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ícone exibido na aba do navegador.</p>

                <form method="post" action="{{ route('config.favicon.save') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf

                    <div class="flex items-center gap-4">
                        <img src="{{ $branding['favicon_url'] }}" alt="Favicon atual" class="h-14 w-14 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
                        <div>
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                <i class="fa-solid fa-image"></i>
                                <span>Inserir novo favicon</span>
                                <input type="file" name="branding_favicon" class="sr-only" data-file-input>
                            </label>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>{{ basename($branding['favicon_path']) }}</div>
                        </div>
                    </div>

                    <button type="submit" class="{{ $buttonClass }} w-full">Salvar favicon</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" id="modules-section">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Módulos habilitados</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Defina quais módulos ficam ativos no hub.</p>

                <form method="post" action="{{ route('config.modules.save') }}" class="mt-5 space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach($modules as $module)
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    name="enabled_modules[]"
                                    value="{{ $module->id }}"
                                    class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                    @checked($module->is_enabled)
                                    @disabled(in_array($module->slug, ['dashboard', 'propostas', 'config'], true))
                                >
                                <span>
                                    <span class="block font-medium">{{ $module->name }}</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $module->slug }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <button type="submit" class="{{ $buttonClass }} w-full">Salvar módulos</button>
                </form>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3" id="catalog-section">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Serviços</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cadastro sem recarregar a página inteira.</p>

            <form
                method="post"
                action="{{ route('config.servicos.store') }}"
                class="mt-5 space-y-3 js-async-form"
                data-refresh-target="#catalog-section"
                data-reset-on-success="true"
            >
                @csrf
                <input name="name" placeholder="Nome do serviço" class="{{ $inputClass }}" required>
                <input name="description" placeholder="Descrição" class="{{ $inputClass }}">
                <button type="submit" class="{{ $buttonClass }} w-full">Adicionar serviço</button>
            </form>

            <div class="mt-5 space-y-3">
                @foreach($servicos as $item)
                    <form
                        method="post"
                        action="{{ route('config.servicos.update', $item) }}"
                        class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 js-async-form"
                        data-refresh-target="#catalog-section"
                    >
                        @csrf
                        @method('PUT')

                        <div class="space-y-3">
                            <input name="name" value="{{ $item->name }}" class="{{ $inputClass }}" required>
                            <input name="description" value="{{ $item->description }}" class="{{ $inputClass }}">
                        </div>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="{{ $buttonClass }} flex-1">Salvar</button>
                            <button
                                type="submit"
                                formaction="{{ route('config.servicos.delete', $item) }}"
                                formmethod="POST"
                                data-http-method="DELETE"
                                class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600"
                            >
                                Excluir
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Status</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Respostas, recusas e fechamento comercial.</p>

            <form
                method="post"
                action="{{ route('config.status.store') }}"
                class="mt-5 space-y-3 js-async-form"
                data-refresh-target="#catalog-section"
                data-reset-on-success="true"
            >
                @csrf

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <input name="system_key" placeholder="Chave" class="{{ $inputClass }}" required>
                    <input name="name" placeholder="Nome" class="{{ $inputClass }}" required>
                </div>

                <input name="color_hex" value="#999999" class="{{ $inputClass }}">

                <div class="grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <label class="flex items-center gap-2"><input type="checkbox" name="requires_closed_value" value="1"> Exige valor fechado</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="requires_refusal_reason" value="1"> Exige motivo de recusa</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="stop_followup_alert" value="1"> Interrompe follow-up</label>
                </div>

                <button type="submit" class="{{ $buttonClass }} w-full">Adicionar status</button>
            </form>

            <div class="mt-5 space-y-3">
                @foreach($statusRetorno as $item)
                    <form
                        method="post"
                        action="{{ route('config.status.update', $item) }}"
                        class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 js-async-form"
                        data-refresh-target="#catalog-section"
                    >
                        @csrf
                        @method('PUT')

                        <div class="mb-3 flex items-center gap-3">
                            <span class="h-4 w-4 rounded-full border border-white/50" style="background-color: {{ $item->color_hex }}"></span>
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $item->name }}</span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <input name="system_key" value="{{ $item->system_key }}" class="{{ $inputClass }}">
                            <input name="name" value="{{ $item->name }}" class="{{ $inputClass }}">
                        </div>

                        <input name="color_hex" value="{{ $item->color_hex }}" class="{{ $inputClass }} mt-3">

                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <label class="flex items-center gap-2"><input type="checkbox" name="requires_closed_value" value="1" @checked($item->requires_closed_value)> Exige valor fechado</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="requires_refusal_reason" value="1" @checked($item->requires_refusal_reason)> Exige motivo de recusa</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="stop_followup_alert" value="1" @checked($item->stop_followup_alert)> Interrompe follow-up</label>
                        </div>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="{{ $buttonClass }} flex-1">Salvar</button>
                            <button
                                type="submit"
                                formaction="{{ route('config.status.delete', $item) }}"
                                formmethod="POST"
                                data-http-method="DELETE"
                                class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600"
                            >
                                Excluir
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Formas de envio</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Com preview imediato do ícone escolhido.</p>

            <form
                method="post"
                action="{{ route('config.formas.store') }}"
                class="mt-5 space-y-3 js-async-form"
                data-refresh-target="#catalog-section"
                data-reset-on-success="true"
                x-data="iconPreview('fa-solid fa-envelope', '#2563EB')"
            >
                @csrf
                <input name="name" placeholder="Forma de envio" class="{{ $inputClass }}" required>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
                    <input name="icon_class" x-model="icon" placeholder="fa-solid fa-envelope" class="{{ $inputClass }}" required>
                    <div class="flex items-center justify-center rounded-xl border border-gray-200 px-4 text-xl dark:border-gray-700" :style="`color:${color}`">
                        <i :class="icon"></i>
                    </div>
                </div>

                <input name="color_hex" x-model="color" class="{{ $inputClass }}" value="#2563EB">
                <button type="submit" class="{{ $buttonClass }} w-full">Adicionar forma</button>
            </form>

            <div class="mt-5 space-y-3">
                @foreach($formasEnvio as $item)
                    <form
                        method="post"
                        action="{{ route('config.formas.update', $item) }}"
                        class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 js-async-form"
                        data-refresh-target="#catalog-section"
                        x-data="iconPreview('{{ $item->icon_class }}', '{{ $item->color_hex }}')"
                    >
                        @csrf
                        @method('PUT')

                        <div class="mb-3 flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl border border-gray-200 dark:border-gray-700" :style="`color:${color}`">
                                <i :class="icon"></i>
                            </span>
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $item->name }}</span>
                        </div>

                        <input name="name" value="{{ $item->name }}" class="{{ $inputClass }}">

                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
                            <input name="icon_class" x-model="icon" class="{{ $inputClass }}">
                            <div class="flex items-center justify-center rounded-xl border border-gray-200 px-4 text-xl dark:border-gray-700" :style="`color:${color}`">
                                <i :class="icon"></i>
                            </div>
                        </div>

                        <input name="color_hex" x-model="color" class="{{ $inputClass }} mt-3">

                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="{{ $buttonClass }} flex-1">Salvar</button>
                            <button
                                type="submit"
                                formaction="{{ route('config.formas.delete', $item) }}"
                                formmethod="POST"
                                data-http-method="DELETE"
                                class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600"
                            >
                                Excluir
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3" id="smtp-section">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-2">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Usuários, módulos e permissões por rota</h3>

            <form method="post" action="{{ route('config.users.store') }}" class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                @csrf

                <input name="name" placeholder="Nome" class="{{ $inputClass }}" required>
                <input type="email" name="email" placeholder="email@dominio.com" class="{{ $inputClass }}" required>

                <div class="relative" x-data="{ show:false }">
                    <input :type="show ? 'text' : 'password'" name="password" placeholder="Senha" class="{{ $inputClass }} pr-11" required>
                    <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>

                <select name="access_mode" class="{{ $inputClass }}">
                    <option value="comum">Comum</option>
                    <option value="superadmin">Superadmin</option>
                    @if(count($accessProfiles))
                        <optgroup label="Perfis de acesso">
                            @foreach($accessProfiles as $profile)
                                <option value="profile:{{ $profile['slug'] }}">{{ $profile['name'] }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>

                <div class="flex items-center rounded-xl border border-dashed border-gray-300 px-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Escolha o tipo de acesso ou um perfil salvo.
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_active" value="1" checked> Ativo
                </label>

                <div class="md:col-span-2 xl:col-span-5">
                    <button type="submit" class="{{ $buttonClass }}">Cadastrar usuário</button>
                </div>
            </form>

            <div class="mt-6 space-y-5">
                @foreach($users as $user)
                    @php($isSuper = $user->role === 'superadmin')

                    <form
                        method="post"
                        action="{{ route('config.users.update', $user) }}"
                        class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800"
                        x-data="{
                            markAllModules() {
                                $el.querySelectorAll('[data-modules] input[type=checkbox]:not(:disabled)').forEach(el => el.checked = true)
                            },
                            markAllRoutes() {
                                $el.querySelectorAll('[data-routes] input[type=checkbox]:not(:disabled)').forEach(el => el.checked = true)
                            }
                        }"
                    >
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                            <input name="name" value="{{ $user->name }}" class="{{ $inputClass }}" required>
                            <input type="email" name="email" value="{{ $user->email }}" class="{{ $inputClass }}" required>

                            <div class="relative" x-data="{ show:false }">
                                <input :type="show ? 'text' : 'password'" name="password" placeholder="Nova senha (opcional)" class="{{ $inputClass }} pr-11">
                                <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    <i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>

                            <select name="access_mode" class="{{ $inputClass }}">
                                <option value="comum" @selected(($user->access_mode_value ?? 'comum')==='comum')>Comum</option>
                                <option value="superadmin" @selected(($user->access_mode_value ?? '')==='superadmin')>Superadmin</option>
                                @if(count($accessProfiles))
                                    <optgroup label="Perfis de acesso">
                                        @foreach($accessProfiles as $profile)
                                            <option value="profile:{{ $profile['slug'] }}" @selected(($user->access_mode_value ?? '')==='profile:'.$profile['slug'])>{{ $profile['name'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>

                            <div class="flex items-center rounded-xl border border-dashed border-gray-300 px-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Use superadmin, comum ou um perfil salvo.
                            </div>

                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="is_active" value="1" @checked($user->is_active) @disabled($user->is_protected)> Ativo
                            </label>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div data-modules>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Módulos</h4>
                                    @unless($isSuper)
                                        <button type="button" @click="markAllModules()" class="text-xs font-medium text-brand-600 dark:text-brand-400">Marcar todos</button>
                                    @endunless
                                </div>

                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    @foreach($modules as $module)
                                        <label class="flex items-start gap-2 rounded-xl border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                            <input type="checkbox" name="module_permissions[]" value="{{ $module->id }}" @checked($isSuper || $user->modules->contains('id', $module->id)) @disabled($isSuper)>
                                            <span>{{ $module->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div data-routes>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Permissões por rota</h4>
                                    @unless($isSuper)
                                        <button type="button" @click="markAllRoutes()" class="text-xs font-medium text-brand-600 dark:text-brand-400">Marcar todos</button>
                                    @endunless
                                </div>

                                <div class="max-h-96 space-y-4 overflow-auto rounded-2xl border border-gray-200 p-3 dark:border-gray-800">
                                    @foreach($routeCatalog as $groupKey => $group)
                                        <div>
                                            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $group['label'] }}</div>
                                            <div class="grid grid-cols-1 gap-2">
                                                @foreach(($routePermissionGroups[$groupKey] ?? collect()) as $permission)
                                                    <label class="flex items-start gap-2 rounded-xl border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                                        <input type="checkbox" name="route_permissions[]" value="{{ $permission->id }}" @checked($isSuper || $user->routePermissions->contains('id', $permission->id)) @disabled($isSuper)>
                                                        <span>
                                                            <span class="block">{{ $permission->label }}</span>
                                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $permission->route_name }}</span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <button type="submit" class="{{ $buttonClass }}">Salvar usuário</button>
                            @if(!$user->is_protected)
                                <button
                                    type="submit"
                                    formaction="{{ route('config.users.delete', $user) }}"
                                    formmethod="POST"
                                    data-http-method="DELETE"
                                    onclick="return confirm('Excluir usuário?')"
                                    class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600"
                                >
                                    Excluir
                                </button>
                            @endif
                        </div>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Perfis de acesso</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monte combinações de módulos e rotas para aplicar rapidamente a um usuário.</p>

                <form method="post" action="{{ route('config.access-profiles.save') }}" class="mt-5 space-y-3">
                    @csrf

                    <input name="profile_name" placeholder="Nome do perfil" class="{{ $inputClass }}" required>
                    <input name="profile_slug" placeholder="Slug do perfil" class="{{ $inputClass }}" required>

                    <div>
                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Módulos do perfil</div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach($modules as $module)
                                <label class="flex items-center gap-2 rounded-xl border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                    <input type="checkbox" name="profile_modules[]" value="{{ $module->id }}"> {{ $module->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Rotas do perfil</div>
                        <div class="max-h-60 space-y-2 overflow-auto rounded-2xl border border-gray-200 p-3 dark:border-gray-800">
                            @foreach($routeCatalog as $groupKey => $group)
                                <div>
                                    <div class="mb-1 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $group['label'] }}</div>
                                    @foreach(($routePermissionGroups[$groupKey] ?? collect()) as $permission)
                                        <label class="mb-2 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="profile_routes[]" value="{{ $permission->id }}">
                                            <span>{{ $permission->label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <button type="submit" class="{{ $buttonClass }} w-full">Salvar perfil</button>
                </form>

                <div class="mt-5 space-y-3">
                    @forelse($accessProfiles as $profile)
                        <form method="post" action="{{ route('config.access-profiles.delete', $profile['slug']) }}" class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            @csrf
                            @method('DELETE')

                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $profile['name'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $profile['slug'] }}</div>
                                </div>
                                <button type="submit" class="rounded-xl border border-error-300 px-3 py-2 text-xs font-medium text-error-600">Excluir</button>
                            </div>
                        </form>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Nenhum perfil de acesso cadastrado.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">SMTP e recuperação de senha</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure o envio de e-mails do sistema. O fluxo de esqueci a senha responde sempre de forma genérica por segurança.</p>

                <form method="post" action="{{ route('config.smtp.save') }}" class="mt-5 space-y-3">
                    @csrf

                    <input name="smtp_host" value="{{ $smtp['host'] }}" placeholder="Host SMTP" class="{{ $inputClass }}">

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input type="number" name="smtp_port" value="{{ $smtp['port'] }}" placeholder="Porta" class="{{ $inputClass }}">
                        <select name="smtp_encryption" class="{{ $inputClass }}">
                            <option value="tls" @selected(($smtp['encryption'] ?? '') === 'tls')>TLS</option>
                            <option value="ssl" @selected(($smtp['encryption'] ?? '') === 'ssl')>SSL</option>
                            <option value="" @selected(($smtp['encryption'] ?? '') === '')>Sem criptografia</option>
                        </select>
                    </div>

                    <input name="smtp_username" value="{{ $smtp['username'] }}" placeholder="Usuário SMTP" class="{{ $inputClass }}">

                    <div class="relative" x-data="{ show:false }">
                        <input :type="show ? 'text' : 'password'" name="smtp_password" value="{{ $smtp['password'] }}" placeholder="Senha SMTP" class="{{ $inputClass }} pr-11">
                        <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input type="email" name="smtp_from_address" value="{{ $smtp['from_address'] }}" placeholder="Remetente" class="{{ $inputClass }}">
                        <input name="smtp_from_name" value="{{ $smtp['from_name'] }}" placeholder="Nome do remetente" class="{{ $inputClass }}">
                    </div>

                    <button type="submit" class="{{ $buttonClass }} w-full">Salvar SMTP</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function configPage() {
    return { toast() {} }
}

function iconPreview(defaultIcon, defaultColor) {
    return { icon: defaultIcon, color: defaultColor }
}

function showConfigToast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `fixed right-6 top-6 z-[999999] rounded-2xl px-4 py-3 text-sm font-medium shadow-theme-lg ${type === 'error' ? 'bg-error-500 text-white' : 'bg-success-500 text-white'}`;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2200);
}

function applyPhoneMask(input) {
    let value = input.value.replace(/\D/g, '').slice(0, 11);

    if (value.length > 10) {
        value = value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    } else if (value.length > 6) {
        value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else if (value.length > 2) {
        value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    }

    input.value = value.trim();
}

function refreshConfigSection(targetSelector, html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const fresh = doc.querySelector(targetSelector);
    const current = document.querySelector(targetSelector);

    if (fresh && current) {
        current.replaceWith(fresh);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-phone-mask]').forEach((input) => applyPhoneMask(input));
});

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-phone-mask]')) {
        applyPhoneMask(event.target);
    }
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-file-input]')) {
        const wrapper = event.target.closest('[data-file-preview]');
        const label = wrapper?.querySelector('[data-file-name]');

        if (label && event.target.files?.length) {
            label.textContent = event.target.files[0].name;
        }
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.js-async-form');
    if (!form) return;

    event.preventDefault();

    const submitter = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
    const rawButtonAction = submitter?.getAttribute('formaction');
    const rawFormAction = form.getAttribute('action');
    const action = rawButtonAction && rawButtonAction.trim() !== '' ? rawButtonAction : rawFormAction;

    const rawButtonMethod = submitter?.getAttribute('formmethod');
    const rawFormMethod = form.getAttribute('method') || 'POST';
    const requestMethod = (rawButtonMethod && rawButtonMethod.trim() !== '' ? rawButtonMethod : rawFormMethod).toUpperCase();

    const targetSelector = form.dataset.refreshTarget;
    const resetOnSuccess = form.dataset.resetOnSuccess === 'true';

    if (!action) {
        showConfigToast('Formulário sem rota de envio.', 'error');
        return;
    }

    if (submitter) submitter.disabled = true;

    try {
        const body = new FormData(form);

        if (submitter?.name) {
            body.append(submitter.name, submitter.value ?? '');
        }

        const buttonHttpMethod = submitter?.dataset.httpMethod;
        if (buttonHttpMethod) {
            body.set('_method', buttonHttpMethod.toUpperCase());
        }

        const response = await fetch(action, {
            method: requestMethod,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            let message = 'Não foi possível concluir esta ação agora.';
            try {
                const data = await response.json();
                if (data?.message) {
                    message = data.message;
                } else if (data?.errors) {
                    const firstError = Object.values(data.errors)[0];
                    if (Array.isArray(firstError) && firstError.length) {
                        message = firstError[0];
                    }
                }
            } catch (_) {}
            throw new Error(message);
        }

        const htmlResponse = await fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        const html = await htmlResponse.text();

        if (targetSelector) {
            refreshConfigSection(targetSelector, html);
        }

        if (resetOnSuccess) {
            form.reset();
        }

        const successMessage = submitter?.textContent?.trim()?.includes('Excluir')
            ? 'Registro excluído com sucesso.'
            : 'Registro salvo com sucesso.';

        showConfigToast(successMessage);
    } catch (error) {
        showConfigToast(error.message || 'Não foi possível concluir esta ação agora.', 'error');
    } finally {
        if (submitter) submitter.disabled = false;
    }
});
</script>
@endpush