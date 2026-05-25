<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\Contract;
use App\Models\DocumentSignatureRequest;
use App\Models\ElectronicSignatureDocument;
use App\Services\DocumentSignatureService;
use App\Support\Hub\HubModulePresenter;
use App\Support\Hub\HubOfficePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignatureController extends HubApiController
{
    public function __construct(
        private readonly DocumentSignatureService $signatureService,
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

        $relations = ['creator', 'updater', 'signable'];
        if ($this->tableExists('document_signature_signers')) {
            $relations[] = 'signers';
        }
        if ($this->tableExists('document_signature_events') && $this->tableExists('document_signature_signers')) {
            $relations[] = 'events.signer';
        } elseif ($this->tableExists('document_signature_events')) {
            $relations[] = 'events';
        }

        $signature->load($relations);

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
            forbiddenMessage: 'Você não possui permissão para sincronizar assinaturas.',
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $signature = $this->signatureService->syncRequest($signature, null, null, $user);
            $relations = ['signable'];
            if ($this->tableExists('document_signature_signers')) {
                $relations[] = 'signers';
            }
            if ($this->tableExists('document_signature_events') && $this->tableExists('document_signature_signers')) {
                $relations[] = 'events.signer';
            } elseif ($this->tableExists('document_signature_events')) {
                $relations[] = 'events';
            }
            $signature->load($relations);
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
}
