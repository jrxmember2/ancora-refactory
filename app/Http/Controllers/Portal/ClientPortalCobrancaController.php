<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CobrancaCase;
use App\Support\ClientPortalAccess;
use App\Support\ClientPortalAuth;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalCobrancaController extends Controller
{
    public function index(Request $request, ClientPortalAccess $access): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $user->can_view_cobrancas, 403);

        $query = $access->scopeCobrancas(CobrancaCase::query(), $user)
            ->with(['condominium', 'block', 'unit'])
            ->withCount(['quotas', 'installments']);

        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function ($inner) use ($term) {
                $inner->where('os_number', 'like', "%{$term}%")
                    ->orWhere('debtor_name_snapshot', 'like', "%{$term}%")
                    ->orWhereHas('unit', fn ($unit) => $unit->where('unit_number', 'like', "%{$term}%"));
            });
        }

        if ($stage = trim((string) $request->input('workflow_stage', ''))) {
            $query->where('workflow_stage', $stage);
        }

        $base = $access->scopeCobrancas(CobrancaCase::query(), $user);

        return view('portal.cobrancas.index', [
            'title' => 'Cobranças',
            'items' => $query->latest('updated_at')->paginate(12)->withQueryString(),
            'filters' => $request->all(),
            'stageLabels' => $this->workflowStageLabels(),
            'summary' => [
                'total' => (clone $base)->count(),
                'agreements' => (clone $base)->whereIn('workflow_stage', ['acordo_ativo', 'aguardando_boletos'])->count(),
                'negotiation' => (clone $base)->whereIn('workflow_stage', ['em_negociacao', 'sem_retorno', 'aguardando_termo'])->count(),
                'closed' => (clone $base)->where('situation', 'pago_encerrado')->count(),
            ],
        ]);
    }

    public function show(Request $request, CobrancaCase $cobranca, ClientPortalAccess $access): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $access->canSeeCobranca($user, $cobranca), 404);

        $cobranca->load([
            'condominium',
            'block',
            'unit',
            'quotas',
            'installments',
            'timeline' => fn ($query) => $query->whereIn('event_type', ['status', 'manual', 'agreement'])->latest('created_at')->limit(12),
        ]);

        return view('portal.cobrancas.show', [
            'title' => 'Cobrança ' . $cobranca->os_number,
            'case' => $cobranca,
            'stageLabels' => $this->workflowStageLabels(),
            'situationLabels' => $this->situationLabels(),
        ]);
    }

    private function workflowStageLabels(): array
    {
        return [
            'triagem' => 'Em triagem',
            'apto_notificar' => 'Apto para notificar',
            'notificado' => 'Notificado',
            'em_negociacao' => 'Em negociação',
            'sem_retorno' => 'Sem retorno',
            'aguardando_termo' => 'Aguardando termo',
            'aguardando_assinatura' => 'Aguardando assinatura',
            'acordo_ativo' => 'Acordo ativo',
            'aguardando_boletos' => 'Aguardando boletos',
            'apto_judicializar' => 'Apto para judicializar',
            'judicializado' => 'Judicializado',
            'encerrado' => 'Encerrado',
        ];
    }

    private function situationLabels(): array
    {
        return [
            'processo_aberto' => 'Processo aberto',
            'em_cobranca' => 'Em cobrança',
            'em_pagamento_acordo' => 'Em pagamento de acordo',
            'ajuizado' => 'Ajuizado',
            'pago_encerrado' => 'Pago / encerrado',
            'cancelado' => 'Cancelado',
        ];
    }
}
