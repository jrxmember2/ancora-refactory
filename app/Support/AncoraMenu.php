<?php

namespace App\Support;

use App\Models\SystemModule;
use App\Models\User;

class AncoraMenu
{
    public static function sidebar(?User $user): array
    {
        $available = collect();

        if ($user) {
            $available = $user->isSuperadmin()
                ? SystemModule::query()->enabled()->get()->keyBy('slug')
                : $user->modules()->where('is_enabled', 1)->orderBy('sort_order')->get()->keyBy('slug');
        }

        $has = static fn (string $slug): bool => $available->has($slug);
        $routePermissions = $user?->isSuperadmin() ? [] : ($user?->accessibleRouteNames() ?? []);
        $canRoute = static fn (string $routeName): bool => $user && ($user->isSuperadmin() || in_array($routeName, $routePermissions, true));

        $groups = [
            [
                'title' => 'Principal',
                // Hub fixo no topo; demais itens em ordem alfabetica (submenus tambem).
                'items' => array_values(array_filter([
                    ['label' => 'Hub', 'path' => route('hub'), 'icon' => 'fa-solid fa-house'],
                    $has('agenda') ? [
                        'label' => 'Agenda',
                        'icon' => 'fa-solid fa-calendar-days',
                        'subItems' => [
                            ['label' => 'Calendario', 'path' => route('agenda.calendar')],
                            ['label' => 'Lista', 'path' => route('agenda.index')],
                            ['label' => 'Novo compromisso', 'path' => route('agenda.create')],
                        ],
                    ] : null,
                    $has('assinador') ? [
                        'label' => 'Assinador Eletronico',
                        'icon' => 'fa-solid fa-signature',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('assinador.dashboard')],
                            ['label' => 'Documentos', 'path' => route('assinador.index')],
                            ['label' => 'Nova assinatura', 'path' => route('assinador.create')],
                        ],
                    ] : null,
                    $has('clientes') ? [
                        'label' => 'Clientes',
                        'icon' => 'fa-solid fa-users',
                        'subItems' => [
                            ['label' => 'Avulsos', 'path' => route('clientes.avulsos')],
                            ['label' => 'Condominios', 'path' => route('clientes.condominios')],
                            ['label' => 'Condominos', 'path' => route('clientes.condominos')],
                            ['label' => 'Parceiros / fornecedores', 'path' => route('clientes.contatos')],
                            ['label' => 'Portal do Cliente', 'path' => route('clientes.portal-users.index')],
                            ['label' => 'Unidades', 'path' => route('clientes.unidades')],
                            ['label' => 'Visao geral', 'path' => route('clientes.index')],
                        ],
                    ] : null,
                    $has('cobrancas') ? [
                        'label' => 'Cobranca',
                        'icon' => 'fa-solid fa-money-bill-wave',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('cobrancas.dashboard')],
                            ['label' => 'Faturamento', 'path' => route('cobrancas.billing.report')],
                            ['label' => 'Importar inadimplencia', 'path' => route('cobrancas.import.index')],
                            ['label' => 'Lista de OS', 'path' => route('cobrancas.index')],
                            ['label' => 'Nova OS', 'path' => route('cobrancas.create')],
                            ['label' => 'TJES avulso', 'path' => route('cobrancas.monetary.standalone.index')],
                        ],
                    ] : null,
                    $has('contratos') ? [
                        'label' => 'Contratos',
                        'icon' => 'fa-solid fa-file-contract',
                        'subItems' => [
                            ['label' => 'Categorias', 'path' => route('contratos.categories.index')],
                            ['label' => 'Configuracoes', 'path' => route('contratos.settings.index')],
                            ['label' => 'Contratos', 'path' => route('contratos.index')],
                            ['label' => 'Dashboard', 'path' => route('contratos.dashboard')],
                            ['label' => 'Novo contrato', 'path' => route('contratos.create')],
                            ['label' => 'Relatorios', 'path' => route('contratos.reports.index')],
                            ['label' => 'Templates', 'path' => route('contratos.templates.index')],
                            ['label' => 'Variaveis', 'path' => route('contratos.variables.index')],
                        ],
                    ] : null,
                    $has('demandas') ? [
                        'label' => 'Demandas',
                        'icon' => 'fa-solid fa-inbox',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('demandas.dashboard')],
                            ['label' => 'Kanban', 'path' => route('demandas.kanban')],
                            ['label' => 'Lista', 'path' => route('demandas.index')],
                        ],
                    ] : null,
                    $has('financeiro') ? [
                        'label' => 'Financeiro 360',
                        'icon' => 'fa-solid fa-chart-pie',
                        'subItems' => [
                            ['label' => 'Bancos e Contas', 'path' => route('financeiro.accounts.index')],
                            ['label' => 'Categorias Financeiras', 'path' => route('financeiro.categories.index')],
                            ['label' => 'Centros de Custo', 'path' => route('financeiro.cost-centers.index')],
                            ['label' => 'Cobrancas', 'path' => route('financeiro.collection.index')],
                            ['label' => 'Conciliacao Bancaria', 'path' => route('financeiro.reconciliation.index')],
                            ['label' => 'Configuracoes', 'path' => route('financeiro.settings.index')],
                            ['label' => 'Contas a Pagar', 'path' => route('financeiro.payables.index')],
                            ['label' => 'Contas a Receber', 'path' => route('financeiro.receivables.index')],
                            ['label' => 'Custas Processuais', 'path' => route('financeiro.process-costs.index')],
                            ['label' => 'Dashboard', 'path' => route('financeiro.dashboard')],
                            ['label' => 'DRE', 'path' => route('financeiro.dre.index')],
                            ['label' => 'Faturamento', 'path' => route('financeiro.billing.index')],
                            ['label' => 'Fluxo de Caixa', 'path' => route('financeiro.cash-flow.index')],
                            ['label' => 'Inadimplencia', 'path' => route('financeiro.delinquency.index')],
                            ['label' => 'Parcelamentos', 'path' => route('financeiro.installments.index')],
                            ['label' => 'Prestacao de Contas', 'path' => route('financeiro.accountability.index')],
                            ['label' => 'Reembolsos', 'path' => route('financeiro.reimbursements.index')],
                            ['label' => 'Relatorios', 'path' => route('financeiro.reports.index')],
                        ],
                    ] : null,
                    $has('processos') ? [
                        'label' => 'Processos',
                        'icon' => 'fa-solid fa-scale-balanced',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('processos.dashboard')],
                            ['label' => 'Importacao', 'path' => route('processos.import.index')],
                            ['label' => 'Lista', 'path' => route('processos.index')],
                            ['label' => 'Novo processo', 'path' => route('processos.create')],
                        ],
                    ] : null,
                    $has('propostas') ? [
                        'label' => 'Propostas',
                        'icon' => 'fa-solid fa-file-signature',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('propostas.dashboard')],
                            ['label' => 'Lista', 'path' => route('propostas.index')],
                            ['label' => 'Nova proposta', 'path' => route('propostas.create')],
                        ],
                    ] : null,
                ])),
            ],
            [
                'title' => 'Administracao',
                // Itens em ordem alfabetica. Versionamento foi para o menu do usuario (Novidades)
                // e Logs virou aba dentro de Configuracoes.
                'items' => array_values(array_filter([
                    $has('busca') ? ['label' => 'Busca', 'path' => route('busca'), 'icon' => 'fa-solid fa-magnifying-glass'] : null,
                    $has('config') ? ['label' => 'Configuracoes', 'path' => route('config.index'), 'icon' => 'fa-solid fa-gear'] : null,
                    $canRoute('ia.office-chat.index') ? ['label' => 'Leme Escritorio', 'path' => route('ia.office-chat.index'), 'icon' => 'fa-solid fa-comments'] : null,
                ])),
            ],
        ];

        return array_values(array_filter($groups, fn ($group) => !empty($group['items'])));
    }

    public static function hubTiles(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $modules = $user->isSuperadmin()
            ? SystemModule::query()->get()
            : $user->modules()->get();

        // Cards do hub em ordem alfabetica pelo nome (case-insensitive).
        $modules = $modules->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $modules->map(function (SystemModule $module) {
            $descriptions = [
                'dashboard' => 'Painel executivo consolidado do ecossistema Ancora.',
                'propostas' => 'Fluxo completo comercial com proposta premium, anexos e follow-up.',
                'busca' => 'Busca ampliada entre usuarios, clientes, condominios, cobrancas, demandas, processos, contratos, assinaturas e financeiro.',
                'config' => 'Branding, usuarios, modulos e cadastros auxiliares.',
                'logs' => 'Rastreabilidade e auditoria do sistema.',
                'clientes' => 'Cadastro central de clientes avulsos e area condominial.',
                'cobrancas' => 'OS de cobranca, quotas, andamentos, GED e trilha para judicializacao.',
                'demandas' => 'Solicitacoes do Portal do Cliente com triagem e respostas do escritorio.',
                'processos' => 'Controle processual com fases, anexos e sincronizacao DataJud.',
                'contratos' => 'Templates, contratos, versionamento em PDF e historico documental do escritorio.',
                'assinador' => 'Central de assinatura eletronica para contratos, termos de acordo e documentos avulsos.',
                'financeiro' => 'ERP financeiro 360 com caixa, recebimentos, pagamentos, relatorios e integracao contratual.',
            ];

            return [
                'slug' => $module->slug,
                'name' => $module->name,
                'icon_class' => $module->icon_class ?: 'fa-solid fa-cube',
                'route' => match ($module->slug) {
                    'dashboard' => route('dashboard'),
                    'propostas' => route('propostas.index'),
                    'busca' => route('busca'),
                    'config' => route('config.index'),
                    'logs' => route('logs.index'),
                    'clientes' => route('clientes.index'),
                    'cobrancas' => route('cobrancas.dashboard'),
                    'demandas' => route('demandas.dashboard'),
                    'processos' => route('processos.dashboard'),
                    'contratos' => route('contratos.dashboard'),
                    'assinador' => route('assinador.dashboard'),
                    'financeiro' => route('financeiro.dashboard'),
                    default => '#',
                },
                'description' => $descriptions[$module->slug] ?? 'Modulo em evolucao no novo core Laravel.',
                'enabled' => (bool) $module->is_enabled,
                'accent' => match ($module->slug) {
                    'propostas' => 'brand',
                    'clientes' => 'success',
                    'cobrancas' => 'warning',
                    'demandas' => 'blue',
                    'processos' => 'brand',
                    'contratos' => 'blue',
                    'assinador' => 'brand',
                    'financeiro' => 'success',
                    'config' => 'warning',
                    'logs' => 'gray',
                    default => 'blue',
                },
            ];
        })->all();
    }
}
