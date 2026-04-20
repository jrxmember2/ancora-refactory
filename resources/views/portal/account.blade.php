@extends('portal.layouts.app')

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Minha Conta</p>
    <h1 class="mt-2 text-3xl font-semibold text-gray-950">Dados de acesso</h1>
</div>

<section class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-950">Informações</h2>
        <dl class="mt-5 space-y-4 text-sm">
            <div><dt class="text-gray-500">Nome</dt><dd class="mt-1 font-semibold text-gray-950">{{ $portalUser->name }}</dd></div>
            <div><dt class="text-gray-500">Chave de acesso</dt><dd class="mt-1 font-semibold text-gray-950">{{ $portalUser->login_key }}</dd></div>
            <div><dt class="text-gray-500">E-mail</dt><dd class="mt-1 font-semibold text-gray-950">{{ $portalUser->email ?: 'Não informado' }}</dd></div>
            <div><dt class="text-gray-500">Telefone</dt><dd class="mt-1 font-semibold text-gray-950">{{ $portalUser->phone ?: 'Não informado' }}</dd></div>
            <div><dt class="text-gray-500">Último acesso</dt><dd class="mt-1 font-semibold text-gray-950">{{ $portalUser->last_login_at?->format('d/m/Y H:i') ?: 'Não informado' }}</dd></div>
        </dl>
    </div>
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-950">Alterar senha</h2>
        <form method="post" action="{{ route('portal.account.password') }}" class="mt-5 space-y-4">
            @csrf
            <input type="password" name="current_password" required placeholder="Senha atual" class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
            <input type="password" name="password" required placeholder="Nova senha" class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
            <input type="password" name="password_confirmation" required placeholder="Confirmar nova senha" class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
            <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Salvar senha</button>
        </form>
    </div>
</section>
@endsection
