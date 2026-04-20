@extends('portal.layouts.app')

@section('content')
<div class="mx-auto max-w-xl rounded-3xl border border-[#eadfd5] bg-white p-8 shadow-sm">
    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Primeiro acesso</p>
    <h1 class="mt-3 text-2xl font-semibold text-gray-950">Crie uma nova senha</h1>
    <p class="mt-2 text-sm text-gray-500">Para manter sua conta segura, atualize a senha inicial antes de continuar.</p>
    <form method="post" action="{{ route('portal.password.update') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Nova senha</label>
            <input type="password" name="password" required class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415] focus:ring-4 focus:ring-[#941415]/10">
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700">Confirmar nova senha</label>
            <input type="password" name="password_confirmation" required class="h-12 w-full rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415] focus:ring-4 focus:ring-[#941415]/10">
        </div>
        <button class="h-12 rounded-2xl bg-[#941415] px-6 text-sm font-semibold text-white">Atualizar senha</button>
    </form>
</div>
@endsection
