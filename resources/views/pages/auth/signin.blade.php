@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 dark:bg-gray-900 sm:p-0">
    <div class="relative flex h-screen w-full flex-col justify-center lg:flex-row dark:bg-gray-900">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto w-full max-w-md pt-10">
                <a href="{{ $ancoraBrand['company_website'] ?? 'https://serratech.tec.br' }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    {{ $ancoraBrand['app_name'] ?? 'Âncora' }}
                </a>
            </div>
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                <div class="mb-8">
                    <img src="{{ $ancoraBrand['logo_light'] ?? '/branding/logo-light.svg' }}" alt="Logo" class="w-auto dark:hidden" style="height: {{ max(36, (int) ($ancoraBrand['logo_height_login'] ?? 82)) }}px" />
                    <img src="{{ $ancoraBrand['logo_dark'] ?? '/branding/logo-dark.svg' }}" alt="Logo" class="hidden w-auto dark:block" style="height: {{ max(36, (int) ($ancoraBrand['logo_height_login'] ?? 82)) }}px" />
                </div>
                <div class="mb-5 sm:mb-8">
                    <h1 class="mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90 sm:text-title-md">Entrar no {{ $ancoraBrand['app_name'] ?? 'Âncora' }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ancoraBrand['slogan'] ?? 'Plataforma modular para gestão jurídica, comercial e condominial.' }}</p>
                </div>
                <form method="post" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">E-mail</label>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="junior@serratech.br" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>
                    <div x-data="{ showPassword: false }">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Senha</label>
                            <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">Esqueci a senha</a>
                        </div>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" name="password" placeholder="Digite sua senha" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            <button type="button" @click="showPassword = !showPassword" class="absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                        </div>
                    </div>
                    <button class="flex w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white transition hover:bg-brand-600">Entrar</button>
                </form>
                <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    <p class="font-semibold">{{ $ancoraBrand['company_name'] ?? 'Âncora' }}</p>
                    <p class="mt-1">{{ $ancoraBrand['slogan'] ?? 'Plataforma modular para gestão jurídica, comercial e condominial.' }}</p>
                </div>
            </div>
        </div>

        <div class="relative hidden h-full w-full items-center overflow-hidden lg:grid lg:w-1/2 dark:bg-white/5 bg-brand-950">
            <x-common.common-grid-shape />
            <div class="relative z-10 mx-auto max-w-xl px-10 text-white">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-xs uppercase tracking-[0.3em] text-white/80">
                    <i class="fa-solid fa-layer-group"></i>
                    Reescrita Big Bang
                </div>
                <h2 class="mt-6 text-4xl font-semibold leading-tight">Base unificada para propostas, clientes, branding e módulos futuros.</h2>
                <p class="mt-4 text-base text-white/70">Agora com branding dinâmico, perfis de acesso, recuperação de senha e base pronta para automações futuras.</p>
                <div class="mt-8 grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-2xl font-semibold">Light + Dark</div><p class="mt-1 text-sm text-white/70">Tema nativo com persistência.</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-2xl font-semibold">Módulos</div><p class="mt-1 text-sm text-white/70">Hub central e shell modular.</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-2xl font-semibold">Deploy</div><p class="mt-1 text-sm text-white/70">Preparado para EasyPanel.</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-2xl font-semibold">Escala</div><p class="mt-1 text-sm text-white/70">Fila, scheduler e novos serviços.</p></div>
                </div>
            </div>
            <button class="absolute right-6 bottom-6 inline-flex size-14 items-center justify-center rounded-full bg-brand-500 text-white shadow-theme-lg hover:bg-brand-600" @click.prevent="$store.theme.toggle()"><i class="fa-solid fa-circle-half-stroke"></i></button>
        </div>
    </div>
</div>
@endsection
