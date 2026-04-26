<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialPayable;
use App\Models\FinancialProcessCost;
use App\Models\FinancialReceivable;
use App\Models\FinancialReimbursement;
use App\Models\FinancialTransaction;
use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinancialReportingService
{
    public function dashboardData(int $year): array
    {
        $now = now();
        $monthStart = Carbon::create($year, $now->month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $receivables = FinancialReceivable::query()->with(['client', 'condominium', 'contract'])->get();
        $payables = FinancialPayable::query()->with(['supplier'])->get();
        $transactions = FinancialTransaction::query()
            ->with(['category', 'costCenter', 'account'])
            ->whereYear('transaction_date', $year)
            ->get();
        $accounts = FinancialAccount::query()->where('is_active', true)->get();

        $receivedMonth = $receivables->filter(fn (FinancialReceivable $item) => $item->received_at && $item->received_at->between($monthStart, $monthEnd));
        $pendingMonth = $receivables->filter(fn (FinancialReceivable $item) => $item->due_date && $item->due_date->between($monthStart, $monthEnd) && !in_array($item->status, ['recebido', 'cancelado'], true));
        $overdue = $receivables->filter(fn (FinancialReceivable $item) => $item->due_date && $item->due_date->isPast() && !in_array($item->status, ['recebido', 'cancelado'], true));
        $payablesMonth = $payables->filter(fn (FinancialPayable $item) => $item->due_date && $item->due_date->between($monthStart, $monthEnd));
        $contractsFaturando = Contract::query()
            ->where('generate_financial_entries', true)
            ->whereIn('status', ['ativo', 'assinado', 'aguardando_assinatura'])
            ->count();

        $incomeTransactions = $transactions->where('transaction_type', 'entrada');
        $expenseTransactions = $transactions->whereIn('transaction_type', ['saida', 'reembolso', 'repasse']);

        $chartMonths = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $receitaMensal = array_fill(0, 12, 0.0);
        $despesaMensal = array_fill(0, 12, 0.0);
        $saldoMensal = array_fill(0, 12, 0.0);

        foreach (range(1, 12) as $month) {
            $monthReceipts = $transactions->filter(fn (FinancialTransaction $item) => $item->transaction_date && (int) $item->transaction_date->month === $month && $item->transaction_type === 'entrada')->sum('amount');
            $monthExpenses = $transactions->filter(fn (FinancialTransaction $item) => $item->transaction_date && (int) $item->transaction_date->month === $month && in_array($item->transaction_type, ['saida', 'reembolso', 'repasse'], true))->sum('amount');
            $receitaMensal[$month - 1] = round((float) $monthReceipts, 2);
            $despesaMensal[$month - 1] = round((float) $monthExpenses, 2);
            $saldoMensal[$month - 1] = round((float) $monthReceipts - (float) $monthExpenses, 2);
        }

        $byClient = $incomeTransactions
            ->groupBy(fn (FinancialTransaction $item) => $item->receivable?->client?->display_name ?: 'Sem cliente')
            ->map(fn (Collection $group, string $key) => ['label' => $key, 'amount' => round((float) $group->sum('amount'), 2)])
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();

        $byCondominium = $incomeTransactions
            ->groupBy(fn (FinancialTransaction $item) => $item->receivable?->condominium?->name ?: 'Sem condominio')
            ->map(fn (Collection $group, string $key) => ['label' => $key, 'amount' => round((float) $group->sum('amount'), 2)])
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();

        $byCategory = $transactions
            ->groupBy(fn (FinancialTransaction $item) => $item->category?->name ?: 'Sem categoria')
            ->map(fn (Collection $group, string $key) => ['label' => $key, 'amount' => round((float) $group->sum('amount'), 2)])
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();

        $byCostCenter = $expenseTransactions
            ->groupBy(fn (FinancialTransaction $item) => $item->costCenter?->name ?: 'Sem centro de custo')
            ->map(fn (Collection $group, string $key) => ['label' => $key, 'amount' => round((float) $group->sum('amount'), 2)])
            ->sortByDesc('amount')
            ->take(8)
            ->values()
            ->all();

        $caixaAtual = $accounts->sum(fn (FinancialAccount $account) => $this->accountBalance($account));
        $ticketMedio = $receivedMonth->count() > 0 ? ((float) $receivedMonth->sum('received_amount') / $receivedMonth->count()) : 0.0;
        $receitaRecorrente = $receivables->where('billing_type', 'mensalidade')->sum('final_amount');
        $receitaExtraordinaria = $receivables->where('billing_type', 'receita_extraordinaria')->sum('final_amount');
        $contasVencidas = $overdue->count() + $payables->filter(fn (FinancialPayable $item) => $item->due_date && $item->due_date->isPast() && !in_array($item->status, ['pago', 'cancelado'], true))->count();
        $contasAVencer = $receivables->filter(fn (FinancialReceivable $item) => $item->due_date && $item->due_date->between($now->copy(), $now->copy()->addDays(7)) && !in_array($item->status, ['recebido', 'cancelado'], true))->count();
        $inadimplenciaValor = (float) $overdue->sum(fn (FinancialReceivable $item) => $item->final_amount - $item->received_amount);

        $alerts = [
            'contas_vencidas' => $overdue->take(8)->values(),
            'caixa_negativo' => $accounts->filter(fn (FinancialAccount $account) => $this->accountBalance($account) < 0)->values(),
            'contratos_sem_cobranca' => Contract::query()
                ->where('generate_financial_entries', true)
                ->whereDoesntHave('receivables')
                ->take(8)
                ->get(),
            'receitas_em_atraso' => $overdue->sortBy('due_date')->take(8)->values(),
            'repasses_pendentes' => $transactions->where('transaction_type', 'repasse')->where('reconciliation_status', 'pendente')->take(8)->values(),
            'custas_sem_reembolso' => FinancialProcessCost::query()->whereIn('status', ['lancado', 'pago'])->take(8)->get(),
        ];

        return [
            'summary' => [
                'receita_mes' => (float) $receivables->filter(fn (FinancialReceivable $item) => $item->due_date && $item->due_date->between($monthStart, $monthEnd))->sum('final_amount'),
                'receita_recebida' => (float) $receivedMonth->sum('received_amount'),
                'receita_pendente' => (float) $pendingMonth->sum(fn (FinancialReceivable $item) => $item->final_amount - $item->received_amount),
                'receita_vencida' => $inadimplenciaValor,
                'despesas_mes' => (float) $payablesMonth->sum('amount'),
                'saldo_liquido' => (float) $incomeTransactions->sum('amount') - (float) $expenseTransactions->sum('amount'),
                'caixa_atual' => $caixaAtual,
                'ticket_medio' => $ticketMedio,
                'receita_recorrente' => (float) $receitaRecorrente,
                'receita_extraordinaria' => (float) $receitaExtraordinaria,
                'contratos_faturando' => $contractsFaturando,
                'contas_vencidas' => $contasVencidas,
                'contas_a_vencer' => $contasAVencer,
                'inadimplencia' => $inadimplenciaValor,
            ],
            'alerts' => $alerts,
            'charts' => [
                'months' => $chartMonths,
                'receita_mensal' => $receitaMensal,
                'despesa_mensal' => $despesaMensal,
                'saldo_mensal' => $saldoMensal,
                'clientes' => $byClient,
                'condominios' => $byCondominium,
                'categorias' => $byCategory,
                'centros' => $byCostCenter,
            ],
            'accounts' => $accounts->map(fn (FinancialAccount $account) => [
                'name' => $account->name,
                'balance' => $this->accountBalance($account),
            ])->values(),
        ];
    }

    public function accountBalance(FinancialAccount $account): float
    {
        $entries = (float) $account->transactions()->where('transaction_type', 'entrada')->sum('amount');
        $debits = (float) $account->transactions()->whereIn('transaction_type', ['saida', 'reembolso', 'repasse'])->sum('amount');

        return round((float) $account->opening_balance + $entries - $debits, 2);
    }

    public function dreData(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = FinancialTransaction::query()->with('category');
        if ($from) {
            $query->whereDate('transaction_date', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('transaction_date', '<=', $to->toDateString());
        }

        $transactions = $query->get();
        $groups = [];
        foreach (FinancialCatalog::dreGroups() as $key => $label) {
            $groups[$key] = [
                'label' => $label,
                'amount' => 0.0,
                'items' => [],
            ];
        }

        foreach ($transactions as $transaction) {
            $group = $transaction->category?->dre_group ?: ($transaction->transaction_type === 'entrada' ? 'receita_bruta' : 'despesas_operacionais');
            $signal = $transaction->transaction_type === 'entrada' ? 1 : -1;
            $amount = round($signal * (float) $transaction->amount, 2);
            $groups[$group]['amount'] += $amount;
            $groups[$group]['items'][] = $transaction;
        }

        $receitaBruta = $groups['receita_bruta']['amount'];
        $deducoes = abs((float) $groups['deducoes']['amount']);
        $custos = abs((float) $groups['custos']['amount']);
        $despesas = abs((float) $groups['despesas_operacionais']['amount'])
            + abs((float) $groups['despesas_administrativas']['amount'])
            + abs((float) $groups['despesas_comerciais']['amount']);
        $resultadoFinanceiro = (float) $groups['resultado_financeiro']['amount'];
        $outrosResultados = (float) $groups['outros_resultados']['amount'];

        $receitaLiquida = $receitaBruta - $deducoes;
        $resultado = $receitaLiquida - $custos - $despesas + $resultadoFinanceiro + $outrosResultados;

        return [
            'groups' => $groups,
            'summary' => [
                'receita_bruta' => $receitaBruta,
                'receita_liquida' => $receitaLiquida,
                'custos' => $custos,
                'despesas' => $despesas,
                'resultado' => $resultado,
                'lucro' => $resultado,
            ],
        ];
    }

    public function accountabilityData(?int $clientId = null, ?int $condominiumId = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $receivables = FinancialReceivable::query()
            ->with(['client', 'condominium', 'contract'])
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($condominiumId, fn ($query) => $query->where('condominium_id', $condominiumId))
            ->when($from, fn ($query) => $query->whereDate('due_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('due_date', '<=', $to->toDateString()))
            ->orderBy('due_date')
            ->get();

        $costs = FinancialProcessCost::query()
            ->with(['client', 'process'])
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($from, fn ($query) => $query->whereDate('cost_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('cost_date', '<=', $to->toDateString()))
            ->orderBy('cost_date')
            ->get();

        $reimbursements = FinancialReimbursement::query()
            ->with(['client', 'process'])
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($from, fn ($query) => $query->whereDate('due_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('due_date', '<=', $to->toDateString()))
            ->orderBy('due_date')
            ->get();

        $honorarios = $receivables->filter(fn (FinancialReceivable $item) => in_array($item->billing_type, ['mensalidade', 'honorario', 'exito'], true));
        $repasses = FinancialTransaction::query()
            ->where('transaction_type', 'repasse')
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to->toDateString()))
            ->get();

        return [
            'receivables' => $receivables,
            'costs' => $costs,
            'reimbursements' => $reimbursements,
            'repasses' => $repasses,
            'summary' => [
                'entradas' => (float) $receivables->sum('received_amount'),
                'honorarios' => (float) $honorarios->sum('received_amount'),
                'custas' => (float) $costs->sum('amount'),
                'repasses' => (float) $repasses->sum('amount'),
                'saldo' => (float) $receivables->sum('received_amount') - (float) $costs->sum('amount') - (float) $repasses->sum('amount'),
            ],
        ];
    }
}
