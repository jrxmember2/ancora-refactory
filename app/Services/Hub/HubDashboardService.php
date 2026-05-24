<?php

namespace App\Services\Hub;

use App\Models\CobrancaCase;
use App\Models\CobrancaCaseInstallment;
use App\Models\Contract;
use App\Models\Demand;
use App\Models\DocumentSignatureRequest;
use App\Models\FinancialPayable;
use App\Models\FinancialReceivable;
use App\Models\HubNotification;
use App\Models\ProcessCase;
use App\Models\User;
use App\Support\Hub\HubApiPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class HubDashboardService
{
    private array $tableExistsCache = [];

    private array $columnExistsCache = [];

    public function build(User $user): array
    {
        $unreadNotifications = $this->unreadNotificationsCount($user);
        $cards = $this->cards($user, $unreadNotifications);
        $shortcuts = $this->shortcuts($user);
        $alerts = $this->alerts($cards, $unreadNotifications);

        return [
            'greeting' => $this->greeting($user),
            'user' => HubApiPresenter::user($user),
            'summary' => [
                'active_modules_count' => count(HubApiPresenter::modules($user)),
                'available_shortcuts_count' => count($shortcuts),
                'unread_notifications_count' => $unreadNotifications,
                'critical_alerts_count' => count($alerts),
                'focus_message' => $this->focusMessage($alerts, $unreadNotifications),
                'has_critical_alerts' => count($alerts) > 0,
            ],
            'cards' => $cards,
            'shortcuts' => $shortcuts,
            'alerts' => $alerts,
            'notifications' => $this->notificationsPreview($user),
            'updated_at' => now()->toAtomString(),
            'unread_notifications_count' => $unreadNotifications,
        ];
    }

    private function greeting(User $user): string
    {
        $hour = now()->hour;
        $salutation = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
        $firstName = trim((string) Str::of((string) $user->name)->squish()->before(' '));

        return $firstName !== '' ? $salutation . ', ' . $firstName : $salutation;
    }

    private function cards(User $user, int $unreadNotifications): array
    {
        $cards = [];

        if ($this->hasModule($user, 'demandas')) {
            $cards[] = $this->card(
                id: 'demandas_abertas',
                title: 'Demandas abertas',
                value: $this->safeCount(fn () => $this->openDemandsCount()),
                description: 'Solicitações internas ainda em andamento.',
                icon: 'fa-solid fa-inbox',
                module: 'demandas',
                accent: 'info',
            );
            $cards[] = $this->card(
                id: 'demandas_sla_vencido',
                title: 'Demandas com SLA vencido',
                value: $this->safeCount(fn () => $this->overdueDemandSlaCount()),
                description: 'Demandas que precisam de atenção imediata.',
                icon: 'fa-solid fa-triangle-exclamation',
                module: 'demandas',
                accent: 'error',
            );
        }

        if ($this->hasModule($user, 'processos')) {
            $cards[] = $this->card(
                id: 'processos_ativos',
                title: 'Processos ativos',
                value: $this->safeCount(fn () => $this->activeProcessesCount($user)),
                description: 'Processos ativos visíveis para o usuário.',
                icon: 'fa-solid fa-scale-balanced',
                module: 'processos',
                accent: 'brand',
            );
            $cards[] = $this->card(
                id: 'processos_movimentacao_recente',
                title: 'Processos com movimentação recente',
                value: $this->safeCount(fn () => $this->recentProcessMovementsCount($user)),
                description: 'Processos com andamentos registrados nos últimos 7 dias.',
                icon: 'fa-solid fa-clock-rotate-left',
                module: 'processos',
                accent: 'info',
            );
        }

        if ($this->hasModule($user, 'cobrancas')) {
            $cards[] = $this->card(
                id: 'cobrancas_abertas',
                title: 'Cobranças abertas',
                value: $this->safeCount(fn () => $this->openCobrancasCount()),
                description: 'Cobranças ainda não encerradas.',
                icon: 'fa-solid fa-money-bill-wave',
                module: 'cobrancas',
                accent: 'warning',
            );
            $cards[] = $this->card(
                id: 'cobrancas_aptas_judicializacao',
                title: 'Cobranças aptas para judicialização',
                value: $this->safeCount(fn () => $this->judicializableCobrancasCount()),
                description: 'Casos prontos para avançar para a etapa judicial.',
                icon: 'fa-solid fa-gavel',
                module: 'cobrancas',
                accent: 'warning',
            );
            $cards[] = $this->card(
                id: 'acordos_vencidos',
                title: 'Acordos vencidos',
                value: $this->safeCount(fn () => $this->overdueAgreementsCount()),
                description: 'Parcelas de acordo vencidas e ainda não regularizadas.',
                icon: 'fa-solid fa-file-invoice-dollar',
                module: 'cobrancas',
                accent: 'error',
            );
        }

        if ($this->hasModule($user, 'contratos')) {
            $cards[] = $this->card(
                id: 'contratos_ativos',
                title: 'Contratos ativos',
                value: $this->safeCount(fn () => $this->activeContractsCount()),
                description: 'Contratos ativos ou assinados.',
                icon: 'fa-solid fa-file-contract',
                module: 'contratos',
                accent: 'info',
            );
            $cards[] = $this->card(
                id: 'contratos_pendentes_assinatura',
                title: 'Contratos pendentes de assinatura',
                value: $this->safeCount(fn () => $this->pendingContractSignaturesCount()),
                description: 'Contratos aguardando conclusão da assinatura.',
                icon: 'fa-solid fa-pen-nib',
                module: 'contratos',
                accent: 'brand',
            );
        }

        if ($this->hasModule($user, 'assinador')) {
            $cards[] = $this->card(
                id: 'assinaturas_pendentes',
                title: 'Assinaturas pendentes',
                value: $this->safeCount(fn () => $this->pendingSignaturesCount()),
                description: 'Documentos aguardando assinatura ou certificação.',
                icon: 'fa-solid fa-signature',
                module: 'assinador',
                accent: 'brand',
            );
        }

        if ($this->hasModule($user, 'financeiro')) {
            $cards[] = $this->card(
                id: 'contas_a_receber_mes',
                title: 'Contas a receber no mês',
                value: $this->safeCount(fn () => $this->openReceivablesThisMonthCount()),
                description: 'Títulos do mês ainda não recebidos.',
                icon: 'fa-solid fa-arrow-trend-up',
                module: 'financeiro',
                accent: 'success',
            );
            $cards[] = $this->card(
                id: 'contas_a_pagar_mes',
                title: 'Contas a pagar no mês',
                value: $this->safeCount(fn () => $this->openPayablesThisMonthCount()),
                description: 'Títulos do mês ainda não pagos.',
                icon: 'fa-solid fa-arrow-trend-down',
                module: 'financeiro',
                accent: 'warning',
            );
        }

        $cards[] = $this->card(
            id: 'notificacoes_nao_lidas',
            title: 'Notificações não lidas',
            value: $unreadNotifications,
            description: 'Alertas internos aguardando sua leitura.',
            icon: 'fa-solid fa-bell',
            module: 'hub',
            accent: 'brand',
            route: 'notifications',
        );

        return $cards;
    }

    private function shortcuts(User $user): array
    {
        $descriptions = [
            'clientes' => 'Cadastros e relacionamentos internos.',
            'cobrancas' => 'Visão operacional da Cobrança.',
            'config' => 'Preferências e administração do sistema.',
            'contratos' => 'Contratos, templates e categorias.',
            'demandas' => 'Solicitações e acompanhamento interno.',
            'financeiro' => 'Indicadores e rotinas financeiras.',
            'ia' => 'Assistente interno do escritório.',
            'logs' => 'Rastreabilidade e auditoria.',
            'processos' => 'Controle processual e andamentos.',
            'propostas' => 'Fluxo comercial e propostas.',
            'assinador' => 'Documentos e assinaturas.',
        ];

        return collect(HubApiPresenter::modules($user))
            ->filter(function (array $module) use ($user) {
                $routeName = (string) ($module['entry_route_name'] ?? '');

                return $routeName !== '' && $this->canRoute($user, $routeName);
            })
            ->map(function (array $module) use ($descriptions) {
                $slug = (string) $module['slug'];

                return [
                    'module' => $slug,
                    'title' => (string) $module['display_name'],
                    'description' => $descriptions[$slug] ?? 'Módulo disponível para o usuário.',
                    'entry_route_name' => $module['entry_route_name'],
                    'icon_class' => $module['icon_class'],
                    'accent' => $module['accent'],
                    'route' => HubApiPresenter::appRouteForModule($slug),
                ];
            })
            ->values()
            ->all();
    }

    private function alerts(array $cards, int $unreadNotifications): array
    {
        $configs = [
            'demandas_sla_vencido' => [
                'accent' => 'error',
                'message' => fn (int $value) => "{$value} demandas estão com SLA vencido.",
            ],
            'cobrancas_aptas_judicializacao' => [
                'accent' => 'warning',
                'message' => fn (int $value) => "{$value} cobranças já podem seguir para judicialização.",
            ],
            'acordos_vencidos' => [
                'accent' => 'error',
                'message' => fn (int $value) => "{$value} acordos possuem parcelas vencidas.",
            ],
            'contratos_pendentes_assinatura' => [
                'accent' => 'warning',
                'message' => fn (int $value) => "{$value} contratos aguardam assinatura.",
            ],
            'assinaturas_pendentes' => [
                'accent' => 'brand',
                'message' => fn (int $value) => "{$value} documentos seguem pendentes de assinatura.",
            ],
            'processos_movimentacao_recente' => [
                'accent' => 'info',
                'message' => fn (int $value) => "{$value} processos receberam movimentação recente.",
            ],
        ];

        $alerts = collect($cards)
            ->filter(fn (array $card) => ($configs[$card['id']] ?? null) !== null && (int) $card['value'] > 0)
            ->map(function (array $card) use ($configs) {
                $config = $configs[$card['id']];

                return [
                    'id' => $card['id'],
                    'title' => $card['title'],
                    'message' => $config['message']((int) $card['value']),
                    'accent' => $config['accent'],
                    'module' => $card['module'],
                    'route' => $card['route'],
                    'action_label' => 'Ver detalhes',
                ];
            })
            ->values();

        if ($unreadNotifications > 0) {
            $alerts->push([
                'id' => 'notificacoes_nao_lidas',
                'title' => 'Notificações pendentes',
                'message' => "{$unreadNotifications} notificações aguardam sua leitura.",
                'accent' => 'brand',
                'module' => 'hub',
                'route' => 'notifications',
                'action_label' => 'Ver detalhes',
            ]);
        }

        return $alerts->take(5)->all();
    }

    private function notificationsPreview(User $user): array
    {
        if (!$this->tableExists('hub_notifications')) {
            return [];
        }

        return $this->safeArray(function () use ($user) {
            return HubNotification::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (HubNotification $notification) => HubApiPresenter::notification($notification))
                ->values()
                ->all();
        });
    }

    private function focusMessage(array $alerts, int $unreadNotifications): string
    {
        if ($alerts !== []) {
            return (string) ($alerts[0]['message'] ?? 'Existem pontos que merecem sua atenção hoje.');
        }

        if ($unreadNotifications > 0) {
            return 'Há notificações aguardando sua leitura.';
        }

        return 'Tudo em dia para começar.';
    }

    private function unreadNotificationsCount(User $user): int
    {
        if (!$this->tableExists('hub_notifications')) {
            return 0;
        }

        return HubNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    private function openDemandsCount(): int
    {
        if (!$this->tableExists('demands') || !$this->hasColumn('demands', 'status')) {
            return 0;
        }

        return Demand::query()
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->count();
    }

    private function overdueDemandSlaCount(): int
    {
        if (
            !$this->tableExists('demands')
            || !$this->hasColumn('demands', 'status')
            || !$this->hasColumn('demands', 'sla_due_at')
        ) {
            return 0;
        }

        return Demand::query()
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();
    }

    private function activeProcessesCount(User $user): int
    {
        if (!$this->tableExists('process_cases')) {
            return 0;
        }

        return $this->visibleProcessQuery($user)
            ->whereNull('closed_at')
            ->count();
    }

    private function recentProcessMovementsCount(User $user): int
    {
        if (!$this->tableExists('process_cases') || !$this->tableExists('process_case_phases')) {
            return 0;
        }

        return $this->visibleProcessQuery($user)
            ->whereHas('phases', function (Builder $query) {
                $query->where('created_at', '>=', now()->subDays(7));

                if ($this->hasColumn('process_case_phases', 'is_private')) {
                    $query->where('is_private', false);
                }
            })
            ->count();
    }

    private function openCobrancasCount(): int
    {
        if (!$this->tableExists('cobranca_cases') || !$this->hasColumn('cobranca_cases', 'situation')) {
            return 0;
        }

        return CobrancaCase::query()
            ->whereNotIn('situation', ['cancelado', 'pago_encerrado'])
            ->count();
    }

    private function judicializableCobrancasCount(): int
    {
        if (!$this->tableExists('cobranca_cases') || !$this->hasColumn('cobranca_cases', 'workflow_stage')) {
            return 0;
        }

        return CobrancaCase::query()
            ->where('workflow_stage', 'apto_judicializar')
            ->count();
    }

    private function overdueAgreementsCount(): int
    {
        if (
            !$this->tableExists('cobranca_case_installments')
            || !$this->hasColumn('cobranca_case_installments', 'due_date')
        ) {
            return 0;
        }

        $query = CobrancaCaseInstallment::query()
            ->whereDate('due_date', '<', today());

        if ($this->hasColumn('cobranca_case_installments', 'status')) {
            $query->whereNotIn('status', ['paga', 'cancelada']);
        }

        if ($this->tableExists('cobranca_cases') && $this->hasColumn('cobranca_cases', 'workflow_stage')) {
            $query->whereHas('cobrancaCase', function (Builder $inner) {
                $inner->whereIn('workflow_stage', ['acordo_ativo', 'aguardando_boletos', 'acordo_inadimplido']);
            });
        }

        return $query->count();
    }

    private function activeContractsCount(): int
    {
        if (!$this->tableExists('contracts') || !$this->hasColumn('contracts', 'status')) {
            return 0;
        }

        return Contract::query()
            ->whereIn('status', ['ativo', 'assinado'])
            ->count();
    }

    private function pendingContractSignaturesCount(): int
    {
        if (!$this->tableExists('contracts') || !$this->hasColumn('contracts', 'status')) {
            return 0;
        }

        return Contract::query()
            ->where('status', 'aguardando_assinatura')
            ->count();
    }

    private function pendingSignaturesCount(): int
    {
        if (!$this->tableExists('document_signature_requests') || !$this->hasColumn('document_signature_requests', 'status')) {
            return 0;
        }

        return DocumentSignatureRequest::query()
            ->whereIn('status', ['pending_signatures', 'partially_signed', 'metadata_ready', 'uploaded', 'certificating'])
            ->count();
    }

    private function openReceivablesThisMonthCount(): int
    {
        if (
            !$this->tableExists('financial_receivables')
            || !$this->hasColumn('financial_receivables', 'due_date')
            || !$this->hasColumn('financial_receivables', 'received_at')
        ) {
            return 0;
        }

        return FinancialReceivable::query()
            ->whereNull('received_at')
            ->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();
    }

    private function openPayablesThisMonthCount(): int
    {
        if (
            !$this->tableExists('financial_payables')
            || !$this->hasColumn('financial_payables', 'due_date')
            || !$this->hasColumn('financial_payables', 'paid_at')
        ) {
            return 0;
        }

        return FinancialPayable::query()
            ->whereNull('paid_at')
            ->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();
    }

    private function hasModule(User $user, string $slug): bool
    {
        return collect(HubApiPresenter::modules($user))
            ->contains(fn (array $module) => (string) $module['slug'] === $slug);
    }

    private function canRoute(User $user, string $routeName): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($user->relationLoaded('routePermissions')) {
            return $user->routePermissions->contains(
                fn ($permission) => (string) $permission->route_name === $routeName
            );
        }

        return $user->routePermissions()->where('route_name', $routeName)->exists();
    }

    private function visibleProcessQuery(User $user): Builder
    {
        $query = ProcessCase::query();

        if (!$user->isSuperadmin()) {
            $needleName = $this->normalize((string) $user->name);
            $needleEmail = $this->normalize((string) $user->email);

            $query->where(function (Builder $inner) use ($user, $needleName, $needleEmail) {
                $inner->where('is_private', false)
                    ->orWhere('created_by', $user->id);

                if ($needleName !== '') {
                    $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleName . '%']);
                }

                if ($needleEmail !== '') {
                    $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleEmail . '%']);
                }
            });
        }

        return $query;
    }

    private function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->squish()->toString();
    }

    private function safeCount(callable $callback): int
    {
        try {
            return max(0, (int) $callback());
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeArray(callable $callback): array
    {
        try {
            $value = $callback();

            return is_array($value) ? $value : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = Schema::hasTable($table);
        }

        return $this->tableExistsCache[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;

        if (!array_key_exists($cacheKey, $this->columnExistsCache)) {
            $this->columnExistsCache[$cacheKey] = $this->tableExists($table) && Schema::hasColumn($table, $column);
        }

        return $this->columnExistsCache[$cacheKey];
    }

    private function card(
        string $id,
        string $title,
        int $value,
        string $description,
        string $icon,
        string $module,
        string $accent,
        ?string $route = null,
    ): array {
        $resolvedRoute = $route ?? HubApiPresenter::appRouteForModule($module);

        return [
            'id' => $id,
            'title' => $title,
            'value' => $value,
            'description' => $description,
            'icon_class' => $icon,
            'module' => $module,
            'accent' => $accent,
            'route' => $resolvedRoute,
            'is_clickable' => $resolvedRoute !== null,
        ];
    }
}
