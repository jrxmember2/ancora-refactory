<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\Contract;
use App\Models\DocumentSignatureRequest;
use App\Models\ElectronicSignatureDocument;
use App\Services\DocumentSignatureService;
use App\Services\SignatureSignerService;
use App\Support\Hub\HubModulePresenter;
use App\Support\Hub\HubOfficePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignatureController extends HubApiController
{
    public function __construct(
        private readonly DocumentSignatureService $signatureService,
        private readonly SignatureSignerService $signerService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['assinador.index'],
            moduleSlugs: ['assinador'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->tableExists('document_signature_requests')) {
            return response()->json([
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                'filters' => [
                    'statuses' => HubOfficePresenter::signatureStatusOptions(),
                    'origins' => HubOfficePresenter::signatureOriginOptions(),
                ],
            ]);
        }

        $relations = ['signable'];
        if ($this->tableExists('document_signature_signers')) {
            $relations[] = 'signers';
        }

        $query = DocumentSignatureRequest::query()
            ->with($relations)
            ->when($request->filled('q'), function (Builder $builder) use ($request) {
                $term = trim((string) $request->query('q', ''));
                $builder->where(function (Builder $inner) use ($term) {
                    $inner->where('document_name', 'like', '%' . $term . '%')
                        ->orWhereHas('signers', function (Builder $rel) use ($term) {
                            $rel->where('name', 'like', '%' . $term . '%')
                                ->orWhere('email', 'like', '%' . $term . '%');
                        });
                });
            })
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', (string) $request->query('status')));

        if ($origin = trim((string) $request->query('origin', ''))) {
            $query->where('signable_type', match ($origin) {
                'contrato' => Contract::class,
                'avulso' => ElectronicSignatureDocument::class,
                'cobranca' => \App\Models\CobrancaCase::class,
                default => null,
            });
        }

        $items = $query
            ->latest('created_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (DocumentSignatureRequest $signature) => HubOfficePresenter::signatureSummary($signature))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'statuses' => HubOfficePresenter::signatureStatusOptions(),
                'origins' => HubOfficePresenter::signatureOriginOptions(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['assinador.create', 'assinador.store'],
            moduleSlugs: ['assinador'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $this->validateRequest($request, [
            'document_file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:51200'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:120'],
            'client_entity_id' => ['nullable', 'integer', 'min:1'],
            'client_condominium_id' => ['nullable', 'integer', 'min:1'],
            'signers_json' => ['required', 'string'],
            'signer_message' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $document = null;

        try {
            $decodedSigners = json_decode((string) $validated['signers_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decodedSigners) || $decodedSigners === []) {
                return response()->json([
                    'message' => 'Informe ao menos um signatário.',
                ], 422);
            }

            $normalizedRows = collect($decodedSigners)
                ->map(function ($row) {
                    $row = is_array($row) ? $row : [];
                    $row['role_label'] = trim((string) ($row['role_label'] ?? '')) ?: 'Signatário';

                    return $row;
                })
                ->values()
                ->all();

            $payloadRequest = Request::create('/', 'POST', ['signers' => $normalizedRows]);
            $signers = $this->signerService->normalizeSigners($payloadRequest);

            $file = $request->file('document_file');
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Envie um PDF válido para iniciar a assinatura.',
                ], 422);
            }

            $uuid = (string) Str::uuid();
            $directory = sprintf('signatures/standalone/%s/%s', now()->format('Y'), now()->format('m'));
            $storedName = $uuid . '.pdf';
            $relativePath = trim((string) $file->storeAs($directory, $storedName, 'local'));

            if ($relativePath === '') {
                throw new \RuntimeException('Não foi possível salvar o PDF enviado no storage privado.');
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
                'client_entity_id' => !empty($validated['client_entity_id']) ? (int) $validated['client_entity_id'] : null,
                'client_condominium_id' => !empty($validated['client_condominium_id']) ? (int) $validated['client_condominium_id'] : null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $signature = $this->signatureService->sendStandaloneDocument(
                $document,
                $signers,
                $user,
                trim((string) ($validated['signer_message'] ?? '')) ?: null,
            );

            $signature->load($this->detailRelations());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first() ?: 'Os signatários informados são inválidos.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (\JsonException) {
            return response()->json([
                'message' => 'Não foi possível interpretar a lista de signatários enviada.',
            ], 422);
        } catch (\Throwable $throwable) {
            if ($document instanceof ElectronicSignatureDocument) {
                $document->forceFill([
                    'status' => 'failed',
                    'updated_by' => $user->id,
                ])->save();
            }

            return response()->json([
                'message' => 'Não foi possível enviar o documento para assinatura digital agora.',
                'detail' => app()->environment('local') ? $throwable->getMessage() : null,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Documento enviado para assinatura digital com sucesso.',
            'item' => HubOfficePresenter::signatureDetail(
                $signature,
                canSync: $this->userCanAnyRoute($user, ['assinador.signatures.sync', 'contratos.signatures.sync'])
            ),
        ], 201);
    }

    public function show(Request $request, DocumentSignatureRequest $signature): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['assinador.show', 'assinador.index'],
            moduleSlugs: ['assinador'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $signature->load($this->detailRelations());

        return response()->json([
            'item' => HubOfficePresenter::signatureDetail(
                $signature,
                canSync: $this->userCanAnyRoute($user, ['assinador.signatures.sync', 'contratos.signatures.sync'])
            ),
        ]);
    }

    public function sync(Request $request, DocumentSignatureRequest $signature): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['assinador.signatures.sync', 'contratos.signatures.sync'],
            moduleSlugs: ['assinador', 'contratos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $signature = $this->signatureService->syncRequest($signature, null, null, $user);
            $signature->load($this->detailRelations());
        } catch (\Throwable $throwable) {
            return response()->json([
                'message' => 'Não foi possível sincronizar a assinatura agora.',
                'detail' => app()->environment('local') ? $throwable->getMessage() : null,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Sincronização concluída com sucesso.',
            'item' => HubOfficePresenter::signatureDetail(
                $signature,
                canSync: $this->userCanAnyRoute($user, ['assinador.signatures.sync', 'contratos.signatures.sync'])
            ),
        ]);
    }

    private function detailRelations(): array
    {
        $relations = ['creator', 'updater', 'signable'];

        if ($this->tableExists('document_signature_signers')) {
            $relations[] = 'signers';
        }

        if ($this->tableExists('document_signature_events') && $this->tableExists('document_signature_signers')) {
            $relations[] = 'events.signer';
        } elseif ($this->tableExists('document_signature_events')) {
            $relations[] = 'events';
        }

        return $relations;
    }
}
