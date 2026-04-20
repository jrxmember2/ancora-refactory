<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal do Cliente' }} | {{ $ancoraBrand['app_name'] ?? 'Âncora' }}</title>
    @include('layouts.partials.asset-loader')
    <link rel="icon" href="{{ $ancoraBrand['favicon'] ?? '/favicon.ico' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="min-h-screen bg-[#f7f2ec]">
    <main class="grid min-h-screen grid-cols-1 lg:grid-cols-[1fr,0.9fr]">
        <section class="flex items-center justify-center px-6 py-10">
            <div class="w-full max-w-md rounded-[2rem] border border-[#eadfd5] bg-white p-8 shadow-2xl shadow-[#941415]/10">
                <img src="{{ $ancoraBrand['logo_light'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="mb-8 h-16 w-auto">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Portal do Cliente</p>
                    <h1 class="mt-3 text-3xl font-semibold text-gray-950">Acesse sua área segura</h1>
                    <p class="mt-2 text-sm text-gray-500">Consulte processos, cobranças e solicitações do seu condomínio ou contrato.</p>
                </div>

                @if(session('success'))
                    <div class="mt-6 rounded-2xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mt-6 rounded-2xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700">{{ session('error') }}</div>
                @endif

                <form method="post" action="{{ route('portal.login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Chave de acesso</label>
                        <input name="login_key" value="{{ old('login_key') }}" required autofocus class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none transition focus:border-[#941415] focus:ring-4 focus:ring-[#941415]/10" placeholder="Ex: DOVER2026">
                    </div>
                    <div x-data="{ show: false }">
                        <label class="mb-2 block text-sm font-medium text-gray-700">Senha</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="password" required class="h-12 w-full rounded-2xl border border-gray-200 px-4 pr-12 text-sm outline-none transition focus:border-[#941415] focus:ring-4 focus:ring-[#941415]/10" placeholder="Sua senha">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <i :class="show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                            </button>
                        </div>
                    </div>
                    <button class="h-12 w-full rounded-2xl bg-[#941415] text-sm font-semibold text-white shadow-lg shadow-[#941415]/20 hover:bg-[#7f1011]">Entrar no portal</button>
                    <a href="{{ route('portal.password.request') }}" class="block text-center text-sm font-medium text-[#941415]">Esqueci minha senha</a>
                </form>
            </div>
        </section>
        <section class="hidden items-center bg-[#941415] px-10 text-white lg:flex">
            <div class="mx-auto max-w-lg">
                <div class="rounded-full border border-white/20 px-4 py-2 text-sm text-white/80">Canal oficial de atendimento</div>
                <h2 class="mt-8 text-5xl font-semibold leading-tight">Tudo que importa, em um só lugar.</h2>
                <p class="mt-6 text-lg leading-8 text-white/80">Acompanhe demandas, andamentos públicos e informações executivas sem depender de mensagens soltas.</p>
                <div class="mt-10 grid grid-cols-3 gap-4 text-center">
                    <div class="rounded-3xl bg-white/10 p-4"><i class="fa-solid fa-scale-balanced text-2xl"></i><div class="mt-2 text-xs">Processos</div></div>
                    <div class="rounded-3xl bg-white/10 p-4"><i class="fa-solid fa-money-bill-wave text-2xl"></i><div class="mt-2 text-xs">Cobranças</div></div>
                    <div class="rounded-3xl bg-white/10 p-4"><i class="fa-solid fa-inbox text-2xl"></i><div class="mt-2 text-xs">Solicitações</div></div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
