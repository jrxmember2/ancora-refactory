<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Recuperar acesso' }} | {{ $ancoraBrand['app_name'] ?? 'Âncora' }}</title>
    @include('layouts.partials.asset-loader')
</head>
<body class="flex min-h-screen items-center justify-center bg-[#f7f2ec] px-6">
    <div class="max-w-xl rounded-3xl border border-[#eadfd5] bg-white p-8 shadow-sm">
        <img src="{{ $ancoraBrand['logo_light'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="mb-8 h-14 w-auto">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Recuperar acesso</p>
        <h1 class="mt-3 text-2xl font-semibold text-gray-950">Esqueci minha senha</h1>
        <p class="mt-3 text-sm leading-6 text-gray-600">Por segurança, a recuperação automática ficará preparada para a próxima etapa. Neste momento, solicite uma nova senha diretamente ao escritório.</p>
        <a href="{{ route('portal.login') }}" class="mt-6 inline-flex rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Voltar ao login</a>
    </div>
</body>
</html>
