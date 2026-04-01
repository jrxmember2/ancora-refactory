@extends('layouts.fullscreen-layout')

@section('content')
<div class="min-h-screen bg-white px-6 py-10 dark:bg-gray-900">
    <div class="mx-auto flex min-h-[80vh] max-w-md items-center">
        <div class="w-full">
            <div class="mb-6 flex justify-center">
                <img src="{{ $ancoraBrand['logo_light'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="w-auto dark:hidden" style="height: {{ max(36, (int) ($ancoraBrand['logo_height_login'] ?? 82)) }}px" />
                <img src="{{ $ancoraBrand['logo_dark'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="hidden w-auto dark:block" style="height: {{ max(36, (int) ($ancoraBrand['logo_height_login'] ?? 82)) }}px" />
            </div>
            <div class="w-full rounded-3xl border border-gray-200 bg-white p-8 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900/70">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Recuperar senha</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Informe seu e-mail. Se existir uma conta cadastrada, enviaremos as instruções para redefinir a senha.</p>
                </div>
                <form method="post" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-gray-900 dark:border-gray-700 dark:text-white" required>
                    </div>
                    <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Enviar instruções</button>
                    <a href="{{ route('login') }}" class="block text-center text-sm font-medium text-brand-600 dark:text-brand-400">Voltar ao login</a>
                </form>
            </div>
            <div class="mt-5 flex items-center justify-between gap-4 px-2 text-xs text-gray-500 dark:text-gray-400">
                <div>
                    <div class="font-semibold text-gray-700 dark:text-gray-200">{{ $ancoraBrand['company_name'] ?? ($ancoraBrand['app_name'] ?? 'Âncora') }}</div>
                    <div class="mt-1">Powered by <a href="https://serratech.tec.br" target="_blank" rel="noopener noreferrer" class="text-brand-600 dark:text-brand-400">Serratech Soluções em TI</a></div>
                </div>
                <a href="https://wa.me/5527997232877" target="_blank" rel="noopener noreferrer" class="inline-flex size-10 items-center justify-center rounded-full border border-gray-200 text-gray-600 hover:border-brand-300 hover:text-brand-600 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-700 dark:hover:text-brand-300" title="Suporte Âncora">
                    <i class="fa-brands fa-whatsapp"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
