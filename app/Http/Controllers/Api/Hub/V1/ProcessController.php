<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\ProcessCase;
use App\Models\ProcessCaseAttachment;
use App\Models\ProcessCaseOption;
use App\Models\ProcessCasePhase;
use App\Models\User;
use App\Support\Hub\HubModulePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProcessController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['processos.index'],
            moduleSlugs: ['processos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = $this->visibleProcessQuery($user)
            ->withCasts([
                'latest_phase_at' => 'datetime',
            ])
            ->with([
                'statusOption',
                'processTypeOption',
                'natureOption',
                'client',
                'clientCondominium',
            ])
            ->select('process_cases.*')
            ->selectSub(
                ProcessCasePhase::query()
                    ->selectRaw('COALESCE(phase_date, created_at)')
                    ->whereColumn('process_case_id', 'process_cases.id')
                    ->latest('phase_date')
                    ->latest('created_at')
                    ->limit(1),
                'latest_phase_at',
            )
            ->selectSub(
                ProcessCasePhase::query()
                    ->select('description')
                    ->whereColumn('process_case_id', 'process_cases.id')
                    ->latest('phase_date')
                    ->latest('created_at')
                    ->limit(1),
                'latest_phase_description',
            );

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('process_number', 'like', "%{$term}%")
                    ->orWhere('client_name_snapshot', 'like', "%{$term}%")
                    ->orWhere('adverse_name', 'like', "%{$term}%")
                    ->orWhere('responsible_lawyer', 'like', "%{$term}%")
                    ->orWhereHas('parties', fn (Builder $query) => $query->where('name_snapshot', 'like', "%{$term}%"))
                    ->orWhereHas('clientCondominium', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
            });
        }

        if ($statusOptionId = (int) $request->integer('status_option_id')) {
            $query->where('status_option_id', $statusOptionId);
        }

        $items = $query
            ->latest('updated_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (ProcessCase $case) => HubModulePresenter::processSummary($case))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'statuses' => ProcessCaseOption::query()
                    ->where('group_key', 'status')
                    ->active()
                    ->get()
                    ->map(fn (ProcessCaseOption $option) => [
                        'id' => (int) $option->id,
                        'label' => (string) $option->name,
                        'color' => $option->color_hex ? (string) $option->color_hex : null,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(Request $request, ProcessCase $process): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['processos.show', 'processos.index'],
            moduleSlugs: ['processos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->canAccessProcess($user, $process)) {
            return $this->notFoundResponse('Processo não encontrado.');
        }

        $process->load([
            'statusOption',
            'actionTypeOption',
            'processTypeOption',
            'client',
            'clientCondominium.syndic',
            'adverse',
            'adverseCondominium',
            'parties.entity',
            'clientPositionOption',
            'adversePositionOption',
            'natureOption',
            'winProbabilityOption',
            'closureTypeOption',
            'creator',
            'phases.creator',
            'phases.attachments.uploader',
            'attachments.uploader',
        ]);

        $latestPhase = $process->phases->first();
        $process->setAttribute('latest_phase_at', $latestPhase?->phase_date ?: $latestPhase?->created_at);
        $process->setAttribute('latest_phase_description', $latestPhase?->description);

        return response()->json([
            'item' => HubModulePresenter::processDetail($process),
        ]);
    }

    public function movements(Request $request, ProcessCase $process): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['processos.show', 'processos.index'],
            moduleSlugs: ['processos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->canAccessProcess($user, $process)) {
            return $this->notFoundResponse('Processo não encontrado.');
        }

        $items = ProcessCasePhase::query()
            ->with(['creator', 'attachments.uploader'])
            ->where('process_case_id', $process->id)
            ->latest('phase_date')
            ->latest('created_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (ProcessCasePhase $phase) => HubModulePresenter::processMovement($phase))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    public function attachments(Request $request, ProcessCase $process): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['processos.show', 'processos.index'],
            moduleSlugs: ['processos'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->canAccessProcess($user, $process)) {
            return $this->notFoundResponse('Processo não encontrado.');
        }

        $items = ProcessCaseAttachment::query()
            ->with(['uploader'])
            ->where('process_case_id', $process->id)
            ->latest('created_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (ProcessCaseAttachment $attachment) => HubModulePresenter::processAttachment($attachment))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    private function visibleProcessQuery(User $user): Builder
    {
        $query = ProcessCase::query();

        if ($user->isSuperadmin()) {
            return $query;
        }

        $needleName = $this->normalize((string) $user->name);
        $needleEmail = $this->normalize((string) $user->email);

        $query->where(function (Builder $inner) use ($user, $needleName, $needleEmail) {
            $inner->where('is_private', false)
                ->orWhere('created_by', $user->id);

            if ($needleName !== '') {
                $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleName . '%']);
            }

            if ($needleEmail !== '') {
                $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleEmail . '%']);
            }
        });

        return $query;
    }

    private function canAccessProcess(User $user, ProcessCase $case): bool
    {
        if (!$case->is_private) {
            return true;
        }

        if ($user->isSuperadmin() || (int) $case->created_by === (int) $user->id) {
            return true;
        }

        $responsible = $this->normalize((string) $case->responsible_lawyer);
        $userName = $this->normalize((string) $user->name);
        $userEmail = $this->normalize((string) $user->email);

        return $responsible !== ''
            && (($userName !== '' && ($responsible === $userName || str_contains($responsible, $userName)))
                || ($userEmail !== '' && ($responsible === $userEmail || str_contains($responsible, $userEmail))));
    }

    private function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->squish()->toString();
    }
}
