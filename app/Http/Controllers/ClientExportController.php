<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Support\AncoraAuth;
use App\Support\SortableQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class ClientExportController extends Controller
{
    public function export(Request $request, string $scope, string $format): Response|BinaryFileResponse|StreamedResponse
    {
        $scope = Str::lower(trim($scope));
        $format = Str::lower(trim($format));

        $config = $this->scopeConfig($scope);
        abort_unless($config !== null, 404);
        abort_unless(in_array($format, ['csv', 'xls', 'pdf'], true), 404);
        $this->authorizeScopeExport($request, $config['permission']);

        $items = $this->buildQuery($scope, $request)->get();
        $dataset = $this->buildDataset($scope, $items);
        $timestamp = now()->format('Ymd_His');
        $baseFilename = $config['filename'] . '_' . $timestamp;
        $headers = $dataset['headers'];
        $rows = $dataset['rows'];

        return match ($format) {
            'csv' => $this->downloadCsv($baseFilename . '.csv', $headers, $rows),
            'xls' => response(
                view('pages.clientes.reports.xls', [
                    'title' => $config['title'],
                    'subtitle' => $config['subtitle'],
                    'headers' => $headers,
                    'rows' => $rows,
                    'summary' => $dataset['summary'],
                    'filtersApplied' => $this->filterSummary($scope, $request),
                    'generatedAt' => now(),
                ])->render(),
                200,
                [
                    'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $baseFilename . '.xls"',
                ]
            ),
            'pdf' => $this->downloadPdf($baseFilename . '.pdf', [
                'title' => $config['title'],
                'subtitle' => $config['subtitle'],
                'summary' => $dataset['summary'],
                'records' => $dataset['records'],
                'filtersApplied' => $this->filterSummary($scope, $request),
                'generatedAt' => now(),
                'pdfMode' => true,
            ]),
        };
    }

    private function scopeConfig(string $scope): ?array
    {
        return [
            'avulsos' => [
                'title' => 'Relatorio de clientes avulsos',
                'subtitle' => 'Cadastro completo de clientes fora da estrutura condominial.',
                'filename' => 'clientes_avulsos',
                'permission' => 'clientes.avulsos',
            ],
            'contatos' => [
                'title' => 'Relatorio de parceiros e fornecedores',
                'subtitle' => 'Sindicos, administradoras e imobiliarias/corretores.',
                'filename' => 'parceiros_fornecedores',
                'permission' => 'clientes.contatos',
            ],
            'condominos' => [
                'title' => 'Relatorio de condominos',
                'subtitle' => 'Proprietarios e locatarios vinculados as unidades.',
                'filename' => 'condominos',
                'permission' => 'clientes.condominos',
            ],
            'condominios' => [
                'title' => 'Relatorio de condominios',
                'subtitle' => 'Ficha cadastral da carteira condominial.',
                'filename' => 'condominios',
                'permission' => 'clientes.condominios',
            ],
            'unidades' => [
                'title' => 'Relatorio de unidades',
                'subtitle' => 'Vinculos entre condominio, bloco, unidade, proprietario e locatario.',
                'filename' => 'unidades',
                'permission' => 'clientes.unidades',
            ],
        ][$scope] ?? null;
    }

    private function authorizeScopeExport(Request $request, string $permission): void
    {
        $user = AncoraAuth::user($request);
        if ($user && $user->isSuperadmin()) {
            return;
        }

        $routePermissions = $request->session()->get('auth_user.route_permissions', []);
        abort_unless(in_array($permission, $routePermissions, true), 403, 'Você não possui permissão para exportar este relatório.');
    }

    private function buildQuery(string $scope, Request $request)
    {
        return match ($scope) {
            'avulsos' => $this->buildAvulsosQuery($request),
            'contatos' => $this->buildContatosQuery($request),
            'condominos' => $this->buildCondominosQuery($request),
            'condominios' => $this->buildCondominiosQuery($request),
            'unidades' => $this->buildUnidadesQuery($request),
            default => abort(404),
        };
    }

    private function buildAvulsosQuery(Request $request)
    {
        $query = ClientEntity::query()->where('profile_scope', 'avulso');

        if ($term = trim((string) $request->input('q'))) {
            $query->where(fn ($sub) => $sub
                ->where('display_name', 'like', "%{$term}%")
                ->orWhere('legal_name', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%"));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->input('is_active'));
        }

        if ($request->filled('role_tag')) {
            $query->where('role_tag', $request->input('role_tag'));
        }

        SortableQuery::apply($query, $request, [
            'name' => 'display_name',
            'role' => 'role_tag',
            'type' => 'entity_type',
            'document' => 'cpf_cnpj',
            'status' => 'is_active',
            'created_at' => 'created_at',
        ], 'name');

        return $query;
    }

    private function buildContatosQuery(Request $request)
    {
        $query = $this->partnerEntitiesQuery();

        if ($term = trim((string) $request->input('q'))) {
            $query->where(fn ($sub) => $sub
                ->where('display_name', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%"));
        }

        $roleFilter = $this->normalizeWhitespace($request->input('role_tag', ''));
        if ($roleFilter !== '' && $this->roleTagMatches($roleFilter, $this->partnerRoleKeywords())) {
            $query->where('role_tag', $roleFilter);
        }

        SortableQuery::apply($query, $request, [
            'name' => 'display_name',
            'role' => 'role_tag',
            'type' => 'entity_type',
            'document' => 'cpf_cnpj',
            'created_at' => 'created_at',
        ], 'name');

        return $query;
    }

    private function buildCondominosQuery(Request $request)
    {
        $query = $this->condominoEntitiesQuery()
            ->withCount(['ownedUnits', 'rentedUnits'])
            ->with([
                'ownedUnits.condominium',
                'ownedUnits.block',
                'rentedUnits.condominium',
                'rentedUnits.block',
                'unitPartyHistories.unit.condominium',
                'unitPartyHistories.unit.block',
                'unitPartyHistories.changedBy',
            ]);

        if ($term = trim((string) $request->input('q'))) {
            $query->where(fn ($sub) => $sub
                ->where('display_name', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%"));
        }

        if ($request->input('vinculo') === 'proprietario') {
            $query->where(function ($sub) {
                $sub->whereIn('id', ClientUnit::query()->select('owner_entity_id')->whereNotNull('owner_entity_id'))
                    ->orWhere(function ($roleQuery) {
                        $this->applyRoleKeywordFilter($roleQuery, ['proprietario']);
                    });
            });
        } elseif ($request->input('vinculo') === 'locatario') {
            $query->where(function ($sub) {
                $sub->whereIn('id', ClientUnit::query()->select('tenant_entity_id')->whereNotNull('tenant_entity_id'))
                    ->orWhere(function ($roleQuery) {
                        $this->applyRoleKeywordFilter($roleQuery, ['locatario', 'inquilino']);
                    });
            });
        }

        SortableQuery::apply($query, $request, [
            'name' => 'display_name',
            'role' => 'role_tag',
            'type' => 'entity_type',
            'document' => 'cpf_cnpj',
            'created_at' => 'created_at',
        ], 'name');

        return $query;
    }

    private function buildCondominiosQuery(Request $request)
    {
        $query = ClientCondominium::query()
            ->select('client_condominiums.*')
            ->leftJoin('client_types as condominium_type_sort', 'condominium_type_sort.id', '=', 'client_condominiums.condominium_type_id')
            ->leftJoin('client_entities as condominium_syndic_sort', 'condominium_syndic_sort.id', '=', 'client_condominiums.syndico_entity_id')
            ->with(['type', 'syndic', 'administradora', 'blocks'])
            ->withCount('units');

        if ($term = trim((string) $request->input('q'))) {
            $query->where('client_condominiums.name', 'like', "%{$term}%");
        }

        SortableQuery::apply($query, $request, [
            'name' => 'client_condominiums.name',
            'type' => 'condominium_type_sort.name',
            'syndic' => 'condominium_syndic_sort.display_name',
            'blocks' => 'client_condominiums.has_blocks',
            'created_at' => 'client_condominiums.created_at',
        ], 'name');

        return $query;
    }

    private function buildUnidadesQuery(Request $request)
    {
        $query = ClientUnit::query()
            ->select('client_units.*')
            ->leftJoin('client_condominiums as unit_condominium_sort', 'unit_condominium_sort.id', '=', 'client_units.condominium_id')
            ->leftJoin('client_condominium_blocks as unit_block_sort', 'unit_block_sort.id', '=', 'client_units.block_id')
            ->leftJoin('client_entities as unit_owner_sort', 'unit_owner_sort.id', '=', 'client_units.owner_entity_id')
            ->leftJoin('client_entities as unit_tenant_sort', 'unit_tenant_sort.id', '=', 'client_units.tenant_entity_id')
            ->with([
                'condominium',
                'block',
                'type',
                'owner',
                'tenant',
                'partyHistories.entity',
                'partyHistories.changedBy',
            ]);

        if ($term = trim((string) $request->input('q'))) {
            $query->where('client_units.unit_number', 'like', "%{$term}%");
        }

        if ($request->filled('condominium_id')) {
            $query->where('client_units.condominium_id', (int) $request->input('condominium_id'));
        }

        SortableQuery::apply($query, $request, [
            'condominium' => 'unit_condominium_sort.name',
            'block' => 'unit_block_sort.name',
            'unit' => function ($query, string $direction) {
                $query->orderByRaw("CASE WHEN TRIM(client_units.unit_number) REGEXP '^[0-9]+$' THEN 0 ELSE 1 END ASC");
                $query->orderByRaw("CASE WHEN TRIM(client_units.unit_number) REGEXP '^[0-9]+$' THEN CAST(TRIM(client_units.unit_number) AS UNSIGNED) END {$direction}");
                $query->orderBy('client_units.unit_number', $direction);
            },
            'owner' => 'unit_owner_sort.display_name',
            'tenant' => 'unit_tenant_sort.display_name',
            'created_at' => 'client_units.created_at',
        ], 'unit', 'desc');

        return $query;
    }

    private function buildDataset(string $scope, Collection $items): array
    {
        return match ($scope) {
            'avulsos' => $this->buildEntityDataset($items, 'avulsos'),
            'contatos' => $this->buildEntityDataset($items, 'contatos'),
            'condominos' => $this->buildEntityDataset($items, 'condominos'),
            'condominios' => $this->buildCondominiosDataset($items),
            'unidades' => $this->buildUnidadesDataset($items),
            default => abort(404),
        };
    }

    private function buildEntityDataset(Collection $items, string $scope): array
    {
        $headers = [
            'Nome principal',
            'Nome juridico',
            'Tipo',
            'Perfil / papel',
            'Documento',
            'RG / IE',
            'Genero',
            'Nacionalidade',
            'Data de nascimento',
            'Profissao',
            'Estado civil',
            'Representante legal',
            'Telefones',
            'E-mails',
            'E-mails de cobranca',
            'Endereco principal',
            'Endereco de cobranca',
            'Socios / acionistas',
            'Observacoes',
            'Descricao',
            'Status',
            'Motivo da inativacao',
            'Fim do contrato',
            'Unidades vinculadas',
            'Historico de vinculacao',
            'Criado em',
            'Atualizado em',
        ];

        $rows = [];
        $records = [];

        foreach ($items as $item) {
            $currentUnits = $scope === 'condominos' ? $this->entityCurrentUnitLines($item) : [];
            $historyLines = $scope === 'condominos' ? $this->entityHistoryLines($item) : [];

            $rows[] = [
                'Nome principal' => $item->display_name ?: '-',
                'Nome juridico' => $item->legal_name ?: '-',
                'Tipo' => strtoupper((string) $item->entity_type),
                'Perfil / papel' => $item->role_tag ?: '-',
                'Documento' => $item->cpf_cnpj ?: '-',
                'RG / IE' => $item->rg_ie ?: '-',
                'Genero' => $item->gender ?: '-',
                'Nacionalidade' => $item->nationality ?: '-',
                'Data de nascimento' => $this->dateValue($item->birth_date),
                'Profissao' => $item->profession ?: '-',
                'Estado civil' => $item->marital_status ?: '-',
                'Representante legal' => $item->legal_representative ?: '-',
                'Telefones' => $this->phonesText($item->phones_json),
                'E-mails' => $this->emailsText($item->emails_json),
                'E-mails de cobranca' => $scope === 'contatos' ? $this->emailsText($item->cobranca_emails_json) : '-',
                'Endereco principal' => $this->addressText($item->primary_address_json),
                'Endereco de cobranca' => $this->addressText($item->billing_address_json),
                'Socios / acionistas' => $this->shareholdersText($item->shareholders_json),
                'Observacoes' => $item->notes ?: '-',
                'Descricao' => $item->description ?: '-',
                'Status' => $item->is_active ? 'Ativo' : 'Inativo',
                'Motivo da inativacao' => $item->inactive_reason ?: '-',
                'Fim do contrato' => $this->dateValue($item->contract_end_date),
                'Unidades vinculadas' => $currentUnits !== [] ? implode("\n", $currentUnits) : '-',
                'Historico de vinculacao' => $historyLines !== [] ? implode("\n", $historyLines) : '-',
                'Criado em' => $this->dateTimeValue($item->created_at),
                'Atualizado em' => $this->dateTimeValue($item->updated_at),
            ];

            $badges = [strtoupper((string) $item->entity_type)];
            if ($item->role_tag) {
                $badges[] = $item->role_tag;
            }
            $badges[] = $item->is_active ? 'Ativo' : 'Inativo';

            $sections = [
                [
                    'title' => 'Identificacao',
                    'fields' => [
                        ['label' => 'Nome juridico', 'value' => $item->legal_name ?: '-'],
                        ['label' => 'Documento', 'value' => $item->cpf_cnpj ?: '-'],
                        ['label' => 'RG / IE', 'value' => $item->rg_ie ?: '-'],
                        ['label' => 'Representante legal', 'value' => $item->legal_representative ?: '-'],
                    ],
                ],
                [
                    'title' => 'Contatos',
                    'fields' => [
                        ['label' => 'Telefones', 'value' => $this->phonesText($item->phones_json)],
                        ['label' => 'E-mails', 'value' => $this->emailsText($item->emails_json)],
                        ['label' => 'E-mails de cobranca', 'value' => $scope === 'contatos' ? $this->emailsText($item->cobranca_emails_json) : '-'],
                    ],
                ],
                [
                    'title' => 'Enderecos',
                    'fields' => [
                        ['label' => 'Principal', 'value' => $this->addressText($item->primary_address_json)],
                        ['label' => 'Cobranca', 'value' => $this->addressText($item->billing_address_json)],
                    ],
                ],
                [
                    'title' => 'Complementos',
                    'fields' => [
                        ['label' => 'Genero', 'value' => $item->gender ?: '-'],
                        ['label' => 'Nacionalidade', 'value' => $item->nationality ?: '-'],
                        ['label' => 'Nascimento', 'value' => $this->dateValue($item->birth_date)],
                        ['label' => 'Profissao', 'value' => $item->profession ?: '-'],
                        ['label' => 'Estado civil', 'value' => $item->marital_status ?: '-'],
                    ],
                ],
                [
                    'title' => 'Anotacoes',
                    'fields' => [
                        ['label' => 'Observacoes', 'value' => $item->notes ?: '-'],
                        ['label' => 'Descricao', 'value' => $item->description ?: '-'],
                        ['label' => 'Socios / acionistas', 'value' => $this->shareholdersText($item->shareholders_json)],
                    ],
                ],
                [
                    'title' => 'Status',
                    'fields' => [
                        ['label' => 'Situacao', 'value' => $item->is_active ? 'Ativo' : 'Inativo'],
                        ['label' => 'Motivo da inativacao', 'value' => $item->inactive_reason ?: '-'],
                        ['label' => 'Fim do contrato', 'value' => $this->dateValue($item->contract_end_date)],
                    ],
                ],
            ];

            if ($scope === 'condominos') {
                $sections[] = [
                    'title' => 'Vinculos atuais',
                    'lines' => $currentUnits,
                ];
                $sections[] = [
                    'title' => 'Historico de vinculacao',
                    'lines' => $historyLines,
                ];
            }

            $records[] = [
                'title' => $item->display_name ?: 'Registro sem nome',
                'subtitle' => $scope === 'condominos'
                    ? sprintf('%s como proprietario e %s como locatario', (int) ($item->owned_units_count ?? 0), (int) ($item->rented_units_count ?? 0))
                    : ($item->role_tag ?: 'Cadastro sem papel definido'),
                'badges' => $badges,
                'sections' => $sections,
                'footer' => 'Criado em ' . $this->dateTimeValue($item->created_at) . ' · Atualizado em ' . $this->dateTimeValue($item->updated_at),
            ];
        }

        $summary = [
            ['label' => 'Registros', 'value' => (string) $items->count()],
            ['label' => 'Ativos', 'value' => (string) $items->where('is_active', true)->count()],
            ['label' => 'PF', 'value' => (string) $items->where('entity_type', 'pf')->count()],
            ['label' => 'PJ', 'value' => (string) $items->where('entity_type', 'pj')->count()],
        ];

        return [
            'headers' => $headers,
            'rows' => $rows,
            'records' => $records,
            'summary' => $summary,
        ];
    }

    private function buildCondominiosDataset(Collection $items): array
    {
        $headers = [
            'Condominio',
            'Tipo',
            'CNPJ',
            'Sindico',
            'Administradora',
            'Status',
            'Possui blocos',
            'Blocos',
            'Total de unidades',
            'Endereco',
            'Valor do boleto',
            'Cancelamento do boleto',
            'Dados bancarios',
            'Caracteristicas',
            'Motivo da inativacao',
            'Fim do contrato',
            'Criado em',
            'Atualizado em',
        ];

        $rows = [];
        $records = [];

        foreach ($items as $item) {
            $blockLines = $item->blocks->pluck('name')->filter()->values()->all();
            $rows[] = [
                'Condominio' => $item->name ?: '-',
                'Tipo' => $item->type?->name ?: '-',
                'CNPJ' => $item->cnpj ?: '-',
                'Sindico' => $item->syndic?->display_name ?: '-',
                'Administradora' => $item->administradora?->display_name ?: '-',
                'Status' => $item->is_active ? 'Ativo' : 'Inativo',
                'Possui blocos' => $item->has_blocks ? 'Sim' : 'Nao',
                'Blocos' => $blockLines !== [] ? implode("\n", $blockLines) : '-',
                'Total de unidades' => (string) ($item->units_count ?? 0),
                'Endereco' => $this->addressText($item->address_json),
                'Valor do boleto' => $this->money($item->boleto_fee_amount),
                'Cancelamento do boleto' => $this->money($item->boleto_cancellation_fee_amount),
                'Dados bancarios' => $item->bank_details ?: '-',
                'Caracteristicas' => $item->characteristics ?: '-',
                'Motivo da inativacao' => $item->inactive_reason ?: '-',
                'Fim do contrato' => $this->dateValue($item->contract_end_date),
                'Criado em' => $this->dateTimeValue($item->created_at),
                'Atualizado em' => $this->dateTimeValue($item->updated_at),
            ];

            $records[] = [
                'title' => $item->name ?: 'Condominio sem nome',
                'subtitle' => ($item->type?->name ?: 'Tipo nao definido') . ' · ' . (($item->units_count ?? 0)) . ' unidade(s)',
                'badges' => [
                    $item->is_active ? 'Ativo' : 'Inativo',
                    $item->has_blocks ? 'Com blocos' : 'Sem blocos',
                ],
                'sections' => [
                    [
                        'title' => 'Cadastro',
                        'fields' => [
                            ['label' => 'Tipo', 'value' => $item->type?->name ?: '-'],
                            ['label' => 'CNPJ', 'value' => $item->cnpj ?: '-'],
                            ['label' => 'Sindico', 'value' => $item->syndic?->display_name ?: '-'],
                            ['label' => 'Administradora', 'value' => $item->administradora?->display_name ?: '-'],
                            ['label' => 'Endereco', 'value' => $this->addressText($item->address_json)],
                        ],
                    ],
                    [
                        'title' => 'Financeiro e operacao',
                        'fields' => [
                            ['label' => 'Valor do boleto', 'value' => $this->money($item->boleto_fee_amount)],
                            ['label' => 'Cancelamento do boleto', 'value' => $this->money($item->boleto_cancellation_fee_amount)],
                            ['label' => 'Dados bancarios', 'value' => $item->bank_details ?: '-'],
                            ['label' => 'Caracteristicas', 'value' => $item->characteristics ?: '-'],
                            ['label' => 'Status', 'value' => $item->is_active ? 'Ativo' : 'Inativo'],
                            ['label' => 'Motivo da inativacao', 'value' => $item->inactive_reason ?: '-'],
                            ['label' => 'Fim do contrato', 'value' => $this->dateValue($item->contract_end_date)],
                        ],
                    ],
                    [
                        'title' => 'Blocos / torres',
                        'lines' => $blockLines,
                    ],
                ],
                'footer' => 'Criado em ' . $this->dateTimeValue($item->created_at) . ' · Atualizado em ' . $this->dateTimeValue($item->updated_at),
            ];
        }

        $summary = [
            ['label' => 'Condominios', 'value' => (string) $items->count()],
            ['label' => 'Ativos', 'value' => (string) $items->where('is_active', true)->count()],
            ['label' => 'Com blocos', 'value' => (string) $items->where('has_blocks', true)->count()],
            ['label' => 'Unidades', 'value' => (string) $items->sum('units_count')],
        ];

        return [
            'headers' => $headers,
            'rows' => $rows,
            'records' => $records,
            'summary' => $summary,
        ];
    }

    private function buildUnidadesDataset(Collection $items): array
    {
        $headers = [
            'Condominio',
            'Bloco',
            'Unidade',
            'Tipo',
            'Proprietario',
            'Documento do proprietario',
            'Telefones do proprietario',
            'E-mails do proprietario',
            'Endereco do proprietario',
            'Observacoes do proprietario',
            'Locatario',
            'Documento do locatario',
            'Telefones do locatario',
            'E-mails do locatario',
            'Endereco do locatario',
            'Observacoes do locatario',
            'Historico de ocupacao',
            'Criado em',
            'Atualizado em',
        ];

        $rows = [];
        $records = [];

        foreach ($items as $item) {
            $historyLines = $this->unitHistoryLines($item);

            $rows[] = [
                'Condominio' => $item->condominium?->name ?: '-',
                'Bloco' => $item->block?->name ?: '-',
                'Unidade' => $item->unit_number ?: '-',
                'Tipo' => $item->type?->name ?: '-',
                'Proprietario' => $item->owner?->display_name ?: '-',
                'Documento do proprietario' => $item->owner?->cpf_cnpj ?: '-',
                'Telefones do proprietario' => $this->phonesText($item->owner?->phones_json),
                'E-mails do proprietario' => $this->emailsText($item->owner?->emails_json),
                'Endereco do proprietario' => $this->addressText($item->owner?->primary_address_json),
                'Observacoes do proprietario' => $item->owner_notes ?: '-',
                'Locatario' => $item->tenant?->display_name ?: '-',
                'Documento do locatario' => $item->tenant?->cpf_cnpj ?: '-',
                'Telefones do locatario' => $this->phonesText($item->tenant?->phones_json),
                'E-mails do locatario' => $this->emailsText($item->tenant?->emails_json),
                'Endereco do locatario' => $this->addressText($item->tenant?->primary_address_json),
                'Observacoes do locatario' => $item->tenant_notes ?: '-',
                'Historico de ocupacao' => $historyLines !== [] ? implode("\n", $historyLines) : '-',
                'Criado em' => $this->dateTimeValue($item->created_at),
                'Atualizado em' => $this->dateTimeValue($item->updated_at),
            ];

            $records[] = [
                'title' => trim(($item->condominium?->name ?: 'Condominio') . ' · Unidade ' . ($item->unit_number ?: '-')),
                'subtitle' => $item->block?->name
                    ? ('Bloco ' . $item->block->name . ' · ' . ($item->type?->name ?: 'Tipo nao informado'))
                    : ($item->type?->name ?: 'Tipo nao informado'),
                'badges' => [
                    $item->tenant ? 'Com locatario' : 'Sem locatario',
                ],
                'sections' => [
                    [
                        'title' => 'Dados da unidade',
                        'fields' => [
                            ['label' => 'Condominio', 'value' => $item->condominium?->name ?: '-'],
                            ['label' => 'Bloco', 'value' => $item->block?->name ?: '-'],
                            ['label' => 'Tipo', 'value' => $item->type?->name ?: '-'],
                            ['label' => 'Numero', 'value' => $item->unit_number ?: '-'],
                        ],
                    ],
                    [
                        'title' => 'Proprietario',
                        'fields' => [
                            ['label' => 'Nome', 'value' => $item->owner?->display_name ?: '-'],
                            ['label' => 'Documento', 'value' => $item->owner?->cpf_cnpj ?: '-'],
                            ['label' => 'Telefones', 'value' => $this->phonesText($item->owner?->phones_json)],
                            ['label' => 'E-mails', 'value' => $this->emailsText($item->owner?->emails_json)],
                            ['label' => 'Endereco', 'value' => $this->addressText($item->owner?->primary_address_json)],
                            ['label' => 'Observacoes', 'value' => $item->owner_notes ?: '-'],
                        ],
                    ],
                    [
                        'title' => 'Locatario',
                        'fields' => [
                            ['label' => 'Nome', 'value' => $item->tenant?->display_name ?: '-'],
                            ['label' => 'Documento', 'value' => $item->tenant?->cpf_cnpj ?: '-'],
                            ['label' => 'Telefones', 'value' => $this->phonesText($item->tenant?->phones_json)],
                            ['label' => 'E-mails', 'value' => $this->emailsText($item->tenant?->emails_json)],
                            ['label' => 'Endereco', 'value' => $this->addressText($item->tenant?->primary_address_json)],
                            ['label' => 'Observacoes', 'value' => $item->tenant_notes ?: '-'],
                        ],
                    ],
                    [
                        'title' => 'Historico de ocupacao',
                        'lines' => $historyLines,
                    ],
                ],
                'footer' => 'Criado em ' . $this->dateTimeValue($item->created_at) . ' · Atualizado em ' . $this->dateTimeValue($item->updated_at),
            ];
        }

        $summary = [
            ['label' => 'Unidades', 'value' => (string) $items->count()],
            ['label' => 'Condominios', 'value' => (string) $items->pluck('condominium_id')->filter()->unique()->count()],
            ['label' => 'Com locatario', 'value' => (string) $items->filter(fn ($item) => $item->tenant_entity_id !== null)->count()],
            ['label' => 'Sem bloco', 'value' => (string) $items->filter(fn ($item) => $item->block_id === null)->count()],
        ];

        return [
            'headers' => $headers,
            'rows' => $rows,
            'records' => $records,
            'summary' => $summary,
        ];
    }

    private function filterSummary(string $scope, Request $request): array
    {
        $parts = [];

        if ($term = trim((string) $request->input('q'))) {
            $parts[] = 'Busca: ' . $term;
        }

        if ($scope === 'avulsos') {
            if (($entityType = (string) $request->input('entity_type', '')) !== '') {
                $parts[] = 'Tipo: ' . strtoupper($entityType);
            }
            if (($roleTag = trim((string) $request->input('role_tag', ''))) !== '') {
                $parts[] = 'Perfil: ' . $roleTag;
            }
            if (($isActive = (string) $request->input('is_active', '')) !== '') {
                $parts[] = 'Status: ' . ($isActive === '1' ? 'Ativos' : 'Inativos');
            }
        }

        if ($scope === 'contatos' && ($roleTag = trim((string) $request->input('role_tag', ''))) !== '') {
            $parts[] = 'Papel: ' . $roleTag;
        }

        if ($scope === 'condominos' && ($vinculo = trim((string) $request->input('vinculo', ''))) !== '') {
            $parts[] = 'Vinculo: ' . ($vinculo === 'proprietario' ? 'Proprietarios' : 'Locatarios');
        }

        if ($scope === 'unidades' && $request->filled('condominium_id')) {
            $condominium = ClientCondominium::query()->find((int) $request->input('condominium_id'));
            if ($condominium) {
                $parts[] = 'Condominio: ' . $condominium->name;
            }
        }

        return $parts;
    }

    private function downloadCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($output, array_map(fn ($header) => $this->csvValue($row[$header] ?? ''), $headers), ';');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function downloadPdf(string $filename, array $payload): Response|BinaryFileResponse
    {
        $dir = storage_path('app/tmp/client-reports');
        File::ensureDirectoryExists($dir);

        $baseName = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '-' . Str::random(6);
        $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
        $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

        try {
            File::put($htmlPath, view('pages.clientes.reports.pdf', $payload)->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);

                return response(
                    view('pages.clientes.reports.pdf', array_merge($payload, ['pdfMode' => false]))->render(),
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8']
                );
            }

            return response()->download($pdfPath, $filename, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
        } catch (\Throwable) {
            File::delete($htmlPath);
            File::delete($pdfPath);

            return response(
                view('pages.clientes.reports.pdf', array_merge($payload, ['pdfMode' => false]))->render(),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }
    }

    private function renderPdfWithChromium(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable([
            'chromium',
            'chromium-browser',
            'google-chrome',
            'google-chrome-stable',
        ]);

        if (!$binary) {
            return false;
        }

        $profileDir = dirname($pdfPath) . DIRECTORY_SEPARATOR . pathinfo($pdfPath, PATHINFO_FILENAME) . '-chrome-profile';
        File::ensureDirectoryExists($profileDir);

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-extensions',
                '--no-first-run',
                '--no-default-browser-check',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--print-to-pdf-no-header',
                '--user-data-dir=' . $profileDir,
                '--print-to-pdf=' . $pdfPath,
                'file://' . str_replace('\\', '/', $htmlPath),
            ], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        } finally {
            File::deleteDirectory($profileDir);
        }
    }

    private function renderPdfWithWkhtmltopdf(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable(['wkhtmltopdf']);
        if (!$binary) {
            return false;
        }

        try {
            $process = new Process([
                $binary,
                '--enable-local-file-access',
                '--encoding',
                'UTF-8',
                '--page-size',
                'A4',
                '--margin-top',
                '10',
                '--margin-right',
                '10',
                '--margin-bottom',
                '10',
                '--margin-left',
                '10',
                $htmlPath,
                $pdfPath,
            ], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        }
    }

    private function availableExecutable(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            try {
                $process = new Process([$candidate, '--version'], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // Some utilities do not expose --version consistently; fall back to PATH lookup below.
            }

            try {
                $process = new Process(['sh', '-lc', 'command -v ' . escapeshellarg($candidate)], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function partnerRoleKeywords(): array
    {
        return ['sindico', 'sindica', 'administradora', 'imobiliaria', 'corretor', 'corretora'];
    }

    private function condominoRoleKeywords(): array
    {
        return ['proprietario', 'locatario', 'inquilino'];
    }

    private function roleSearchTerms(array $keywords): array
    {
        $accented = [
            'sindico' => ['sindico', 'síndico'],
            'sindica' => ['sindica', 'síndica'],
            'imobiliaria' => ['imobiliaria', 'imobiliária'],
            'proprietario' => ['proprietario', 'proprietário'],
            'locatario' => ['locatario', 'locatário'],
        ];

        $terms = [];
        foreach ($keywords as $keyword) {
            $terms[] = $keyword;
            foreach ($accented[$keyword] ?? [] as $variant) {
                $terms[] = $variant;
            }
        }

        return array_values(array_unique($terms));
    }

    private function normalizedRoleText(?string $value): string
    {
        return Str::of(Str::ascii((string) $value))->lower()->squish()->toString();
    }

    private function roleTagMatches(?string $roleTag, array $keywords): bool
    {
        $roleTag = $this->normalizedRoleText($roleTag);
        if ($roleTag === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (str_contains($roleTag, $this->normalizedRoleText($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function applyRoleKeywordFilter($query, array $keywords): void
    {
        $terms = $this->roleSearchTerms($keywords);

        $query->where(function ($roleQuery) use ($terms) {
            foreach ($terms as $term) {
                $roleQuery->orWhere('role_tag', 'like', '%' . $term . '%');
            }
        });
    }

    private function partnerEntitiesQuery()
    {
        return ClientEntity::query()
            ->where('profile_scope', 'contato')
            ->where(function ($query) {
                $this->applyRoleKeywordFilter($query, $this->partnerRoleKeywords());
            })
            ->whereNotIn('id', ClientUnit::query()->select('owner_entity_id')->whereNotNull('owner_entity_id'))
            ->whereNotIn('id', ClientUnit::query()->select('tenant_entity_id')->whereNotNull('tenant_entity_id'));
    }

    private function condominoEntitiesQuery()
    {
        return ClientEntity::query()
            ->where('profile_scope', 'contato')
            ->where(function ($query) {
                $query->whereIn('id', ClientUnit::query()->select('owner_entity_id')->whereNotNull('owner_entity_id'))
                    ->orWhereIn('id', ClientUnit::query()->select('tenant_entity_id')->whereNotNull('tenant_entity_id'))
                    ->orWhere(function ($roleQuery) {
                        $this->applyRoleKeywordFilter($roleQuery, $this->condominoRoleKeywords());
                    });
            });
    }

    private function normalizeWhitespace(?string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
    }

    private function phonesText(?array $rows): string
    {
        $values = collect($rows ?? [])->pluck('number')->filter()->values()->all();

        return $values !== [] ? implode("\n", $values) : '-';
    }

    private function emailsText(?array $rows): string
    {
        $values = collect($rows ?? [])->pluck('email')->filter()->values()->all();

        return $values !== [] ? implode("\n", $values) : '-';
    }

    private function shareholdersText(?array $rows): string
    {
        $values = collect($rows ?? [])
            ->map(function ($row) {
                $parts = array_filter([
                    trim((string) ($row['name'] ?? '')),
                    trim((string) ($row['document'] ?? '')),
                    trim((string) ($row['role'] ?? '')),
                ]);

                return $parts !== [] ? implode(' - ', $parts) : null;
            })
            ->filter()
            ->values()
            ->all();

        return $values !== [] ? implode("\n", $values) : '-';
    }

    private function addressText(?array $address): string
    {
        $address = $address ?? [];
        $parts = array_filter([
            $address['street'] ?? null,
            $address['number'] ?? null,
            $address['complement'] ?? null,
            $address['neighborhood'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['zip'] ?? null,
        ], fn ($value) => trim((string) $value) !== '');

        $text = $parts !== [] ? implode(', ', $parts) : '-';
        $notes = trim((string) ($address['notes'] ?? ''));

        if ($notes !== '') {
            $text .= "\nObs.: " . $notes;
        }

        return $text;
    }

    private function entityCurrentUnitLines(ClientEntity $entity): array
    {
        $owned = collect($entity->ownedUnits ?? [])
            ->map(fn ($unit) => 'Proprietario · ' . $this->unitReference($unit));
        $rented = collect($entity->rentedUnits ?? [])
            ->map(fn ($unit) => 'Locatario · ' . $this->unitReference($unit));

        return $owned->concat($rented)->filter()->values()->all();
    }

    private function entityHistoryLines(ClientEntity $entity): array
    {
        return collect($entity->unitPartyHistories ?? [])
            ->sortByDesc(fn ($history) => optional($history->started_at)?->getTimestamp() ?? 0)
            ->map(function ($history) {
                $type = $history->party_type === 'owner' ? 'Proprietario' : 'Locatario';
                $period = trim($this->dateTimeValue($history->started_at) . ' ate ' . ($history->ended_at ? $this->dateTimeValue($history->ended_at) : 'Atual'));
                $unit = $history->unit ? $this->unitReference($history->unit) : 'Unidade nao localizada';

                return $type . ' · ' . $unit . ' · ' . $period;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function unitHistoryLines(ClientUnit $unit): array
    {
        return collect($unit->partyHistories ?? [])
            ->sortByDesc(fn ($history) => optional($history->started_at)?->getTimestamp() ?? 0)
            ->map(function ($history) {
                $type = $history->party_type === 'owner' ? 'Proprietario' : 'Locatario';
                $name = $history->display_name_snapshot ?: 'Cadastro nao identificado';
                $period = $this->dateTimeValue($history->started_at) . ' ate ' . ($history->ended_at ? $this->dateTimeValue($history->ended_at) : 'Atual');

                return $type . ' · ' . $name . ' · ' . $period;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function unitReference($unit): string
    {
        $parts = array_filter([
            $unit->condominium?->name ?: null,
            $unit->block?->name ?: null,
            $unit->unit_number ? 'Unidade ' . $unit->unit_number : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Unidade nao informada';
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace("\n", ' | ', $value);
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private function dateValue(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y') : (string) $value;
    }

    private function dateTimeValue(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y H:i') : (string) $value;
    }
}
