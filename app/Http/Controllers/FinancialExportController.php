<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialCostCenter;
use App\Models\FinancialImportLog;
use App\Models\FinancialInstallment;
use App\Models\FinancialPayable;
use App\Models\FinancialProcessCost;
use App\Models\FinancialReceivable;
use App\Models\FinancialReimbursement;
use App\Models\FinancialStatement;
use App\Models\FinancialTransaction;
use App\Services\FinancialPdfService;
use App\Services\FinancialReportingService;
use App\Support\Financeiro\FinancialCatalog;
use App\Support\Financeiro\FinancialValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class FinancialExportController extends Controller
{
    public function __construct(
        private readonly FinancialReportingService $reportingService,
        private readonly FinancialPdfService $pdfService,
    ) {
    }

    public function export(Request $request, string $scope, string $format): Response|BinaryFileResponse|StreamedResponse|View
    {
        $scope = Str::lower(trim($scope));
        $format = Str::lower(trim($format));

        abort_unless(array_key_exists($scope, FinancialCatalog::exportScopes()), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf', 'print'], true), 404);

        $dataset = $this->dataset($scope, $request);
        $baseFilename = 'financeiro-' . $scope . '-' . now()->format('Ymd_His');

        return match ($format) {
            'csv' => $this->downloadCsv($baseFilename . '.csv', $dataset['headers'], $dataset['rows']),
            'xlsx' => $this->downloadXlsx($baseFilename . '.xlsx', $dataset['headers'], $dataset['rows'], $dataset['title']),
            'pdf' => $this->downloadPdf($baseFilename . '.pdf', $dataset),
            default => view('pages.financeiro.reports.table-pdf', array_merge($dataset, ['pdfMode' => false])),
        };
    }

    private function dataset(string $scope, Request $request): array
    {
        return match ($scope) {
            'receivables' => $this->receivablesDataset($request),
            'payables' => $this->payablesDataset($request),
            'cash-flow' => $this->cashFlowDataset($request),
            'accounts' => $this->accountsDataset($request),
            'collection' => $this->collectionDataset($request),
            'reimbursements' => $this->reimbursementsDataset($request),
            'process-costs' => $this->processCostsDataset($request),
            'statements' => $this->statementsDataset($request),
            'billing' => $this->billingDataset($request),
            'installments' => $this->installmentsDataset($request),
            'categories' => $this->categoriesDataset($request),
            'cost-centers' => $this->costCentersDataset($request),
            'delinquency' => $this->delinquencyDataset($request),
            'dre' => $this->dreDataset($request),
            'accountability' => $this->accountabilityDataset($request),
            'reports' => $this->reportsDataset($request),
            default => abort(404),
        };
    }

    private function receivablesDataset(Request $request): array
    {
        $query = FinancialReceivable::query()
            ->with(['client', 'condominium', 'unit', 'contract', 'category', 'account'])
            ->when($request->filled('q'), function (Builder $builder) use ($request) {
                $term = trim((string) $request->input('q'));
                $builder->where(function (Builder $sub) use ($term) {
                    $sub->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhereHas('client', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'));
                });
            });

        $this->filterReceivables($query, $request);
        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('due_date')->get();

        return [
            'title' => 'Contas a receber',
            'subtitle' => 'Relatorio financeiro de recebimentos.',
            'headers' => ['Codigo', 'Titulo', 'Cliente', 'Condominio', 'Unidade', 'Contrato', 'Categoria', 'Valor final', 'Recebido', 'Saldo', 'Vencimento', 'Status'],
            'rows' => $items->map(fn (FinancialReceivable $item) => [
                'Codigo' => $item->code,
                'Titulo' => $item->title,
                'Cliente' => $item->client?->display_name,
                'Condominio' => $item->condominium?->name,
                'Unidade' => $item->unit?->unit_number,
                'Contrato' => $item->contract?->code ?: $item->contract?->title,
                'Categoria' => $item->category?->name,
                'Valor final' => FinancialValue::money($item->final_amount),
                'Recebido' => FinancialValue::money($item->received_amount),
                'Saldo' => FinancialValue::money((float) $item->final_amount - (float) $item->received_amount),
                'Vencimento' => optional($item->due_date)->format('d/m/Y'),
                'Status' => FinancialCatalog::receivableStatuses()[$item->status] ?? $item->status,
            ])->all(),
            'summary' => [
                'Registros' => $items->count(),
                'Valor final' => FinancialValue::money($items->sum('final_amount')),
                'Recebido' => FinancialValue::money($items->sum('received_amount')),
                'Saldo' => FinancialValue::money($items->sum(fn (FinancialReceivable $item) => (float) $item->final_amount - (float) $item->received_amount)),
            ],
        ];
    }

    private function payablesDataset(Request $request): array
    {
        $query = FinancialPayable::query()->with(['supplier', 'category', 'account', 'costCenter']);
        $this->filterPayables($query, $request);
        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('due_date')->get();

        return [
            'title' => 'Contas a pagar',
            'subtitle' => 'Relatorio financeiro de pagamentos.',
            'headers' => ['Codigo', 'Titulo', 'Fornecedor', 'Categoria', 'Centro de custo', 'Conta', 'Valor', 'Pago', 'Saldo', 'Vencimento', 'Status'],
            'rows' => $items->map(fn (FinancialPayable $item) => [
                'Codigo' => $item->code,
                'Titulo' => $item->title,
                'Fornecedor' => $item->supplier?->display_name ?: $item->supplier_name_snapshot,
                'Categoria' => $item->category?->name,
                'Centro de custo' => $item->costCenter?->name,
                'Conta' => $item->account?->name,
                'Valor' => FinancialValue::money($item->amount),
                'Pago' => FinancialValue::money($item->paid_amount),
                'Saldo' => FinancialValue::money((float) $item->amount - (float) $item->paid_amount),
                'Vencimento' => optional($item->due_date)->format('d/m/Y'),
                'Status' => FinancialCatalog::payableStatuses()[$item->status] ?? $item->status,
            ])->all(),
            'summary' => [
                'Registros' => $items->count(),
                'Valor' => FinancialValue::money($items->sum('amount')),
                'Pago' => FinancialValue::money($items->sum('paid_amount')),
                'Saldo' => FinancialValue::money($items->sum(fn (FinancialPayable $item) => (float) $item->amount - (float) $item->paid_amount)),
            ],
        ];
    }

    private function cashFlowDataset(Request $request): array
    {
        $query = FinancialTransaction::query()->with(['account', 'category', 'costCenter']);
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->input('transaction_type'));
        }
        if ($request->filled('account_id')) {
            $query->where('account_id', (int) $request->integer('account_id'));
        }
        $this->applySelectedIds($query, $request);
        $items = $query->orderByDesc('transaction_date')->get();

        return [
            'title' => 'Fluxo de caixa',
            'subtitle' => 'Movimentacoes financeiras consolidadas.',
            'headers' => ['Codigo', 'Data', 'Tipo', 'Conta', 'Categoria', 'Centro de custo', 'Forma', 'Valor', 'Descricao', 'Status'],
            'rows' => $items->map(fn (FinancialTransaction $item) => [
                'Codigo' => $item->code,
                'Data' => optional($item->transaction_date)->format('d/m/Y H:i'),
                'Tipo' => FinancialCatalog::transactionTypes()[$item->transaction_type] ?? $item->transaction_type,
                'Conta' => $item->account?->name,
                'Categoria' => $item->category?->name,
                'Centro de custo' => $item->costCenter?->name,
                'Forma' => $item->payment_method,
                'Valor' => FinancialValue::money($item->amount),
                'Descricao' => $item->description,
                'Status' => $item->reconciliation_status,
            ])->all(),
            'summary' => [
                'Registros' => $items->count(),
                'Entradas' => FinancialValue::money($items->where('transaction_type', 'entrada')->sum('amount')),
                'Saidas' => FinancialValue::money($items->whereIn('transaction_type', ['saida', 'reembolso', 'repasse'])->sum('amount')),
            ],
        ];
    }

    private function accountsDataset(Request $request): array
    {
        $query = FinancialAccount::query();
        $this->applySelectedIds($query, $request);
        $items = $query->orderByDesc('is_primary')->orderBy('name')->get();

        return [
            'title' => 'Bancos e contas',
            'subtitle' => 'Cadastro de bancos, contas, carteiras e caixas.',
            'headers' => ['Codigo', 'Nome', 'Banco', 'Tipo', 'Agencia', 'Conta', 'Pix', 'Saldo inicial', 'Limite', 'Principal', 'Ativa'],
            'rows' => $items->map(fn (FinancialAccount $item) => [
                'Codigo' => $item->code,
                'Nome' => $item->name,
                'Banco' => $item->bank_name,
                'Tipo' => FinancialCatalog::accountTypes()[$item->account_type] ?? $item->account_type,
                'Agencia' => $item->agency,
                'Conta' => trim($item->account_number . ' ' . $item->account_digit),
                'Pix' => $item->pix_key,
                'Saldo inicial' => FinancialValue::money($item->opening_balance),
                'Limite' => FinancialValue::money($item->credit_limit),
                'Principal' => $item->is_primary ? 'Sim' : 'Nao',
                'Ativa' => $item->is_active ? 'Sim' : 'Nao',
            ])->all(),
            'summary' => [
                'Registros' => $items->count(),
                'Saldo inicial total' => FinancialValue::money($items->sum('opening_balance')),
            ],
        ];
    }

    private function collectionDataset(Request $request): array
    {
        $query = FinancialReceivable::query()
            ->with(['client', 'condominium', 'unit'])
            ->where('generate_collection', true)
            ->whereNotIn('status', ['recebido', 'cancelado']);

        $this->filterReceivables($query, $request);
        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('due_date')->get();

        return [
            'title' => 'Cobrancas financeiras',
            'subtitle' => 'Fila de recebiveis aptos a cobranca automatica.',
            'headers' => ['Codigo', 'Titulo', 'Cliente', 'Condominio', 'Unidade', 'Vencimento', 'Etapa', 'Saldo', 'Status'],
            'rows' => $items->map(fn (FinancialReceivable $item) => [
                'Codigo' => $item->code,
                'Titulo' => $item->title,
                'Cliente' => $item->client?->display_name,
                'Condominio' => $item->condominium?->name,
                'Unidade' => $item->unit?->unit_number,
                'Vencimento' => optional($item->due_date)->format('d/m/Y'),
                'Etapa' => FinancialCatalog::collectionStages()[$item->collection_stage] ?? ($item->collection_stage ?: 'Nao iniciado'),
                'Saldo' => FinancialValue::money((float) $item->final_amount - (float) $item->received_amount),
                'Status' => FinancialCatalog::receivableStatuses()[$item->status] ?? $item->status,
            ])->all(),
            'summary' => [
                'Titulos' => $items->count(),
                'Saldo' => FinancialValue::money($items->sum(fn (FinancialReceivable $item) => (float) $item->final_amount - (float) $item->received_amount)),
            ],
        ];
    }

    private function reimbursementsDataset(Request $request): array
    {
        $query = FinancialReimbursement::query()->with(['client', 'process']);
        $this->applySelectedIds($query, $request);
        $items = $query->orderByDesc('id')->get();

        return [
            'title' => 'Reembolsos',
            'subtitle' => 'Controle de valores reembolsaveis.',
            'headers' => ['Codigo', 'Cliente', 'Processo', 'Tipo', 'Valor', 'Pago pelo escritorio', 'Reembolsado', 'Status', 'Vencimento'],
            'rows' => $items->map(fn (FinancialReimbursement $item) => [
                'Codigo' => $item->code,
                'Cliente' => $item->client?->display_name,
                'Processo' => $item->process?->process_number ?: $item->process_id,
                'Tipo' => $item->type,
                'Valor' => FinancialValue::money($item->amount),
                'Pago pelo escritorio' => FinancialValue::money($item->paid_by_office_amount),
                'Reembolsado' => FinancialValue::money($item->reimbursed_amount),
                'Status' => $item->status,
                'Vencimento' => optional($item->due_date)->format('d/m/Y'),
            ])->all(),
            'summary' => [
                'Registros' => $items->count(),
                'Valor' => FinancialValue::money($items->sum('amount')),
            ],
        ];
    }

    private function processCostsDataset(Request $request): array
    {
        $query = FinancialProcessCost::query()->with(['client', 'process', 'category']);
        $this->applySelectedIds($query, $request);
        $items = $query->orderByDesc('id')->get();

        return [
            'title' => 'Custas processuais',
            'subtitle' => 'Controle de custas, despesas judiciais e reembolsos associados.',
            'headers' => ['Codigo', 'Cliente', 'Processo', 'Tipo', 'Categoria', 'Valor', 'Reembolsado', 'Data', 'Status'],
            'rows' => $items->map(fn (FinancialProcessCost $item) => [
                'Codigo' => $item->code,
                'Cliente' => $item->client?->display_name,
                'Processo' => $item->process?->process_number ?: $item->process_id,
                'Tipo' => $item->cost_type,
                'Categoria' => $item->category?->name,
                'Valor' => FinancialValue::money($item->amount),
                'Reembolsado' => FinancialValue::money($item->reimbursed_amount),
                'Data' => optional($item->cost_date)->format('d/m/Y'),
                'Status' => $item->status,
            ])->all(),
            'summary' => [
                'Registros' => $items->count(),
                'Valor' => FinancialValue::money($items->sum('amount')),
            ],
        ];
    }

    private function statementsDataset(Request $request): array
    {
        $query = FinancialStatement::query()
            ->with('account')
            ->when($request->filled('account_id'), fn (Builder $builder) => $builder->where('account_id', (int) $request->integer('account_id')))
            ->when($request->filled('status'), function (Builder $builder) use ($request) {
                if ($request->input('status') === 'conciliado') {
                    $builder->where('is_reconciled', true);
                    return;
                }

                if ($request->input('status') === 'pendente') {
                    $builder->where('is_reconciled', false);
                }
            });

        $this->applySelectedIds($query, $request);
        $items = $query->orderByDesc('statement_date')->get();

        return [
            'title' => 'Conciliacao bancaria',
            'subtitle' => 'Linhas importadas de extrato bancario.',
            'headers' => ['Conta', 'Data', 'Descricao', 'Documento', 'Tipo', 'Valor', 'Saldo apos', 'Conciliado'],
            'rows' => $items->map(fn (FinancialStatement $item) => [
                'Conta' => $item->account?->name,
                'Data' => optional($item->statement_date)->format('d/m/Y H:i'),
                'Descricao' => $item->description,
                'Documento' => $item->document_number,
                'Tipo' => $item->direction,
                'Valor' => FinancialValue::money($item->amount),
                'Saldo apos' => FinancialValue::money($item->balance_after),
                'Conciliado' => $item->is_reconciled ? 'Sim' : 'Nao',
            ])->all(),
            'summary' => [
                'Registros' => $items->count(),
                'Nao conciliados' => $items->where('is_reconciled', false)->count(),
            ],
        ];
    }

    private function billingDataset(Request $request): array
    {
        $query = Contract::query()
            ->with(['client', 'condominium'])
            ->where('generate_financial_entries', true);

        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('title')->get();

        return [
            'title' => 'Faturamento',
            'subtitle' => 'Contratos aptos a gerar cobranca financeira.',
            'headers' => ['Codigo', 'Titulo', 'Cliente', 'Condominio', 'Tipo', 'Valor mensal', 'Dia vencimento', 'Recorrencia', 'Status'],
            'rows' => $items->map(fn (Contract $item) => [
                'Codigo' => $item->code,
                'Titulo' => $item->title,
                'Cliente' => $item->client?->display_name,
                'Condominio' => $item->condominium?->name,
                'Tipo' => $item->type,
                'Valor mensal' => FinancialValue::money($item->monthly_value ?: $item->contract_value ?: $item->total_value),
                'Dia vencimento' => $item->due_day,
                'Recorrencia' => $item->recurrence,
                'Status' => $item->status,
            ])->all(),
            'summary' => [
                'Contratos faturando' => $items->count(),
            ],
        ];
    }

    private function installmentsDataset(Request $request): array
    {
        $query = FinancialInstallment::query()->with(['contract', 'parentReceivable', 'receivable']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('due_date')->get();

        return [
            'title' => 'Parcelamentos',
            'subtitle' => 'Parcelas geradas a partir de negociacoes e faturamento.',
            'headers' => ['Codigo', 'Titulo', 'Contrato', 'Recebivel origem', 'Recebivel gerado', 'Parcela', 'Valor', 'Vencimento', 'Status'],
            'rows' => $items->map(fn (FinancialInstallment $item) => [
                'Codigo' => $item->code,
                'Titulo' => $item->title,
                'Contrato' => $item->contract?->code ?: $item->contract?->title,
                'Recebivel origem' => $item->parentReceivable?->code,
                'Recebivel gerado' => $item->receivable?->code,
                'Parcela' => $item->installment_number . '/' . $item->installment_total,
                'Valor' => FinancialValue::money($item->amount),
                'Vencimento' => optional($item->due_date)->format('d/m/Y'),
                'Status' => $item->status,
            ])->all(),
            'summary' => [
                'Parcelas' => $items->count(),
                'Valor' => FinancialValue::money($items->sum('amount')),
            ],
        ];
    }

    private function categoriesDataset(Request $request): array
    {
        $query = FinancialCategory::query();
        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('type')->orderBy('name')->get();

        return [
            'title' => 'Categorias financeiras',
            'subtitle' => 'Mapa de categorias para receitas e despesas.',
            'headers' => ['Tipo', 'Nome', 'Descricao', 'Grupo DRE', 'Cor', 'Ativa'],
            'rows' => $items->map(fn (FinancialCategory $item) => [
                'Tipo' => $item->type,
                'Nome' => $item->name,
                'Descricao' => $item->description,
                'Grupo DRE' => $item->dre_group,
                'Cor' => $item->color_hex,
                'Ativa' => $item->is_active ? 'Sim' : 'Nao',
            ])->all(),
            'summary' => [
                'Categorias' => $items->count(),
            ],
        ];
    }

    private function costCentersDataset(Request $request): array
    {
        $query = FinancialCostCenter::query();
        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('name')->get();

        return [
            'title' => 'Centros de custo',
            'subtitle' => 'Mapa de centros de custo do escritorio.',
            'headers' => ['Nome', 'Descricao', 'Ativo'],
            'rows' => $items->map(fn (FinancialCostCenter $item) => [
                'Nome' => $item->name,
                'Descricao' => $item->description,
                'Ativo' => $item->is_active ? 'Sim' : 'Nao',
            ])->all(),
            'summary' => [
                'Centros' => $items->count(),
            ],
        ];
    }

    private function delinquencyDataset(Request $request): array
    {
        $query = FinancialReceivable::query()
            ->with(['client', 'condominium', 'unit'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['recebido', 'cancelado']);
        $this->filterReceivables($query, $request);
        $this->applySelectedIds($query, $request);
        $items = $query->orderBy('due_date')->get();

        return [
            'title' => 'Inadimplencia',
            'subtitle' => 'Titulos vencidos e ainda em aberto.',
            'headers' => ['Codigo', 'Titulo', 'Cliente', 'Condominio', 'Unidade', 'Vencimento', 'Valor', 'Saldo', 'Status'],
            'rows' => $items->map(fn (FinancialReceivable $item) => [
                'Codigo' => $item->code,
                'Titulo' => $item->title,
                'Cliente' => $item->client?->display_name,
                'Condominio' => $item->condominium?->name,
                'Unidade' => $item->unit?->unit_number,
                'Vencimento' => optional($item->due_date)->format('d/m/Y'),
                'Valor' => FinancialValue::money($item->final_amount),
                'Saldo' => FinancialValue::money((float) $item->final_amount - (float) $item->received_amount),
                'Status' => $item->status,
            ])->all(),
            'summary' => [
                'Titulos' => $items->count(),
                'Saldo em atraso' => FinancialValue::money($items->sum(fn (FinancialReceivable $item) => (float) $item->final_amount - (float) $item->received_amount)),
            ],
        ];
    }

    private function dreDataset(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfYear();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfMonth();
        $data = $this->reportingService->dreData($from, $to);

        $rows = [];
        foreach ($data['groups'] as $group) {
            $rows[] = [
                'Grupo' => $group['label'],
                'Valor' => FinancialValue::money($group['amount']),
            ];
        }

        return [
            'title' => 'DRE',
            'subtitle' => 'Demonstrativo do resultado do exercicio.',
            'headers' => ['Grupo', 'Valor'],
            'rows' => $rows,
            'summary' => [
                'Receita bruta' => FinancialValue::money($data['summary']['receita_bruta']),
                'Receita liquida' => FinancialValue::money($data['summary']['receita_liquida']),
                'Resultado' => FinancialValue::money($data['summary']['resultado']),
            ],
        ];
    }

    private function accountabilityDataset(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfMonth();
        $data = $this->reportingService->accountabilityData(
            $request->filled('client_id') ? (int) $request->integer('client_id') : null,
            $request->filled('condominium_id') ? (int) $request->integer('condominium_id') : null,
            $from,
            $to
        );

        return [
            'title' => 'Prestacao de contas',
            'subtitle' => 'Resumo consolidado de entradas, honorarios, custas e repasses.',
            'headers' => ['Indicador', 'Valor'],
            'rows' => [
                ['Indicador' => 'Entradas', 'Valor' => FinancialValue::money($data['summary']['entradas'])],
                ['Indicador' => 'Honorarios', 'Valor' => FinancialValue::money($data['summary']['honorarios'])],
                ['Indicador' => 'Custas', 'Valor' => FinancialValue::money($data['summary']['custas'])],
                ['Indicador' => 'Repasses', 'Valor' => FinancialValue::money($data['summary']['repasses'])],
                ['Indicador' => 'Saldo', 'Valor' => FinancialValue::money($data['summary']['saldo'])],
            ],
            'summary' => [
                'Periodo' => $from->format('d/m/Y') . ' a ' . $to->format('d/m/Y'),
            ],
        ];
    }

    private function reportsDataset(Request $request): array
    {
        $imports = FinancialImportLog::query()->latest('id')->take(20)->get();

        return [
            'title' => 'Relatorios financeiros',
            'subtitle' => 'Historico recente de lotes e consolidacoes.',
            'headers' => ['ID', 'Escopo', 'Formato', 'Status', 'Linhas preview', 'Sucesso', 'Falhas', 'Processado em'],
            'rows' => $imports->map(fn (FinancialImportLog $item) => [
                'ID' => $item->id,
                'Escopo' => FinancialCatalog::importScopes()[$item->scope] ?? $item->scope,
                'Formato' => strtoupper($item->source_format),
                'Status' => $item->status,
                'Linhas preview' => $item->preview_rows_count,
                'Sucesso' => $item->success_rows,
                'Falhas' => $item->failed_rows,
                'Processado em' => optional($item->processed_at)->format('d/m/Y H:i'),
            ])->all(),
            'summary' => [
                'Lotes' => $imports->count(),
            ],
        ];
    }

    private function filterReceivables(Builder $query, Request $request): void
    {
        foreach (['client_id', 'condominium_id', 'unit_id', 'contract_id', 'category_id', 'cost_center_id', 'account_id', 'responsible_user_id'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, $request->input($key));
            }
        }

        foreach (['billing_type', 'status'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, $request->input($key));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('due_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('due_date', '<=', $request->input('date_to'));
        }
        if ($request->boolean('overdue_only')) {
            $query->whereDate('due_date', '<', now()->toDateString())->whereNotIn('status', ['recebido', 'cancelado']);
        }
        if ($request->boolean('without_pdf')) {
            $query->whereNull('final_pdf_path');
        }
    }

    private function filterPayables(Builder $query, Request $request): void
    {
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function (Builder $sub) use ($term) {
                $sub->where('code', 'like', '%' . $term . '%')
                    ->orWhere('title', 'like', '%' . $term . '%')
                    ->orWhere('supplier_name_snapshot', 'like', '%' . $term . '%')
                    ->orWhereHas('supplier', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'));
            });
        }

        foreach (['status', 'category_id', 'cost_center_id', 'account_id'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, $request->input($key));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('due_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('due_date', '<=', $request->input('date_to'));
        }
    }

    private function applySelectedIds(Builder $query, Request $request): void
    {
        $selected = array_filter(array_map('intval', (array) $request->input('selected', [])));
        if ($selected !== []) {
            $query->whereIn($query->getModel()->getQualifiedKeyName(), $selected);
        }
    }

    private function downloadCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($output, array_map(fn ($header) => $row[$header] ?? '', $headers), ';');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function downloadXlsx(string $filename, array $headers, array $rows, string $sheetName): BinaryFileResponse
    {
        $dir = storage_path('app/generated/financial');
        File::ensureDirectoryExists($dir);

        $jsonPath = $dir . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME) . '.json';
        $xlsxPath = $dir . DIRECTORY_SEPARATOR . $filename;
        File::put($jsonPath, json_encode([
            'sheet_name' => $sheetName,
            'headers' => array_values($headers),
            'rows' => array_map(fn ($row) => array_map(fn ($header) => $row[$header] ?? '', $headers), $rows),
        ], JSON_UNESCAPED_UNICODE));

        $script = base_path('scripts/write_generic_xlsx.py');
        $process = new Process(['python3', $script, $jsonPath, $xlsxPath], timeout: 120);
        $process->run();
        File::delete($jsonPath);

        if (!$process->isSuccessful() || !is_file($xlsxPath)) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Nao foi possivel gerar o arquivo XLSX.');
        }

        return response()->download($xlsxPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function downloadPdf(string $filename, array $dataset): View|BinaryFileResponse
    {
        $dir = storage_path('app/generated/financial');
        $payload = array_merge($dataset, [
            'brand' => \App\Support\AncoraSettings::brand(),
            'generatedAt' => now(),
            'pdfMode' => true,
        ]);

        $path = $this->pdfService->renderViewToPdf('pages.financeiro.reports.table-pdf', $payload, $dir, pathinfo($filename, PATHINFO_FILENAME));
        if (!$path) {
            return view('pages.financeiro.reports.table-pdf', array_merge($payload, ['pdfMode' => false]));
        }

        return response()->download($path, $filename, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }
}
