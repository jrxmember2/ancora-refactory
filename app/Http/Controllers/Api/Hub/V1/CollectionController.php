<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\CobrancaCase;
use App\Models\CobrancaCaseAttachment;
use App\Models\CobrancaCaseInstallment;
use App\Models\CobrancaCaseTimeline;
use App\Support\Hub\HubModulePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = CobrancaCase::query()
            ->with(['condominium', 'unit.owner', 'unit.tenant', 'debtor']);

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('os_number', 'like', "%{$term}%")
                    ->orWhere('debtor_name_snapshot', 'like', "%{$term}%")
                    ->orWhere('debtor_document_snapshot', 'like', "%{$term}%")
                    ->orWhere('judicial_case_number', 'like', "%{$term}%")
                    ->orWhereHas('condominium', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('unit', fn (Builder $query) => $query->where('unit_number', 'like', "%{$term}%")->orWhere('unit_label', 'like', "%{$term}%"))
                    ->orWhereHas('unit.owner', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"))
                    ->orWhereHas('unit.tenant', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"));
            });
        }

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where(function (Builder $inner) use ($status) {
                $inner->where('workflow_stage', $status)
                    ->orWhere('situation', $status)
                    ->orWhere('billing_status', $status);
            });
        }

        if ($workflowStage = trim((string) $request->query('workflow_stage', ''))) {
            $query->where('workflow_stage', $workflowStage);
        }

        if ($situation = trim((string) $request->query('situation', ''))) {
            $query->where('situation', $situation);
        }

        if ($billingStatus = trim((string) $request->query('billing_status', ''))) {
            $query->where('billing_status', $billingStatus);
        }

        $items = $query
            ->latest('updated_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCase $case) => HubModulePresenter::collectionSummary($case))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'workflow_stages' => collect(HubModulePresenter::collectionWorkflowStageLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'situations' => collect(HubModulePresenter::collectionSituationLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'billing_statuses' => collect(HubModulePresenter::collectionBillingStatusLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $collection->load([
            'condominium.syndic',
            'condominium.administradora',
            'block',
            'unit.owner',
            'unit.tenant',
            'debtor',
            'contacts',
            'quotas',
        ]);

        try {
            $collection->load([
                'agreementTerm',
                'signatureRequests.signers',
            ]);
        } catch (\Throwable) {
        }

        return response()->json([
            'item' => HubModulePresenter::collectionDetail($collection),
        ]);
    }

    public function installments(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $items = CobrancaCaseInstallment::query()
            ->where('cobranca_case_id', $collection->id)
            ->orderBy('due_date')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCaseInstallment $installment) => HubModulePresenter::collectionInstallment($installment))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    public function timeline(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $items = CobrancaCaseTimeline::query()
            ->with(['user'])
            ->where('cobranca_case_id', $collection->id)
            ->latest('created_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCaseTimeline $timeline) => HubModulePresenter::collectionTimeline($timeline))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    public function attachments(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $items = CobrancaCaseAttachment::query()
            ->with(['uploader'])
            ->where('cobranca_case_id', $collection->id)
            ->latest('created_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCaseAttachment $attachment) => HubModulePresenter::collectionAttachment($attachment))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }
}
