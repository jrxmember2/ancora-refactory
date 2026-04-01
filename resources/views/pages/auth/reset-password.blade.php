@extends('layouts.fullscreen-layout')

@section('content')
<div class="min-h-screen bg-white px-6 py-10 dark:bg-gray-900">
    <div class="mx-auto flex min-h-[80vh] max-w-md items-center">
        <div class="w-full rounded-3xl border border-gray-200 bg-white p-8 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900/70" x-data="{ showPassword: false }">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Definir nova senha</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Crie uma senha forte para continuar acessando o sistema.</p>
            </div>
            <form method="post" action="{{ route('password.reset.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                    <input type="email" name="email" value="{{ old('email', $email) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-gray-900 dark:border-gray-700 dark:text-white" required>
                </div>
                <div class="relative">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nova senha</label>
                    <input :type="showPassword ? 'text' : 'password'" name="password" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 pr-11 text-gray-900 dark:border-gray-700 dark:text-white" required>
                    <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-[42px] text-gray-500 dark:text-gray-400"><i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmar senha</label>
                    <input :type="showPassword ? 'text' : 'password'" name="password_confirmation" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-gray-900 dark:border-gray-700 dark:text-white" required>
                </div>
                <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar nova senha</button>
            </form>
        </div>
    </div>
</div>
@endsection
