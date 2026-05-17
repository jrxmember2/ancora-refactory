<dialog id="portal-app-login-logs-modal" class="fixed inset-0 m-auto max-h-[90vh] w-full max-w-6xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Logins do app Ancora Clientes</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Historico dos acessos realizados pela API mobile Android.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($portalAppLoginLogsReady && $portalAppLoginLogCount > 0)
                    <a href="{{ route('clientes.config.portal-app-logins.export') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Exportar XLSX</a>
                @endif
                <button type="button" onclick="document.getElementById('portal-app-login-logs-modal').close()" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
        </div>
    </div>

    <div class="p-6">
        @if(!$portalAppLoginLogsReady)
            <x-ancora.empty-state icon="fa-solid fa-mobile-screen-button" title="Log do app ainda indisponivel" subtitle="Rode a migration do modulo mobile para habilitar o historico de acessos do aplicativo." />
        @elseif($portalAppLoginLogs->isEmpty())
            <x-ancora.empty-state icon="fa-solid fa-mobile-screen-button" title="Nenhum login do app registrado" subtitle="Assim que um usuario entrar pelo aplicativo, o acesso aparecera aqui." />
        @else
            <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">Exibindo os ultimos {{ number_format($portalAppLoginLogs->count(), 0, ',', '.') }} acessos.</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                        <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Usuario</th>
                            <th class="px-4 py-3">Dispositivo</th>
                            <th class="px-4 py-3">IP</th>
                            <th class="px-4 py-3">Localizacao</th>
                            <th class="px-4 py-3">User-Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700 dark:divide-gray-800 dark:text-gray-200">
                        @foreach($portalAppLoginLogs as $log)
                            <tr>
                                <td class="px-4 py-3 align-top whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') ?: '-' }}</td>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $log->portalUser?->name ?: 'Usuario removido' }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $log->portalUser?->login_key ?: 'sem login' }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div>{{ $log->device_name ?: 'Dispositivo nao informado' }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ strtoupper($log->platform ?: 'android') }}{{ $log->app_version ? ' - v' . $log->app_version : '' }}</div>
                                </td>
                                <td class="px-4 py-3 align-top whitespace-nowrap">{{ $log->ip_address ?: '-' }}</td>
                                <td class="px-4 py-3 align-top">
                                    <div>{{ $log->location_label ?: 'Nao informada' }}</div>
                                    @if($log->country || $log->region || $log->city)
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ collect([$log->city, $log->region, $log->country])->filter()->implode(' / ') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top text-xs text-gray-500 dark:text-gray-400">{{ $log->user_agent ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</dialog>
