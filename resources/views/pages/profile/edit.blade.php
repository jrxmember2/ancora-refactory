@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Meus dados" subtitle="Atualize seu nome, e-mail, foto e senha de acesso." />

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr,0.85fr]">
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" data-file-preview>
        @csrf

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input name="name" value="{{ old('name', $user->name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" required>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" required>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Foto do perfil</label>
                <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                    <i class="fa-solid fa-camera"></i>
                    <span>Escolher nova foto</span>
                    <input type="file" name="avatar" accept=".png,.jpg,.jpeg,.webp" class="sr-only" data-file-input>
                </label>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>Nenhum arquivo selecionado</div>
            </div>
        </div>

        <div class="mt-6 border-t border-gray-100 pt-6 dark:border-gray-800">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Trocar senha</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Preencha somente se quiser alterar sua senha atual.</p>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Senha atual</label>
                    <input type="password" name="current_password" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nova senha</label>
                    <input type="password" name="password" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmar nova senha</label>
                    <input type="password" name="password_confirmation" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">Salvar alterações</button>
            <a href="{{ route('hub') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Voltar</a>
        </div>
    </form>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center gap-4">
                @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-3xl object-cover">
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-brand-500 text-2xl font-semibold text-white">{{ $user->initials }}</div>
                @endif

                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $user->name }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 text-sm">
                <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Perfil</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $user->role === 'superadmin' ? 'Superadmin' : 'Usuário interno' }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Último login</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ optional($user->last_login_at)->format('d/m/Y H:i') ?: 'Ainda não registrado' }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Status de acesso</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $user->is_active ? 'Ativo' : 'Inativo' }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
            <div class="font-semibold text-gray-900 dark:text-white">Tema e novidades</div>
            <p class="mt-2">Você pode alternar entre tema claro e escuro diretamente no menu do header. As novidades recentes do sistema ficam disponíveis no item <strong>Novidades</strong>.</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-file-input]')) return;
        const wrapper = event.target.closest('[data-file-preview]');
        const label = wrapper?.querySelector('[data-file-name]');
        if (!label) return;
        const files = Array.from(event.target.files || []);
        label.textContent = files.length ? files.map((file) => file.name).join(', ') : 'Nenhum arquivo selecionado';
    });
</script>
@endpush
