<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\FinancialAccount;
use App\Models\FinancialPayable;
use App\Models\FinancialReceivable;
use App\Models\FinancialTransaction;
use App\Services\FinancialReportingService;
use App\Support\Financeiro\FinancialCatalog;
use App\Support\Hub\HubModulePresenter;
use App\Support\Hub\HubOfficePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends HubApiController
{
    public function __construct(
        private readonly FinancialReportingService $reportingService,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['financeiro.dashboard'],
            moduleSlugs: ['financeiro'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->tableExists('financial_receivables') || !$this->tableExists('financial_payables')) {
            return response()->json(HubOfficePresenter::financeDashboard(
                summary: [],
                cards: [],
                alerts: [],
                cashflowPreview: [],
            ));
        }

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $dueSoonEnd = $now->copy()->addDays(7);

        $receitasMes = (float) FinancialReceivable::query()
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('final_amount');

        $despesasMes = (float) FinancialPayable::query()
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $recebiveisEmAberto = (float) FinancialReceivable::query()
            ->whereNotIn('status', ['recebido', 'cancelado'])
            ->sum(DB::raw('GREATEST(final_amount - received_amount, 0)'));

        $pagaveisEmAberto = (float) FinancialPayable::query()
            ->whereNotIn('status', ['pago', 'cancelado'])
            ->sum(DB::raw('GREATEST(amount - paid_amount, 0)'));

        $contasVencidas = (int) FinancialReceivable::query()
            ->whereDate('due_date', '<', $now->toDateString())
            ->whereNotIn('status', ['recebido', 'cancelado'])
            ->count();
        $contasVencidas += (int) FinancialPayable::query()
            ->whereDate('due_date', '<', $now->toDateString())
            ->whereNotIn('status', ['pago', 'cancelado'])
            ->count();

        $contasAVencer = (int) FinancialReceivable::query()
            ->whereBetween('due_date', [$now->toDateString(), $dueSoonEnd->toDateString()])
            ->whereNotIn('status', ['recebido', 'cancelado'])
            ->count();
        $contasAVencer += (int) FinancialPayable::query()
            ->whereBetween('due_date', [$now->toDateString(), $dueSoonEnd->toDateString()])
            ->whereNotIn('status', ['pago', 'cancelado'])
            ->count();

        $saldoAtual = $this->tableExists('financial_accounts')
            ? FinancialAccount::query()
                ->where('is_active', true)
                ->get()
                ->sum(fn (FinancialAccount $account) => $this->reportingService->accountBalance($account))
            : 0.0;

        $saldoPrevisto = (float) $saldoAtual + $recebiveisEmAberto - $pagaveisEmAberto;

        $alerts = [];
        if ($contasVencidas > 0) {
            $alerts[] = HubOfficePresenter::financeAlert(
                title: 'Contas vencidas',
                message: 'Há ' . $contasVencidas . ' contas vencidas que merecem atenção imediata.',
                tone: 'warning',
            );
        }
        if ($pagaveisEmAberto > $recebiveisEmAberto) {
            $alerts[] = HubOfficePresenter::financeAlert(
                title: 'Saída maior que entrada',
                message: 'As contas a pagar em aberto estão acima dos recebíveis em aberto.',
                tone: 'info',
            );
        }

        $cashflowPreview = [];
        if ($this->tableExists('financial_transactions')) {
            $cashflowPreview = FinancialTransaction::query()
                ->with(['account', 'category'])
                ->latest('transaction_date')
                ->limit(8)
                ->get()
                ->map(fn (FinancialTransaction $item) => HubOfficePresenter::financeCashflow($item))
                ->values()
                ->all();
        }

        return response()->json(HubOfficePresenter::financeDashboard(
            summary: [
                'month_label' => $monthStart->translatedFormat('F \d\e Y'),
                'receitas_month' => $receitasMes,
                'receitas_month_label' => HubOfficePresenter::money($receitasMes),
                'despesas_month' => $despesasMes,
                'despesas_month_label' => HubOfficePresenter::money($despesasMes),
                'saldo_previsto' => $saldoPrevisto,
                'saldo_previsto_label' => HubOfficePresenter::money($saldoPrevisto),
                'contas_vencidas' => $contasVencidas,
                'contas_a_vencer' => $contasAVencer,
                'recebiveis_em_aberto' => $recebiveisEmAberto,
                'recebiveis_em_aberto_label' => HubOfficePresenter::money($recebiveisEmAberto),
            ],
            cards: [
                HubOfficePresenter::financeSummaryCard('receitas', 'Receitas', $receitasMes, 'Previsão de entradas do mês.', 'success'),
                HubOfficePresenter::financeSummaryCard('despesas', 'Despesas', $despesasMes, 'Compromissos previstos no mês.', 'warning'),
                HubOfficePresenter::financeSummaryCard('saldo', 'Saldo previsto', $saldoPrevisto, 'Saldo estimado considerando contas abertas.', 'brand'),
                HubOfficePresenter::financeSummaryCard('recebiveis', 'Recebíveis em aberto', $recebiveisEmAberto, 'Valores ainda pendentes de recebimento.', 'info'),
            ],
            alerts: $alerts,
            cashflowPreview: $cashflowPreview,
        ));
    }

    public function receivables(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['financeiro.receivables.index'],
            moduleSlugs: ['financeiro'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->tableExists('financial_receivables')) {
            return response()->json([
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                'filters' => ['states' => $this->financeStateOptions()],
            ]);
        }

        $query = FinancialReceivable::query()
            ->with(['client', 'condominium', 'contract'])
            ->when($request->filled('q'), function (Builder $builder) use ($request) {
                $term = trim((string) $request->query('q', ''));
                $builder->where(function (Builder $inner) use ($term) {
                    $inner->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhereHas('client', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'));
                });
            });

        $this->applyFinanceStateFilter($query, (string) $request->query('filter', 'all'), true);

        $items = $query
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (FinancialReceivable $item) => HubOfficePresenter::financeReceivable($item))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'states' => $this->financeStateOptions(),
            ],
        ]);
    }

    public function payables(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['financeiro.payables.index'],
            moduleSlugs: ['financeiro'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->tableExists('financial_payables')) {
            return response()->json([
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                'filters' => ['states' => $this->financeStateOptions()],
            ]);
        }

        $query = FinancialPayable::query()
            ->with('supplier')
            ->when($request->filled('q'), function (Builder $builder) use ($request) {
                $term = trim((string) $request->query('q', ''));
                $builder->where(function (Builder $inner) use ($term) {
                    $inner->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhereHas('supplier', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'));
                });
            });

        $this->applyFinanceStateFilter($query, (string) $request->query('filter', 'all'), false);

        $items = $query
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (FinancialPayable $item) => HubOfficePresenter::financePayable($item))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'states' => $this->financeStateOptions(),
            ],
        ]);
    }

    public function cashflow(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['financeiro.cash-flow.index'],
            moduleSlugs: ['financeiro'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->tableExists('financial_transactions')) {
            return response()->json([
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                'summary' => [
                    'period_label' => 'Últimos 30 dias',
                    'entradas_label' => HubOfficePresenter::money(0),
                    'saidas_label' => HubOfficePresenter::money(0),
                    'saldo_label' => HubOfficePresenter::money(0),
                ],
            ]);
        }

        [$from, $to, $periodLabel] = $this->cashflowPeriod($request->query('period'));

        $query = FinancialTransaction::query()
            ->with(['account', 'category'])
            ->whereBetween('transaction_date', [$from->startOfDay(), $to->endOfDay()]);

        $baseSummaryQuery = clone $query;

        $items = $query
            ->latest('transaction_date')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        $entradas = (float) (clone $baseSummaryQuery)
            ->where('transaction_type', 'entrada')
            ->sum('amount');

        $saidas = (float) (clone $baseSummaryQuery)
            ->whereIn('transaction_type', ['saida', 'reembolso', 'repasse'])
            ->sum('amount');

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (FinancialTransaction $item) => HubOfficePresenter::financeCashflow($item))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'summary' => [
                'period_label' => $periodLabel,
                'entradas' => $entradas,
                'entradas_label' => HubOfficePresenter::money($entradas),
                'saidas' => $saidas,
                'saidas_label' => HubOfficePresenter::money($saidas),
                'saldo' => $entradas - $saidas,
                'saldo_label' => HubOfficePresenter::money($entradas - $saidas),
            ],
        ]);
    }

    private function financeStateOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Todas'],
            ['value' => 'overdue', 'label' => 'Vencidas'],
            ['value' => 'upcoming', 'label' => 'A vencer'],
            ['value' => 'paid', 'label' => 'Pagas'],
        ];
    }

    private function applyFinanceStateFilter(Builder $query, string $filter, bool $receivable): void
    {
        $today = now()->toDateString();
        $paidStatuses = $receivable ? ['recebido', 'cancelado'] : ['pago', 'cancelado'];
        $fullyPaidStatus = $receivable ? 'recebido' : 'pago';

        match ($filter) {
            'overdue' => $query
                ->whereDate('due_date', '<', $today)
                ->whereNotIn('status', $paidStatuses),
            'upcoming' => $query
                ->whereDate('due_date', '>=', $today)
                ->whereNotIn('status', $paidStatuses),
            'paid' => $query->where('status', $fullyPaidStatus),
            default => null,
        };
    }

    private function cashflowPeriod(?string $period): array
    {
        $today = now();

        return match ($period) {
            '7d' => [$today->copy()->subDays(6), $today->copy(), 'Últimos 7 dias'],
            '90d' => [$today->copy()->subDays(89), $today->copy(), 'Últimos 90 dias'],
            default => [$today->copy()->subDays(29), $today->copy(), 'Últimos 30 dias'],
        };
    }
}
