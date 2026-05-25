<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractVersion;
use App\Services\ContractPdfService;
use App\Support\Contracts\ContractCatalog;
use App\Support\Hub\HubModulePresenter;
use App\Support\Hub\HubOfficePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContractController extends HubApiController
{
    public function __construct(
        private readonly ContractPdfService $pdfService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['contratos.index'],
            moduleSlugs: ['contratos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->tableExists('contracts')) {
            return response()->json([
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                'filters' => ['statuses' => HubOfficePresenter::contractStatusOptions()],
            ]);
        }

        $status = trim((string) $request->query('status', ''));

        $relations = ['category', 'client', 'condominium', 'syndic', 'responsible'];
        if ($this->tableExists('document_signature_requests')) {
            $relations[] = 'signatureRequests';
        }

        $query = Contract::query()
            ->with($relations)
            ->when($request->filled('q'), function (Builder $builder) use ($request) {
                $term = trim((string) $request->query('q', ''));
                $builder->where(function (Builder $inner) use ($term) {
                    $inner->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhere('type', 'like', '%' . $term . '%')
                        ->orWhereHas('client', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'))
                        ->orWhereHas('syndic', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'));
                });
            });

        if ($status !== '') {
            if ($status === 'vencido') {
                $query->where(function (Builder $builder) {
                    $builder->where('status', 'vencido')
                        ->orWhere(function (Builder $inner) {
                            $inner->whereNotIn('status', ['rescindido', 'cancelado', 'arquivado'])
                                ->where(function (Builder $term) {
                                    $term->where('indefinite_term', false)->orWhereNull('indefinite_term');
                                })
                                ->whereDate('end_date', '<', now()->toDateString());
                        });
                });
            } else {
                $query->where('status', $status);
            }
        }

        $items = $query
            ->orderByDesc('updated_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (Contract $contract) => HubOfficePresenter::contractSummary($contract))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'statuses' => HubOfficePresenter::contractStatusOptions(),
            ],
        ]);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['contratos.show', 'contratos.index'],
            moduleSlugs: ['contratos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $relations = [
            'category',
            'client',
            'condominium.syndic',
            'syndic',
            'proposal',
            'process',
            'responsible',
            'financialAccount',
        ];

        if ($this->tableExists('contract_versions')) {
            $relations[] = 'versions';
        }

        if ($this->tableExists('contract_attachments')) {
            $relations[] = 'attachments';
        }

        if ($this->tableExists('document_signature_requests') && $this->tableExists('document_signature_signers')) {
            $relations[] = 'signatureRequests.signers';
        }

        $contract->load($relations);

        return response()->json([
            'item' => HubOfficePresenter::contractDetail($contract),
        ]);
    }

    public function documents(Request $request, Contract $contract): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['contratos.show', 'contratos.download-pdf', 'contratos.attachments.download', 'contratos.versions.download'],
            moduleSlugs: ['contratos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $relations = [];
        if ($this->tableExists('contract_versions')) {
            $relations[] = 'versions';
        }
        if ($this->tableExists('contract_attachments')) {
            $relations[] = 'attachments';
        }

        if ($relations !== []) {
            $contract->load($relations);
        }

        $items = collect();

        $mainDocument = HubOfficePresenter::contractMainDocument($contract);
        if ($mainDocument) {
            $items->push($mainDocument);
        }

        if ($contract->relationLoaded('versions')) {
            $items = $items->concat(
                $contract->versions
                    ->sortByDesc('version_number')
                    ->map(fn (ContractVersion $version) => HubOfficePresenter::contractVersionDocument($version))
            );
        }

        if ($contract->relationLoaded('attachments')) {
            $items = $items->concat(
                $contract->attachments
                    ->sortByDesc('created_at')
                    ->map(fn (ContractAttachment $attachment) => HubOfficePresenter::contractAttachmentDocument($attachment))
            );
        }

        return response()->json([
            'items' => $items->values()->all(),
        ]);
    }

    public function download(Request $request, Contract $contract): JsonResponse|BinaryFileResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['contratos.download-pdf', 'contratos.attachments.download', 'contratos.versions.download', 'contratos.show'],
            moduleSlugs: ['contratos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $kind = trim((string) $request->query('kind', 'main'));
        $referenceId = (int) $request->integer('reference_id');

        return match ($kind) {
            'version' => $this->downloadVersion($contract, $referenceId),
            'attachment' => $this->downloadAttachment($contract, $referenceId),
            default => $this->downloadMainPdf($contract),
        };
    }

    private function downloadMainPdf(Contract $contract): JsonResponse|BinaryFileResponse
    {
        $path = $this->pdfService->absolutePath($contract->final_pdf_path);
        if (!$path) {
            return response()->json([
                'message' => 'Este contrato ainda não possui PDF final gerado.',
            ], 404);
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }

    private function downloadVersion(Contract $contract, int $referenceId): JsonResponse|BinaryFileResponse
    {
        if ($referenceId <= 0 || !$this->tableExists('contract_versions')) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        $version = ContractVersion::query()
            ->where('contract_id', $contract->id)
            ->find($referenceId);

        if (!$version) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        $path = $this->pdfService->absolutePath($version->pdf_path);
        if (!$path) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }

    private function downloadAttachment(Contract $contract, int $referenceId): JsonResponse|BinaryFileResponse
    {
        if ($referenceId <= 0 || !$this->tableExists('contract_attachments')) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        $attachment = ContractAttachment::query()
            ->where('contract_id', $contract->id)
            ->find($referenceId);

        if (!$attachment) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        $path = storage_path('app/public/' . ltrim((string) $attachment->relative_path, '/'));
        if (!is_file($path)) {
            return $this->notFoundResponse('Documento não encontrado.');
        }

        return response()->download(
            $path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }
}
