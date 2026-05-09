<?php

namespace App\Http\Controllers;

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
use App\Models\Proposal;
use App\Models\User;
use App\Services\DocumentSignatureService;
use App\Support\AncoraAuth;
use App\Support\Contracts\ContractCatalog;
use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    private array $tableExistsCache = [];

    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q', ''));
        $sections = $this->buildSections($request, $term);
        $totalResults = collect($sections)->sum(fn (array $section): int => $section['items']->count());

        return view('pages.admin.search', [
            'title' => 'Busca',
            'term' => $term,
            'sections' => $sections,
            'totalResults' => $totalResults,
        ]);
    }

    private function buildSections(Request $request, string $term): array
    {
        return array_values(array_filter([
            $this->usersSection($term),
            $this->proposalsSection($request, $term),
            $this->clientsSection($request, $term),
            $this->condominiumsSection($request, $term),
            $this->ownersSection($request, $term),
            $this->collectionsSection($request, $term),
            $this->demandsSection($request, $term),
            $this->processesSection($request, $term),
            $this->contractsSection($request, $term),
            $this->signaturesSection($request, $term),
            $this->financialSection($request, $term),
        ]));
    }

    private function usersSection(string $term): ?array
    {
        if (!$this->tableExists('users')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $items = User::query()
                ->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%')
                        ->orWhere('role', 'like', '%' . $term . '%');
                })
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->map(fn (User $user): array => $this->resultItem(
                    title: $user->name ?: ('Usuario #' . $user->id),
                    subtitle: $user->email ?: 'E-mail nao informado',
                    meta: $user->is_active ? 'Usuario ativo' : 'Usuario inativo',
                    badge: Str::headline((string) ($user->role ?: 'usuario'))
                ));
        }

        return $this->section(
            key: 'users',
            label: 'Usuarios',
            subtitle: 'Nome, e-mail e papel de acesso.',
            icon: 'fa-solid fa-user-group',
            emptySubtitle: 'Nada encontrado em usuarios.',
            items: $items
        );
    }

    private function proposalsSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'propostas') || !$this->tableExists('propostas')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'propostas.index')
                ? route('propostas.index', ['q' => $term])
                : null;

            $items = Proposal::query()
                ->with(['servico', 'statusRetorno'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('proposal_code', 'like', '%' . $term . '%')
                        ->orWhere('client_name', 'like', '%' . $term . '%')
                        ->orWhere('contact_email', 'like', '%' . $term . '%')
                        ->orWhere('requester_name', 'like', '%' . $term . '%')
                        ->orWhere('notes', 'like', '%' . $term . '%');
                })
                ->orderByDesc('proposal_date')
                ->limit(8)
                ->get()
                ->map(function (Proposal $proposal) use ($request, $fallbackUrl): array {
                    $meta = collect([
                        $proposal->servico?->name,
                        $proposal->statusRetorno?->name,
                        $proposal->proposal_total !== null ? $this->money((float) $proposal->proposal_total) : null,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $proposal->proposal_code ?: ('Proposta #' . $proposal->id),
                        subtitle: $proposal->client_name ?: ($proposal->requester_name ?: 'Cliente nao informado'),
                        meta: $meta,
                        badge: $proposal->proposal_date?->format('d/m/Y'),
                        url: $this->canRoute($request, 'propostas.show')
                            ? route('propostas.show', ['proposta' => $proposal])
                            : $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'proposals',
            label: 'Propostas',
            subtitle: 'Codigo, cliente, solicitante e servico.',
            icon: 'fa-solid fa-file-signature',
            emptySubtitle: 'Nada encontrado em propostas.',
            items: $items
        );
    }

    private function clientsSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'clientes') || !$this->tableExists('client_entities')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $items = ClientEntity::query()
                ->withCount(['ownedUnits', 'rentedUnits'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('display_name', 'like', '%' . $term . '%')
                        ->orWhere('legal_name', 'like', '%' . $term . '%')
                        ->orWhere('cpf_cnpj', 'like', '%' . $term . '%')
                        ->orWhere('role_tag', 'like', '%' . $term . '%');
                })
                ->orderBy('display_name')
                ->limit(8)
                ->get()
                ->map(function (ClientEntity $entity) use ($request): array {
                    $meta = collect([
                        $entity->role_tag ?: null,
                        $entity->cpf_cnpj ?: null,
                        ($entity->owned_units_count ?? 0) > 0 ? (($entity->owned_units_count ?? 0) . ' unidade(s) como proprietario') : null,
                        ($entity->rented_units_count ?? 0) > 0 ? (($entity->rented_units_count ?? 0) . ' unidade(s) como locatario') : null,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $entity->display_name ?: ('Cliente #' . $entity->id),
                        subtitle: $entity->legal_name ?: 'Cadastro de cliente',
                        meta: $meta,
                        badge: $entity->profile_scope === 'contato' ? 'Contato' : 'Avulso',
                        url: $this->entityUrl($request, $entity)
                    );
                });
        }

        return $this->section(
            key: 'clients',
            label: 'Clientes',
            subtitle: 'Avulsos, contatos e papeis vinculados.',
            icon: 'fa-solid fa-address-book',
            emptySubtitle: 'Nada encontrado em clientes.',
            items: $items
        );
    }

    private function condominiumsSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'clientes') || !$this->tableExists('client_condominiums')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'clientes.condominios')
                ? route('clientes.condominios', ['q' => $term])
                : null;

            $items = ClientCondominium::query()
                ->with(['syndic', 'administradora'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', '%' . $term . '%')
                        ->orWhere('cnpj', 'like', '%' . $term . '%');
                })
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->map(function (ClientCondominium $condominium) use ($request, $fallbackUrl): array {
                    $meta = collect([
                        $condominium->cnpj ?: null,
                        $condominium->syndic?->display_name ? 'Sindico: ' . $condominium->syndic->display_name : null,
                        $condominium->administradora?->display_name ? 'Administradora: ' . $condominium->administradora->display_name : null,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $condominium->name ?: ('Condominio #' . $condominium->id),
                        subtitle: 'Cadastro condominial',
                        meta: $meta,
                        badge: $condominium->is_active === false ? 'Inativo' : 'Ativo',
                        url: $this->canRoute($request, 'clientes.condominios.edit')
                            ? route('clientes.condominios.edit', ['condominio' => $condominium])
                            : $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'condominiums',
            label: 'Condominios',
            subtitle: 'Nome, CNPJ, sindico e administradora.',
            icon: 'fa-solid fa-building',
            emptySubtitle: 'Nada encontrado em condominios.',
            items: $items
        );
    }

    private function ownersSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'clientes') || !$this->tableExists('client_entities') || !$this->tableExists('client_units')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'clientes.condominos')
                ? route('clientes.condominos', ['q' => $term, 'vinculo' => 'proprietario'])
                : null;

            $items = ClientEntity::query()
                ->where('profile_scope', 'contato')
                ->withCount('ownedUnits')
                ->where(function (Builder $query) {
                    $query->whereIn('id', ClientUnit::query()->select('owner_entity_id')->whereNotNull('owner_entity_id'))
                        ->orWhere('role_tag', 'like', '%proprietario%')
                        ->orWhere('role_tag', 'like', '%proprietário%');
                })
                ->where(function (Builder $query) use ($term) {
                    $query->where('display_name', 'like', '%' . $term . '%')
                        ->orWhere('legal_name', 'like', '%' . $term . '%')
                        ->orWhere('cpf_cnpj', 'like', '%' . $term . '%');
                })
                ->orderBy('display_name')
                ->limit(8)
                ->get()
                ->map(function (ClientEntity $entity) use ($fallbackUrl): array {
                    $meta = collect([
                        $entity->cpf_cnpj ?: null,
                        ($entity->owned_units_count ?? 0) > 0 ? (($entity->owned_units_count ?? 0) . ' unidade(s) vinculada(s)') : 'Sem unidade vinculada',
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $entity->display_name ?: ('Proprietario #' . $entity->id),
                        subtitle: $entity->legal_name ?: 'Condomino',
                        meta: $meta,
                        badge: 'Proprietario',
                        url: $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'owners',
            label: 'Proprietarios',
            subtitle: 'Condominos vinculados como proprietarios.',
            icon: 'fa-solid fa-key',
            emptySubtitle: 'Nada encontrado em proprietarios.',
            items: $items
        );
    }

    private function collectionsSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'cobrancas') || !$this->tableExists('cobranca_cases')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'cobrancas.index')
                ? route('cobrancas.index', ['q' => $term])
                : null;

            $items = CobrancaCase::query()
                ->with(['condominium', 'unit'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('os_number', 'like', '%' . $term . '%')
                        ->orWhere('debtor_name_snapshot', 'like', '%' . $term . '%')
                        ->orWhere('debtor_document_snapshot', 'like', '%' . $term . '%')
                        ->orWhere('judicial_case_number', 'like', '%' . $term . '%')
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'))
                        ->orWhereHas('unit', fn (Builder $rel) => $rel->where('unit_number', 'like', '%' . $term . '%'));
                })
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map(function (CobrancaCase $case) use ($request, $fallbackUrl): array {
                    $meta = collect([
                        $case->condominium?->name,
                        $case->unit?->unit_number ? 'Unidade ' . $case->unit->unit_number : null,
                        $case->situation ? Str::headline((string) $case->situation) : null,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $case->os_number ?: ('OS #' . $case->id),
                        subtitle: $case->debtor_name_snapshot ?: 'Devedor nao informado',
                        meta: $meta,
                        badge: $case->workflow_stage ? Str::headline((string) $case->workflow_stage) : null,
                        url: $this->canRoute($request, 'cobrancas.show')
                            ? route('cobrancas.show', ['cobranca' => $case])
                            : $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'collections',
            label: 'Cobrancas',
            subtitle: 'OS, devedor, unidade, condominio e processo judicial.',
            icon: 'fa-solid fa-money-bill-wave',
            emptySubtitle: 'Nada encontrado em cobrancas.',
            items: $items
        );
    }

    private function demandsSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'demandas') || !$this->tableExists('demands')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'demandas.index')
                ? route('demandas.index', ['q' => $term])
                : null;

            $items = Demand::query()
                ->with(['entity', 'condominium', 'category'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('protocol', 'like', '%' . $term . '%')
                        ->orWhere('subject', 'like', '%' . $term . '%')
                        ->orWhere('description', 'like', '%' . $term . '%')
                        ->orWhereHas('entity', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'));
                })
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map(function (Demand $demand) use ($request, $fallbackUrl): array {
                    $meta = collect([
                        $demand->clientName(),
                        $demand->category?->name,
                        $demand->publicStatusLabel(),
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $demand->protocol ?: ('Demanda #' . $demand->id),
                        subtitle: $demand->subject ?: 'Assunto nao informado',
                        meta: $meta,
                        badge: Demand::priorityLabels()[$demand->priority] ?? Str::headline((string) $demand->priority),
                        url: $this->canRoute($request, 'demandas.show')
                            ? route('demandas.show', ['demanda' => $demand])
                            : $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'demands',
            label: 'Demandas',
            subtitle: 'Protocolo, assunto, cliente e servico.',
            icon: 'fa-solid fa-inbox',
            emptySubtitle: 'Nada encontrado em demandas.',
            items: $items
        );
    }

    private function processesSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'processos') || !$this->tableExists('process_cases')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'processos.index')
                ? route('processos.index', ['q' => $term])
                : null;

            $items = $this->visibleProcessQuery($request)
                ->with(['statusOption', 'processTypeOption', 'client', 'adverse', 'clientCondominium', 'adverseCondominium'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('process_number', 'like', '%' . $term . '%')
                        ->orWhere('responsible_lawyer', 'like', '%' . $term . '%')
                        ->orWhereHas('client', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('adverse', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('clientCondominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'))
                        ->orWhereHas('adverseCondominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'));
                })
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map(function (ProcessCase $case) use ($request, $fallbackUrl): array {
                    $clientLabel = $case->clientCondominium?->name ?: $case->client?->display_name;
                    $adverseLabel = $case->adverseCondominium?->name ?: $case->adverse?->display_name;

                    $meta = collect([
                        $clientLabel ? 'Cliente: ' . $clientLabel : null,
                        $adverseLabel ? 'Adverso: ' . $adverseLabel : null,
                        $case->statusOption?->name,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $case->process_number ?: ('Processo #' . $case->id),
                        subtitle: $case->processTypeOption?->name ?: 'Processo sem tipo definido',
                        meta: $meta,
                        badge: $case->opened_at?->format('d/m/Y'),
                        url: $this->canRoute($request, 'processos.show')
                            ? route('processos.show', ['processo' => $case])
                            : $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'processes',
            label: 'Processos',
            subtitle: 'Numero, polos, tipo e status.',
            icon: 'fa-solid fa-scale-balanced',
            emptySubtitle: 'Nada encontrado em processos.',
            items: $items
        );
    }

    private function contractsSection(Request $request, string $term): ?array
    {
        if (!$this->hasModule($request, 'contratos') || !$this->tableExists('contracts')) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'contratos.index')
                ? route('contratos.index', ['q' => $term])
                : null;

            $items = Contract::query()
                ->with(['client', 'condominium', 'responsible'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhere('type', 'like', '%' . $term . '%')
                        ->orWhereHas('client', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'));
                })
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map(function (Contract $contract) use ($request, $fallbackUrl): array {
                    $meta = collect([
                        $contract->client?->display_name ?: $contract->condominium?->name,
                        ContractCatalog::statuses()[$contract->status] ?? Str::headline((string) $contract->status),
                        $contract->responsible?->name ? 'Resp.: ' . $contract->responsible->name : null,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $contract->code ?: ('Contrato #' . $contract->id),
                        subtitle: $contract->title ?: ($contract->type ?: 'Contrato sem titulo'),
                        meta: $meta,
                        badge: $contract->type ?: null,
                        url: $this->canRoute($request, 'contratos.show')
                            ? route('contratos.show', ['contrato' => $contract])
                            : $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'contracts',
            label: 'Contratos',
            subtitle: 'Codigo, titulo, tipo e vinculacoes.',
            icon: 'fa-solid fa-file-contract',
            emptySubtitle: 'Nada encontrado em contratos.',
            items: $items
        );
    }

    private function signaturesSection(Request $request, string $term): ?array
    {
        if (
            !$this->hasModule($request, 'assinador')
            || !$this->tableExists('document_signature_requests')
            || !$this->tableExists('document_signature_signers')
        ) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $fallbackUrl = $this->canRoute($request, 'assinador.index')
                ? route('assinador.index', ['document_name' => $term])
                : null;

            $items = DocumentSignatureRequest::query()
                ->whereIn('signable_type', [Contract::class, CobrancaCase::class, ElectronicSignatureDocument::class])
                ->with(['signable', 'signers'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('document_name', 'like', '%' . $term . '%')
                        ->orWhere('provider_document_id', 'like', '%' . $term . '%')
                        ->orWhereHas('signers', function (Builder $builder) use ($term) {
                            $builder->where('name', 'like', '%' . $term . '%')
                                ->orWhere('email', 'like', '%' . $term . '%');
                        });
                })
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(function (DocumentSignatureRequest $signature) use ($request, $fallbackUrl): array {
                    $meta = collect([
                        $this->signatureSourceLabel($signature),
                        $this->signatureSourceName($signature),
                        DocumentSignatureService::requestStatusLabels()[$signature->status] ?? Str::headline((string) $signature->status),
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $signature->document_name ?: ('Assinatura #' . $signature->id),
                        subtitle: $signature->signers->pluck('name')->filter()->implode(', ') ?: 'Sem signatarios',
                        meta: $meta,
                        badge: $this->signatureBadge($signature),
                        url: $this->signatureUrl($request, $signature) ?: $fallbackUrl
                    );
                });
        }

        return $this->section(
            key: 'signatures',
            label: 'Assinador Eletronico',
            subtitle: 'Documentos, signatarios e status de assinatura.',
            icon: 'fa-solid fa-signature',
            emptySubtitle: 'Nada encontrado em assinaturas.',
            items: $items
        );
    }

    private function financialSection(Request $request, string $term): ?array
    {
        if (
            !$this->hasModule($request, 'financeiro')
            || !$this->tableExists('financial_receivables')
            || !$this->tableExists('financial_payables')
        ) {
            return null;
        }

        $items = collect();

        if ($term !== '') {
            $receivableFallback = $this->canRoute($request, 'financeiro.receivables.index')
                ? route('financeiro.receivables.index', ['q' => $term])
                : null;
            $payableFallback = $this->canRoute($request, 'financeiro.payables.index')
                ? route('financeiro.payables.index', ['q' => $term])
                : null;

            $receivables = FinancialReceivable::query()
                ->with(['client', 'condominium', 'contract'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhereHas('client', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('condominium', fn (Builder $rel) => $rel->where('name', 'like', '%' . $term . '%'));
                })
                ->orderByDesc('due_date')
                ->limit(4)
                ->get()
                ->map(function (FinancialReceivable $item) use ($request, $receivableFallback): array {
                    $meta = collect([
                        $item->client?->display_name ?: $item->condominium?->name,
                        $item->due_date?->format('d/m/Y') ? 'Venc.: ' . $item->due_date->format('d/m/Y') : null,
                        $item->final_amount !== null ? $this->money((float) $item->final_amount) : null,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $item->code ?: ('Recebivel #' . $item->id),
                        subtitle: $item->title ?: 'Conta a receber',
                        meta: $meta,
                        badge: FinancialCatalog::receivableStatuses()[$item->status] ?? Str::headline((string) $item->status),
                        url: $this->canRoute($request, 'financeiro.receivables.show')
                            ? route('financeiro.receivables.show', ['receivable' => $item])
                            : $receivableFallback
                    );
                });

            $payables = FinancialPayable::query()
                ->with(['supplier'])
                ->where(function (Builder $query) use ($term) {
                    $query->where('code', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%')
                        ->orWhereHas('supplier', fn (Builder $rel) => $rel->where('display_name', 'like', '%' . $term . '%'));
                })
                ->orderByDesc('due_date')
                ->limit(4)
                ->get()
                ->map(function (FinancialPayable $item) use ($request, $payableFallback): array {
                    $meta = collect([
                        $item->supplier?->display_name,
                        $item->due_date?->format('d/m/Y') ? 'Venc.: ' . $item->due_date->format('d/m/Y') : null,
                        $item->amount !== null ? $this->money((float) $item->amount) : null,
                    ])->filter()->implode(' · ');

                    return $this->resultItem(
                        title: $item->code ?: ('Pagavel #' . $item->id),
                        subtitle: $item->title ?: 'Conta a pagar',
                        meta: $meta,
                        badge: FinancialCatalog::payableStatuses()[$item->status] ?? Str::headline((string) $item->status),
                        url: $this->canRoute($request, 'financeiro.payables.show')
                            ? route('financeiro.payables.show', ['payable' => $item])
                            : $payableFallback
                    );
                });

            $items = $receivables->concat($payables)->values();
        }

        return $this->section(
            key: 'financial',
            label: 'Financeiro 360',
            subtitle: 'Contas a receber e a pagar do modulo financeiro.',
            icon: 'fa-solid fa-chart-pie',
            emptySubtitle: 'Nada encontrado em Financeiro 360.',
            items: $items
        );
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

    private function entityUrl(Request $request, ClientEntity $entity): ?string
    {
        $displayName = $entity->display_name ?: $entity->legal_name ?: ('Cliente #' . $entity->id);
        $isCondomino = $entity->profile_scope === 'contato'
            && (
                ($entity->owned_units_count ?? 0) > 0
                || ($entity->rented_units_count ?? 0) > 0
                || $this->containsCondominoRole((string) $entity->role_tag)
            );

        if ($isCondomino) {
            return $this->canRoute($request, 'clientes.condominos')
                ? route('clientes.condominos', ['q' => $displayName])
                : null;
        }

        if ($entity->profile_scope === 'contato') {
            if ($this->canRoute($request, 'clientes.contatos.edit')) {
                return route('clientes.contatos.edit', ['contato' => $entity]);
            }

            return $this->canRoute($request, 'clientes.contatos')
                ? route('clientes.contatos', ['q' => $displayName])
                : null;
        }

        if ($this->canRoute($request, 'clientes.avulsos.edit')) {
            return route('clientes.avulsos.edit', ['avulso' => $entity]);
        }

        return $this->canRoute($request, 'clientes.avulsos')
            ? route('clientes.avulsos', ['q' => $displayName])
            : null;
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

    private function signatureSourceName(DocumentSignatureRequest $signature): string
    {
        $signable = $signature->signable;

        return match ($signature->signable_type) {
            Contract::class => trim((string) ($signable?->code ?: $signable?->title ?: ('Contrato #' . $signature->signable_id))),
            CobrancaCase::class => trim((string) ($signable?->os_number ?: ('OS #' . $signature->signable_id))),
            ElectronicSignatureDocument::class => trim((string) ($signable?->title ?: $signable?->original_name ?: ('Documento #' . $signature->signable_id))),
            default => trim((string) $signature->document_name),
        };
    }

    private function signatureBadge(DocumentSignatureRequest $signature): string
    {
        return match ($signature->signable_type) {
            Contract::class => 'Contrato',
            CobrancaCase::class => 'OS',
            ElectronicSignatureDocument::class => 'Avulso',
            default => 'Doc.',
        };
    }

    private function signatureUrl(Request $request, DocumentSignatureRequest $signature): ?string
    {
        return match ($signature->signable_type) {
            Contract::class => $this->canRoute($request, 'contratos.show')
                ? route('contratos.show', ['contrato' => $signature->signable_id, 'tab' => 'assinaturas'])
                : null,
            CobrancaCase::class => $this->canRoute($request, 'cobrancas.show')
                ? route('cobrancas.show', ['cobranca' => $signature->signable_id])
                : null,
            ElectronicSignatureDocument::class => $this->canRoute($request, 'assinador.show')
                ? route('assinador.show', ['documento' => $signature->signable_id])
                : null,
            default => null,
        };
    }

    private function hasModule(Request $request, string $slug): bool
    {
        return AncoraAuth::hasModule($request, $slug);
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

    private function section(
        string $key,
        string $label,
        string $subtitle,
        string $icon,
        string $emptySubtitle,
        Collection $items
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'subtitle' => $subtitle,
            'icon' => $icon,
            'empty_subtitle' => $emptySubtitle,
            'items' => $items,
        ];
    }

    private function resultItem(
        string $title,
        string $subtitle = '',
        string $meta = '',
        ?string $badge = null,
        ?string $url = null
    ): array {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'meta' => $meta,
            'badge' => $badge,
            'url' => $url,
        ];
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = Schema::hasTable($table);
        }

        return $this->tableExistsCache[$table];
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->squish()->toString();
    }
}
