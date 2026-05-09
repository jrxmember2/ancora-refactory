<?php

namespace App\Services;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\Contract;
use App\Models\CobrancaCase;
use App\Models\Demand;
use App\Models\DocumentSignatureRequest;
use App\Models\ElectronicSignatureDocument;
use App\Models\FinancialPayable;
use App\Models\FinancialReceivable;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use App\Models\Proposal;
use App\Models\User;
use App\Services\DocumentSignatureService;
use App\Support\AncoraAuth;
use App\Support\Contracts\ContractCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HubExecutiveDashboardService
{
    private array $tableExistsCache = [];

    public function build(Request $request): array
    {
        $period = $this->resolvePeriod((string) $request->input('period', 'month'));
        $range = $this->periodRange($period);

        $cards = array_values(array_filter([
            $this->clientsCard($request, $range),
            $this->proposalsCard($request, $range),
            $this->collectionsCard($request, $range),
            $this->demandsCard($request, $range),
            $this->processesCard($request, $range),
            $this->contractsCard($request, $range),
            $this->signaturesCard($request, $range),
            $this->financialReceiptsCard($request, $range),
            $this->financialExpensesCard($request, $range),
            $this->criticalAlertsCard($request, $range),
        ]));

        return [
            'period' => $period,
            'period_options' => $this->periodOptions(),
            'range' => $range,
            'cards' => $cards,
            'card_count' => count($cards),
            'generated_at' => now(),
        ];
    }

    private function clientsCard(Request $request, array $range): ?array
    {
        if (
            !$this->hasModule($request, 'clientes')
            || !$this->tableExists('client_entities')
            || !$this->tableExists('client_condominiums')
            || !$this->tableExists('client_units')
        ) {
            return null;
        }

        $entities = ClientEntity::query()
            ->withCount(['ownedUnits', 'rentedUnits'])
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->orderByDesc('created_at')
            ->get();

        $condominiums = ClientCondominium::query()
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->orderByDesc('created_at')
            ->get();

        $units = ClientUnit::query()
            ->with(['condominium', 'owner'])
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->orderByDesc('created_at')
            ->get();

        $avulsos = $entities->where('profile_scope', 'avulso');
        $contatos = $entities->where('profile_scope', 'contato');
        $condominos = $contatos->filter(fn (ClientEntity $entity) => $this->isCondominoEntity($entity));
        $partners = $contatos->reject(fn (ClientEntity $entity) => $this->isCondominoEntity($entity));

        $detailItems = $this->mergeAndLimit([
            $entities->map(function (ClientEntity $entity) use ($request) {
                return $this->detailItem(
                    title: $entity->display_name ?: ('Cliente #' . $entity->id),
                    subtitle: $entity->legal_name ?: 'Cadastro de cliente',
                    meta: $entity->role_tag ?: ($entity->profile_scope === 'avulso' ? 'Cliente avulso' : 'Contato'),
                    value: $this->dateTime($entity->created_at),
                    badge: $entity->profile_scope === 'avulso' ? 'Cliente' : ($this->isCondominoEntity($entity) ? 'Condomino' : 'Contato'),
                    url: $this->entityUrl($request, $entity),
                    sortTimestamp: $entity->created_at?->timestamp
                );
            }),
            $condominiums->map(function (ClientCondominium $condominium) use ($request) {
                return $this->detailItem(
                    title: $condominium->name ?: ('Condominio #' . $condominium->id),
                    subtitle: 'Cadastro condominial',
                    meta: $condominium->cnpj ?: 'Sem CNPJ',
                    value: $this->dateTime($condominium->created_at),
                    badge: 'Condominio',
                    url: $this->condominiumUrl($request, $condominium),
                    sortTimestamp: $condominium->created_at?->timestamp
                );
            }),
            $units->map(function (ClientUnit $unit) use ($request) {
                $subtitle = collect([
                    $unit->condominium?->name,
                    $unit->owner?->display_name ? 'Prop.: ' . $unit->owner->display_name : null,
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: 'Unidade ' . ($unit->unit_number ?: ('#' . $unit->id)),
                    subtitle: $subtitle !== '' ? $subtitle : 'Unidade cadastrada',
                    meta: $unit->block?->name ? 'Bloco ' . $unit->block->name : '',
                    value: $this->dateTime($unit->created_at),
                    badge: 'Unidade',
                    url: $this->unitUrl($request, $unit),
                    sortTimestamp: $unit->created_at?->timestamp
                );
            }),
        ], 8);

        $total = $entities->count() + $condominiums->count() + $units->count();

        return $this->card(
            key: 'clients',
            label: 'Cadastros',
            value: $this->number($total),
            hint: sprintf(
                '%s clientes/contatos · %s condominios · %s unidades',
                $this->number($entities->count()),
                $this->number($condominiums->count()),
                $this->number($units->count())
            ),
            icon: 'fa-solid fa-users',
            detailTitle: 'Cadastros no periodo',
            detailSubtitle: 'Clientes, condominios e unidades criados em ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Clientes avulsos', $this->number($avulsos->count())),
                $this->detailStat('Parceiros / fornecedores', $this->number($partners->count())),
                $this->detailStat('Condominos', $this->number($condominos->count())),
                $this->detailStat('Condominios', $this->number($condominiums->count())),
                $this->detailStat('Unidades', $this->number($units->count())),
            ],
            detailItems: $detailItems,
            emptyTitle: 'Sem cadastros no periodo',
            emptySubtitle: 'Novos clientes, condominios e unidades aparecerao aqui.',
            actionUrl: $this->routeIfAllowed($request, 'clientes.index'),
            actionLabel: 'Abrir clientes'
        );
    }

    private function proposalsCard(Request $request, array $range): ?array
    {
        if (!$this->hasModule($request, 'propostas') || !$this->tableExists('propostas')) {
            return null;
        }

        $baseQuery = Proposal::query()
            ->with(['statusRetorno', 'servico'])
            ->whereBetween('proposal_date', [$range['start']->toDateString(), $range['end']->toDateString()]);

        $items = (clone $baseQuery)
            ->orderByDesc('proposal_date')
            ->limit(8)
            ->get();

        $total = (clone $baseQuery)->count();
        $proposalValue = (float) (clone $baseQuery)->sum('proposal_total');
        $closedCount = (clone $baseQuery)
            ->whereHas('statusRetorno', fn (Builder $query) => $query->where('system_key', 'fechada'))
            ->count();
        $closedValue = (float) (clone $baseQuery)
            ->whereHas('statusRetorno', fn (Builder $query) => $query->where('system_key', 'fechada'))
            ->sum('closed_total');
        $followups = Proposal::query()
            ->whereNotNull('followup_date')
            ->whereDate('followup_date', '<=', $range['end']->toDateString())
            ->whereHas('statusRetorno', fn (Builder $query) => $query->where('stop_followup_alert', false))
            ->count();

        return $this->card(
            key: 'proposals',
            label: 'Propostas',
            value: $this->number($total),
            hint: $this->money($proposalValue) . ' em valor proposto · ' . $this->number($closedCount) . ' fechadas',
            icon: 'fa-solid fa-file-signature',
            detailTitle: 'Propostas no periodo',
            detailSubtitle: 'Propostas registradas no recorte de ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Valor proposto', $this->money($proposalValue)),
                $this->detailStat('Fechadas', $this->number($closedCount)),
                $this->detailStat('Valor fechado', $this->money($closedValue)),
                $this->detailStat('Follow-ups vencidos', $this->number($followups)),
            ],
            detailItems: $items->map(function (Proposal $proposal) use ($request) {
                $meta = collect([
                    $proposal->servico?->name,
                    $proposal->statusRetorno?->name,
                    $proposal->proposal_date?->format('d/m/Y'),
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: $proposal->proposal_code ?: ('Proposta #' . $proposal->id),
                    subtitle: $proposal->client_name ?: 'Cliente nao informado',
                    meta: $meta,
                    value: $this->money((float) $proposal->proposal_total),
                    badge: $proposal->statusRetorno?->name,
                    url: $this->routeIfAllowed($request, 'propostas.show', ['proposta' => $proposal], 'propostas.index'),
                    sortTimestamp: $proposal->proposal_date?->timestamp ?? $proposal->created_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem propostas no periodo',
            emptySubtitle: 'As propostas cadastradas no recorte selecionado aparecerao aqui.',
            actionUrl: $this->routeIfAllowed($request, 'propostas.dashboard', [], 'propostas.index'),
            actionLabel: 'Abrir propostas'
        );
    }

    private function collectionsCard(Request $request, array $range): ?array
    {
        if (!$this->hasModule($request, 'cobrancas') || !$this->tableExists('cobranca_cases')) {
            return null;
        }

        $baseQuery = CobrancaCase::query()
            ->with(['condominium', 'unit'])
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        $items = (clone $baseQuery)->orderByDesc('updated_at')->limit(8)->get();
        $total = (clone $baseQuery)->count();
        $agreementTotal = (float) (clone $baseQuery)->sum('agreement_total');
        $feesTotal = (float) (clone $baseQuery)->sum('fees_amount');
        $negotiation = (clone $baseQuery)->whereIn('workflow_stage', ['em_negociacao', 'sem_retorno', 'aguardando_termo'])->count();
        $judicialize = (clone $baseQuery)->where('workflow_stage', 'apto_judicializar')->count();

        return $this->card(
            key: 'collections',
            label: 'Cobrancas',
            value: $this->number($total),
            hint: $this->money($agreementTotal) . ' em acordos · ' . $this->number($judicialize) . ' aptas a judicializar',
            icon: 'fa-solid fa-money-bill-wave',
            detailTitle: 'OS de cobranca no periodo',
            detailSubtitle: 'Operacoes de cobranca iniciadas em ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Valor de acordos', $this->money($agreementTotal)),
                $this->detailStat('Honorarios', $this->money($feesTotal)),
                $this->detailStat('Em negociacao', $this->number($negotiation)),
                $this->detailStat('Aptas a judicializar', $this->number($judicialize)),
            ],
            detailItems: $items->map(function (CobrancaCase $case) use ($request) {
                $meta = collect([
                    $case->condominium?->name,
                    $case->unit?->unit_number ? 'Unidade ' . $case->unit->unit_number : null,
                    $case->workflow_stage ? Str::headline((string) $case->workflow_stage) : null,
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: $case->os_number ?: ('OS #' . $case->id),
                    subtitle: $case->debtor_name_snapshot ?: 'Devedor nao informado',
                    meta: $meta,
                    value: $case->agreement_total !== null ? $this->money((float) $case->agreement_total) : 'Sem acordo',
                    badge: $case->situation ? Str::headline((string) $case->situation) : null,
                    url: $this->routeIfAllowed($request, 'cobrancas.show', ['cobranca' => $case], 'cobrancas.index'),
                    sortTimestamp: $case->updated_at?->timestamp ?? $case->created_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem OS no periodo',
            emptySubtitle: 'As cobrancas abertas no recorte selecionado aparecerao aqui.',
            actionUrl: $this->routeIfAllowed($request, 'cobrancas.dashboard', [], 'cobrancas.index'),
            actionLabel: 'Abrir cobrancas'
        );
    }

    private function demandsCard(Request $request, array $range): ?array
    {
        if (!$this->hasModule($request, 'demandas') || !$this->tableExists('demands')) {
            return null;
        }

        $periodQuery = Demand::query()
            ->with(['tag', 'category', 'condominium', 'entity', 'assignee'])
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        $items = (clone $periodQuery)->orderByDesc('updated_at')->limit(8)->get();
        $total = (clone $periodQuery)->count();
        $open = (clone $periodQuery)->whereNotIn('status', ['concluida', 'cancelada'])->count();
        $waitingClient = (clone $periodQuery)->where('status', 'aguardando_cliente')->count();
        $closed = Demand::query()->whereBetween('closed_at', [$range['start'], $range['end']])->count();
        $overdue = Demand::query()
            ->whereNotIn('status', ['concluida', 'cancelada'])
            ->whereNotNull('sla_due_at')
            ->whereBetween('sla_due_at', [$range['start'], $range['end']])
            ->where('sla_due_at', '<', now())
            ->count();

        return $this->card(
            key: 'demands',
            label: 'Demandas',
            value: $this->number($total),
            hint: $this->number($open) . ' abertas · ' . $this->number($overdue) . ' SLA vencido',
            icon: 'fa-solid fa-inbox',
            detailTitle: 'Demandas no periodo',
            detailSubtitle: 'Demandas abertas e movimentadas no recorte de ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Abertas', $this->number($open)),
                $this->detailStat('Concluidas no periodo', $this->number($closed)),
                $this->detailStat('Aguardando cliente', $this->number($waitingClient)),
                $this->detailStat('SLA vencido', $this->number($overdue)),
            ],
            detailItems: $items->map(function (Demand $demand) use ($request) {
                $meta = collect([
                    $demand->clientName(),
                    $demand->category?->name,
                    $demand->publicStatusLabel(),
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: $demand->protocol ?: ('Demanda #' . $demand->id),
                    subtitle: $demand->subject ?: 'Assunto nao informado',
                    meta: $meta,
                    value: $this->dateTime($demand->updated_at),
                    badge: Demand::priorityLabels()[$demand->priority] ?? Str::headline((string) $demand->priority),
                    url: $this->routeIfAllowed($request, 'demandas.show', ['demanda' => $demand], 'demandas.index'),
                    sortTimestamp: $demand->updated_at?->timestamp ?? $demand->created_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem demandas no periodo',
            emptySubtitle: 'As demandas registradas neste recorte aparecerao aqui.',
            actionUrl: $this->routeIfAllowed($request, 'demandas.dashboard', [], 'demandas.index'),
            actionLabel: 'Abrir demandas'
        );
    }

    private function processesCard(Request $request, array $range): ?array
    {
        if (!$this->hasModule($request, 'processos') || !$this->tableExists('process_cases') || !$this->tableExists('process_case_phases')) {
            return null;
        }

        $periodQuery = $this->visibleProcessQuery($request)
            ->with(['statusOption', 'processTypeOption'])
            ->where(function (Builder $query) use ($range) {
                $query->whereBetween('opened_at', [$range['start']->toDateString(), $range['end']->toDateString()])
                    ->orWhere(function (Builder $fallback) use ($range) {
                        $fallback->whereNull('opened_at')->whereBetween('created_at', [$range['start'], $range['end']]);
                    });
            });

        $items = (clone $periodQuery)->orderByDesc('updated_at')->limit(8)->get();
        $total = (clone $periodQuery)->count();
        $active = (clone $periodQuery)->whereNull('closed_at')->count();
        $private = (clone $periodQuery)->where('is_private', true)->count();
        $datajudSynced = (clone $periodQuery)->whereNotNull('last_datajud_sync_at')->count();
        $movements = $this->visibleProcessPhaseQuery($request)
            ->where(function (Builder $query) use ($range) {
                $query->whereBetween('phase_date', [$range['start']->toDateString(), $range['end']->toDateString()])
                    ->orWhere(function (Builder $fallback) use ($range) {
                        $fallback->whereNull('phase_date')->whereBetween('created_at', [$range['start'], $range['end']]);
                    });
            })
            ->count();

        return $this->card(
            key: 'processes',
            label: 'Processos',
            value: $this->number($total),
            hint: $this->number($movements) . ' andamentos · ' . $this->number($active) . ' ativos',
            icon: 'fa-solid fa-scale-balanced',
            detailTitle: 'Processos no periodo',
            detailSubtitle: 'Recorte operacional de processos e andamentos em ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Ativos', $this->number($active)),
                $this->detailStat('Andamentos no periodo', $this->number($movements)),
                $this->detailStat('Privados', $this->number($private)),
                $this->detailStat('DataJud sincronizado', $this->number($datajudSynced)),
            ],
            detailItems: $items->map(function (ProcessCase $case) use ($request) {
                $meta = collect([
                    $case->processTypeOption?->name,
                    $case->statusOption?->name,
                    $case->opened_at?->format('d/m/Y'),
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: $case->process_number ?: ('Processo #' . $case->id),
                    subtitle: $case->client_name_snapshot ?: 'Cliente nao informado',
                    meta: $meta,
                    value: $case->responsible_lawyer ?: 'Sem responsavel',
                    badge: $case->is_private ? 'Privado' : 'Publico',
                    url: $this->routeIfAllowed($request, 'processos.show', ['processo' => $case], 'processos.index'),
                    sortTimestamp: $case->updated_at?->timestamp ?? $case->created_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem processos no periodo',
            emptySubtitle: 'Os processos deste recorte aparecerao aqui respeitando as permissoes do usuario.',
            actionUrl: $this->routeIfAllowed($request, 'processos.dashboard', [], 'processos.index'),
            actionLabel: 'Abrir processos'
        );
    }

    private function contractsCard(Request $request, array $range): ?array
    {
        if (!$this->hasModule($request, 'contratos') || !$this->tableExists('contracts')) {
            return null;
        }

        $periodQuery = Contract::query()
            ->with(['client', 'condominium', 'responsible'])
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        $items = (clone $periodQuery)->orderByDesc('created_at')->limit(8)->get();
        $total = (clone $periodQuery)->count();
        $active = (clone $periodQuery)->where('status', 'ativo')->count();
        $awaiting = (clone $periodQuery)->where('status', 'aguardando_assinatura')->count();
        $autoFinance = (clone $periodQuery)->where('generate_financial_entries', true)->count();
        $valueTotal = (clone $periodQuery)->get()->sum(function (Contract $contract) {
            return (float) ($contract->contract_value ?? $contract->total_value ?? $contract->monthly_value ?? 0);
        });
        $upcoming = Contract::query()
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->copy()->addDays(30)->toDateString())
            ->count();

        return $this->card(
            key: 'contracts',
            label: 'Contratos',
            value: $this->number($total),
            hint: $this->number($awaiting) . ' aguardando assinatura · ' . $this->number($autoFinance) . ' com financeiro automatico',
            icon: 'fa-solid fa-file-contract',
            detailTitle: 'Contratos no periodo',
            detailSubtitle: 'Contratos criados em ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Ativos', $this->number($active)),
                $this->detailStat('Aguardando assinatura', $this->number($awaiting)),
                $this->detailStat('Financeiro automatico', $this->number($autoFinance)),
                $this->detailStat('Vencendo em 30 dias', $this->number($upcoming)),
                $this->detailStat('Valor somado', $this->money((float) $valueTotal)),
            ],
            detailItems: $items->map(function (Contract $contract) use ($request) {
                $meta = collect([
                    $contract->type ?: null,
                    ContractCatalog::statuses()[$contract->status] ?? Str::headline((string) $contract->status),
                    $contract->start_date?->format('d/m/Y'),
                ])->filter()->implode(' · ');

                $principal = $contract->client?->display_name ?: ($contract->condominium?->name ?: 'Sem vinculo principal');

                return $this->detailItem(
                    title: $contract->code ?: ('Contrato #' . $contract->id),
                    subtitle: $contract->title ?: $principal,
                    meta: $meta,
                    value: $principal,
                    badge: $contract->generate_financial_entries ? 'Financeiro 360' : 'Manual',
                    url: $this->routeIfAllowed($request, 'contratos.show', ['contrato' => $contract], 'contratos.index'),
                    sortTimestamp: $contract->created_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem contratos no periodo',
            emptySubtitle: 'Os contratos criados neste recorte aparecerao aqui.',
            actionUrl: $this->routeIfAllowed($request, 'contratos.dashboard', [], 'contratos.index'),
            actionLabel: 'Abrir contratos'
        );
    }

    private function signaturesCard(Request $request, array $range): ?array
    {
        if (
            !$this->hasModule($request, 'assinador')
            || !$this->tableExists('document_signature_requests')
        ) {
            return null;
        }

        $periodQuery = DocumentSignatureRequest::query()
            ->with(['signers', 'signable'])
            ->whereBetween('created_at', [$range['start'], $range['end']]);

        $items = (clone $periodQuery)->orderByDesc('created_at')->limit(8)->get();
        $total = (clone $periodQuery)->count();
        $pending = (clone $periodQuery)->whereIn('status', ['pending_signatures', 'partially_signed', 'metadata_ready', 'uploaded', 'certificating'])->count();
        $completed = DocumentSignatureRequest::query()
            ->whereBetween('completed_at', [$range['start'], $range['end']])
            ->count();
        $rejected = (clone $periodQuery)->whereIn('status', ['rejected_by_signer', 'rejected_by_user', 'failed'])->count();
        $standalone = (clone $periodQuery)->where('signable_type', ElectronicSignatureDocument::class)->count();

        return $this->card(
            key: 'signatures',
            label: 'Assinaturas',
            value: $this->number($total),
            hint: $this->number($pending) . ' pendentes · ' . $this->number($completed) . ' concluidas',
            icon: 'fa-solid fa-signature',
            detailTitle: 'Assinaturas no periodo',
            detailSubtitle: 'Envios e conclusoes monitorados em ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Pendentes', $this->number($pending)),
                $this->detailStat('Concluidas no periodo', $this->number($completed)),
                $this->detailStat('Avulsas', $this->number($standalone)),
                $this->detailStat('Recusadas / falharam', $this->number($rejected)),
            ],
            detailItems: $items->map(function (DocumentSignatureRequest $signature) use ($request) {
                $signers = $signature->signers->pluck('name')->filter()->implode(', ');
                $meta = collect([
                    $this->signatureSourceLabel($signature),
                    DocumentSignatureService::requestStatusLabels()[$signature->status] ?? Str::headline((string) $signature->status),
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: $signature->document_name ?: ('Assinatura #' . $signature->id),
                    subtitle: $signers !== '' ? $signers : 'Sem signatarios',
                    meta: $meta,
                    value: $this->dateTime($signature->created_at),
                    badge: $this->signatureSourceBadge($signature),
                    url: $this->signatureUrl($request, $signature),
                    sortTimestamp: $signature->created_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem assinaturas no periodo',
            emptySubtitle: 'Os envios de assinatura aparecerao aqui conforme o periodo selecionado.',
            actionUrl: $this->routeIfAllowed($request, 'assinador.dashboard', [], 'assinador.index'),
            actionLabel: 'Abrir assinador'
        );
    }

    private function financialReceiptsCard(Request $request, array $range): ?array
    {
        if (!$this->hasModule($request, 'financeiro') || !$this->tableExists('financial_receivables')) {
            return null;
        }

        $receivedQuery = FinancialReceivable::query()
            ->with(['client', 'condominium', 'contract'])
            ->whereNotNull('received_at')
            ->whereBetween('received_at', [$range['start'], $range['end']]);

        $items = (clone $receivedQuery)->orderByDesc('received_at')->limit(8)->get();
        $amount = (float) (clone $receivedQuery)->sum('received_amount');
        $count = (clone $receivedQuery)->count();
        $ticket = $count > 0 ? $amount / $count : 0.0;
        $clients = (clone $receivedQuery)
            ->get(['client_id', 'condominium_id'])
            ->map(fn (FinancialReceivable $item) => $item->client_id ?: ('c-' . $item->condominium_id))
            ->filter()
            ->unique()
            ->count();
        $openInPeriod = FinancialReceivable::query()
            ->whereBetween('due_date', [$range['start']->toDateString(), $range['end']->toDateString()])
            ->whereNotIn('status', ['recebido', 'cancelado'])
            ->sum(DB::raw('final_amount - received_amount'));

        return $this->card(
            key: 'financial_receipts',
            label: 'Recebimentos',
            value: $this->money($amount),
            hint: $this->number($count) . ' titulos liquidados · ticket medio ' . $this->money($ticket),
            icon: 'fa-solid fa-sack-dollar',
            detailTitle: 'Recebimentos no periodo',
            detailSubtitle: 'Contas a receber liquidadas entre ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Titulos recebidos', $this->number($count)),
                $this->detailStat('Ticket medio', $this->money($ticket)),
                $this->detailStat('Clientes/condominios atendidos', $this->number($clients)),
                $this->detailStat('Em aberto no periodo', $this->money((float) $openInPeriod)),
            ],
            detailItems: $items->map(function (FinancialReceivable $item) use ($request) {
                $principal = $item->client?->display_name ?: ($item->condominium?->name ?: 'Sem vinculo principal');
                $meta = collect([
                    $principal,
                    $item->received_at?->format('d/m/Y H:i'),
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: $item->code ?: ('Recebivel #' . $item->id),
                    subtitle: $item->title ?: 'Conta a receber',
                    meta: $meta,
                    value: $this->money((float) $item->received_amount),
                    badge: $item->status ? Str::headline((string) $item->status) : null,
                    url: $this->routeIfAllowed($request, 'financeiro.receivables.show', ['receivable' => $item], 'financeiro.receivables.index'),
                    sortTimestamp: $item->received_at?->timestamp ?? $item->updated_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem recebimentos no periodo',
            emptySubtitle: 'As baixas financeiras de contas a receber aparecerao aqui.',
            actionUrl: $this->routeIfAllowed($request, 'financeiro.dashboard', [], 'financeiro.receivables.index'),
            actionLabel: 'Abrir Financeiro 360'
        );
    }

    private function financialExpensesCard(Request $request, array $range): ?array
    {
        if (!$this->hasModule($request, 'financeiro') || !$this->tableExists('financial_payables')) {
            return null;
        }

        $paidQuery = FinancialPayable::query()
            ->with(['supplier'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$range['start'], $range['end']]);

        $items = (clone $paidQuery)->orderByDesc('paid_at')->limit(8)->get();
        $amount = (float) (clone $paidQuery)->sum('paid_amount');
        $count = (clone $paidQuery)->count();
        $suppliers = (clone $paidQuery)
            ->get(['supplier_entity_id'])
            ->pluck('supplier_entity_id')
            ->filter()
            ->unique()
            ->count();
        $openInPeriod = FinancialPayable::query()
            ->whereBetween('due_date', [$range['start']->toDateString(), $range['end']->toDateString()])
            ->whereNotIn('status', ['pago', 'cancelado'])
            ->sum(DB::raw('amount - paid_amount'));
        $overdue = FinancialPayable::query()
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['pago', 'cancelado'])
            ->count();

        return $this->card(
            key: 'financial_expenses',
            label: 'Despesas',
            value: $this->money($amount),
            hint: $this->number($count) . ' titulos pagos · ' . $this->number($overdue) . ' ainda vencidos',
            icon: 'fa-solid fa-file-invoice-dollar',
            detailTitle: 'Despesas no periodo',
            detailSubtitle: 'Contas a pagar liquidadas entre ' . $range['label'] . '.',
            detailStats: [
                $this->detailStat('Titulos pagos', $this->number($count)),
                $this->detailStat('Fornecedores envolvidos', $this->number($suppliers)),
                $this->detailStat('Em aberto no periodo', $this->money((float) $openInPeriod)),
                $this->detailStat('Titulos vencidos', $this->number($overdue)),
            ],
            detailItems: $items->map(function (FinancialPayable $item) use ($request) {
                $meta = collect([
                    $item->supplier?->display_name,
                    $item->paid_at?->format('d/m/Y H:i'),
                ])->filter()->implode(' · ');

                return $this->detailItem(
                    title: $item->code ?: ('Pagavel #' . $item->id),
                    subtitle: $item->title ?: 'Conta a pagar',
                    meta: $meta,
                    value: $this->money((float) $item->paid_amount),
                    badge: $item->status ? Str::headline((string) $item->status) : null,
                    url: $this->routeIfAllowed($request, 'financeiro.payables.show', ['payable' => $item], 'financeiro.payables.index'),
                    sortTimestamp: $item->paid_at?->timestamp ?? $item->updated_at?->timestamp
                );
            })->all(),
            emptyTitle: 'Sem despesas no periodo',
            emptySubtitle: 'As baixas de contas a pagar aparecerao aqui conforme o recorte escolhido.',
            actionUrl: $this->routeIfAllowed($request, 'financeiro.dashboard', [], 'financeiro.payables.index'),
            actionLabel: 'Abrir Financeiro 360'
        );
    }

    private function criticalAlertsCard(Request $request, array $range): ?array
    {
        if (
            !$this->hasModule($request, 'financeiro')
            && !$this->hasModule($request, 'demandas')
            && !$this->hasModule($request, 'contratos')
            && !$this->hasModule($request, 'assinador')
            && !$this->hasModule($request, 'processos')
            && !$this->hasModule($request, 'cobrancas')
        ) {
            return null;
        }

        $items = collect();

        $financialOverdueCount = 0;
        $demandOverdueCount = 0;
        $contractAwaitingCount = 0;
        $signaturePendingCount = 0;
        $processStaleCount = 0;
        $collectionsJudicialCount = 0;

        if ($this->hasModule($request, 'financeiro') && $this->tableExists('financial_receivables')) {
            $financialOverdue = FinancialReceivable::query()
                ->with(['client', 'condominium'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', ['recebido', 'cancelado'])
                ->orderBy('due_date')
                ->limit(4)
                ->get();

            $financialOverdueCount = FinancialReceivable::query()
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', ['recebido', 'cancelado'])
                ->count();

            $items = $items->concat($financialOverdue->map(function (FinancialReceivable $item) use ($request) {
                return $this->detailItem(
                    title: $item->code ?: ('Recebivel #' . $item->id),
                    subtitle: $item->title ?: 'Conta vencida',
                    meta: collect([
                        $item->client?->display_name ?: $item->condominium?->name,
                        $item->due_date?->format('d/m/Y'),
                    ])->filter()->implode(' · '),
                    value: $this->money((float) $item->final_amount - (float) $item->received_amount),
                    badge: 'Financeiro',
                    url: $this->routeIfAllowed($request, 'financeiro.receivables.show', ['receivable' => $item], 'financeiro.receivables.index'),
                    sortTimestamp: $item->due_date?->timestamp
                );
            }));
        }

        if ($this->hasModule($request, 'demandas') && $this->tableExists('demands')) {
            $demandOverdue = Demand::query()
                ->with(['condominium', 'entity'])
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->orderBy('sla_due_at')
                ->limit(4)
                ->get();

            $demandOverdueCount = Demand::query()
                ->whereNotIn('status', ['concluida', 'cancelada'])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->count();

            $items = $items->concat($demandOverdue->map(function (Demand $item) use ($request) {
                return $this->detailItem(
                    title: $item->protocol ?: ('Demanda #' . $item->id),
                    subtitle: $item->subject ?: 'SLA vencido',
                    meta: collect([
                        $item->clientName(),
                        $item->sla_due_at?->format('d/m/Y H:i'),
                    ])->filter()->implode(' · '),
                    value: $item->slaStatusLabel(),
                    badge: 'Demanda',
                    url: $this->routeIfAllowed($request, 'demandas.show', ['demanda' => $item], 'demandas.index'),
                    sortTimestamp: $item->sla_due_at?->timestamp
                );
            }));
        }

        if ($this->hasModule($request, 'contratos') && $this->tableExists('contracts')) {
            $contractAwaiting = Contract::query()
                ->with(['client', 'condominium'])
                ->where('status', 'aguardando_assinatura')
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get();

            $contractAwaitingCount = Contract::query()->where('status', 'aguardando_assinatura')->count();

            $items = $items->concat($contractAwaiting->map(function (Contract $item) use ($request) {
                return $this->detailItem(
                    title: $item->code ?: ('Contrato #' . $item->id),
                    subtitle: $item->title ?: 'Aguardando assinatura',
                    meta: $item->client?->display_name ?: ($item->condominium?->name ?: 'Sem vinculo principal'),
                    value: $this->dateTime($item->updated_at),
                    badge: 'Contrato',
                    url: $this->routeIfAllowed($request, 'contratos.show', ['contrato' => $item], 'contratos.index'),
                    sortTimestamp: $item->updated_at?->timestamp
                );
            }));
        }

        if ($this->hasModule($request, 'assinador') && $this->tableExists('document_signature_requests')) {
            $signaturePending = DocumentSignatureRequest::query()
                ->whereIn('status', ['pending_signatures', 'partially_signed', 'metadata_ready', 'uploaded', 'certificating'])
                ->latest('updated_at')
                ->limit(4)
                ->get();

            $signaturePendingCount = DocumentSignatureRequest::query()
                ->whereIn('status', ['pending_signatures', 'partially_signed', 'metadata_ready', 'uploaded', 'certificating'])
                ->count();

            $items = $items->concat($signaturePending->map(function (DocumentSignatureRequest $item) use ($request) {
                return $this->detailItem(
                    title: $item->document_name ?: ('Assinatura #' . $item->id),
                    subtitle: $this->signatureSourceLabel($item),
                    meta: DocumentSignatureService::requestStatusLabels()[$item->status] ?? Str::headline((string) $item->status),
                    value: $this->dateTime($item->updated_at),
                    badge: 'Assinatura',
                    url: $this->signatureUrl($request, $item),
                    sortTimestamp: $item->updated_at?->timestamp
                );
            }));
        }

        if ($this->hasModule($request, 'processos') && $this->tableExists('process_cases') && $this->tableExists('process_case_phases')) {
            $staleCases = $this->visibleProcessQuery($request)
                ->withMax('phases', 'phase_date')
                ->whereNull('closed_at')
                ->get()
                ->map(function (ProcessCase $case) {
                    $lastMovement = $case->phases_max_phase_date
                        ? Carbon::parse($case->phases_max_phase_date)
                        : ($case->opened_at ?: $case->created_at);

                    return [
                        'case' => $case,
                        'last_movement' => $lastMovement ? Carbon::parse($lastMovement) : null,
                    ];
                })
                ->filter(fn (array $row) => $row['last_movement'] && $row['last_movement']->diffInDays(now()) >= 90)
                ->sortBy(fn (array $row) => $row['last_movement']?->timestamp ?? PHP_INT_MAX)
                ->values();

            $processStaleCount = $staleCases->count();

            $items = $items->concat($staleCases->take(4)->map(function (array $row) use ($request) {
                /** @var ProcessCase $item */
                $item = $row['case'];

                return $this->detailItem(
                    title: $item->process_number ?: ('Processo #' . $item->id),
                    subtitle: $item->client_name_snapshot ?: 'Sem cliente principal',
                    meta: 'Ultimo andamento em ' . $row['last_movement']?->format('d/m/Y'),
                    value: $row['last_movement'] ? $row['last_movement']->diffInDays(now()) . ' dias' : '-',
                    badge: 'Processo',
                    url: $this->routeIfAllowed($request, 'processos.show', ['processo' => $item], 'processos.index'),
                    sortTimestamp: $row['last_movement']?->timestamp
                );
            }));
        }

        if ($this->hasModule($request, 'cobrancas') && $this->tableExists('cobranca_cases')) {
            $collectionsJudicial = CobrancaCase::query()
                ->with(['condominium', 'unit'])
                ->where('workflow_stage', 'apto_judicializar')
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get();

            $collectionsJudicialCount = CobrancaCase::query()->where('workflow_stage', 'apto_judicializar')->count();

            $items = $items->concat($collectionsJudicial->map(function (CobrancaCase $item) use ($request) {
                return $this->detailItem(
                    title: $item->os_number ?: ('OS #' . $item->id),
                    subtitle: $item->debtor_name_snapshot ?: 'Devedor nao informado',
                    meta: collect([
                        $item->condominium?->name,
                        $item->unit?->unit_number ? 'Unidade ' . $item->unit->unit_number : null,
                    ])->filter()->implode(' · '),
                    value: $this->dateTime($item->updated_at),
                    badge: 'Cobranca',
                    url: $this->routeIfAllowed($request, 'cobrancas.show', ['cobranca' => $item], 'cobrancas.index'),
                    sortTimestamp: $item->updated_at?->timestamp
                );
            }));
        }

        $total = $financialOverdueCount + $demandOverdueCount + $contractAwaitingCount + $signaturePendingCount + $processStaleCount + $collectionsJudicialCount;

        return $this->card(
            key: 'critical_alerts',
            label: 'Alertas criticos',
            value: $this->number($total),
            hint: $this->number($financialOverdueCount) . ' financeiro · ' . $this->number($demandOverdueCount) . ' SLA · ' . $this->number($signaturePendingCount) . ' assinaturas',
            icon: 'fa-solid fa-triangle-exclamation',
            detailTitle: 'Alertas criticos do escritorio',
            detailSubtitle: 'Pendencias atuais que merecem acompanhamento prioritario.',
            detailStats: [
                $this->detailStat('Financeiro vencido', $this->number($financialOverdueCount)),
                $this->detailStat('Demandas com SLA vencido', $this->number($demandOverdueCount)),
                $this->detailStat('Contratos aguardando assinatura', $this->number($contractAwaitingCount)),
                $this->detailStat('Assinaturas pendentes', $this->number($signaturePendingCount)),
                $this->detailStat('Processos sem andamento 90d+', $this->number($processStaleCount)),
                $this->detailStat('OS aptas a judicializar', $this->number($collectionsJudicialCount)),
            ],
            detailItems: $items
                ->sortBy(fn (array $item) => $item['sort_timestamp'] ?? PHP_INT_MAX)
                ->take(12)
                ->values()
                ->all(),
            emptyTitle: 'Sem alertas criticos',
            emptySubtitle: 'Nao ha pendencias criticas abertas neste momento.',
            actionUrl: null,
            actionLabel: null
        );
    }

    private function card(
        string $key,
        string $label,
        string $value,
        string $hint,
        string $icon,
        string $detailTitle,
        string $detailSubtitle,
        array $detailStats,
        array $detailItems,
        string $emptyTitle,
        string $emptySubtitle,
        ?string $actionUrl,
        ?string $actionLabel
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'icon' => $icon,
            'detail' => [
                'title' => $detailTitle,
                'subtitle' => $detailSubtitle,
                'stats' => $detailStats,
                'items' => $detailItems,
                'empty_title' => $emptyTitle,
                'empty_subtitle' => $emptySubtitle,
                'action_url' => $actionUrl,
                'action_label' => $actionLabel,
            ],
        ];
    }

    private function detailStat(string $label, string $value): array
    {
        return [
            'label' => $label,
            'value' => $value,
        ];
    }

    private function detailItem(
        string $title,
        string $subtitle = '',
        string $meta = '',
        string $value = '',
        ?string $badge = null,
        ?string $url = null,
        ?int $sortTimestamp = null
    ): array {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'meta' => $meta,
            'value' => $value,
            'badge' => $badge,
            'url' => $url,
            'sort_timestamp' => $sortTimestamp,
        ];
    }

    private function mergeAndLimit(array $groups, int $limit): array
    {
        return collect($groups)
            ->flatten(1)
            ->sortByDesc(fn (array $item) => $item['sort_timestamp'] ?? 0)
            ->take($limit)
            ->values()
            ->all();
    }

    private function resolvePeriod(string $period): string
    {
        return in_array($period, ['day', 'week', 'month', 'year'], true) ? $period : 'month';
    }

    private function periodOptions(): array
    {
        return [
            'day' => 'Hoje',
            'week' => 'Semana',
            'month' => 'Mes',
            'year' => 'Ano',
        ];
    }

    private function periodRange(string $period): array
    {
        $now = now();

        $start = match ($period) {
            'day' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(Carbon::MONDAY),
            'year' => $now->copy()->startOfYear(),
            default => $now->copy()->startOfMonth(),
        };

        $label = match ($period) {
            'day' => 'hoje (' . $start->format('d/m/Y') . ')',
            'week' => 'esta semana (' . $start->format('d/m') . ' a ' . $now->format('d/m') . ')',
            'year' => 'este ano (' . $start->format('d/m/Y') . ' a ' . $now->format('d/m/Y') . ')',
            default => 'este mes (' . $start->format('d/m') . ' a ' . $now->format('d/m') . ')',
        };

        return [
            'key' => $period,
            'start' => $start,
            'end' => $now,
            'label' => $label,
            'headline' => match ($period) {
                'day' => 'Visao do dia',
                'week' => 'Visao da semana',
                'year' => 'Visao do ano',
                default => 'Visao do mes',
            },
        ];
    }

    private function hasModule(Request $request, string $slug): bool
    {
        return AncoraAuth::hasModule($request, $slug);
    }

    private function routeIfAllowed(Request $request, string $primary, array $params = [], ?string $fallback = null, array $fallbackParams = []): ?string
    {
        if ($this->canRoute($request, $primary)) {
            return route($primary, $params);
        }

        if ($fallback && $this->canRoute($request, $fallback)) {
            return route($fallback, $fallbackParams);
        }

        return null;
    }

    private function canRoute(Request $request, string $routeName): bool
    {
        $user = AncoraAuth::user($request);
        if (!$user) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        if (!(method_exists($request, 'hasSession') && $request->hasSession())) {
            return false;
        }

        return in_array($routeName, (array) $request->session()->get('auth_user.route_permissions', []), true);
    }

    private function entityUrl(Request $request, ClientEntity $entity): ?string
    {
        $name = $entity->display_name ?: $entity->legal_name ?: ('Cliente #' . $entity->id);

        if ($entity->profile_scope === 'avulso') {
            return $this->routeIfAllowed($request, 'clientes.avulsos.edit', ['avulso' => $entity], 'clientes.avulsos', ['q' => $name]);
        }

        if ($this->isCondominoEntity($entity)) {
            return $this->routeIfAllowed($request, 'clientes.condominos', ['q' => $name]);
        }

        return $this->routeIfAllowed($request, 'clientes.contatos.edit', ['contato' => $entity], 'clientes.contatos', ['q' => $name]);
    }

    private function condominiumUrl(Request $request, ClientCondominium $condominium): ?string
    {
        return $this->routeIfAllowed(
            $request,
            'clientes.condominios.edit',
            ['condominio' => $condominium],
            'clientes.condominios',
            ['q' => $condominium->name]
        );
    }

    private function unitUrl(Request $request, ClientUnit $unit): ?string
    {
        return $this->routeIfAllowed(
            $request,
            'clientes.unidades.edit',
            ['unidade' => $unit],
            'clientes.unidades',
            ['q' => $unit->unit_number]
        );
    }

    private function signatureUrl(Request $request, DocumentSignatureRequest $signature): ?string
    {
        return match ($signature->signable_type) {
            Contract::class => $this->routeIfAllowed($request, 'contratos.show', ['contrato' => $signature->signable_id, 'tab' => 'assinaturas'], 'contratos.index'),
            CobrancaCase::class => $this->routeIfAllowed($request, 'cobrancas.show', ['cobranca' => $signature->signable_id], 'cobrancas.index'),
            ElectronicSignatureDocument::class => $this->routeIfAllowed($request, 'assinador.show', ['documento' => $signature->signable_id], 'assinador.index'),
            default => $this->routeIfAllowed($request, 'assinador.index'),
        };
    }

    private function signatureSourceLabel(DocumentSignatureRequest $signature): string
    {
        return match ($signature->signable_type) {
            Contract::class => 'Contrato',
            CobrancaCase::class => 'Cobranca / Termo',
            ElectronicSignatureDocument::class => 'Avulso',
            default => 'Assinatura',
        };
    }

    private function signatureSourceBadge(DocumentSignatureRequest $signature): string
    {
        return match ($signature->signable_type) {
            Contract::class => 'Contrato',
            CobrancaCase::class => 'OS',
            ElectronicSignatureDocument::class => 'Avulso',
            default => 'Doc.',
        };
    }

    private function visibleProcessQuery(Request $request): Builder
    {
        $query = ProcessCase::query();
        $user = AncoraAuth::user($request);

        if (!$user?->isSuperadmin()) {
            $needleName = $this->normalize((string) $user?->name);
            $needleEmail = $this->normalize((string) $user?->email);

            $query->where(function (Builder $inner) use ($user, $needleName, $needleEmail) {
                $inner->where('is_private', false)
                    ->orWhere('created_by', $user?->id);

                if ($needleName !== '') {
                    $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleName . '%']);
                }

                if ($needleEmail !== '') {
                    $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleEmail . '%']);
                }
            });
        }

        return $query;
    }

    private function visibleProcessPhaseQuery(Request $request): Builder
    {
        return ProcessCasePhase::query()->whereHas('processCase', function (Builder $query) use ($request) {
            $visible = $this->visibleProcessQuery($request);
            $query->whereIn('process_cases.id', $visible->select('process_cases.id'));
        });
    }

    private function isCondominoEntity(ClientEntity $entity): bool
    {
        return $entity->profile_scope === 'contato'
            && (
                ($entity->owned_units_count ?? 0) > 0
                || ($entity->rented_units_count ?? 0) > 0
                || $this->containsCondominoRole((string) $entity->role_tag)
            );
    }

    private function containsCondominoRole(string $roleTag): bool
    {
        $normalized = $this->normalize($roleTag);

        return $normalized !== ''
            && (
                str_contains($normalized, 'proprietario')
                || str_contains($normalized, 'locatario')
                || str_contains($normalized, 'inquilino')
            );
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = Schema::hasTable($table);
        }

        return $this->tableExistsCache[$table];
    }

    private function number(int|float $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function dateTime(?Carbon $value): string
    {
        return $value ? $value->format('d/m/Y H:i') : '-';
    }

    private function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->squish()->toString();
    }
}
