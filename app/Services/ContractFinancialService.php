<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialCostCenter;
use App\Models\FinancialInstallment;
use App\Models\FinancialReceivable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContractFinancialService
{
    public function __construct(
        private readonly FinancialCodeService $codeService,
        private readonly FinancialLedgerService $ledgerService,
        private readonly FinancialReceivableSeriesService $seriesService,
    ) {
    }

    public function hasFinancialEntries(Contract $contract): bool
    {
        return FinancialReceivable::query()
            ->where('contract_id', $contract->id)
            ->exists();
    }

    public function hasProtectedFinancialEntries(Contract $contract): bool
    {
        return FinancialReceivable::query()
            ->where('contract_id', $contract->id)
            ->where(function ($query) {
                $query->where('received_amount', '>', 0)
                    ->orWhereIn('status', ['recebido', 'parcial'])
                    ->orWhereExists(function ($subquery) {
                        $subquery->selectRaw('1')
                            ->from('financial_transactions')
                            ->whereColumn('financial_transactions.receivable_id', 'financial_receivables.id');
                    });
            })
            ->exists();
    }

    public function validateFinancialData(array $payload): array
    {
        $errors = [];
        $billingType = $this->normalize((string) ($payload['billing_type'] ?? ''));
        $paymentMethod = $this->normalize((string) ($payload['payment_method'] ?? ''));
        $installmentQuantity = (int) ($payload['installment_quantity'] ?? 0);
        $dueDay = (int) ($payload['due_day'] ?? 0);
        $totalValue = (float) ($payload['total_value'] ?? 0);
        $monthlyValue = (float) ($payload['monthly_value'] ?? 0);

        if ($billingType === '') {
            $errors[] = 'Selecione a forma de cobranca para gerar lancamentos automaticos no Financeiro 360.';
        }

        if ($paymentMethod === '') {
            $errors[] = 'Selecione a forma de pagamento antes de gerar lancamentos automaticos no Financeiro 360.';
        }

        if (!in_array($billingType, ['mensal', 'unica', 'parcelada'], true)) {
            $errors[] = 'A geracao automatica no Financeiro 360 esta disponivel apenas para cobranca Mensal, Parcela unica ou Parcelado.';
        }

        if ($dueDay < 1 || $dueDay > 31) {
            $errors[] = 'Informe o dia de vencimento para gerar os lancamentos financeiros.';
        }

        if ($billingType === 'mensal' && $monthlyValue <= 0) {
            $errors[] = 'Informe o valor mensal para contratos com cobranca Mensal.';
        }

        if (in_array($billingType, ['unica', 'parcelada'], true) && $totalValue <= 0) {
            $errors[] = 'Informe o valor total para contratos com cobranca Parcela unica ou Parcelado.';
        }

        if ($billingType === 'parcelada' && $installmentQuantity < 2) {
            $errors[] = 'Informe a quantidade de parcelas com valor minimo de 2 para a cobranca Parcelada.';
        }

        if ($billingType === 'mensal' && trim((string) ($payload['recurrence'] ?? '')) === '') {
            $errors[] = 'Informe a recorrencia do contrato mensal para gerar a programacao financeira.';
        }

        if ($paymentMethod === 'boleto' && $billingType === 'mensal' && trim((string) ($payload['recurrence'] ?? '')) === '') {
            $errors[] = 'Contratos mensais com pagamento em boleto exigem recorrencia definida.';
        }

        if (!$this->paymentMethodDispensesFinancialAccount($paymentMethod) && !$this->resolveFinancialAccountIdFromPayload($payload)) {
            $errors[] = 'Selecione um banco/conta para gerar os lancamentos automaticos do Financeiro 360.';
        }

        return $errors;
    }

    public function generateFinancialEntries(Contract $contract, ?int $userId = null): array
    {
        return DB::transaction(fn () => $this->syncFinancialEntries($contract, $userId, false));
    }

    public function recreateFinancialEntries(Contract $contract, ?int $userId = null): array
    {
        return DB::transaction(fn () => $this->syncFinancialEntries($contract, $userId, true));
    }

    public function importFinancialEntriesFromContract(Contract $contract, ?int $userId = null): array
    {
        return DB::transaction(function () use ($contract, $userId) {
            $contract->loadMissing(['client', 'condominium', 'unit', 'responsible', 'financialAccount']);

            return $this->persistSchedule(
                $contract,
                $this->seriesService->buildContractRows($contract),
                $userId
            );
        });
    }

    /**
     * Gera, sob demanda, um recebivel de honorario de exito (billing_type 'exito') a partir de
     * uma base de ganho e um percentual. Acao manual e explicita: nao ha gatilho automatico no
     * encerramento do processo, para nao criar lancamento financeiro sem revisao humana.
     */
    public function generateSuccessFeeReceivable(
        Contract $contract,
        float $baseAmount,
        float $percentage,
        ?Carbon $dueDate = null,
        ?int $userId = null
    ): FinancialReceivable {
        $amount = round(max(0.0, $baseAmount) * max(0.0, $percentage) / 100, 2);
        $due = ($dueDate ?: now())->copy()->startOfDay();

        return DB::transaction(function () use ($contract, $amount, $percentage, $baseAmount, $due, $userId) {
            $contract->loadMissing(['client', 'condominium', 'unit', 'responsible', 'financialAccount']);

            $receivable = FinancialReceivable::query()->create([
                'code' => $this->codeService->next('financial_receivables', 'entry_prefix', 'REC'),
                'title' => $contract->title . ' - Honorario de exito',
                'reference' => 'Honorario de exito',
                'billing_type' => 'exito',
                'client_id' => $contract->client_id,
                'condominium_id' => $contract->condominium_id,
                'unit_id' => $contract->unit_id,
                'contract_id' => $contract->id,
                'process_id' => $contract->process_id,
                'category_id' => $this->resolveCategoryId($contract, 'honorario'),
                'cost_center_id' => $this->resolveCostCenterId($contract),
                'account_id' => $this->resolveFinancialAccountIdForContract($contract),
                'original_amount' => $amount,
                'final_amount' => $amount,
                'due_date' => $due->toDateString(),
                'competence_date' => $due->copy()->startOfMonth()->toDateString(),
                'payment_method' => $contract->payment_method,
                'status' => 'aberto',
                'generate_collection' => !$this->paymentMethodDispensesFinancialAccount((string) $contract->payment_method),
                'responsible_user_id' => $contract->responsible_user_id,
                'created_by' => $userId,
                'updated_by' => $userId,
                'notes' => 'Honorario de exito gerado a partir do contrato ' . ($contract->code ?: ('#' . $contract->id))
                    . ' (' . number_format($percentage, 2, ',', '.') . '% sobre ' . number_format($baseAmount, 2, ',', '.') . ').',
            ]);

            $this->ledgerService->syncReceivable($receivable);

            return $receivable;
        });
    }

    public function createMissingFinancialEntriesForPeriod(
        Contract $contract,
        Carbon $from,
        Carbon $to,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($contract, $from, $to, $userId) {
            $contract->loadMissing(['client', 'condominium', 'unit', 'responsible', 'financialAccount']);

            return $this->persistSchedule(
                $contract,
                $this->seriesService->buildContractRowsInRange($contract, $from, $to),
                $userId
            );
        });
    }

    public function refreshOpenAndFutureFinancialEntries(
        Contract $contract,
        ?int $userId = null,
        ?Carbon $from = null
    ): array {
        return DB::transaction(function () use ($contract, $userId, $from) {
            $contract->loadMissing(['client', 'condominium', 'unit', 'responsible', 'financialAccount']);

            if (!$this->canGenerateAutomatically($contract)) {
                return [
                    'created' => collect(),
                    'skipped' => collect(),
                    'deleted' => 0,
                    'protected' => collect(),
                ];
            }

            $windowStart = ($from ?: now()->startOfMonth())->copy()->startOfDay();
            $schedule = array_values(array_filter(
                $this->seriesService->buildContractRows($contract),
                fn (array $row) => $row['due_date']->gte($windowStart)
            ));

            $existing = FinancialReceivable::query()
                ->where('contract_id', $contract->id)
                ->whereDate('due_date', '>=', $windowStart->toDateString())
                ->with('transactions')
                ->get();

            $protected = $existing->filter(fn (FinancialReceivable $receivable) => $this->isProtectedReceivable($receivable));
            $deletableIds = $existing
                ->reject(fn (FinancialReceivable $receivable) => $this->isProtectedReceivable($receivable))
                ->pluck('id')
                ->all();

            $deleted = $this->deleteReceivableSet($deletableIds);

            $sync = $this->persistSchedule(
                $contract,
                $schedule,
                $userId
            );

            $sync['deleted'] = $deleted;
            $sync['protected'] = $protected->values();

            return $sync;
        });
    }

    private function syncFinancialEntries(Contract $contract, ?int $userId, bool $recreate): array
    {
        $contract->loadMissing(['client', 'condominium', 'unit', 'responsible', 'financialAccount']);

        if (!$this->canGenerateAutomatically($contract)) {
            return [
                'created' => collect(),
                'skipped' => collect(),
                'deleted' => 0,
            ];
        }

        $deleted = 0;
        if ($recreate) {
            if ($this->hasProtectedFinancialEntries($contract)) {
                throw new \RuntimeException('Ja existem lancamentos financeiros com baixa ou movimentacao vinculada a este contrato. Mantenha os registros atuais e ajuste o Financeiro manualmente.');
            }

            $deleted = $this->deleteExistingEntries($contract);
        }

        $sync = $this->persistSchedule($contract, $this->buildSchedule($contract), $userId);
        $created = $sync['created'];
        $skipped = $sync['skipped'];

        Log::info('contracts.financial.sync', [
            'contract_id' => $contract->id,
            'billing_type' => $contract->billing_type,
            'recreate' => $recreate,
            'deleted' => $deleted,
            'created' => $created->count(),
            'skipped' => $skipped->count(),
        ]);

        return [
            'created' => $created,
            'skipped' => $skipped,
            'deleted' => $deleted,
        ];
    }

    private function canGenerateAutomatically(Contract $contract): bool
    {
        return $contract->generate_financial_entries
            && in_array($contract->status, ['ativo', 'assinado'], true)
            && in_array($contract->billing_type, ['mensal', 'unica', 'parcelada'], true);
    }

    private function deleteExistingEntries(Contract $contract): int
    {
        $receivableIds = FinancialReceivable::query()
            ->where('contract_id', $contract->id)
            ->pluck('id');

        return $this->deleteReceivableSet($receivableIds->all(), $contract->id);
    }

    private function buildSchedule(Contract $contract): array
    {
        return $this->seriesService->buildContractRows($contract);
    }

    private function persistSchedule(Contract $contract, array $schedule, ?int $userId = null): array
    {
        $created = collect();
        $skipped = collect();
        $seriesGroup = $this->seriesService->makeSeriesGroup('contract-' . $contract->id);

        foreach ($schedule as $row) {
            $exists = FinancialReceivable::query()
                ->where('contract_id', $contract->id)
                ->whereDate('due_date', $row['due_date']->toDateString())
                ->where('billing_type', $row['billing_type'])
                ->exists();

            if ($exists) {
                $skipped->push($row['due_date']->toDateString());
                continue;
            }

            $notes = collect([
                trim((string) ($row['notes'] ?? '')) ?: null,
                trim((string) ($contract->financial_notes ?? '')) ?: null,
            ])->filter()->implode("\n\n");

            $receivable = FinancialReceivable::query()->create([
                'code' => $this->codeService->next('financial_receivables', 'entry_prefix', 'REC'),
                'title' => $row['title'],
                'reference' => $row['reference'],
                'billing_type' => $row['billing_type'],
                'client_id' => $contract->client_id,
                'condominium_id' => $contract->condominium_id,
                'unit_id' => $contract->unit_id,
                'contract_id' => $contract->id,
                'process_id' => $contract->process_id,
                'category_id' => $this->resolveCategoryId($contract, $row['billing_type']),
                'cost_center_id' => $this->resolveCostCenterId($contract),
                'account_id' => $this->resolveFinancialAccountIdForContract($contract),
                'original_amount' => $row['amount'],
                'final_amount' => $row['amount'],
                'due_date' => $row['due_date']->toDateString(),
                'competence_date' => $row['competence_date']->toDateString(),
                'payment_method' => $contract->payment_method,
                'recurrence' => $row['recurrence'] ?? null,
                'series_group' => $seriesGroup,
                'series_index' => $row['series_index'] ?? null,
                'series_total' => $row['series_total'] ?? null,
                'status' => 'aberto',
                'generate_collection' => !$this->paymentMethodDispensesFinancialAccount((string) $contract->payment_method),
                'responsible_user_id' => $contract->responsible_user_id,
                'created_by' => $userId,
                'updated_by' => $userId,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            if (!empty($row['installment_number']) && !empty($row['installment_total'])) {
                FinancialInstallment::query()->create([
                    'code' => $this->codeService->next('financial_installments', 'entry_prefix', 'PAR'),
                    'title' => $row['reference'],
                    'client_id' => $contract->client_id,
                    'condominium_id' => $contract->condominium_id,
                    'unit_id' => $contract->unit_id,
                    'contract_id' => $contract->id,
                    'receivable_id' => $receivable->id,
                    'installment_number' => $row['installment_number'],
                    'installment_total' => $row['installment_total'],
                    'amount' => $row['amount'],
                    'due_date' => $row['due_date']->toDateString(),
                    'status' => 'aberto',
                ]);
            }

            $this->ledgerService->syncReceivable($receivable);
            $created->push($receivable);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'deleted' => 0,
        ];
    }

    private function deleteReceivableSet(array $receivableIds, ?int $contractId = null): int
    {
        $receivableIds = array_values(array_filter(array_map('intval', $receivableIds)));
        if ($receivableIds === [] && !$contractId) {
            return 0;
        }

        FinancialInstallment::query()
            ->where(function ($query) use ($contractId, $receivableIds) {
                if ($contractId) {
                    $query->where('contract_id', $contractId);
                }
                if ($receivableIds !== []) {
                    $method = $contractId ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('receivable_id', $receivableIds)
                        ->orWhereIn('parent_receivable_id', $receivableIds);
                }
            })
            ->delete();

        return FinancialReceivable::query()
            ->where(function ($query) use ($contractId, $receivableIds) {
                if ($contractId) {
                    $query->where('contract_id', $contractId);
                }
                if ($receivableIds !== []) {
                    $method = $contractId ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('id', $receivableIds);
                }
            })
            ->delete();
    }

    private function isProtectedReceivable(FinancialReceivable $receivable): bool
    {
        return (float) $receivable->received_amount > 0
            || in_array($receivable->status, ['recebido', 'parcial'], true)
            || $receivable->transactions->isNotEmpty();
    }

    private function resolveCategoryId(Contract $contract, string $billingType): ?int
    {
        $future = trim((string) ($contract->financial_category_future ?? ''));
        if ($future !== '') {
            $id = FinancialCategory::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($future)])
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        $name = match ($billingType) {
            'mensalidade' => 'Assessoria',
            'parcela' => 'Acordos',
            default => 'Honorarios',
        };

        return FinancialCategory::query()
            ->where('name', $name)
            ->value('id');
    }

    private function resolveCostCenterId(Contract $contract): ?int
    {
        $future = trim((string) ($contract->cost_center_future ?? ''));
        if ($future === '') {
            return null;
        }

        return FinancialCostCenter::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($future)])
            ->value('id');
    }

    private function resolveFinancialAccountIdForContract(Contract $contract): ?int
    {
        return $this->resolveFinancialAccountIdFromPayload([
            'financial_account_id' => $contract->financial_account_id,
            'payment_method' => $contract->payment_method,
        ]);
    }

    private function resolveFinancialAccountIdFromPayload(array $payload): ?int
    {
        $paymentMethod = $this->normalize((string) ($payload['payment_method'] ?? ''));
        $selected = (int) ($payload['financial_account_id'] ?? 0);

        if ($this->paymentMethodDispensesFinancialAccount($paymentMethod)) {
            return $selected > 0 ? $selected : FinancialAccount::query()
                ->where('is_active', true)
                ->whereIn('account_type', ['caixa', 'carteira'])
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->value('id');
        }

        return $selected > 0 ? $selected : null;
    }

    private function paymentMethodDispensesFinancialAccount(string $paymentMethod): bool
    {
        return in_array($this->normalize($paymentMethod), ['especie', 'dinheiro'], true);
    }

    private function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->squish()->toString();
    }
}
