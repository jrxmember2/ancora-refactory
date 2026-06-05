<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsFinancialFormOptions;
use App\Http\Requests\StoreFinancialAccountRequest;
use App\Http\Requests\StoreFinancialPayableRequest;
use App\Http\Requests\StoreFinancialReceivableRequest;
use App\Http\Requests\UpdateFinancialAccountRequest;
use App\Http\Requests\UpdateFinancialPayableRequest;
use App\Http\Requests\UpdateFinancialReceivableRequest;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\FinancialAccount;
use App\Models\FinancialAttachment;
use App\Models\FinancialCategory;
use App\Models\FinancialCostCenter;
use App\Models\FinancialImportLog;
use App\Models\FinancialInstallment;
use App\Models\FinancialPayable;
use App\Models\FinancialProcessCost;
use App\Models\FinancialReceivable;
use App\Models\FinancialReimbursement;
use App\Models\FinancialSetting;
use App\Models\FinancialStatement;
use App\Models\FinancialTransaction;
use App\Services\ContractFinancialService;
use App\Services\FinancialBillingService;
use App\Services\FinancialCodeService;
use App\Services\FinancialLedgerService;
use App\Services\FinancialPdfService;
use App\Services\FinancialReceivableSeriesService;
use App\Services\FinancialReportingService;
use App\Support\AncoraAuth;
use App\Support\BrazilianCurrencyFormatter;
use App\Support\Financeiro\FinancialCatalog;
use App\Support\Financeiro\FinancialColumns;
use App\Support\FinancialSettings;
use App\Support\Financeiro\FinancialValue;
use App\Support\SortableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialController extends Controller
{
    use BuildsFinancialFormOptions;

    public function __construct(
        private readonly FinancialReportingService $reportingService,
        private readonly FinancialLedgerService $ledgerService,
        private readonly FinancialBillingService $billingService,
        private readonly ContractFinancialService $contractFinancialService,
        private readonly FinancialReceivableSeriesService $receivableSeriesService,
        private readonly FinancialCodeService $codeService,
        private readonly FinancialPdfService $pdfService,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $year = max(2024, (int) $request->integer('year', now()->year));

        return view('pages.financeiro.dashboard', array_merge($this->financialFormOptions(), [
            'title' => 'Financeiro 360',
            'year' => $year,
            'years' => collect([$year - 1, $year, $year + 1])->unique()->values(),
            'data' => $this->reportingService->dashboardData($year),
        ]));
    }

    public function cashFlowIndex(Request $request): View
    {
        $query = FinancialTransaction::query()
            ->with(['account', 'destinationAccount', 'category', 'costCenter', 'receivable', 'payable'])
            ->when($request->filled('transaction_type'), fn (Builder $builder) => $builder->where('transaction_type', $request->input('transaction_type')))
            ->when($request->filled('account_id'), fn (Builder $builder) => $builder->where('account_id', (int) $request->integer('account_id')))
            ->when($request->filled('category_id'), fn (Builder $builder) => $builder->where('category_id', (int) $request->integer('category_id')))
            ->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('transaction_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('transaction_date', '<=', $request->input('date_to')));

        $sortState = SortableQuery::apply($query, $request, [
            'date' => 'transaction_date',
            'type' => 'transaction_type',
            'amount' => 'amount',
            'account' => 'account_id',
            'category' => 'category_id',
            'status' => 'reconciliation_status',
        ], 'date', 'desc');

        $items = $query->paginate(20)->withQueryString();
        $accounts = FinancialAccount::query()->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->get();
        $balances = $accounts->map(fn (FinancialAccount $account) => [
            'name' => $account->name,
            'balance' => $this->reportingService->accountBalance($account),
        ]);

        $openReceivables = FinancialReceivable::query()->whereNotIn('status', ['recebido', 'cancelado'])->sum(DB::raw('final_amount - received_amount'));
        $openPayables = FinancialPayable::query()->whereNotIn('status', ['pago', 'cancelado'])->sum(DB::raw('amount - paid_amount'));

        return view('pages.financeiro.cash-flow.index', array_merge($this->financialFormOptions(), [
            'title' => 'Fluxo de Caixa',
            'items' => $items,
            'sortState' => $sortState,
            'balances' => $balances,
            'summary' => [
                'saldo_real' => $balances->sum('balance'),
                'saldo_previsto' => $balances->sum('balance') + (float) $openReceivables - (float) $openPayables,
                'receber_aberto' => (float) $openReceivables,
                'pagar_aberto' => (float) $openPayables,
            ],
            'filters' => $request->all(),
        ]));
    }

    public function cashFlowStore(Request $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'transaction_type' => ['required', 'string'],
            'account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'destination_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:financial_cost_centers,id'],
            'amount' => ['required', 'string', 'max:40'],
            'transaction_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:120'],
        ]);

        $transaction = $this->ledgerService->recordStandaloneTransaction([
            'transaction_type' => $validated['transaction_type'],
            'account_id' => $this->intOrNull($validated['account_id'] ?? null),
            'destination_account_id' => $this->intOrNull($validated['destination_account_id'] ?? null),
            'category_id' => $this->intOrNull($validated['category_id'] ?? null),
            'cost_center_id' => $this->intOrNull($validated['cost_center_id'] ?? null),
            'amount' => $this->moneyToDecimal($validated['amount']),
            'transaction_date' => $validated['transaction_date'] ? Carbon::parse($validated['transaction_date'])->toDateTimeString() : now()->toDateTimeString(),
            'payment_method' => $validated['payment_method'] ?? null,
            'description' => $validated['description'] ?? null,
            'source' => $validated['source'] ?? 'Lancamento manual',
            'document_number' => $validated['document_number'] ?? null,
            'created_by' => $user->id,
            'direction' => $validated['transaction_type'] === 'entrada' ? 'entrada' : 'saida',
        ]);

        $this->logFinancialAction(
            $request,
            'financeiro.cash-flow.store',
            'financial_transactions',
            $transaction->id,
            sprintf(
                'Registrou movimentacao %s — %s.',
                $transaction->code ?: ('#' . $transaction->id),
                $this->transactionAuditSummary($transaction->loadMissing(['account', 'destinationAccount', 'category']))
            )
        );

        return back()->with('success', 'Movimentacao registrada com sucesso.');
    }

    public function receivablesIndex(Request $request): View
    {
        $query = FinancialReceivable::query()
            ->with(['client', 'condominium', 'unit.block', 'category', 'costCenter', 'account', 'contract', 'responsible'])
            ->when($request->filled('q'), function (Builder $builder) use ($request) {
                $term = trim((string) $request->input('q'));
                $builder->where(function (Builder $sub) use ($term) {
                    $sub->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhereHas('client', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'));
                });
            });

        $this->applyReceivableFilters($query, $request);

        $sortState = SortableQuery::apply($query, $request, [
            'code' => 'code',
            'title' => 'title',
            'due_date' => 'due_date',
            'amount' => 'final_amount',
            'status' => 'status',
            'received_at' => 'received_at',
        ], 'due_date', 'asc');

        $items = $query->paginate(20)->withQueryString();

        return view('pages.financeiro.receivables.index', array_merge($this->financialFormOptions(), [
            'title' => 'Contas a Receber',
            'items' => $items,
            'sortState' => $sortState,
            'filters' => $request->all(),
            'summary' => [
                'total' => (clone $query)->sum('final_amount'),
                'recebido' => (clone $query)->sum('received_amount'),
                'pendente' => (clone $query)->sum(DB::raw('final_amount - received_amount')),
                'vencido' => (clone $query)->whereDate('due_date', '<', now()->toDateString())->whereNotIn('status', ['recebido', 'cancelado'])->sum(DB::raw('final_amount - received_amount')),
            ],
        ]));
    }

    public function receivablesCreate(): View
    {
        return view('pages.financeiro.receivables.form', array_merge($this->financialFormOptions(), [
            'title' => 'Nova conta a receber',
            'mode' => 'create',
            'item' => null,
        ]));
    }

    public function receivablesStore(StoreFinancialReceivableRequest $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if ($request->boolean('import_contract_schedule') && $request->filled('contract_id')) {
            $contract = Contract::query()->findOrFail((int) $request->input('contract_id'));
            $errors = $this->contractFinancialService->validateFinancialData($contract->toArray());
            if ($errors !== []) {
                return back()
                    ->withInput()
                    ->with('error', 'Nao foi possivel importar o financeiro do contrato. Revise os dados financeiros do contrato antes de continuar.')
                    ->with('errors_list', array_values(array_unique($errors)));
            }

            $result = $this->contractFinancialService->importFinancialEntriesFromContract($contract, $user->id);
            $created = $result['created']->count();
            $skipped = $result['skipped']->count();

            if ($created === 0 && $skipped === 0) {
                return back()
                    ->withInput()
                    ->with('error', 'O contrato selecionado nao possui agenda financeira suficiente para gerar contas a receber.');
            }

            return redirect()
                ->route('financeiro.receivables.index')
                ->with('success', 'Importacao concluida: ' . $created . ' conta(s) criada(s) e ' . $skipped . ' pulada(s) por duplicidade.');
        }

        $payload = $this->normalizedReceivablePayload($request);
        $result = $this->storeReceivableSeries(
            $payload,
            $request,
            $user->id
        );

        $created = $result['created'];
        $first = $created->first();
        $this->logFinancialAction(
            $request,
            'financeiro.receivables.store',
            'financial_receivables',
            $first?->id,
            sprintf(
                'Criou %d conta(s) a receber [%s] — %s — total da serie %s.',
                $created->count(),
                $created->map(fn (FinancialReceivable $r) => $r->code ?: ('#' . $r->id))->implode(', ') ?: 'sem codigo',
                $first ? $this->receivableAuditSummary($first) : '-',
                FinancialValue::money($created->sum(fn (FinancialReceivable $r) => (float) $r->final_amount))
            )
        );

        if ($created->count() === 1 && $result['series_total'] === 1) {
            return redirect()
                ->route('financeiro.receivables.show', $first)
                ->with('success', 'Conta a receber criada com sucesso.');
        }

        return redirect()
            ->route('financeiro.receivables.index')
            ->with('success', $created->count() . ' conta(s) a receber criada(s) na serie.');
    }

    public function receivablesShow(FinancialReceivable $receivable): View
    {
        $receivable->load(['client', 'condominium', 'unit.block', 'category', 'costCenter', 'account', 'contract', 'process', 'responsible', 'transactions.account', 'attachments.uploader', 'installments.receivable']);

        return view('pages.financeiro.receivables.show', array_merge($this->financialFormOptions(), [
            'title' => $receivable->code ?: $receivable->title,
            'item' => $receivable,
        ]));
    }

    public function receivablesEdit(FinancialReceivable $receivable): View
    {
        $receivable->load(['client', 'condominium', 'unit', 'category', 'costCenter', 'account', 'contract', 'responsible']);

        return view('pages.financeiro.receivables.form', array_merge($this->financialFormOptions(), [
            'title' => 'Editar conta a receber',
            'mode' => 'edit',
            'item' => $receivable,
        ]));
    }

    public function receivablesUpdate(UpdateFinancialReceivableRequest $request, FinancialReceivable $receivable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $before = 'valores anteriores -> ' . $this->receivableAuditSummary($receivable);

        $payload = $this->normalizedReceivablePayload($request);
        $payload['updated_by'] = $user->id;

        $receivable->update($payload);
        $this->ledgerService->syncReceivable($receivable->fresh());
        $receivable->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.receivables.update',
            'financial_receivables',
            $receivable->id,
            sprintf('Atualizou conta a receber %s — %s. (%s)', $receivable->code ?: ('#' . $receivable->id), $this->receivableAuditSummary($receivable), $before)
        );

        return redirect()->route('financeiro.receivables.show', $receivable)->with('success', 'Conta a receber atualizada com sucesso.');
    }

    public function receivablesDestroy(Request $request, FinancialReceivable $receivable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $settlementsCount = (int) $receivable->transactions()->count();
        $details = sprintf(
            'Excluiu conta a receber %s — %s · recebido %s · baixas vinculadas: %d.',
            $receivable->code ?: ('#' . $receivable->id),
            $this->receivableAuditSummary($receivable),
            FinancialValue::money($receivable->received_amount),
            $settlementsCount
        );

        $receivableId = $receivable->id;
        $receivable->delete();

        $this->logFinancialAction($request, 'financeiro.receivables.delete', 'financial_receivables', $receivableId, $details);

        return redirect()->route('financeiro.receivables.index')->with('success', 'Conta a receber excluida com sucesso.');
    }

    public function receivablesDuplicate(Request $request, FinancialReceivable $receivable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $clone = $receivable->replicate(['code', 'received_amount', 'received_at', 'status', 'series_group', 'series_index', 'series_total']);
        $clone->code = $this->codeService->next('financial_receivables', 'entry_prefix', 'REC');
        $clone->status = 'aberto';
        $clone->received_amount = 0;
        $clone->received_at = null;
        $clone->series_group = null;
        $clone->series_index = null;
        $clone->series_total = null;
        $clone->created_by = $user->id;
        $clone->updated_by = $user->id;
        $clone->save();

        $this->logFinancialAction(
            $request,
            'financeiro.receivables.duplicate',
            'financial_receivables',
            $clone->id,
            sprintf(
                'Duplicou conta a receber %s a partir de %s — %s.',
                $clone->code ?: ('#' . $clone->id),
                $receivable->code ?: ('#' . $receivable->id),
                $this->receivableAuditSummary($clone)
            )
        );

        return redirect()->route('financeiro.receivables.edit', $clone)->with('success', 'Conta a receber duplicada com sucesso.');
    }

    public function receivablesSettle(Request $request, FinancialReceivable $receivable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'settlement_amount' => ['required', 'string', 'max:40'],
            'settlement_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'payment_method' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $amount = $this->moneyToDecimal($validated['settlement_amount']);
        $this->ledgerService->recordReceivableSettlement(
            $receivable,
            $amount,
            $validated['settlement_date'] ? Carbon::parse($validated['settlement_date']) : now(),
            $this->intOrNull($validated['account_id'] ?? null),
            $validated['payment_method'] ?? null,
            $validated['description'] ?? null,
            $user->id
        );
        $receivable->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.receivables.settle',
            'financial_receivables',
            $receivable->id,
            sprintf(
                'Registrou baixa de %s na conta a receber %s — recebido %s, saldo %s, status %s.',
                FinancialValue::money($amount),
                $receivable->code ?: ('#' . $receivable->id),
                FinancialValue::money($receivable->received_amount),
                FinancialValue::money((float) $receivable->final_amount - (float) $receivable->received_amount),
                $receivable->status
            )
        );

        return back()->with('success', 'Baixa registrada com sucesso.');
    }

    public function receivablesParcel(Request $request, FinancialReceivable $receivable): RedirectResponse
    {
        $validated = $request->validate([
            'installment_total' => ['required', 'integer', 'min:2', 'max:120'],
            'first_due_date' => ['required', 'date'],
        ]);

        return $this->createInstallmentsForReceivable(
            $request,
            $receivable,
            (int) $validated['installment_total'],
            Carbon::parse($validated['first_due_date'])
        );
    }

    public function receivablesRenegotiate(Request $request, FinancialReceivable $receivable): RedirectResponse
    {
        $previousStatus = $receivable->status;
        $receivable->forceFill(['status' => 'negociado'])->save();

        $this->logFinancialAction(
            $request,
            'financeiro.receivables.renegotiate',
            'financial_receivables',
            $receivable->id,
            sprintf(
                'Marcou conta a receber %s como negociada (status anterior: %s) — %s.',
                $receivable->code ?: ('#' . $receivable->id),
                $previousStatus,
                $this->receivableAuditSummary($receivable)
            )
        );

        return back()->with('success', 'Recebivel marcado como negociado.');
    }

    public function receivablesReceipt(FinancialReceivable $receivable): View|BinaryFileResponse
    {
        $receivable->load(['client', 'condominium', 'unit.block', 'transactions.account']);
        $payload = [
            'item' => $receivable,
            'brand' => \App\Support\AncoraSettings::brand(),
            'pdfMode' => true,
        ];

        $dir = storage_path('app/generated/financial');
        $basename = 'recibo-' . ($receivable->code ?: $receivable->id) . '-' . now()->format('YmdHis');
        $pdfPath = $this->pdfService->renderViewToPdf('pages.financeiro.receivables.receipt-pdf', $payload, $dir, $basename);

        if (!$pdfPath) {
            return view('pages.financeiro.receivables.receipt-pdf', array_merge($payload, ['pdfMode' => false]));
        }

        return response()->download($pdfPath, basename($pdfPath), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }

    public function receivablesPdf(FinancialReceivable $receivable): View|BinaryFileResponse
    {
        return $this->receivablesReceipt($receivable);
    }

    public function receivablesUploadAttachment(Request $request, FinancialReceivable $receivable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,csv,txt'],
            'file_type' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $files = $request->file('files', []);
        $this->storeAttachments($files, 'receivable', $receivable->id, $request->input('file_type'), $request->input('description'), $user->id);

        $names = collect($files)->map(fn ($f) => $f instanceof UploadedFile ? $f->getClientOriginalName() : null)->filter()->implode(', ');
        $this->logFinancialAction(
            $request,
            'financeiro.receivables.attachments.upload',
            'financial_receivables',
            $receivable->id,
            sprintf('Anexou %d arquivo(s) na conta a receber %s [%s].', is_array($files) ? count($files) : 0, $receivable->code ?: ('#' . $receivable->id), $names ?: '-')
        );

        return back()->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    public function receivablesDownloadAttachment(FinancialReceivable $receivable, FinancialAttachment $attachment): BinaryFileResponse
    {
        abort_unless($attachment->owner_type === 'receivable' && (int) $attachment->owner_id === (int) $receivable->id, 404);
        $path = storage_path('app/public/' . ltrim($attachment->relative_path, '/'));
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->original_name);
    }

    public function receivablesDeleteAttachment(FinancialReceivable $receivable, FinancialAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->owner_type === 'receivable' && (int) $attachment->owner_id === (int) $receivable->id, 404);
        $path = storage_path('app/public/' . ltrim($attachment->relative_path, '/'));
        if (is_file($path)) {
            File::delete($path);
        }
        $attachmentName = $attachment->original_name;
        $attachment->delete();

        $this->logFinancialAction(
            request(),
            'financeiro.receivables.attachments.delete',
            'financial_receivables',
            $receivable->id,
            sprintf('Excluiu anexo "%s" da conta a receber %s.', $attachmentName, $receivable->code ?: ('#' . $receivable->id))
        );

        return back()->with('success', 'Anexo removido com sucesso.');
    }

    public function payablesIndex(Request $request): View
    {
        $query = FinancialPayable::query()
            ->with(['supplier', 'category', 'costCenter', 'account', 'responsible'])
            ->when($request->filled('q'), function (Builder $builder) use ($request) {
                $term = trim((string) $request->input('q'));
                $builder->where(function (Builder $sub) use ($term) {
                    $sub->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhere('supplier_name_snapshot', 'like', '%' . $term . '%')
                        ->orWhereHas('supplier', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'));
                });
            })
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('category_id'), fn (Builder $builder) => $builder->where('category_id', (int) $request->integer('category_id')))
            ->when($request->filled('cost_center_id'), fn (Builder $builder) => $builder->where('cost_center_id', (int) $request->integer('cost_center_id')))
            ->when($request->filled('account_id'), fn (Builder $builder) => $builder->where('account_id', (int) $request->integer('account_id')))
            ->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('due_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('due_date', '<=', $request->input('date_to')));

        $sortState = SortableQuery::apply($query, $request, [
            'code' => 'code',
            'title' => 'title',
            'due_date' => 'due_date',
            'amount' => 'amount',
            'status' => 'status',
            'paid_at' => 'paid_at',
        ], 'due_date', 'asc');

        $items = $query->paginate(20)->withQueryString();

        return view('pages.financeiro.payables.index', array_merge($this->financialFormOptions(), [
            'title' => 'Contas a Pagar',
            'items' => $items,
            'sortState' => $sortState,
            'filters' => $request->all(),
            'summary' => [
                'total' => (clone $query)->sum('amount'),
                'pago' => (clone $query)->sum('paid_amount'),
                'pendente' => (clone $query)->sum(DB::raw('amount - paid_amount')),
                'vencido' => (clone $query)->whereDate('due_date', '<', now()->toDateString())->whereNotIn('status', ['pago', 'cancelado'])->sum(DB::raw('amount - paid_amount')),
            ],
        ]));
    }

    public function payablesCreate(): View
    {
        return view('pages.financeiro.payables.form', array_merge($this->financialFormOptions(), [
            'title' => 'Nova conta a pagar',
            'mode' => 'create',
            'item' => null,
        ]));
    }

    public function payablesStore(StoreFinancialPayableRequest $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $payload = $this->normalizedPayablePayload($request);
        $result = $this->storePayableSeries($payload, $request, $user->id);

        $created = $result['created'];
        $first = $created->first();
        $this->logFinancialAction(
            $request,
            'financeiro.payables.store',
            'financial_payables',
            $first?->id,
            sprintf(
                'Criou %d conta(s) a pagar [%s] — %s — total da serie %s.',
                $created->count(),
                $created->map(fn (FinancialPayable $p) => $p->code ?: ('#' . $p->id))->implode(', ') ?: 'sem codigo',
                $first ? $this->payableAuditSummary($first) : '-',
                FinancialValue::money($created->sum(fn (FinancialPayable $p) => (float) $p->amount))
            )
        );

        if ($created->count() === 1 && $result['series_total'] === 1) {
            return redirect()
                ->route('financeiro.payables.show', $first)
                ->with('success', 'Conta a pagar criada com sucesso.');
        }

        return redirect()
            ->route('financeiro.payables.index')
            ->with('success', $created->count() . ' conta(s) a pagar criada(s) na serie.');
    }

    public function payablesShow(FinancialPayable $payable): View
    {
        $payable->load(['supplier', 'category', 'costCenter', 'account', 'responsible', 'transactions.account', 'attachments.uploader']);

        return view('pages.financeiro.payables.show', array_merge($this->financialFormOptions(), [
            'title' => $payable->code ?: $payable->title,
            'item' => $payable,
        ]));
    }

    public function payablesEdit(FinancialPayable $payable): View
    {
        $payable->load(['supplier', 'category', 'costCenter', 'account', 'responsible']);

        return view('pages.financeiro.payables.form', array_merge($this->financialFormOptions(), [
            'title' => 'Editar conta a pagar',
            'mode' => 'edit',
            'item' => $payable,
        ]));
    }

    public function payablesUpdate(UpdateFinancialPayableRequest $request, FinancialPayable $payable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $before = sprintf(
            'valores anteriores -> %s',
            $this->payableAuditSummary($payable)
        );

        $payload = $this->normalizedPayablePayload($request);
        $payload['updated_by'] = $user->id;

        $payable->update($payload);
        $this->ledgerService->syncPayable($payable->fresh());
        $payable->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.payables.update',
            'financial_payables',
            $payable->id,
            sprintf(
                'Atualizou conta a pagar %s — %s. (%s)',
                $payable->code ?: ('#' . $payable->id),
                $this->payableAuditSummary($payable),
                $before
            )
        );

        return redirect()->route('financeiro.payables.show', $payable)->with('success', 'Conta a pagar atualizada com sucesso.');
    }

    public function payablesDestroy(Request $request, FinancialPayable $payable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $paymentsCount = (int) $payable->transactions()->count();
        $details = sprintf(
            'Excluiu conta a pagar %s — %s · pago %s · pagamentos vinculados: %d.',
            $payable->code ?: ('#' . $payable->id),
            $this->payableAuditSummary($payable),
            FinancialValue::money($payable->paid_amount),
            $paymentsCount
        );

        $payableId = $payable->id;
        $payable->delete();

        $this->logFinancialAction($request, 'financeiro.payables.delete', 'financial_payables', $payableId, $details);

        return redirect()->route('financeiro.payables.index')->with('success', 'Conta a pagar excluida com sucesso.');
    }

    public function payablesDuplicate(Request $request, FinancialPayable $payable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $clone = $payable->replicate(['code', 'paid_amount', 'paid_at', 'status']);
        $clone->code = $this->codeService->next('financial_payables', 'entry_prefix', 'PAG');
        $clone->status = 'aberto';
        $clone->paid_amount = 0;
        $clone->paid_at = null;
        $clone->created_by = $user->id;
        $clone->updated_by = $user->id;
        $clone->save();

        $this->logFinancialAction(
            $request,
            'financeiro.payables.duplicate',
            'financial_payables',
            $clone->id,
            sprintf(
                'Duplicou conta a pagar %s a partir de %s — %s.',
                $clone->code ?: ('#' . $clone->id),
                $payable->code ?: ('#' . $payable->id),
                $this->payableAuditSummary($clone)
            )
        );

        return redirect()->route('financeiro.payables.edit', $clone)->with('success', 'Conta a pagar duplicada com sucesso.');
    }

    public function payablesSettle(Request $request, FinancialPayable $payable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'settlement_amount' => ['required', 'string', 'max:40'],
            'settlement_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'payment_method' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $amount = $this->moneyToDecimal($validated['settlement_amount']);
        $this->ledgerService->recordPayableSettlement(
            $payable,
            $amount,
            $validated['settlement_date'] ? Carbon::parse($validated['settlement_date']) : now(),
            $this->intOrNull($validated['account_id'] ?? null),
            $validated['payment_method'] ?? null,
            $validated['description'] ?? null,
            $user->id
        );
        $payable->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.payables.settle',
            'financial_payables',
            $payable->id,
            sprintf(
                'Registrou pagamento de %s na conta a pagar %s — pago %s, saldo %s, status %s.',
                FinancialValue::money($amount),
                $payable->code ?: ('#' . $payable->id),
                FinancialValue::money($payable->paid_amount),
                FinancialValue::money((float) $payable->amount - (float) $payable->paid_amount),
                $payable->status
            )
        );

        return back()->with('success', 'Pagamento registrado com sucesso.');
    }

    public function payablesUploadAttachment(Request $request, FinancialPayable $payable): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,csv,txt'],
            'file_type' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $files = $request->file('files', []);
        $this->storeAttachments($files, 'payable', $payable->id, $request->input('file_type'), $request->input('description'), $user->id);

        $names = collect($files)->map(fn ($f) => $f instanceof UploadedFile ? $f->getClientOriginalName() : null)->filter()->implode(', ');
        $this->logFinancialAction(
            $request,
            'financeiro.payables.attachments.upload',
            'financial_payables',
            $payable->id,
            sprintf('Anexou %d arquivo(s) na conta a pagar %s [%s].', is_array($files) ? count($files) : 0, $payable->code ?: ('#' . $payable->id), $names ?: '-')
        );

        return back()->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    public function payablesDownloadAttachment(FinancialPayable $payable, FinancialAttachment $attachment): BinaryFileResponse
    {
        abort_unless($attachment->owner_type === 'payable' && (int) $attachment->owner_id === (int) $payable->id, 404);
        $path = storage_path('app/public/' . ltrim($attachment->relative_path, '/'));
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->original_name);
    }

    public function payablesDeleteAttachment(FinancialPayable $payable, FinancialAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->owner_type === 'payable' && (int) $attachment->owner_id === (int) $payable->id, 404);
        $path = storage_path('app/public/' . ltrim($attachment->relative_path, '/'));
        if (is_file($path)) {
            File::delete($path);
        }
        $attachmentName = $attachment->original_name;
        $attachment->delete();

        $this->logFinancialAction(
            request(),
            'financeiro.payables.attachments.delete',
            'financial_payables',
            $payable->id,
            sprintf('Excluiu anexo "%s" da conta a pagar %s.', $attachmentName, $payable->code ?: ('#' . $payable->id))
        );

        return back()->with('success', 'Anexo removido com sucesso.');
    }

    public function billingIndex(Request $request): View
    {
        $contracts = $this->billingService->contractsReadyForBilling();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfMonth();

        $items = $contracts->map(function ($contract) use ($from, $to) {
            $generated = FinancialReceivable::query()
                ->where('contract_id', $contract->id)
                ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
                ->count();

            return [
                'contract' => $contract,
                'generated' => $generated,
            ];
        });

        return view('pages.financeiro.billing.index', array_merge($this->financialFormOptions(), [
            'title' => 'Faturamento',
            'items' => $items,
            'from' => $from,
            'to' => $to,
        ]));
    }

    public function billingGenerateContract(Request $request, \App\Models\Contract $contract): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $result = $this->billingService->generateForContract(
            $contract,
            Carbon::parse($validated['from'])->startOfDay(),
            Carbon::parse($validated['to'])->endOfDay(),
            $user->id
        );

        $createdTotal = $result['created']->sum(fn ($r) => (float) ($r->final_amount ?? 0));
        $this->logFinancialAction(
            $request,
            'financeiro.billing.generate-contract',
            'contracts',
            $contract->id,
            sprintf(
                'Gerou faturamento do contrato %s (periodo %s a %s) — %d cobranca(s) criada(s) [total %s], %d pulada(s) por duplicidade.',
                $contract->code ?: ('#' . $contract->id),
                Carbon::parse($validated['from'])->format('d/m/Y'),
                Carbon::parse($validated['to'])->format('d/m/Y'),
                $result['created']->count(),
                FinancialValue::money($createdTotal),
                count($result['skipped'])
            )
        );

        return back()->with('success', 'Faturamento processado: ' . $result['created']->count() . ' cobranca(s) criada(s) e ' . count($result['skipped']) . ' pulada(s) por duplicidade.');
    }

    public function accountsIndex(): View
    {
        return view('pages.financeiro.accounts.index', array_merge($this->financialFormOptions(), [
            'title' => 'Bancos e Contas',
            'items' => FinancialAccount::query()->orderByDesc('is_primary')->orderBy('name')->get(),
        ]));
    }

    public function accountsStore(StoreFinancialAccountRequest $request): RedirectResponse
    {
        $payload = $this->normalizedAccountPayload($request);
        $payload['code'] = trim((string) ($payload['code'] ?? '')) !== ''
            ? $payload['code']
            : $this->codeService->next('financial_accounts', 'entry_prefix', 'CTA');

        if (!empty($payload['is_primary'])) {
            FinancialAccount::query()->update(['is_primary' => false]);
        }

        $account = FinancialAccount::query()->create($payload);

        $this->logFinancialAction(
            $request,
            'financeiro.accounts.store',
            'financial_accounts',
            $account->id,
            sprintf('Criou conta financeira %s — %s.', $account->code ?: ('#' . $account->id), $this->accountAuditSummary($account))
        );

        return back()->with('success', 'Conta financeira criada com sucesso.');
    }

    public function accountsUpdate(UpdateFinancialAccountRequest $request, FinancialAccount $account): RedirectResponse
    {
        $payload = $this->normalizedAccountPayload($request);

        if (!empty($payload['is_primary'])) {
            FinancialAccount::query()->whereKeyNot($account->id)->update(['is_primary' => false]);
        }

        $before = 'valores anteriores -> ' . $this->accountAuditSummary($account);
        $account->update($payload);
        $account->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.accounts.update',
            'financial_accounts',
            $account->id,
            sprintf('Atualizou conta financeira %s — %s. (%s)', $account->code ?: ('#' . $account->id), $this->accountAuditSummary($account), $before)
        );

        return back()->with('success', 'Conta financeira atualizada com sucesso.');
    }

    public function accountsDestroy(Request $request, FinancialAccount $account): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $accountId = $account->id;
        $movements = (int) $account->transactions()->count();
        $details = sprintf(
            'Excluiu conta financeira %s — %s · movimentacoes vinculadas: %d.',
            $account->code ?: ('#' . $account->id),
            $this->accountAuditSummary($account),
            $movements
        );
        $account->delete();

        $this->logFinancialAction($request, 'financeiro.accounts.delete', 'financial_accounts', $accountId, $details);

        return back()->with('success', 'Conta financeira excluida com sucesso.');
    }

    public function reconciliationIndex(Request $request): View
    {
        $statements = FinancialStatement::query()
            ->with(['account', 'reconciliations.transaction'])
            ->when($request->filled('account_id'), fn (Builder $builder) => $builder->where('account_id', (int) $request->integer('account_id')))
            ->when($request->filled('status'), function (Builder $builder) use ($request) {
                if ($request->input('status') === 'conciliado') {
                    $builder->where('is_reconciled', true);
                } elseif ($request->input('status') === 'pendente') {
                    $builder->where('is_reconciled', false);
                }
            })
            ->orderByDesc('statement_date')
            ->paginate(20)
            ->withQueryString();

        $pendingTransactions = FinancialTransaction::query()
            ->where('reconciliation_status', 'pendente')
            ->orderByDesc('transaction_date')
            ->limit(100)
            ->get();

        return view('pages.financeiro.reconciliation.index', array_merge($this->financialFormOptions(), [
            'title' => 'Conciliacao Bancaria',
            'items' => $statements,
            'pendingTransactions' => $pendingTransactions,
            'filters' => $request->all(),
        ]));
    }

    public function reconciliationUpload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'statement_file' => ['required', 'file', 'mimes:ofx,csv,xlsx'],
        ]);

        $log = app(FinancialImportController::class)->previewImport($request, 'statements', true);

        return redirect()->route('financeiro.import.show', $log)->with('success', 'Extrato analisado. Revise os dados antes de importar.');
    }

    public function reconciliationConciliate(Request $request, FinancialStatement $statement): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:financial_transactions,id'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($statement, $validated, $request) {
            $transaction = FinancialTransaction::query()->findOrFail((int) $validated['transaction_id']);
            $statement->forceFill(['is_reconciled' => true])->save();
            $transaction->forceFill([
                'reconciliation_status' => 'conciliado',
                'reconciled_at' => now(),
                'reconciled_by' => AncoraAuth::user($request)?->id,
            ])->save();

            $statement->reconciliations()->create([
                'transaction_id' => $transaction->id,
                'result' => 'conciliado',
                'matched_amount' => $statement->amount,
                'notes' => $validated['notes'] ?? null,
                'reconciled_by' => AncoraAuth::user($request)?->id,
                'reconciled_at' => now(),
            ]);
        });

        $this->logFinancialAction(
            $request,
            'financeiro.reconciliation.conciliate',
            'financial_statements',
            $statement->id,
            sprintf(
                'Conciliou extrato #%d (%s) com a movimentacao #%d — valor %s%s.',
                $statement->id,
                optional($statement->statement_date)->format('d/m/Y') ?: '-',
                (int) $validated['transaction_id'],
                FinancialValue::money($statement->amount),
                !empty($validated['notes']) ? ' · obs: ' . Str::limit((string) $validated['notes'], 120) : ''
            )
        );

        return back()->with('success', 'Lancamento conciliado com sucesso.');
    }

    public function collectionIndex(Request $request): View
    {
        $items = FinancialReceivable::query()
            ->with(['client', 'condominium', 'unit'])
            ->where('generate_collection', true)
            ->whereNotIn('status', ['recebido', 'cancelado'])
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return view('pages.financeiro.collection.index', array_merge($this->financialFormOptions(), [
            'title' => 'Cobrancas',
            'items' => $items,
            'stageLabels' => FinancialCatalog::collectionStages(),
        ]));
    }

    public function delinquencyIndex(Request $request): View
    {
        $query = FinancialReceivable::query()
            ->with(['client', 'condominium', 'unit'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['recebido', 'cancelado']);

        $this->applyReceivableFilters($query, $request);
        $items = $query->orderBy('due_date')->paginate(20)->withQueryString();

        return view('pages.financeiro.delinquency.index', array_merge($this->financialFormOptions(), [
            'title' => 'Inadimplencia',
            'items' => $items,
            'filters' => $request->all(),
            'summary' => [
                'quantidade' => $items->total(),
                'valor' => $query->sum(DB::raw('final_amount - received_amount')),
            ],
        ]));
    }

    public function costCentersIndex(): View
    {
        return view('pages.financeiro.cost-centers.index', array_merge($this->financialFormOptions(), [
            'title' => 'Centros de Custo',
            'items' => FinancialCostCenter::query()->orderBy('name')->get(),
        ]));
    }

    public function costCentersStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $costCenter = FinancialCostCenter::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logFinancialAction(
            $request,
            'financeiro.cost-centers.store',
            'financial_cost_centers',
            $costCenter->id,
            sprintf('Criou centro de custo "%s"%s.', $costCenter->name, $costCenter->is_active ? '' : ' (inativo)')
        );

        return back()->with('success', 'Centro de custo criado com sucesso.');
    }

    public function costCentersUpdate(Request $request, FinancialCostCenter $costCenter): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = sprintf('antes: "%s" (%s)', $costCenter->name, $costCenter->is_active ? 'ativo' : 'inativo');
        $costCenter->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $costCenter->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.cost-centers.update',
            'financial_cost_centers',
            $costCenter->id,
            sprintf('Atualizou centro de custo "%s" (%s). %s', $costCenter->name, $costCenter->is_active ? 'ativo' : 'inativo', $before)
        );

        return back()->with('success', 'Centro de custo atualizado com sucesso.');
    }

    public function costCentersDestroy(Request $request, FinancialCostCenter $costCenter): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $costCenterId = $costCenter->id;
        $details = sprintf('Excluiu centro de custo "%s".', $costCenter->name);
        $costCenter->delete();

        $this->logFinancialAction($request, 'financeiro.cost-centers.delete', 'financial_cost_centers', $costCenterId, $details);

        return back()->with('success', 'Centro de custo excluido com sucesso.');
    }

    public function categoriesIndex(): View
    {
        return view('pages.financeiro.categories.index', array_merge($this->financialFormOptions(), [
            'title' => 'Categorias Financeiras',
            'items' => FinancialCategory::query()->with('parent')->orderBy('type')->orderBy('name')->get(),
            'parentOptions' => FinancialCategory::query()->whereNull('parent_id')->orderBy('type')->orderBy('name')->get(),
        ]));
    }

    public function categoriesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'name' => ['required', 'string', 'max:180'],
            'parent_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'dre_group' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::dreGroups()))],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = FinancialCategory::query()->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'parent_id' => $this->resolveCategoryParentId($validated['parent_id'] ?? null, null),
            'description' => $validated['description'] ?? null,
            'dre_group' => $validated['dre_group'] ?? null,
            'color_hex' => $validated['color_hex'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logFinancialAction(
            $request,
            'financeiro.categories.store',
            'financial_categories',
            $category->id,
            sprintf(
                'Criou categoria "%s" (tipo: %s, grupo DRE: %s)%s.',
                $category->name,
                $category->type,
                $category->dre_group ?: '-',
                $category->parent_id ? ', subcategoria de #' . $category->parent_id : ''
            )
        );

        return back()->with('success', 'Categoria criada com sucesso.');
    }

    public function categoriesUpdate(Request $request, FinancialCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'name' => ['required', 'string', 'max:180'],
            'parent_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'dre_group' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::dreGroups()))],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = sprintf('antes: "%s" (tipo: %s, grupo DRE: %s)', $category->name, $category->type, $category->dre_group ?: '-');
        $category->update([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'parent_id' => $this->resolveCategoryParentId($validated['parent_id'] ?? null, $category->id),
            'description' => $validated['description'] ?? null,
            'dre_group' => $validated['dre_group'] ?? null,
            'color_hex' => $validated['color_hex'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $category->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.categories.update',
            'financial_categories',
            $category->id,
            sprintf('Atualizou categoria "%s" (tipo: %s, grupo DRE: %s). %s', $category->name, $category->type, $category->dre_group ?: '-', $before)
        );

        return back()->with('success', 'Categoria atualizada com sucesso.');
    }

    /**
     * Resolve o pai de uma categoria evitando ciclos: nao permite ser pai de si mesma e o pai
     * deve ser uma categoria raiz (hierarquia de 1 nivel), mantendo o DRE simples.
     */
    private function resolveCategoryParentId(mixed $parentId, ?int $selfId): ?int
    {
        $parentId = (int) $parentId;
        if ($parentId <= 0 || $parentId === (int) $selfId) {
            return null;
        }

        $parent = FinancialCategory::query()->find($parentId);
        if (!$parent || $parent->parent_id !== null) {
            return null; // pai precisa ser categoria raiz (sem avo)
        }

        return $parent->id;
    }

    public function categoriesDestroy(Request $request, FinancialCategory $category): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $categoryId = $category->id;
        $details = sprintf('Excluiu categoria "%s" (tipo: %s, grupo DRE: %s).', $category->name, $category->type, $category->dre_group ?: '-');
        $category->delete();

        $this->logFinancialAction($request, 'financeiro.categories.delete', 'financial_categories', $categoryId, $details);

        return back()->with('success', 'Categoria excluida com sucesso.');
    }

    public function installmentsIndex(Request $request): View
    {
        $items = FinancialInstallment::query()
            ->with(['contract', 'parentReceivable', 'receivable'])
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->input('status')))
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return view('pages.financeiro.installments.index', [
            'title' => 'Parcelamentos',
            'items' => $items,
            'filters' => $request->all(),
        ]);
    }

    public function installmentsShow(FinancialInstallment $installment): View
    {
        $installment->load(['contract', 'parentReceivable', 'receivable.transactions']);

        return view('pages.financeiro.installments.show', [
            'title' => $installment->code ?: ('Parcelamento #' . $installment->id),
            'item' => $installment,
        ]);
    }

    public function installmentsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_receivable_id' => ['required', 'integer', 'exists:financial_receivables,id'],
            'installment_total' => ['required', 'integer', 'min:2'],
            'first_due_date' => ['required', 'date'],
        ]);

        $receivable = FinancialReceivable::query()->findOrFail((int) $validated['parent_receivable_id']);
        return $this->createInstallmentsForReceivable($request, $receivable, (int) $validated['installment_total'], Carbon::parse($validated['first_due_date']));
    }

    public function installmentsDestroy(Request $request, FinancialInstallment $installment): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $installmentId = $installment->id;
        $details = sprintf(
            'Excluiu parcela %s (%d/%d) — valor %s, vencimento %s.',
            $installment->code ?: ('#' . $installment->id),
            (int) $installment->installment_number,
            (int) $installment->installment_total,
            FinancialValue::money($installment->amount),
            optional($installment->due_date)->format('d/m/Y') ?: '-'
        );
        $installment->delete();

        $this->logFinancialAction($request, 'financeiro.installments.delete', 'financial_installments', $installmentId, $details);

        return back()->with('success', 'Parcela removida com sucesso.');
    }

    public function reimbursementsIndex(Request $request): View
    {
        $items = FinancialReimbursement::query()
            ->with(['client', 'process', 'responsible'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('pages.financeiro.reimbursements.index', array_merge($this->financialFormOptions(), [
            'title' => 'Reembolsos',
            'items' => $items,
            'filters' => $request->all(),
        ]));
    }

    public function reimbursementsCreate(): View
    {
        return view('pages.financeiro.reimbursements.form', array_merge($this->financialFormOptions(), [
            'title' => 'Novo reembolso',
            'mode' => 'create',
            'item' => null,
        ]));
    }

    public function reimbursementsStore(Request $request): RedirectResponse
    {
        $validated = $this->validateReimbursement($request);
        $validated['code'] = $this->codeService->next('financial_reimbursements', 'entry_prefix', 'RMB');
        $reimbursement = FinancialReimbursement::query()->create($validated);

        $this->logFinancialAction(
            $request,
            'financeiro.reimbursements.store',
            'financial_reimbursements',
            $reimbursement->id,
            sprintf('Criou reembolso %s — %s.', $reimbursement->code ?: ('#' . $reimbursement->id), $this->reimbursementAuditSummary($reimbursement))
        );

        return redirect()->route('financeiro.reimbursements.index')->with('success', 'Reembolso criado com sucesso.');
    }

    public function reimbursementsEdit(FinancialReimbursement $reimbursement): View
    {
        return view('pages.financeiro.reimbursements.form', array_merge($this->financialFormOptions(), [
            'title' => 'Editar reembolso',
            'mode' => 'edit',
            'item' => $reimbursement,
        ]));
    }

    public function reimbursementsUpdate(Request $request, FinancialReimbursement $reimbursement): RedirectResponse
    {
        $before = 'valores anteriores -> ' . $this->reimbursementAuditSummary($reimbursement);
        $reimbursement->update($this->validateReimbursement($request));
        $reimbursement->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.reimbursements.update',
            'financial_reimbursements',
            $reimbursement->id,
            sprintf('Atualizou reembolso %s — %s. (%s)', $reimbursement->code ?: ('#' . $reimbursement->id), $this->reimbursementAuditSummary($reimbursement), $before)
        );

        return redirect()->route('financeiro.reimbursements.index')->with('success', 'Reembolso atualizado com sucesso.');
    }

    public function reimbursementsDestroy(Request $request, FinancialReimbursement $reimbursement): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $reimbursementId = $reimbursement->id;
        $details = sprintf('Excluiu reembolso %s — %s.', $reimbursement->code ?: ('#' . $reimbursement->id), $this->reimbursementAuditSummary($reimbursement));
        $reimbursement->delete();

        $this->logFinancialAction($request, 'financeiro.reimbursements.delete', 'financial_reimbursements', $reimbursementId, $details);

        return back()->with('success', 'Reembolso excluido com sucesso.');
    }

    public function processCostsIndex(Request $request): View
    {
        $items = FinancialProcessCost::query()
            ->with(['client', 'process', 'category', 'costCenter', 'reimbursement'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('pages.financeiro.process-costs.index', array_merge($this->financialFormOptions(), [
            'title' => 'Custas Processuais',
            'items' => $items,
            'filters' => $request->all(),
        ]));
    }

    public function processCostsCreate(): View
    {
        return view('pages.financeiro.process-costs.form', array_merge($this->financialFormOptions(), [
            'title' => 'Nova custa processual',
            'mode' => 'create',
            'item' => null,
        ]));
    }

    public function processCostsStore(Request $request): RedirectResponse
    {
        $validated = $this->validateProcessCost($request);
        $validated['code'] = $this->codeService->next('financial_process_costs', 'entry_prefix', 'CST');
        $processCost = FinancialProcessCost::query()->create($validated);

        $this->logFinancialAction(
            $request,
            'financeiro.process-costs.store',
            'financial_process_costs',
            $processCost->id,
            sprintf('Criou custa processual %s — %s.', $processCost->code ?: ('#' . $processCost->id), $this->processCostAuditSummary($processCost))
        );

        return redirect()->route('financeiro.process-costs.index')->with('success', 'Custa processual criada com sucesso.');
    }

    public function processCostsEdit(FinancialProcessCost $processCost): View
    {
        return view('pages.financeiro.process-costs.form', array_merge($this->financialFormOptions(), [
            'title' => 'Editar custa processual',
            'mode' => 'edit',
            'item' => $processCost,
        ]));
    }

    public function processCostsUpdate(Request $request, FinancialProcessCost $processCost): RedirectResponse
    {
        $before = 'valores anteriores -> ' . $this->processCostAuditSummary($processCost);
        $processCost->update($this->validateProcessCost($request));
        $processCost->refresh();

        $this->logFinancialAction(
            $request,
            'financeiro.process-costs.update',
            'financial_process_costs',
            $processCost->id,
            sprintf('Atualizou custa processual %s — %s. (%s)', $processCost->code ?: ('#' . $processCost->id), $this->processCostAuditSummary($processCost), $before)
        );

        return redirect()->route('financeiro.process-costs.index')->with('success', 'Custa processual atualizada com sucesso.');
    }

    public function processCostsDestroy(Request $request, FinancialProcessCost $processCost): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $processCostId = $processCost->id;
        $details = sprintf('Excluiu custa processual %s — %s.', $processCost->code ?: ('#' . $processCost->id), $this->processCostAuditSummary($processCost));
        $processCost->delete();

        $this->logFinancialAction($request, 'financeiro.process-costs.delete', 'financial_process_costs', $processCostId, $details);

        return back()->with('success', 'Custa processual excluida com sucesso.');
    }

    public function accountabilityIndex(Request $request): View
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfMonth();
        $clientId = $this->intOrNull($request->input('client_id'));
        $condominiumId = $this->intOrNull($request->input('condominium_id'));

        return view('pages.financeiro.accountability.index', array_merge($this->financialFormOptions(), [
            'title' => 'Prestacao de Contas',
            'from' => $from,
            'to' => $to,
            'clientId' => $clientId,
            'condominiumId' => $condominiumId,
            'data' => $this->reportingService->accountabilityData($clientId, $condominiumId, $from, $to),
        ]));
    }

    public function accountabilityPdf(Request $request): View|BinaryFileResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfMonth();
        $clientId = $this->intOrNull($request->input('client_id'));
        $condominiumId = $this->intOrNull($request->input('condominium_id'));
        $payload = [
            'title' => 'Prestacao de contas',
            'from' => $from,
            'to' => $to,
            'data' => $this->reportingService->accountabilityData($clientId, $condominiumId, $from, $to),
            'brand' => \App\Support\AncoraSettings::brand(),
            'pdfMode' => true,
        ];

        $path = $this->pdfService->renderViewToPdf('pages.financeiro.accountability.pdf', $payload, storage_path('app/generated/financial'), 'prestacao-contas-' . now()->format('YmdHis'));
        if (!$path) {
            return view('pages.financeiro.accountability.pdf', array_merge($payload, ['pdfMode' => false]));
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }

    public function dreIndex(Request $request): View
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfYear();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfMonth();
        $basis = $request->input('basis') === 'competencia' ? 'competencia' : 'caixa';

        return view('pages.financeiro.dre.index', array_merge($this->financialFormOptions(), [
            'title' => 'DRE',
            'from' => $from,
            'to' => $to,
            'basis' => $basis,
            'data' => $this->reportingService->dreData($from, $to, $basis),
        ]));
    }

    public function drePdf(Request $request): View|BinaryFileResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfYear();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfMonth();
        $basis = $request->input('basis') === 'competencia' ? 'competencia' : 'caixa';
        $payload = [
            'title' => 'DRE',
            'from' => $from,
            'to' => $to,
            'basis' => $basis,
            'data' => $this->reportingService->dreData($from, $to, $basis),
            'brand' => \App\Support\AncoraSettings::brand(),
            'pdfMode' => true,
        ];

        $path = $this->pdfService->renderViewToPdf('pages.financeiro.dre.pdf', $payload, storage_path('app/generated/financial'), 'dre-' . now()->format('YmdHis'));
        if (!$path) {
            return view('pages.financeiro.dre.pdf', array_merge($payload, ['pdfMode' => false]));
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }

    public function reportsIndex(Request $request): View
    {
        $summary = $this->reportingService->dashboardData((int) $request->integer('year', now()->year));

        return view('pages.financeiro.reports.index', array_merge($this->financialFormOptions(), [
            'title' => 'Relatorios Financeiros',
            'summary' => $summary['summary'],
            'exportScopes' => FinancialCatalog::exportScopes(),
            'importScopes' => FinancialCatalog::importScopes(),
            'recentImports' => FinancialImportLog::query()->latest('id')->limit(8)->get(),
        ]));
    }

    public function settingsIndex(): View
    {
        return view('pages.financeiro.settings.index', [
            'title' => 'Configuracoes Financeiras',
            'settings' => FinancialSettings::all(),
            'defaults' => FinancialSettings::defaults(),
            'receivableStatuses' => FinancialCatalog::receivableStatuses(),
            'payableStatuses' => FinancialCatalog::payableStatuses(),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->get(),
        ]);
    }

    public function settingsSave(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_interest_percent' => ['nullable', 'string', 'max:20'],
            'default_penalty_percent' => ['nullable', 'string', 'max:20'],
            'default_account_id' => ['nullable', 'integer'],
            'alert_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'entry_prefix' => ['nullable', 'string', 'max:20'],
            'auto_numbering' => ['nullable', 'boolean'],
            'default_receivable_status' => ['nullable', 'string'],
            'default_payable_status' => ['nullable', 'string'],
            'billing_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'default_city' => ['nullable', 'string', 'max:120'],
            'default_state' => ['nullable', 'string', 'max:10'],
        ]);

        $changed = [];
        foreach (FinancialSettings::defaults() as $key => $default) {
            $newValue = (string) ($validated[$key] ?? ($request->boolean($key) ? '1' : ($request->has($key) ? '0' : $default)));
            $previous = (string) FinancialSettings::get($key, $default);
            FinancialSetting::query()->updateOrCreate(['key' => $key], ['value' => $newValue]);
            if ($previous !== $newValue) {
                $changed[] = sprintf('%s: "%s" -> "%s"', $key, $previous, $newValue);
            }
        }

        $this->logFinancialAction(
            $request,
            'financeiro.settings.save',
            'financeiro',
            null,
            $changed === []
                ? 'Salvou configuracoes financeiras sem alteracoes de valores.'
                : 'Alterou configuracoes financeiras — ' . implode(' · ', $changed) . '.'
        );

        return back()->with('success', 'Configuracoes financeiras atualizadas com sucesso.');
    }

    private function normalizedReceivablePayload(Request $request): array
    {
        $original = $this->moneyToDecimal($request->input('original_amount'));
        $interest = $this->moneyToDecimal($request->input('interest_amount'));
        $penalty = $this->moneyToDecimal($request->input('penalty_amount'));
        $correction = $this->moneyToDecimal($request->input('correction_amount'));
        $discount = $this->moneyToDecimal($request->input('discount_amount'));

        return [
            'code' => trim((string) $request->input('code', '')) ?: null,
            'title' => trim((string) $request->input('title')),
            'reference' => trim((string) $request->input('reference', '')) ?: null,
            'billing_type' => trim((string) $request->input('billing_type', '')) ?: null,
            'client_id' => $this->intOrNull($request->input('client_id')),
            'condominium_id' => $this->intOrNull($request->input('condominium_id')),
            'unit_id' => $this->intOrNull($request->input('unit_id')),
            'contract_id' => $this->intOrNull($request->input('contract_id')),
            'process_id' => $this->intOrNull($request->input('process_id')),
            'category_id' => $this->intOrNull($request->input('category_id')),
            'cost_center_id' => $this->intOrNull($request->input('cost_center_id')),
            'account_id' => $this->intOrNull($request->input('account_id')),
            'original_amount' => $original,
            'interest_amount' => $interest,
            'penalty_amount' => $penalty,
            'correction_amount' => $correction,
            'discount_amount' => $discount,
            'final_amount' => round($original + $interest + $penalty + $correction - $discount, 2),
            'due_date' => $request->input('due_date') ?: null,
            'competence_date' => $request->input('competence_date') ?: null,
            'payment_method' => trim((string) $request->input('payment_method', '')) ?: null,
            'recurrence' => trim((string) $request->input('recurrence', '')) ?: null,
            'status' => trim((string) $request->input('status', FinancialSettings::get('default_receivable_status', 'aberto'))),
            'collection_stage' => trim((string) $request->input('collection_stage', '')) ?: null,
            'generate_collection' => $request->boolean('generate_collection'),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'responsible_user_id' => $this->intOrNull($request->input('responsible_user_id')),
        ];
    }

    private function normalizedPayablePayload(Request $request): array
    {
        return [
            'code' => trim((string) $request->input('code', '')) ?: null,
            'title' => trim((string) $request->input('title')),
            'supplier_entity_id' => $this->intOrNull($request->input('supplier_entity_id')),
            'supplier_name_snapshot' => trim((string) $request->input('supplier_name_snapshot', '')) ?: null,
            'category_id' => $this->intOrNull($request->input('category_id')),
            'cost_center_id' => $this->intOrNull($request->input('cost_center_id')),
            'account_id' => $this->intOrNull($request->input('account_id')),
            'process_id' => $this->intOrNull($request->input('process_id')),
            'amount' => $this->moneyToDecimal($request->input('amount')),
            'due_date' => $request->input('due_date') ?: null,
            'competence_date' => $request->input('competence_date') ?: null,
            'status' => trim((string) $request->input('status', FinancialSettings::get('default_payable_status', 'aberto'))),
            'payment_method' => trim((string) $request->input('payment_method', '')) ?: null,
            'recurrence' => trim((string) $request->input('recurrence', '')) ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'responsible_user_id' => $this->intOrNull($request->input('responsible_user_id')),
        ];
    }

    private function normalizedAccountPayload(Request $request): array
    {
        return [
            'code' => trim((string) $request->input('code', '')) ?: null,
            'name' => trim((string) $request->input('name')),
            'bank_name' => trim((string) $request->input('bank_name', '')) ?: null,
            'agency' => trim((string) $request->input('agency', '')) ?: null,
            'account_number' => trim((string) $request->input('account_number', '')) ?: null,
            'account_digit' => trim((string) $request->input('account_digit', '')) ?: null,
            'account_type' => trim((string) $request->input('account_type')),
            'pix_key' => trim((string) $request->input('pix_key', '')) ?: null,
            'account_holder' => trim((string) $request->input('account_holder', '')) ?: null,
            'opening_balance' => $this->moneyToDecimal($request->input('opening_balance')),
            'credit_limit' => $this->moneyToDecimal($request->input('credit_limit')),
            'is_primary' => $request->boolean('is_primary'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function validateReimbursement(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'process_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'string', 'max:40'],
            'paid_by_office_amount' => ['nullable', 'string', 'max:40'],
            'reimbursed_amount' => ['nullable', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
            'reimbursed_at' => ['nullable', 'date'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return [
            'client_id' => $this->intOrNull($validated['client_id'] ?? null),
            'process_id' => $this->intOrNull($validated['process_id'] ?? null),
            'type' => trim((string) ($validated['type'] ?? '')) ?: null,
            'amount' => $this->moneyToDecimal($validated['amount']),
            'paid_by_office_amount' => $this->moneyToDecimal($validated['paid_by_office_amount'] ?? null),
            'reimbursed_amount' => $this->moneyToDecimal($validated['reimbursed_amount'] ?? null),
            'due_date' => $validated['due_date'] ?? null,
            'reimbursed_at' => $validated['reimbursed_at'] ?? null,
            'status' => $validated['status'],
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'responsible_user_id' => $this->intOrNull($validated['responsible_user_id'] ?? null),
        ];
    }

    private function validateProcessCost(Request $request): array
    {
        $validated = $request->validate([
            'process_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'category_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:financial_cost_centers,id'],
            'reimbursement_id' => ['nullable', 'integer', 'exists:financial_reimbursements,id'],
            'cost_type' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'string', 'max:40'],
            'reimbursed_amount' => ['nullable', 'string', 'max:40'],
            'cost_date' => ['nullable', 'date'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return [
            'process_id' => $this->intOrNull($validated['process_id'] ?? null),
            'client_id' => $this->intOrNull($validated['client_id'] ?? null),
            'category_id' => $this->intOrNull($validated['category_id'] ?? null),
            'cost_center_id' => $this->intOrNull($validated['cost_center_id'] ?? null),
            'reimbursement_id' => $this->intOrNull($validated['reimbursement_id'] ?? null),
            'cost_type' => trim((string) ($validated['cost_type'] ?? '')) ?: null,
            'amount' => $this->moneyToDecimal($validated['amount']),
            'reimbursed_amount' => $this->moneyToDecimal($validated['reimbursed_amount'] ?? null),
            'cost_date' => $validated['cost_date'] ?? null,
            'status' => $validated['status'],
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ];
    }

    private function applyReceivableFilters(Builder $query, Request $request): void
    {
        foreach (['client_id', 'condominium_id', 'unit_id', 'contract_id', 'category_id', 'cost_center_id', 'account_id', 'responsible_user_id'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, (int) $request->input($key));
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

    private function storeAttachments(array $files, string $ownerType, int $ownerId, ?string $fileType, ?string $description, ?int $userId): void
    {
        $baseDir = storage_path('app/public/financial/' . $ownerType . '/' . $ownerId);
        File::ensureDirectoryExists($baseDir);

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $storedName = now()->format('YmdHis') . '-' . uniqid('', true) . '.' . strtolower((string) $file->getClientOriginalExtension());
            $fullPath = $baseDir . DIRECTORY_SEPARATOR . $storedName;
            $file->move($baseDir, $storedName);

            FinancialAttachment::query()->create([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'relative_path' => 'financial/' . $ownerType . '/' . $ownerId . '/' . $storedName,
                'file_type' => $fileType ?: strtolower((string) $file->getClientOriginalExtension()),
                'mime_type' => $file->getMimeType(),
                'file_size' => is_file($fullPath) ? filesize($fullPath) : 0,
                'description' => $description ?: null,
                'uploaded_by' => $userId,
            ]);
        }
    }

    private function storeReceivableSeries(array $payload, Request $request, int $userId): array
    {
        $occurrences = $request->filled('occurrences') ? max(1, (int) $request->input('occurrences')) : null;
        $repeatUntil = $request->filled('repeat_until')
            ? Carbon::parse((string) $request->input('repeat_until'))->startOfDay()
            : null;
        $rows = $this->receivableSeriesService->buildManualRows(
            $payload,
            $payload['recurrence'] ?? null,
            $occurrences,
            $repeatUntil
        );

        $seriesTotal = count($rows);
        $seriesGroup = $seriesTotal > 1 ? $this->receivableSeriesService->makeSeriesGroup('manual') : null;
        $created = collect();

        DB::transaction(function () use ($payload, $rows, $seriesGroup, $seriesTotal, $userId, $created) {
            foreach ($rows as $index => $row) {
                $itemPayload = array_merge($payload, [
                    'code' => $this->receivableCodeForSeries($payload['code'] ?? null, $index, $seriesTotal),
                    'title' => $row['title'],
                    'reference' => $row['reference'],
                    'billing_type' => $row['billing_type'],
                    'original_amount' => $row['amount'],
                    'final_amount' => round(
                        $row['amount']
                        + (float) ($payload['interest_amount'] ?? 0)
                        + (float) ($payload['penalty_amount'] ?? 0)
                        + (float) ($payload['correction_amount'] ?? 0)
                        - (float) ($payload['discount_amount'] ?? 0),
                        2
                    ),
                    'due_date' => $row['due_date']->toDateString(),
                    'competence_date' => $row['competence_date']->toDateString(),
                    'recurrence' => $row['recurrence'] ?? ($payload['recurrence'] ?? null),
                    'series_group' => $seriesGroup,
                    'series_index' => $seriesTotal > 1 ? ($row['series_index'] ?? ($index + 1)) : null,
                    'series_total' => $seriesTotal > 1 ? ($row['series_total'] ?? $seriesTotal) : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $item = FinancialReceivable::query()->create(FinancialColumns::filter('financial_receivables', $itemPayload));
                $this->ledgerService->syncReceivable($item);
                $created->push($item);
            }
        });

        return [
            'created' => $created,
            'series_total' => $seriesTotal,
        ];
    }

    private function receivableCodeForSeries(?string $requestedCode, int $index, int $seriesTotal): string
    {
        $requestedCode = trim((string) $requestedCode);
        if ($requestedCode === '') {
            return $this->codeService->next('financial_receivables', 'entry_prefix', 'REC');
        }

        if ($seriesTotal <= 1) {
            return $requestedCode;
        }

        $suffix = '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        $baseLength = max(1, 60 - strlen($suffix));
        $base = Str::limit($requestedCode, $baseLength, '');

        return $base . $suffix;
    }

    /**
     * Gera contas a pagar recorrentes/em serie, espelhando o comportamento das contas a
     * receber. Reaproveita o motor de series (FinancialReceivableSeriesService), mapeando o
     * valor da conta a pagar ('amount') para a chave 'original_amount' esperada pelo motor.
     */
    private function storePayableSeries(array $payload, Request $request, int $userId): array
    {
        $occurrences = $request->filled('occurrences') ? max(1, (int) $request->input('occurrences')) : null;
        $repeatUntil = $request->filled('repeat_until')
            ? Carbon::parse((string) $request->input('repeat_until'))->startOfDay()
            : null;

        $seriesPayload = array_merge($payload, [
            'original_amount' => (float) ($payload['amount'] ?? 0),
            'reference' => null,
            'billing_type' => null,
        ]);

        $rows = $this->receivableSeriesService->buildManualRows(
            $seriesPayload,
            $payload['recurrence'] ?? null,
            $occurrences,
            $repeatUntil
        );

        $seriesTotal = count($rows);
        $seriesGroup = $seriesTotal > 1 ? $this->receivableSeriesService->makeSeriesGroup('payable') : null;
        $created = collect();

        DB::transaction(function () use ($payload, $rows, $seriesGroup, $seriesTotal, $userId, $created) {
            foreach ($rows as $index => $row) {
                $itemPayload = array_merge($payload, [
                    'code' => $this->payableCodeForSeries($payload['code'] ?? null, $index, $seriesTotal),
                    'title' => trim((string) ($row['title'] ?? '')) !== '' ? $row['title'] : ($payload['title'] ?? ''),
                    'amount' => $row['amount'],
                    'due_date' => $row['due_date']->toDateString(),
                    'competence_date' => $row['competence_date']->toDateString(),
                    'recurrence' => $row['recurrence'] ?? ($payload['recurrence'] ?? null),
                    'series_group' => $seriesGroup,
                    'series_index' => $seriesTotal > 1 ? ($row['series_index'] ?? ($index + 1)) : null,
                    'series_total' => $seriesTotal > 1 ? ($row['series_total'] ?? $seriesTotal) : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // 'reference' nao existe em financial_payables; garantimos que nao vai no insert.
                unset($itemPayload['reference']);

                $item = FinancialPayable::query()->create(FinancialColumns::filter('financial_payables', $itemPayload));
                $this->ledgerService->syncPayable($item);
                $created->push($item);
            }
        });

        return [
            'created' => $created,
            'series_total' => $seriesTotal,
        ];
    }

    private function payableCodeForSeries(?string $requestedCode, int $index, int $seriesTotal): string
    {
        $requestedCode = trim((string) $requestedCode);
        if ($requestedCode === '') {
            return $this->codeService->next('financial_payables', 'entry_prefix', 'PAG');
        }

        if ($seriesTotal <= 1) {
            return $requestedCode;
        }

        $suffix = '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        $baseLength = max(1, 60 - strlen($suffix));
        $base = Str::limit($requestedCode, $baseLength, '');

        return $base . $suffix;
    }

    private function createInstallmentsForReceivable(Request $request, FinancialReceivable $receivable, int $totalInstallments, Carbon $firstDueDate): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $remaining = round((float) $receivable->final_amount - (float) $receivable->received_amount, 2);

        if ($remaining <= 0) {
            return back()->with('error', 'Nao ha saldo a parcelar neste recebivel.');
        }

        DB::transaction(function () use ($receivable, $user, $totalInstallments, $firstDueDate, $remaining) {
            $receivable->forceFill(['status' => 'negociado'])->save();

            $base = floor(($remaining / $totalInstallments) * 100) / 100;
            $allocated = 0.0;
            $seriesGroup = $this->receivableSeriesService->makeSeriesGroup('installment');

            for ($i = 1; $i <= $totalInstallments; $i++) {
                $amount = $i === $totalInstallments ? round($remaining - $allocated, 2) : round($base, 2);
                $allocated += $amount;
                $dueDate = $firstDueDate->copy()->addMonths($i - 1);

                $child = FinancialReceivable::query()->create([
                    'code' => $this->codeService->next('financial_receivables', 'entry_prefix', 'REC'),
                    'title' => $receivable->title . ' - Parcela ' . $i . '/' . $totalInstallments,
                    'reference' => $receivable->reference,
                    'billing_type' => 'parcela',
                    'client_id' => $receivable->client_id,
                    'condominium_id' => $receivable->condominium_id,
                    'unit_id' => $receivable->unit_id,
                    'contract_id' => $receivable->contract_id,
                    'process_id' => $receivable->process_id,
                    'category_id' => $receivable->category_id,
                    'cost_center_id' => $receivable->cost_center_id,
                    'account_id' => $receivable->account_id,
                    'original_amount' => $amount,
                    'final_amount' => $amount,
                    'due_date' => $dueDate->toDateString(),
                    'competence_date' => $dueDate->copy()->startOfMonth()->toDateString(),
                    'recurrence' => $totalInstallments > 1 ? 'mensal' : 'unica',
                    'series_group' => $seriesGroup,
                    'series_index' => $i,
                    'series_total' => $totalInstallments,
                    'status' => 'aberto',
                    'generate_collection' => true,
                    'notes' => 'Parcela criada a partir de ' . ($receivable->code ?: ('#' . $receivable->id)) . '.',
                    'responsible_user_id' => $receivable->responsible_user_id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                FinancialInstallment::query()->create([
                    'code' => $this->codeService->next('financial_installments', 'entry_prefix', 'PAR'),
                    'title' => $receivable->title,
                    'client_id' => $receivable->client_id,
                    'condominium_id' => $receivable->condominium_id,
                    'unit_id' => $receivable->unit_id,
                    'contract_id' => $receivable->contract_id,
                    'parent_receivable_id' => $receivable->id,
                    'receivable_id' => $child->id,
                    'installment_number' => $i,
                    'installment_total' => $totalInstallments,
                    'amount' => $amount,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'aberto',
                ]);
            }
        });

        $this->logFinancialAction(
            $request,
            'financeiro.installments.store',
            'financial_installments',
            $receivable->id,
            sprintf(
                'Gerou parcelamento em %dx da conta a receber %s — saldo parcelado %s, 1o vencimento %s.',
                $totalInstallments,
                $receivable->code ?: ('#' . $receivable->id),
                FinancialValue::money($remaining),
                $firstDueDate->format('d/m/Y')
            )
        );

        return back()->with('success', 'Parcelamento gerado com sucesso.');
    }

    /**
     * Resumo legível de uma conta a pagar para a trilha de auditoria.
     */
    private function payableAuditSummary(FinancialPayable $payable): string
    {
        $parts = [
            'titulo: ' . ($payable->title ?: '-'),
            'valor: ' . FinancialValue::money($payable->amount),
        ];

        if ($payable->due_date) {
            $parts[] = 'vencimento: ' . $payable->due_date->format('d/m/Y');
        }

        $supplier = $payable->supplier?->display_name ?: $payable->supplier_name_snapshot;
        if ($supplier) {
            $parts[] = 'fornecedor: ' . $supplier;
        }

        $parts[] = 'status: ' . $payable->status;

        return implode(' · ', $parts);
    }

    /**
     * Resumo legível de uma conta a receber para a trilha de auditoria.
     */
    private function receivableAuditSummary(FinancialReceivable $receivable): string
    {
        $parts = [
            'titulo: ' . ($receivable->title ?: '-'),
            'valor: ' . FinancialValue::money($receivable->final_amount),
        ];

        if ($receivable->due_date) {
            $parts[] = 'vencimento: ' . $receivable->due_date->format('d/m/Y');
        }

        $client = $receivable->client?->display_name ?: $receivable->condominium?->name;
        if ($client) {
            $parts[] = 'cliente: ' . $client;
        }

        $parts[] = 'status: ' . $receivable->status;

        return implode(' · ', $parts);
    }

    /**
     * Resumo legível de uma movimentação do fluxo de caixa (transação) para auditoria.
     */
    private function transactionAuditSummary(FinancialTransaction $transaction): string
    {
        $typeLabel = FinancialCatalog::transactionTypes()[$transaction->transaction_type] ?? $transaction->transaction_type;
        $parts = [
            'tipo: ' . $typeLabel,
            'valor: ' . FinancialValue::money($transaction->amount),
        ];

        if ($transaction->transaction_date) {
            $parts[] = 'data: ' . $transaction->transaction_date->format('d/m/Y H:i');
        }
        if ($transaction->account) {
            $parts[] = 'conta: ' . $transaction->account->name;
        }
        if ($transaction->destinationAccount) {
            $parts[] = 'conta destino: ' . $transaction->destinationAccount->name;
        }
        if ($transaction->category) {
            $parts[] = 'categoria: ' . $transaction->category->name;
        }
        $method = FinancialCatalog::paymentMethods()[$transaction->payment_method] ?? $transaction->payment_method;
        if ($method) {
            $parts[] = 'forma: ' . $method;
        }
        if ($transaction->document_number) {
            $parts[] = 'documento: ' . $transaction->document_number;
        }
        if ($transaction->description) {
            $parts[] = 'descricao: ' . Str::limit($transaction->description, 120);
        }

        return implode(' · ', $parts);
    }

    /**
     * Resumo legível de um reembolso para auditoria.
     */
    private function reimbursementAuditSummary(FinancialReimbursement $reimbursement): string
    {
        $parts = [
            'tipo: ' . ($reimbursement->type ?: '-'),
            'valor: ' . FinancialValue::money($reimbursement->amount),
            'reembolsado: ' . FinancialValue::money($reimbursement->reimbursed_amount),
        ];

        $client = $reimbursement->client?->display_name;
        if ($client) {
            $parts[] = 'cliente: ' . $client;
        }
        if ($reimbursement->process_id) {
            $parts[] = 'processo: #' . $reimbursement->process_id;
        }
        if ($reimbursement->due_date) {
            $parts[] = 'vencimento: ' . $reimbursement->due_date->format('d/m/Y');
        }
        $parts[] = 'status: ' . $reimbursement->status;

        return implode(' · ', $parts);
    }

    /**
     * Resumo legível de uma custa processual para auditoria.
     */
    private function processCostAuditSummary(FinancialProcessCost $processCost): string
    {
        $parts = [
            'tipo: ' . ($processCost->cost_type ?: '-'),
            'valor: ' . FinancialValue::money($processCost->amount),
            'reembolsado: ' . FinancialValue::money($processCost->reimbursed_amount),
        ];

        if ($processCost->process_id) {
            $parts[] = 'processo: #' . $processCost->process_id;
        }
        $client = $processCost->client?->display_name;
        if ($client) {
            $parts[] = 'cliente: ' . $client;
        }
        if ($processCost->category) {
            $parts[] = 'categoria: ' . $processCost->category->name;
        }
        if ($processCost->cost_date) {
            $parts[] = 'data: ' . $processCost->cost_date->format('d/m/Y');
        }
        $parts[] = 'status: ' . $processCost->status;

        return implode(' · ', $parts);
    }

    /**
     * Resumo legível de uma conta bancária/financeira para auditoria.
     */
    private function accountAuditSummary(FinancialAccount $account): string
    {
        $typeLabel = FinancialCatalog::accountTypes()[$account->account_type] ?? $account->account_type;
        $parts = [
            'nome: ' . ($account->name ?: '-'),
            'tipo: ' . $typeLabel,
        ];

        if ($account->bank_name) {
            $parts[] = 'banco: ' . $account->bank_name;
        }
        if ($account->account_number) {
            $parts[] = 'conta: ' . $account->account_number . ($account->account_digit ? '-' . $account->account_digit : '');
        }
        $parts[] = 'saldo inicial: ' . FinancialValue::money($account->opening_balance);
        if ($account->is_primary) {
            $parts[] = 'principal';
        }
        $parts[] = $account->is_active ? 'ativa' : 'inativa';

        return implode(' · ', $parts);
    }

    /**
     * Registra uma entrada detalhada na trilha de auditoria do módulo financeiro e
     * sinaliza ao middleware (audit.activity) para não duplicar com o log genérico.
     */
    private function logFinancialAction(Request $request, string $action, string $entityType, ?int $entityId, string $details): void
    {
        try {
            $user = AncoraAuth::user($request);
            AuditLog::query()->create([
                'user_id' => $user?->id,
                'user_email' => $user?->email ?? 'desconhecido',
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
            $request->attributes->set('audit.skip_generic', true);
        } catch (\Throwable) {
            // Auditoria nunca deve derrubar a operação principal do usuário.
        }
    }
}
