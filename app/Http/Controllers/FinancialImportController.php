<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
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
use App\Services\FinancialCodeService;
use App\Services\FinancialLedgerService;
use App\Support\AncoraAuth;
use App\Support\Financeiro\FinancialCatalog;
use App\Support\Financeiro\FinancialValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class FinancialImportController extends Controller
{
    public function __construct(
        private readonly FinancialCodeService $codeService,
        private readonly FinancialLedgerService $ledgerService,
    ) {
    }

    public function template(string $scope): BinaryFileResponse
    {
        $scope = trim(strtolower($scope));
        abort_unless(array_key_exists($scope, FinancialCatalog::importScopes()), 404);

        $headers = $this->templateHeaders($scope);
        $dir = storage_path('app/generated/financial');
        File::ensureDirectoryExists($dir);
        $path = $dir . DIRECTORY_SEPARATOR . 'modelo-' . $scope . '.csv';

        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');
        fclose($handle);

        return response()->download($path, basename($path), ['Content-Type' => 'text/csv; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    public function preview(Request $request, string $scope): RedirectResponse
    {
        $log = $this->previewImport($request, $scope);
        return redirect()->route('financeiro.import.show', $log)->with('success', 'Importacao analisada com sucesso.');
    }

    public function previewImport(Request $request, string $scope, bool $fromReconciliation = false): FinancialImportLog
    {
        $scope = trim(strtolower($scope));
        abort_unless(array_key_exists($scope, FinancialCatalog::importScopes()), 404);

        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $request->validate([
            'import_file' => ['required_without:statement_file', 'file', 'mimes:csv,xlsx,ofx'],
            'statement_file' => ['required_without:import_file', 'file', 'mimes:csv,xlsx,ofx'],
        ]);

        $file = $request->file('import_file') ?: $request->file('statement_file');
        abort_unless($file instanceof UploadedFile && $file->isValid(), 422);

        $stored = $this->storeImportFile($file, $scope);
        $parsed = $this->parseImportFile($stored['path'], $scope);
        $normalized = $this->normalizePreviewRows($scope, $parsed['headers'], $parsed['rows'], $request);
        $errors = $this->validatePreviewRows($scope, $normalized['rows']);

        return FinancialImportLog::query()->create([
            'scope' => $scope,
            'source_format' => $stored['extension'],
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $stored['relative'],
            'preview_rows_count' => count($normalized['rows']),
            'status' => $errors === [] ? 'preview' : 'preview_with_errors',
            'payload_json' => [
                'headers' => $normalized['headers'],
                'rows' => $normalized['rows'],
                'context' => [
                    'account_id' => $request->input('account_id'),
                    'from_reconciliation' => $fromReconciliation,
                ],
            ],
            'errors_json' => $errors,
            'processed_by' => $user->id,
        ]);
    }

    public function show(FinancialImportLog $import): View
    {
        return view('pages.financeiro.imports.show', [
            'title' => 'Preview da importacao financeira',
            'item' => $import,
            'scopeLabel' => FinancialCatalog::importScopes()[$import->scope] ?? $import->scope,
            'headers' => $import->payload_json['headers'] ?? [],
            'rows' => $import->payload_json['rows'] ?? [],
            'errors' => $import->errors_json ?? [],
        ]);
    }

    public function process(Request $request, FinancialImportLog $import): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $rows = $import->payload_json['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            return back()->with('error', 'Nao ha linhas validas para importar.');
        }

        try {
            DB::transaction(function () use ($import, $rows, $user) {
                foreach ($rows as $row) {
                    $this->processRow($import->scope, (array) $row, (int) $user->id, (array) ($import->payload_json['context'] ?? []));
                }
            });
        } catch (\Throwable $e) {
            $import->forceFill([
                'status' => 'error',
                'failed_rows' => count($rows),
                'errors_json' => array_merge((array) ($import->errors_json ?? []), [$e->getMessage()]),
                'processed_at' => now(),
            ])->save();

            return back()->with('error', 'Falha ao importar lote: ' . $e->getMessage());
        }

        $import->forceFill([
            'status' => 'processed',
            'success_rows' => count($rows),
            'failed_rows' => 0,
            'processed_at' => now(),
        ])->save();

        return back()->with('success', 'Importacao concluida com sucesso.');
    }

    private function processRow(string $scope, array $row, int $userId, array $context): void
    {
        match ($scope) {
            'receivables' => $this->processReceivableRow($row, $userId),
            'payables' => $this->processPayableRow($row, $userId),
            'categories' => $this->processCategoryRow($row),
            'cost-centers' => $this->processCostCenterRow($row),
            'accounts' => $this->processAccountRow($row),
            'transactions' => $this->processTransactionRow($row, $userId),
            'installments' => $this->processInstallmentRow($row, $userId),
            'reimbursements' => $this->processReimbursementRow($row),
            'statements' => $this->processStatementRow($row, $context),
            default => null,
        };
    }

    private function processReceivableRow(array $row, int $userId): void
    {
        $title = $this->rowValue($row, ['titulo', 'title']);
        $amount = FinancialValue::decimalFromInput($this->rowValue($row, ['valor', 'valor_final', 'final_amount'])) ?? 0;
        if ($title === '' || $amount <= 0) {
            throw new \RuntimeException('Linha de contas a receber sem titulo ou valor valido.');
        }

        $client = $this->findClientByName($this->rowValue($row, ['cliente', 'client']));
        $condominium = $this->findCondominiumByName($this->rowValue($row, ['condominio', 'condominium']));
        $unit = $this->findUnit($this->rowValue($row, ['unidade', 'unit']), $condominium?->id);
        $category = $this->findCategoryByName($this->rowValue($row, ['categoria', 'category']));
        $account = $this->findAccountByName($this->rowValue($row, ['conta', 'account']));

        FinancialReceivable::query()->updateOrCreate(
            [
                'title' => $title,
                'due_date' => $this->rowDate($row, ['vencimento', 'due_date']),
                'client_id' => $client?->id,
                'condominium_id' => $condominium?->id,
            ],
            [
                'code' => $this->codeService->next('financial_receivables', 'entry_prefix', 'REC'),
                'reference' => $this->rowValue($row, ['referencia', 'reference']),
                'billing_type' => $this->rowValue($row, ['tipo_cobranca', 'billing_type']) ?: 'honorario',
                'unit_id' => $unit?->id,
                'category_id' => $category?->id,
                'account_id' => $account?->id,
                'original_amount' => FinancialValue::decimalFromInput($this->rowValue($row, ['valor_original', 'original_amount'])) ?? $amount,
                'interest_amount' => FinancialValue::decimalFromInput($this->rowValue($row, ['juros', 'interest_amount'])) ?? 0,
                'penalty_amount' => FinancialValue::decimalFromInput($this->rowValue($row, ['multa', 'penalty_amount'])) ?? 0,
                'correction_amount' => FinancialValue::decimalFromInput($this->rowValue($row, ['correcao', 'correction_amount'])) ?? 0,
                'discount_amount' => FinancialValue::decimalFromInput($this->rowValue($row, ['desconto', 'discount_amount'])) ?? 0,
                'final_amount' => $amount,
                'competence_date' => $this->rowDate($row, ['competencia', 'competence_date']),
                'status' => $this->rowValue($row, ['status']) ?: 'aberto',
                'notes' => $this->rowValue($row, ['observacoes', 'notes']) ?: null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function processPayableRow(array $row, int $userId): void
    {
        $title = $this->rowValue($row, ['titulo', 'title']);
        $amount = FinancialValue::decimalFromInput($this->rowValue($row, ['valor', 'amount'])) ?? 0;
        if ($title === '' || $amount <= 0) {
            throw new \RuntimeException('Linha de contas a pagar sem titulo ou valor valido.');
        }

        $supplier = $this->findClientByName($this->rowValue($row, ['fornecedor', 'supplier']));
        $category = $this->findCategoryByName($this->rowValue($row, ['categoria', 'category']));
        $costCenter = $this->findCostCenterByName($this->rowValue($row, ['centro_custo', 'cost_center']));
        $account = $this->findAccountByName($this->rowValue($row, ['conta', 'account']));

        FinancialPayable::query()->updateOrCreate(
            [
                'title' => $title,
                'due_date' => $this->rowDate($row, ['vencimento', 'due_date']),
                'supplier_entity_id' => $supplier?->id,
            ],
            [
                'code' => $this->codeService->next('financial_payables', 'entry_prefix', 'PAG'),
                'supplier_name_snapshot' => $supplier?->display_name ?: $this->rowValue($row, ['fornecedor', 'supplier']),
                'category_id' => $category?->id,
                'cost_center_id' => $costCenter?->id,
                'account_id' => $account?->id,
                'amount' => $amount,
                'competence_date' => $this->rowDate($row, ['competencia', 'competence_date']),
                'status' => $this->rowValue($row, ['status']) ?: 'aberto',
                'payment_method' => $this->rowValue($row, ['forma_pagamento', 'payment_method']) ?: null,
                'notes' => $this->rowValue($row, ['observacoes', 'notes']) ?: null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function processCategoryRow(array $row): void
    {
        $name = $this->rowValue($row, ['nome', 'name']);
        if ($name === '') {
            throw new \RuntimeException('Categoria sem nome.');
        }

        FinancialCategory::query()->updateOrCreate(
            ['name' => $name],
            [
                'type' => $this->rowValue($row, ['tipo', 'type']) ?: 'receita',
                'description' => $this->rowValue($row, ['descricao', 'description']) ?: null,
                'dre_group' => $this->rowValue($row, ['grupo_dre', 'dre_group']) ?: null,
                'color_hex' => $this->rowValue($row, ['cor', 'color']) ?: null,
                'is_active' => $this->rowBool($row, ['ativa', 'ativo', 'is_active'], true),
            ]
        );
    }

    private function processCostCenterRow(array $row): void
    {
        $name = $this->rowValue($row, ['nome', 'name']);
        if ($name === '') {
            throw new \RuntimeException('Centro de custo sem nome.');
        }

        FinancialCostCenter::query()->updateOrCreate(
            ['name' => $name],
            [
                'description' => $this->rowValue($row, ['descricao', 'description']) ?: null,
                'is_active' => $this->rowBool($row, ['ativo', 'is_active'], true),
            ]
        );
    }

    private function processAccountRow(array $row): void
    {
        $name = $this->rowValue($row, ['nome', 'name']);
        if ($name === '') {
            throw new \RuntimeException('Conta financeira sem nome.');
        }

        FinancialAccount::query()->updateOrCreate(
            ['name' => $name],
            [
                'code' => $this->codeService->next('financial_accounts', 'entry_prefix', 'CTA'),
                'bank_name' => $this->rowValue($row, ['banco', 'bank_name']) ?: null,
                'agency' => $this->rowValue($row, ['agencia', 'agency']) ?: null,
                'account_number' => $this->rowValue($row, ['conta', 'account_number']) ?: null,
                'account_digit' => $this->rowValue($row, ['digito', 'account_digit']) ?: null,
                'account_type' => $this->rowValue($row, ['tipo', 'account_type']) ?: 'conta_corrente',
                'pix_key' => $this->rowValue($row, ['pix', 'pix_key']) ?: null,
                'opening_balance' => FinancialValue::decimalFromInput($this->rowValue($row, ['saldo_inicial', 'opening_balance'])) ?? 0,
                'credit_limit' => FinancialValue::decimalFromInput($this->rowValue($row, ['limite', 'credit_limit'])) ?? 0,
                'is_primary' => $this->rowBool($row, ['principal', 'is_primary'], false),
                'is_active' => $this->rowBool($row, ['ativa', 'is_active'], true),
            ]
        );
    }

    private function processTransactionRow(array $row, int $userId): void
    {
        $amount = FinancialValue::decimalFromInput($this->rowValue($row, ['valor', 'amount'])) ?? 0;
        if ($amount <= 0) {
            throw new \RuntimeException('Movimentacao sem valor valido.');
        }

        $account = $this->findAccountByName($this->rowValue($row, ['conta', 'account']));
        $category = $this->findCategoryByName($this->rowValue($row, ['categoria', 'category']));
        $costCenter = $this->findCostCenterByName($this->rowValue($row, ['centro_custo', 'cost_center']));

        $this->ledgerService->recordStandaloneTransaction([
            'transaction_type' => $this->rowValue($row, ['tipo', 'transaction_type']) ?: 'entrada',
            'account_id' => $account?->id,
            'category_id' => $category?->id,
            'cost_center_id' => $costCenter?->id,
            'amount' => $amount,
            'transaction_date' => $this->rowDate($row, ['data', 'transaction_date']) ?: now()->toDateString(),
            'payment_method' => $this->rowValue($row, ['forma', 'payment_method']) ?: null,
            'description' => $this->rowValue($row, ['descricao', 'description']) ?: null,
            'source' => $this->rowValue($row, ['origem', 'source']) ?: 'Importacao',
            'document_number' => $this->rowValue($row, ['documento', 'document_number']) ?: null,
            'created_by' => $userId,
            'direction' => ($this->rowValue($row, ['tipo', 'transaction_type']) ?: 'entrada') === 'entrada' ? 'entrada' : 'saida',
        ]);
    }

    private function processInstallmentRow(array $row, int $userId): void
    {
        $parentCode = $this->rowValue($row, ['recebivel_origem', 'parent_receivable']);
        $receivable = FinancialReceivable::query()->where('code', $parentCode)->first();
        if (!$receivable) {
            throw new \RuntimeException('Recebivel origem nao localizado para parcelamento.');
        }

        $total = (int) ($this->rowValue($row, ['quantidade', 'installment_total']) ?: 0);
        $firstDueDate = $this->rowDate($row, ['primeiro_vencimento', 'first_due_date']);
        if ($total < 2 || !$firstDueDate) {
            throw new \RuntimeException('Parcelamento exige quantidade >= 2 e primeiro vencimento.');
        }

        $request = request();
        $request->merge(['installment_total' => $total, 'first_due_date' => $firstDueDate]);
        app(FinancialController::class)->receivablesParcel($request, $receivable);
    }

    private function processReimbursementRow(array $row): void
    {
        $amount = FinancialValue::decimalFromInput($this->rowValue($row, ['valor', 'amount'])) ?? 0;
        if ($amount <= 0) {
            throw new \RuntimeException('Reembolso sem valor valido.');
        }

        $client = $this->findClientByName($this->rowValue($row, ['cliente', 'client']));

        FinancialReimbursement::query()->create([
            'code' => $this->codeService->next('financial_reimbursements', 'entry_prefix', 'RMB'),
            'client_id' => $client?->id,
            'process_id' => null,
            'type' => $this->rowValue($row, ['tipo', 'type']) ?: null,
            'amount' => $amount,
            'paid_by_office_amount' => FinancialValue::decimalFromInput($this->rowValue($row, ['pago_pelo_escritorio', 'paid_by_office_amount'])) ?? 0,
            'reimbursed_amount' => FinancialValue::decimalFromInput($this->rowValue($row, ['reembolsado', 'reimbursed_amount'])) ?? 0,
            'due_date' => $this->rowDate($row, ['vencimento', 'due_date']),
            'status' => $this->rowValue($row, ['status']) ?: 'pendente',
        ]);
    }

    private function processStatementRow(array $row, array $context): void
    {
        $amount = FinancialValue::decimalFromInput($this->rowValue($row, ['valor', 'amount'])) ?? 0;
        if ($amount <= 0) {
            throw new \RuntimeException('Extrato sem valor valido.');
        }

        $account = null;
        if (!empty($context['account_id'])) {
            $account = FinancialAccount::query()->find((int) $context['account_id']);
        }
        if (!$account) {
            $account = $this->findAccountByName($this->rowValue($row, ['conta', 'account']));
        }

        FinancialStatement::query()->updateOrCreate(
            [
                'raw_hash' => sha1(json_encode($row, JSON_UNESCAPED_UNICODE)),
            ],
            [
                'account_id' => $account?->id,
                'statement_date' => $this->rowDate($row, ['data', 'statement_date']),
                'description' => $this->rowValue($row, ['descricao', 'description']),
                'document_number' => $this->rowValue($row, ['documento', 'document_number']) ?: null,
                'amount' => $amount,
                'balance_after' => FinancialValue::decimalFromInput($this->rowValue($row, ['saldo', 'balance_after'])),
                'direction' => $this->rowValue($row, ['tipo', 'direction']) ?: 'entrada',
                'is_reconciled' => false,
                'payload_json' => $row,
            ]
        );
    }

    private function validatePreviewRows(string $scope, array $rows): array
    {
        $errors = [];
        foreach ($rows as $index => $row) {
            $hasValue = collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty();
            if (!$hasValue) {
                continue;
            }

            match ($scope) {
                'receivables' => $this->requireColumns($errors, $index, $row, ['titulo', 'valor', 'vencimento']),
                'payables' => $this->requireColumns($errors, $index, $row, ['titulo', 'valor', 'vencimento']),
                'categories' => $this->requireColumns($errors, $index, $row, ['nome']),
                'cost-centers' => $this->requireColumns($errors, $index, $row, ['nome']),
                'accounts' => $this->requireColumns($errors, $index, $row, ['nome', 'tipo']),
                'transactions' => $this->requireColumns($errors, $index, $row, ['tipo', 'valor', 'data']),
                'installments' => $this->requireColumns($errors, $index, $row, ['recebivel_origem', 'quantidade', 'primeiro_vencimento']),
                'reimbursements' => $this->requireColumns($errors, $index, $row, ['cliente', 'valor']),
                'statements' => $this->requireColumns($errors, $index, $row, ['data', 'descricao', 'valor']),
                default => null,
            };
        }

        return $errors;
    }

    private function requireColumns(array &$errors, int $index, array $row, array $keys): void
    {
        foreach ($keys as $key) {
            if (trim((string) ($row[$key] ?? '')) === '') {
                $errors[] = 'Linha ' . ($index + 1) . ' sem a coluna obrigatoria "' . $key . '".';
            }
        }
    }

    private function normalizePreviewRows(string $scope, array $headers, array $rows, Request $request): array
    {
        $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $mappedRows = [];

        foreach ($rows as $row) {
            $assoc = [];
            foreach ($normalizedHeaders as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = trim((string) ($row[$index] ?? ''));
            }

            if ($scope === 'statements' && $request->filled('account_id')) {
                $assoc['account_id'] = (string) $request->input('account_id');
            }

            $mappedRows[] = $assoc;
        }

        return [
            'headers' => $normalizedHeaders,
            'rows' => $mappedRows,
        ];
    }

    private function parseImportFile(string $path, string $scope): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'ofx' || ($scope === 'statements' && $extension === 'ofx')) {
            return $this->parseOfx($path);
        }

        $script = base_path('scripts/parse_generic_tabular.py');
        $process = new Process(['python3', $script, $path], timeout: 120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Falha ao ler o arquivo enviado.');
        }

        $payload = json_decode($process->getOutput(), true);
        if (!is_array($payload) || !empty($payload['error'])) {
            throw new \RuntimeException((string) ($payload['error'] ?? 'Falha ao interpretar o arquivo.'));
        }

        return [
            'headers' => array_values($payload['headers'] ?? []),
            'rows' => array_values($payload['rows'] ?? []),
        ];
    }

    private function parseOfx(string $path): array
    {
        $content = (string) file_get_contents($path);
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $content, $matches);

        $rows = [];
        foreach ($matches[1] ?? [] as $block) {
            $rows[] = [
                $this->extractOfxValue($block, 'DTPOSTED'),
                $this->extractOfxValue($block, 'MEMO'),
                $this->extractOfxValue($block, 'CHECKNUM'),
                $this->extractOfxValue($block, 'TRNTYPE'),
                $this->extractOfxValue($block, 'TRNAMT'),
                '',
            ];
        }

        return [
            'headers' => ['data', 'descricao', 'documento', 'tipo', 'valor', 'saldo'],
            'rows' => $rows,
        ];
    }

    private function extractOfxValue(string $block, string $tag): string
    {
        if (!preg_match('/<' . preg_quote($tag, '/') . '>([^<\r\n]+)/i', $block, $match)) {
            return '';
        }

        return trim((string) $match[1]);
    }

    private function storeImportFile(UploadedFile $file, string $scope): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $dir = storage_path('app/public/financial/imports/' . $scope);
        File::ensureDirectoryExists($dir);
        $filename = now()->format('YmdHis') . '-' . uniqid('', true) . '.' . $extension;
        $file->move($dir, $filename);

        return [
            'path' => $dir . DIRECTORY_SEPARATOR . $filename,
            'relative' => 'financial/imports/' . $scope . '/' . $filename,
            'extension' => $extension,
        ];
    }

    private function templateHeaders(string $scope): array
    {
        return match ($scope) {
            'receivables' => ['titulo', 'cliente', 'condominio', 'unidade', 'categoria', 'valor_original', 'juros', 'multa', 'correcao', 'desconto', 'valor', 'vencimento', 'competencia', 'status'],
            'payables' => ['titulo', 'fornecedor', 'categoria', 'centro_custo', 'conta', 'valor', 'vencimento', 'competencia', 'status'],
            'categories' => ['tipo', 'nome', 'descricao', 'grupo_dre', 'cor', 'ativa'],
            'cost-centers' => ['nome', 'descricao', 'ativo'],
            'accounts' => ['nome', 'banco', 'tipo', 'agencia', 'conta', 'digito', 'pix', 'saldo_inicial', 'limite', 'principal', 'ativa'],
            'transactions' => ['tipo', 'conta', 'categoria', 'centro_custo', 'valor', 'data', 'forma', 'origem', 'descricao', 'documento'],
            'installments' => ['recebivel_origem', 'quantidade', 'primeiro_vencimento'],
            'reimbursements' => ['cliente', 'tipo', 'valor', 'pago_pelo_escritorio', 'reembolsado', 'status', 'vencimento'],
            'statements' => ['data', 'descricao', 'documento', 'tipo', 'valor', 'saldo'],
            default => [],
        };
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::lower(trim($header));
        $header = str_replace(
            ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', ' ', '-', '/', '.'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', '_', '_', '_', '_'],
            $header
        );

        return preg_replace('/[^a-z0-9_]/', '', $header) ?: '';
    }

    private function rowValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (array_key_exists($normalized, $row) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }

        return '';
    }

    private function rowDate(array $row, array $keys): ?string
    {
        $value = $this->rowValue($row, $keys);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function rowBool(array $row, array $keys, bool $default = false): bool
    {
        $value = Str::lower($this->rowValue($row, $keys));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'sim', 's', 'true', 'ativo'], true);
    }

    private function findClientByName(string $name): ?ClientEntity
    {
        return $name !== '' ? ClientEntity::query()->where('display_name', $name)->orWhere('legal_name', $name)->first() : null;
    }

    private function findCondominiumByName(string $name): ?ClientCondominium
    {
        return $name !== '' ? ClientCondominium::query()->where('name', $name)->first() : null;
    }

    private function findUnit(string $unitNumber, ?int $condominiumId = null): ?ClientUnit
    {
        if ($unitNumber === '') {
            return null;
        }

        return ClientUnit::query()
            ->when($condominiumId, fn ($query) => $query->where('condominium_id', $condominiumId))
            ->where('unit_number', $unitNumber)
            ->first();
    }

    private function findCategoryByName(string $name): ?FinancialCategory
    {
        return $name !== '' ? FinancialCategory::query()->where('name', $name)->first() : null;
    }

    private function findCostCenterByName(string $name): ?FinancialCostCenter
    {
        return $name !== '' ? FinancialCostCenter::query()->where('name', $name)->first() : null;
    }

    private function findAccountByName(string $name): ?FinancialAccount
    {
        return $name !== '' ? FinancialAccount::query()->where('name', $name)->first() : null;
    }
}
