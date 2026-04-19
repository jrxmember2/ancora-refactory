@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 dark:bg-gray-900 sm:p-0">
    <div class="relative flex h-screen w-full flex-col justify-center lg:flex-row dark:bg-gray-900">
        <div class="relative flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4">
                <div class="mb-8">
                    <img src="{{ $ancoraBrand['logo_light'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="w-auto dark:hidden" style="height: {{ max(36, (int) ($ancoraBrand['logo_height_login'] ?? 82)) }}px" />
                    <img src="{{ $ancoraBrand['logo_dark'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="hidden w-auto dark:block" style="height: {{ max(36, (int) ($ancoraBrand['logo_height_login'] ?? 82)) }}px" />
                </div>

                <form method="post" action="/login" class="space-y-5">
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
                            <button type="button" @click="showPassword = !showPassword" class="absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <button class="flex w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white transition hover:bg-brand-600">Entrar</button>
                </form>
            </div>

            <div class="absolute bottom-4 left-4 z-20">
                <div class="flex flex-col items-start gap-1">
                    <a href="https://www.serratech.tec.br" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white/95 px-3 py-2 text-[11px] font-medium text-gray-700 shadow-theme-xs backdrop-blur hover:text-brand-600 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-300 dark:hover:text-brand-400">
                        <span>Powered by Serratech</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    </a>
                    <div class="pl-1 text-[9px] leading-none tracking-[0.14em] text-gray-400 dark:text-gray-500">{{ $ancoraVersion['label'] ?? 'v1.28 • 19/04/2026' }}</div>
                </div>
            </div>
        </div>

        <div class="relative hidden h-full w-full items-center overflow-hidden lg:grid lg:w-1/2 dark:bg-white/5 bg-brand-950">
            <x-common.common-grid-shape />
            <div class="relative z-10 mx-auto max-w-xl px-10 text-white">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-xs uppercase tracking-[0.3em] text-white/80">
                    <i class="fa-solid fa-layer-group"></i>
                    Ecossistema Âncora
                </div>
                <h2 class="mt-6 text-3xl font-semibold leading-tight">Gestão jurídica e condominial em um único ambiente, com cadastros organizados, propostas profissionais e rotina mais fluida.</h2>
                <p class="mt-4 text-base text-white/70">Estruture clientes, condomínios, unidades, documentos e permissões em uma base pensada para apoiar a operação do seu escritório.</p>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-lg font-semibold">Cadastros organizados</div><p class="mt-1 text-sm text-white/70">Clientes, síndicos, administradoras, unidades e documentos com mais clareza no dia a dia.</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-lg font-semibold">Propostas profissionais</div><p class="mt-1 text-sm text-white/70">Fluxo comercial com histórico, anexos e documento premium sem retrabalho.</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-lg font-semibold">Acesso controlado</div><p class="mt-1 text-sm text-white/70">Perfis, permissões e mais segurança para trabalhar em equipe.</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"><div class="text-lg font-semibold">Operação mais profissional</div><p class="mt-1 text-sm text-white/70">Mais organização, previsibilidade e valor percebido para o escritório.</p></div>
                </div>
            </div>

            <div class="absolute bottom-4 right-4 z-20">
                <button class="inline-flex size-12 items-center justify-center rounded-full bg-brand-500 text-white shadow-theme-lg hover:bg-brand-600" @click.prevent="$store.theme.toggle()"><i class="fa-solid fa-circle-half-stroke"></i></button>
            </div>
        </div>
    </div>
</div>
@endsection
