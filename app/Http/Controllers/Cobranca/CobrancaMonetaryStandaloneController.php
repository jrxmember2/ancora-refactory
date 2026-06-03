<?php

namespace App\Http\Controllers\Cobranca;

use App\Http\Controllers\Concerns\CobrancaMonetarySupport;
use App\Http\Controllers\Controller;
use App\Models\ClientEntity;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaStandaloneMonetaryUpdate;
use App\Models\CobrancaStandaloneMonetaryUpdateItem;
use App\Services\CobrancaMonetaryUpdateService;
use App\Support\AncoraAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Atualizacao monetaria TJES "avulsa" (sem vinculo a uma OS de cobranca).
 * Extraido do CobrancaController durante a decomposicao (strangler). Os nomes de rota
 * permanecem identicos (cobrancas.monetary.standalone.*).
 */
class CobrancaMonetaryStandaloneController extends Controller
{
    use CobrancaMonetarySupport;

    public function standaloneMonetaryIndex(Request $request): View
    {
        $storageReady = $this->standaloneMonetaryStorageReady();
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'client_entity_id' => (int) $request->integer('client_entity_id'),
        ];

        $items = collect();
        if ($storageReady) {
            $query = CobrancaStandaloneMonetaryUpdate::query()
                ->with(['client', 'generator', 'items']);

            if ($filters['q'] !== '') {
                $search = '%' . str_replace(' ', '%', $filters['q']) . '%';
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('title', 'like', $search)
                        ->orWhere('debtor_name_snapshot', 'like', $search)
                        ->orWhere('debtor_document_snapshot', 'like', $search)
                        ->orWhereHas('client', function ($clientQuery) use ($search) {
                            $clientQuery
                                ->where('display_name', 'like', $search)
                                ->orWhere('legal_name', 'like', $search);
                        });
                });
            }

            if ($filters['client_entity_id'] > 0) {
                $query->where('client_entity_id', $filters['client_entity_id']);
            }

            $items = $query
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString();
        }

        return view('pages.cobrancas.monetary.standalone.index', [
            'title' => 'TJES avulso',
            'storageReady' => $storageReady,
            'filters' => $filters,
            'items' => $items,
            'clients' => $this->standaloneClientOptions(),
        ]);
    }

    public function standaloneMonetaryCreate(): View
    {
        return view('pages.cobrancas.monetary.standalone.create', [
            'title' => 'Novo TJES avulso',
            'storageReady' => $this->standaloneMonetaryStorageReady(),
            'clients' => $this->standaloneClientOptions(),
            'defaultFinalDate' => now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function standaloneMonetaryPreview(Request $request, CobrancaMonetaryUpdateService $service): JsonResponse
    {
        if (!$this->standaloneMonetaryStorageReady()) {
            return response()->json([
                'message' => 'A estrutura do TJES avulso ainda nao existe no banco. Rode as migrations antes de simular.',
            ], 409);
        }

        try {
            [$metadata, $errors, $quotaRows, $options] = $this->standaloneMonetaryPayloadFromRequest($request, false);
            unset($metadata);

            if ($errors !== []) {
                return response()->json(['message' => implode(' ', $errors)], 422);
            }

            $calculation = $service->calculate($this->standaloneCalculationCase($quotaRows), $options);

            return response()->json($service->formatPreview($calculation));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function standaloneMonetaryStore(Request $request, CobrancaMonetaryUpdateService $service): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if (!$this->standaloneMonetaryStorageReady()) {
            return back()->withInput()->with('error', 'A estrutura do TJES avulso ainda nao existe no banco. Rode as migrations antes de salvar calculos.');
        }

        try {
            [$metadata, $errors, $quotaRows, $options] = $this->standaloneMonetaryPayloadFromRequest($request, true);
            if ($errors !== []) {
                return back()->withInput()->with('error', implode(' ', $errors));
            }

            $calculation = $service->calculate($this->standaloneCalculationCase($quotaRows), $options);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Nao foi possivel calcular a atualizacao monetaria avulsa: ' . $e->getMessage());
        }

        $memory = DB::transaction(function () use ($metadata, $calculation, $user, $request) {
            $memory = $this->persistStandaloneMonetaryUpdate($metadata, $calculation, $user->id);

            $this->logAction(
                $request,
                'save_cobranca_standalone_monetary_update',
                $memory->id,
                'Atualizacao monetaria TJES avulsa - ' . $memory->title
            );

            return $memory;
        });

        return redirect()
            ->route('cobrancas.monetary.standalone.show', $memory)
            ->with('success', 'Memoria de calculo avulsa salva com sucesso. Total geral: R$ ' . number_format((float) $memory->grand_total, 2, ',', '.'));
    }

    public function standaloneMonetaryShow(CobrancaStandaloneMonetaryUpdate $memory): View
    {
        $memory->load(['items', 'client', 'generator']);

        return view('pages.cobrancas.monetary.standalone.show', [
            'title' => $memory->title,
            'memory' => $memory,
        ]);
    }

    public function standaloneMonetaryPdf(Request $request, CobrancaStandaloneMonetaryUpdate $memory): View|BinaryFileResponse
    {
        $memory->load(['items', 'client', 'generator']);

        $this->logAction(
            $request,
            'print_cobranca_standalone_monetary_update',
            $memory->id,
            'PDF da memoria de calculo TJES avulsa - ' . $memory->title
        );

        $viewData = [
            'memory' => $memory,
            'autoPrint' => true,
            'pdfMode' => false,
        ];

        if ($pdfResponse = $this->standaloneMonetaryPdfResponse($viewData)) {
            return $pdfResponse;
        }

        return view('pages.cobrancas.monetary.standalone-document', $viewData);
    }

    public function standaloneMonetaryDestroy(Request $request, CobrancaStandaloneMonetaryUpdate $memory): RedirectResponse
    {
        $id = $memory->id;
        $title = $memory->title;
        $memory->delete();

        $this->logAction(
            $request,
            'delete_cobranca_standalone_monetary_update',
            $id,
            'Exclusao da memoria de calculo TJES avulsa - ' . $title
        );

        return redirect()
            ->route('cobrancas.monetary.standalone.index')
            ->with('success', 'Memoria de calculo avulsa excluida com sucesso.');
    }

    private function standaloneMonetaryPdfResponse(array $viewData): ?BinaryFileResponse
    {
        $htmlPath = null;
        $pdfPath = null;

        try {
            $dir = storage_path('app/generated/cobranca-standalone-monetary-updates');
            File::ensureDirectoryExists($dir);

            /** @var CobrancaStandaloneMonetaryUpdate $memory */
            $memory = $viewData['memory'];
            $baseName = Str::slug(($memory->title ?: 'memoria-avulsa') . '-' . $memory->id) . '-' . now()->format('YmdHis') . '-' . Str::random(6);
            $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
            $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

            File::put($htmlPath, view('pages.cobrancas.monetary.standalone-document', array_merge($viewData, [
                'autoPrint' => false,
                'pdfMode' => true,
            ]))->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);
                return null;
            }

            return response()
                ->file($pdfPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . Str::slug($memory->title ?: 'memoria-tjes-avulsa') . '.pdf"',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable) {
            if ($htmlPath) {
                File::delete($htmlPath);
            }
            if ($pdfPath) {
                File::delete($pdfPath);
            }

            return null;
        }
    }

    private function persistStandaloneMonetaryUpdate(array $metadata, array $calculation, int $userId): CobrancaStandaloneMonetaryUpdate
    {
        $settings = $calculation['settings'];
        $totals = $calculation['totals'];

        /** @var CobrancaStandaloneMonetaryUpdate $update */
        $update = CobrancaStandaloneMonetaryUpdate::query()->create([
            'client_entity_id' => $metadata['client_entity_id'],
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'debtor_name_snapshot' => $metadata['debtor_name_snapshot'],
            'debtor_document_snapshot' => $metadata['debtor_document_snapshot'],
            'debtor_email_snapshot' => $metadata['debtor_email_snapshot'],
            'debtor_phone_snapshot' => $metadata['debtor_phone_snapshot'],
            'index_code' => $settings['index_code'],
            'calculation_date' => $settings['calculation_date'],
            'final_date' => $settings['final_date']->toDateString(),
            'interest_type' => $settings['interest_type'],
            'interest_rate_monthly' => $settings['interest_type'] === 'contractual' ? $settings['interest_rate_monthly'] : null,
            'fine_percent' => $settings['fine_percent'],
            'attorney_fee_type' => $settings['attorney_fee_type'],
            'attorney_fee_value' => $settings['attorney_fee_type'] === 'fixed'
                ? $this->decimalFromCents((int) $settings['attorney_fee_value'])
                : (float) $settings['attorney_fee_value'],
            'costs_amount' => $this->decimalFromCents((int) $settings['costs_cents']),
            'costs_date' => $settings['costs_date']?->toDateString(),
            'costs_corrected_amount' => $this->decimalFromCents((int) $totals['costs_corrected_cents']),
            'boleto_fee_total' => $this->decimalFromCents((int) $totals['boleto_fee_cents']),
            'boleto_cancellation_fee_total' => $this->decimalFromCents((int) $totals['boleto_cancellation_fee_cents']),
            'abatement_amount' => $this->decimalFromCents((int) $totals['abatement_cents']),
            'original_total' => $this->decimalFromCents((int) $totals['original_cents']),
            'corrected_total' => $this->decimalFromCents((int) $totals['corrected_cents']),
            'interest_total' => $this->decimalFromCents((int) $totals['interest_cents']),
            'fine_total' => $this->decimalFromCents((int) $totals['fine_cents']),
            'debit_total' => $this->decimalFromCents((int) $totals['debit_total_cents']),
            'attorney_fee_amount' => $this->decimalFromCents((int) $totals['attorney_fee_cents']),
            'grand_total' => $this->decimalFromCents((int) $totals['grand_total_cents']),
            'payload_json' => $this->monetaryPayload($calculation),
            'generated_by' => $userId,
        ]);

        foreach ($calculation['items'] as $index => $item) {
            CobrancaStandaloneMonetaryUpdateItem::query()->create([
                'cobranca_standalone_monetary_update_id' => $update->id,
                'item_order' => $index + 1,
                'reference_label' => Str::limit((string) $item['reference_label'], 100, ''),
                'due_date' => $item['due_date']->toDateString(),
                'original_amount' => $this->decimalFromCents((int) $item['original_cents']),
                'correction_factor' => $item['correction_factor'],
                'corrected_amount' => $this->decimalFromCents((int) $item['corrected_cents']),
                'interest_months' => $item['interest_months'],
                'interest_percent' => $item['interest_percent'],
                'interest_amount' => $this->decimalFromCents((int) $item['interest_cents']),
                'fine_percent' => $item['fine_percent'],
                'fine_amount' => $this->decimalFromCents((int) $item['fine_cents']),
                'total_amount' => $this->decimalFromCents((int) $item['total_cents']),
                'created_at' => now(),
            ]);
        }

        return $update->load(['items', 'client', 'generator']);
    }

    private function standaloneMonetaryPayloadFromRequest(Request $request, bool $requireDebtor): array
    {
        $errors = [];
        $client = null;
        $clientId = (int) $request->integer('client_entity_id');

        if ($clientId > 0) {
            $client = ClientEntity::query()->find($clientId);
            if (!$client || $client->profile_scope !== 'avulso') {
                $errors[] = 'Selecione um cliente avulso valido.';
                $client = null;
            }
        }

        $debtorName = trim((string) $request->input('debtor_name_snapshot', ''));
        if ($debtorName === '') {
            $debtorName = trim((string) ($client?->display_name ?: $client?->legal_name ?: ''));
        }

        $debtorDocument = trim((string) $request->input('debtor_document_snapshot', ''));
        if ($debtorDocument === '') {
            $debtorDocument = trim((string) ($client?->cpf_cnpj ?? ''));
        }

        $debtorEmail = strtolower(trim((string) $request->input('debtor_email_snapshot', '')));
        if ($debtorEmail === '') {
            $debtorEmail = strtolower(trim((string) $this->firstEntityEmail($client)));
        }
        if ($debtorEmail !== '' && !filter_var($debtorEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail valido para o devedor.';
        }

        $debtorPhone = $this->normalizePhoneValue($request->input('debtor_phone_snapshot'), 40);
        if (($debtorPhone ?? '') === '') {
            $debtorPhone = $this->primaryEntityPhone($client);
        }

        if ($requireDebtor && $debtorName === '') {
            $errors[] = 'Informe o nome do devedor ou selecione um cliente avulso.';
        }

        [$quotaRows, $quotaErrors] = $this->standaloneQuotaRowsFromRequest($request);
        $errors = array_merge($errors, $quotaErrors);
        if ($quotaRows === [] && $quotaErrors === []) {
            $errors[] = 'Cadastre ao menos um debito selecionado para calcular.';
        }

        $title = $this->standaloneMonetaryTitle(
            trim((string) $request->input('title', '')),
            $debtorName,
            trim((string) ($client?->display_name ?: $client?->legal_name ?: '')),
            trim((string) $request->input('final_date', ''))
        );

        $metadata = [
            'client_entity_id' => $client?->id,
            'title' => $title,
            'description' => Str::limit(trim((string) $request->input('description', '')), 5000, '') ?: null,
            'debtor_name_snapshot' => Str::limit($debtorName, 180, ''),
            'debtor_document_snapshot' => Str::limit($debtorDocument, 40, '') ?: null,
            'debtor_email_snapshot' => Str::limit($debtorEmail, 190, '') ?: null,
            'debtor_phone_snapshot' => Str::limit((string) ($debtorPhone ?? ''), 40, '') ?: null,
        ];

        $options = [
            'final_date' => $request->input('final_date'),
            'index_code' => $request->input('index_code', 'ATM'),
            'interest_type' => $request->input('interest_type', 'legal'),
            'interest_rate_monthly' => $request->input('interest_rate_monthly'),
            'fine_percent' => $request->input('fine_percent'),
            'attorney_fee_type' => $request->input('attorney_fee_type', 'percent'),
            'attorney_fee_value' => $request->input('attorney_fee_value'),
            'costs_amount' => $request->input('costs_amount'),
            'costs_date' => $request->input('costs_date'),
            'abatement_amount' => $request->input('abatement_amount'),
            'boleto_fee_amount' => $request->input('boleto_fee_amount'),
            'boleto_cancellation_fee_amount' => $request->input('boleto_cancellation_fee_amount'),
            'apply_boleto_fee' => $request->boolean('apply_boleto_fee'),
            'apply_boleto_cancellation_fee' => $request->boolean('apply_boleto_cancellation_fee'),
        ];

        return [$metadata, $errors, $quotaRows, $options];
    }

    private function standaloneClientOptions()
    {
        return ClientEntity::query()
            ->where('profile_scope', 'avulso')
            ->where('is_active', 1)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'legal_name', 'cpf_cnpj', 'emails_json', 'phones_json']);
    }

    private function standaloneMonetaryStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_monetary_index_factors')
                && Schema::hasTable('cobranca_standalone_monetary_updates')
                && Schema::hasTable('cobranca_standalone_monetary_update_items');
        } catch (\Throwable) {
            return false;
        }
    }

    private function standaloneCalculationCase(array $quotaRows): CobrancaCase
    {
        $case = new CobrancaCase();
        $quotas = collect($quotaRows)
            ->values()
            ->map(function (array $row, int $index) {
                return (new CobrancaCaseQuota())->forceFill([
                    'id' => (int) ($row['id'] ?? ($index + 1)),
                    'reference_label' => $row['reference_label'],
                    'due_date' => $row['due_date'],
                    'original_amount' => $row['original_amount'],
                    'updated_amount' => null,
                    'status' => 'taxa_mes',
                ]);
            });

        $case->setRelation('quotas', $quotas);

        return $case;
    }

    private function standaloneQuotaRowsFromRequest(Request $request): array
    {
        $errors = [];

        $rows = collect((array) $request->input('quotas', []))
            ->map(function ($row, $index) use (&$errors) {
                $selected = !empty($row['selected']);
                $dueDate = trim((string) ($row['due_date'] ?? ''));
                $original = $this->moneyToDb($row['original_amount'] ?? null);
                $reference = $this->normalizeReferenceLabel((string) ($row['reference_label'] ?? ''));
                $rowNumber = (int) $index + 1;

                if ($dueDate === '' && $original === null && $reference === '') {
                    return null;
                }

                if (!$selected) {
                    return null;
                }

                if ($dueDate === '') {
                    $errors[] = 'Informe o vencimento do debito ' . $rowNumber . '.';
                    return null;
                }

                try {
                    $dueDate = Carbon::parse($dueDate)->toDateString();
                } catch (\Throwable) {
                    $errors[] = 'Informe um vencimento valido para o debito ' . $rowNumber . '.';
                    return null;
                }

                if ($original === null || $original <= 0) {
                    $errors[] = 'Informe um valor original maior que zero para o debito ' . $rowNumber . '.';
                    return null;
                }

                return [
                    'id' => $rowNumber,
                    'reference_label' => Str::limit($reference, 100, ''),
                    'due_date' => $dueDate,
                    'original_amount' => $original,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [$rows, $errors];
    }

    private function standaloneMonetaryTitle(string $explicitTitle, string $debtorName, string $clientName, string $finalDate): string
    {
        if ($explicitTitle !== '') {
            return Str::limit($explicitTitle, 180, '');
        }

        $subject = $debtorName !== '' ? $debtorName : ($clientName !== '' ? $clientName : 'Calculo avulso');
        $dateLabel = now()->format('d-m-Y');
        if (trim($finalDate) !== '') {
            try {
                $dateLabel = Carbon::parse($finalDate)->format('d-m-Y');
            } catch (\Throwable) {
                $dateLabel = trim($finalDate);
            }
        }

        return Str::limit('Memoria TJES avulsa - ' . $subject . ' - ' . $dateLabel, 180, '');
    }
}
