<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\CobrancaCase;
use App\Models\Contract;
use App\Models\DocumentSignatureRequest;
use App\Models\ElectronicSignatureDocument;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\DocumentSignatureDownloadService;
use App\Services\DocumentSignatureMessageService;
use App\Services\DocumentSignatureService;
use App\Services\SignatureSignerService;
use App\Support\AncoraAuth;
use App\Support\ContractSettings;
use App\Support\Signatures\DocumentSignatureCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ElectronicSignerController extends Controller
{
    private const ORIGIN_CONTRACT = 'contrato';
    private const ORIGIN_COBRANCA = 'cobranca';
    private const ORIGIN_STANDALONE = 'avulso';

    public function __construct(
        private readonly DocumentSignatureService $signatureService,
        private readonly AssinafyService $assinafyService,
        private readonly DocumentSignatureMessageService $messageService,
        private readonly DocumentSignatureDownloadService $downloadService,
        private readonly SignatureSignerService $signerService,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $query = $this->signatureBaseQuery();

        $summary = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending_signatures')->count(),
            'partial' => (clone $query)->where('status', 'partially_signed')->count(),
            'certificated' => (clone $query)->where('status', 'certificated')->count(),
            'rejected' => (clone $query)->whereIn('status', ['rejected_by_signer', 'rejected_by_user'])->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'expired' => (clone $query)->where('status', 'expired')->count(),
            'sent_today' => (clone $query)->whereDate('created_at', today())->count(),
            'completed_month' => (clone $query)
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];

        $latestRequests = $this->decorateRequests(
            (clone $query)
                ->latest('created_at')
                ->limit(8)
                ->get()
        );

        $pendingRequests = $this->decorateRequests(
            (clone $query)
                ->whereIn('status', ['pending_signatures', 'partially_signed', 'certificating', 'metadata_ready', 'uploaded'])
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
        );

        return view('pages.assinador.dashboard', [
            'title' => 'Assinador Eletronico',
            'summary' => $summary,
            'statusLabels' => DocumentSignatureService::requestStatusLabels(),
            'latestRequests' => $latestRequests,
            'pendingRequests' => $pendingRequests,
        ]);
    }

    public function index(Request $request): View
    {
        $filters = [
            'status' => trim((string) $request->input('status', '')),
            'origin' => trim((string) $request->input('origin', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
            'document_name' => trim((string) $request->input('document_name', '')),
            'signer' => trim((string) $request->input('signer', '')),
            'created_by' => (int) $request->integer('created_by'),
        ];

        $query = $this->signatureBaseQuery();
        $this->applyIndexFilters($query, $filters);

        $items = $query
            ->latest('created_at')
            ->paginate(20)
            ->through(fn (DocumentSignatureRequest $signature) => $this->decorateRequest($signature))
            ->withQueryString();

        return view('pages.assinador.index', [
            'title' => 'Documentos para assinatura',
            'filters' => $filters,
            'items' => $items,
            'statusLabels' => DocumentSignatureService::requestStatusLabels(),
            'originOptions' => $this->originOptions(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('pages.assinador.create', [
            'title' => 'Nova assinatura avulsa',
            'signers' => old('signers', [$this->signerService->blankSigner()]),
            'signerMessage' => old('signer_message', ContractSettings::get('assinafy_default_signer_message', 'Segue o documento para assinatura digital.')),
            'defaultSignerOptions' => $this->signerService->defaultSignerOptions(),
            'signatureRoleOptions' => DocumentSignatureCatalog::roleOptions(),
            'messageVariableDefinitions' => $this->messageService->definitions(),
            'providerConfigured' => $this->assinafyService->isConfigured(),
            'missingConfig' => $this->assinafyService->missingConfig(),
            'clients' => ClientEntity::query()->active()->get(['id', 'display_name', 'legal_name']),
            'condominiums' => ClientCondominium::query()
                ->where(function (Builder $query) {
                    $query->where('is_active', 1)->orWhereNull('is_active');
                })
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'document_file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:51200'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:120'],
            'client_entity_id' => ['nullable', 'integer', 'min:1'],
            'client_condominium_id' => ['nullable', 'integer', 'min:1'],
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.name' => ['required', 'string', 'max:180'],
            'signers.*.email' => ['required', 'email', 'max:180'],
            'signers.*.phone' => ['nullable', 'string', 'max:40'],
            'signers.*.document_number' => ['nullable', 'string', 'max:40'],
            'signers.*.role_label' => ['nullable', 'string', 'max:120'],
            'signers.*.order_index' => ['nullable', 'integer', 'min:1'],
            'signer_message' => ['nullable', 'string', 'max:5000'],
        ]);

        $document = null;

        try {
            $normalizedRows = collect($validated['signers'] ?? [])
                ->map(function ($row) {
                    $row = is_array($row) ? $row : [];
                    $row['role_label'] = trim((string) ($row['role_label'] ?? '')) ?: 'Signatario';

                    return $row;
                })
                ->all();

            $request->merge(['signers' => $normalizedRows]);
            $signers = $this->signerService->normalizeSigners($request);

            $file = $request->file('document_file');
            $uuid = (string) Str::uuid();
            $directory = sprintf('signatures/standalone/%s/%s', now()->format('Y'), now()->format('m'));
            $storedName = $uuid . '.pdf';
            $relativePath = trim((string) $file->storeAs($directory, $storedName, 'local'));

            if ($relativePath === '') {
                throw new \RuntimeException('Nao foi possivel salvar o PDF enviado no storage privado.');
            }

            $document = ElectronicSignatureDocument::query()->create([
                'uuid' => $uuid,
                'title' => trim((string) $validated['title']),
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'category' => trim((string) ($validated['category'] ?? '')) ?: null,
                'status' => 'draft',
                'original_name' => trim((string) $file->getClientOriginalName()) ?: $storedName,
                'stored_name' => $storedName,
                'local_pdf_path' => $relativePath,
                'mime_type' => trim((string) $file->getClientMimeType()) ?: 'application/pdf',
                'file_size' => $file->getSize(),
                'sha256_hash' => hash_file('sha256', $file->getRealPath()),
                'client_entity_id' => $validated['client_entity_id'] ?: null,
                'client_condominium_id' => $validated['client_condominium_id'] ?: null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $message = $this->normalizeStandaloneMessage($request, $document);
            $this->signatureService->sendStandaloneDocument($document, $signers, $user, $message);
        } catch (\Throwable $e) {
            if ($document instanceof ElectronicSignatureDocument) {
                $document->forceFill([
                    'status' => 'failed',
                    'updated_by' => $user->id,
                ])->save();
            }

            return back()
                ->withInput()
                ->with('error', 'Nao foi possivel enviar o documento avulso para assinatura: ' . $e->getMessage());
        }

        return redirect()
            ->route('assinador.show', $document)
            ->with('success', 'Documento enviado para assinatura digital com sucesso.');
    }

    public function show(Request $request, ElectronicSignatureDocument $documento): View
    {
        $documento->load([
            'creator',
            'updater',
            'client',
            'condominium',
            'signatureRequests.signers',
            'signatureRequests.events.signer',
            'signatureRequests.creator',
            'signatureRequests.updater',
        ]);

        $requests = $this->decorateRequests($documento->signatureRequests);
        $latestRequest = $requests->first();

        return view('pages.assinador.show', [
            'title' => $documento->title ?: 'Documento avulso',
            'documento' => $documento,
            'requests' => $requests,
            'latestRequest' => $latestRequest,
            'statusLabels' => DocumentSignatureService::requestStatusLabels(),
            'signerStatusLabels' => DocumentSignatureService::signerStatusLabels(),
        ]);
    }

    public function sync(Request $request, DocumentSignatureRequest $signature): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        abort_unless($this->supportsSignature($signature), 404);

        try {
            $this->signatureService->syncRequest($signature, null, null, $user);
        } catch (\Throwable $e) {
            return redirect($this->returnUrl($request, $signature))
                ->with('error', 'Nao foi possivel sincronizar a assinatura digital: ' . $e->getMessage());
        }

        return redirect($this->returnUrl($request, $signature))
            ->with('success', 'Assinatura digital sincronizada com sucesso.');
    }

    public function download(Request $request, DocumentSignatureRequest $signature, string $artifact): BinaryFileResponse|RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        abort_unless($this->supportsSignature($signature), 404);
        abort_unless(in_array($artifact, ['original', 'signed', 'certificate', 'bundle'], true), 404);

        return $this->downloadService->downloadArtifact(
            $signature,
            $artifact,
            $user,
            $this->returnUrl($request, $signature)
        );
    }

    private function signatureBaseQuery(): Builder
    {
        return DocumentSignatureRequest::query()
            ->whereIn('signable_type', array_values($this->originOptions()))
            ->with(['signable', 'signers', 'creator', 'updater']);
    }

    private function applyIndexFilters(Builder $query, array $filters): void
    {
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($this->originOptions()[$filters['origin']])) {
            $query->where('signable_type', $this->originOptions()[$filters['origin']]);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if ($filters['document_name'] !== '') {
            $query->where('document_name', 'like', '%' . $filters['document_name'] . '%');
        }

        if ($filters['signer'] !== '') {
            $query->whereHas('signers', function (Builder $builder) use ($filters) {
                $builder->where('name', 'like', '%' . $filters['signer'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['signer'] . '%');
            });
        }

        if ($filters['created_by'] > 0) {
            $query->where('created_by', $filters['created_by']);
        }
    }

    private function decorateRequests(iterable $requests): Collection
    {
        return collect($requests)->map(fn (DocumentSignatureRequest $signature) => $this->decorateRequest($signature));
    }

    private function decorateRequest(DocumentSignatureRequest $signature): DocumentSignatureRequest
    {
        $signature->setAttribute('source_key', $this->sourceKey($signature));
        $signature->setAttribute('source_label', $this->sourceLabel($signature));
        $signature->setAttribute('source_name', $this->sourceName($signature));
        $signature->setAttribute('source_url', $this->sourceUrl($signature));
        $signature->setAttribute('view_url', $this->viewUrl($signature));
        $signature->setAttribute('status_badge_class', $this->statusBadgeClass((string) $signature->status));

        return $signature;
    }

    private function originOptions(): array
    {
        return [
            self::ORIGIN_CONTRACT => Contract::class,
            self::ORIGIN_COBRANCA => CobrancaCase::class,
            self::ORIGIN_STANDALONE => ElectronicSignatureDocument::class,
        ];
    }

    private function sourceKey(DocumentSignatureRequest $signature): string
    {
        return match ($signature->signable_type) {
            Contract::class => self::ORIGIN_CONTRACT,
            CobrancaCase::class => self::ORIGIN_COBRANCA,
            ElectronicSignatureDocument::class => self::ORIGIN_STANDALONE,
            default => 'desconhecido',
        };
    }

    private function sourceLabel(DocumentSignatureRequest $signature): string
    {
        return match ($this->sourceKey($signature)) {
            self::ORIGIN_CONTRACT => 'Contrato',
            self::ORIGIN_COBRANCA => 'Cobranca / Termo de acordo',
            self::ORIGIN_STANDALONE => 'Avulso',
            default => 'Origem desconhecida',
        };
    }

    private function sourceName(DocumentSignatureRequest $signature): string
    {
        $signable = $signature->signable;

        return match ($signature->signable_type) {
            Contract::class => trim((string) ($signable?->code ?: $signable?->title ?: ('Contrato #' . $signature->signable_id))),
            CobrancaCase::class => trim((string) ($signable?->os_number ?: ('OS #' . $signature->signable_id))),
            ElectronicSignatureDocument::class => trim((string) ($signable?->title ?: $signable?->original_name ?: ('Documento #' . $signature->signable_id))),
            default => trim((string) $signature->document_name),
        };
    }

    private function sourceUrl(DocumentSignatureRequest $signature): ?string
    {
        return match ($signature->signable_type) {
            Contract::class => route('contratos.show', ['contrato' => $signature->signable_id, 'tab' => 'assinaturas']),
            CobrancaCase::class => route('cobrancas.show', $signature->signable_id),
            ElectronicSignatureDocument::class => route('assinador.show', ['documento' => $signature->signable_id]),
            default => null,
        };
    }

    private function viewUrl(DocumentSignatureRequest $signature): ?string
    {
        if ($signature->signable_type === ElectronicSignatureDocument::class) {
            return route('assinador.show', ['documento' => $signature->signable_id]);
        }

        return $this->sourceUrl($signature);
    }

    private function supportsSignature(DocumentSignatureRequest $signature): bool
    {
        return in_array($signature->signable_type, array_values($this->originOptions()), true);
    }

    private function returnUrl(Request $request, DocumentSignatureRequest $signature): string
    {
        $requested = trim((string) $request->input('redirect_to', $request->query('redirect_to', '')));
        if ($requested !== '') {
            if (Str::startsWith($requested, '/')) {
                return $requested;
            }

            $appUrl = rtrim((string) config('app.url'), '/');
            if ($appUrl !== '' && Str::startsWith($requested, $appUrl)) {
                return $requested;
            }
        }

        return $this->sourceUrl($signature) ?: route('assinador.index');
    }

    private function normalizeStandaloneMessage(Request $request, ElectronicSignatureDocument $document): ?string
    {
        $message = trim((string) $request->input(
            'signer_message',
            ContractSettings::get('assinafy_default_signer_message', 'Segue o documento para assinatura digital.')
        ));

        if ($message === '') {
            return null;
        }

        return Str::limit($this->messageService->renderForStandalone($message, $document), 500, '');
    }

    private function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'certificated' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300',
            'rejected_by_signer', 'rejected_by_user', 'failed', 'expired' => 'border-error-200 bg-error-50 text-error-700 dark:border-error-800/70 dark:bg-error-500/10 dark:text-error-300',
            default => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-800/70 dark:bg-warning-500/10 dark:text-warning-200',
        };
    }
}
