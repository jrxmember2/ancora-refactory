@extends('layouts.fullscreen-layout')

@section('content')
<div class="min-h-screen bg-white px-6 py-10 dark:bg-gray-900">
    <div class="mx-auto flex min-h-[80vh] max-w-md items-center">
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
    </div>
</div>
@endsection
