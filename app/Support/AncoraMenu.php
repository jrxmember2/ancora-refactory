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

        $groups = [
            [
                'title' => 'Principal',
                'items' => array_values(array_filter([
                    ['label' => 'Hub', 'path' => route('hub'), 'icon' => 'fa-solid fa-house'],
                    $has('propostas') ? [
                        'label' => 'Propostas',
                        'icon' => 'fa-solid fa-file-signature',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('propostas.dashboard')],
                            ['label' => 'Lista', 'path' => route('propostas.index')],
                            ['label' => 'Nova proposta', 'path' => route('propostas.create')],
                        ],
                    ] : null,
                    $has('clientes') ? [
                        'label' => 'Clientes',
                        'icon' => 'fa-solid fa-users',
                        'subItems' => [
                            ['label' => 'Visao geral', 'path' => route('clientes.index')],
                            ['label' => 'Avulsos', 'path' => route('clientes.avulsos')],
                            ['label' => 'Parceiros / fornecedores', 'path' => route('clientes.contatos')],
                            ['label' => 'Condominos', 'path' => route('clientes.condominos')],
                            ['label' => 'Portal do Cliente', 'path' => route('clientes.portal-users.index')],
                            ['label' => 'Condominios', 'path' => route('clientes.condominios')],
                            ['label' => 'Unidades', 'path' => route('clientes.unidades')],
                        ],
                    ] : null,
                    $has('cobrancas') ? [
                        'label' => 'Cobranca',
                        'icon' => 'fa-solid fa-money-bill-wave',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('cobrancas.dashboard')],
                            ['label' => 'Lista de OS', 'path' => route('cobrancas.index')],
                            ['label' => 'Nova OS', 'path' => route('cobrancas.create')],
                            ['label' => 'Faturamento', 'path' => route('cobrancas.billing.report')],
                            ['label' => 'Importar inadimplencia', 'path' => route('cobrancas.import.index')],
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
                    $has('processos') ? [
                        'label' => 'Processos',
                        'icon' => 'fa-solid fa-scale-balanced',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('processos.dashboard')],
                            ['label' => 'Lista', 'path' => route('processos.index')],
                            ['label' => 'Novo processo', 'path' => route('processos.create')],
                        ],
                    ] : null,
                    $has('contratos') ? [
                        'label' => 'Contratos',
                        'icon' => 'fa-solid fa-file-contract',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('contratos.dashboard')],
                            ['label' => 'Contratos', 'path' => route('contratos.index')],
                            ['label' => 'Novo contrato', 'path' => route('contratos.create')],
                            ['label' => 'Templates', 'path' => route('contratos.templates.index')],
                            ['label' => 'Categorias', 'path' => route('contratos.categories.index')],
                            ['label' => 'Variaveis', 'path' => route('contratos.variables.index')],
                            ['label' => 'Relatorios', 'path' => route('contratos.reports.index')],
                            ['label' => 'Configuracoes', 'path' => route('contratos.settings.index')],
                        ],
                    ] : null,
                    $has('financeiro') ? [
                        'label' => 'Financeiro 360',
                        'icon' => 'fa-solid fa-chart-pie',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('financeiro.dashboard')],
                            ['label' => 'Fluxo de Caixa', 'path' => route('financeiro.cash-flow.index')],
                            ['label' => 'Contas a Receber', 'path' => route('financeiro.receivables.index')],
                            ['label' => 'Contas a Pagar', 'path' => route('financeiro.payables.index')],
                            ['label' => 'Faturamento', 'path' => route('financeiro.billing.index')],
                            ['label' => 'Bancos e Contas', 'path' => route('financeiro.accounts.index')],
                            ['label' => 'Conciliacao Bancaria', 'path' => route('financeiro.reconciliation.index')],
                            ['label' => 'Cobrancas', 'path' => route('financeiro.collection.index')],
                            ['label' => 'Inadimplencia', 'path' => route('financeiro.delinquency.index')],
                            ['label' => 'Centros de Custo', 'path' => route('financeiro.cost-centers.index')],
                            ['label' => 'Categorias Financeiras', 'path' => route('financeiro.categories.index')],
                            ['label' => 'Parcelamentos', 'path' => route('financeiro.installments.index')],
                            ['label' => 'Reembolsos', 'path' => route('financeiro.reimbursements.index')],
                            ['label' => 'Custas Processuais', 'path' => route('financeiro.process-costs.index')],
                            ['label' => 'Prestacao de Contas', 'path' => route('financeiro.accountability.index')],
                            ['label' => 'DRE', 'path' => route('financeiro.dre.index')],
                            ['label' => 'Relatorios', 'path' => route('financeiro.reports.index')],
                            ['label' => 'Configuracoes', 'path' => route('financeiro.settings.index')],
                        ],
                    ] : null,
                ])),
            ],
            [
                'title' => 'Administracao',
                'items' => array_values(array_filter([
                    $has('busca') ? ['label' => 'Busca', 'path' => route('busca'), 'icon' => 'fa-solid fa-magnifying-glass'] : null,
                    $has('config') ? ['label' => 'Configuracoes', 'path' => route('config.index'), 'icon' => 'fa-solid fa-gear'] : null,
                    ['label' => 'Versionamento', 'path' => route('changelog.index'), 'icon' => 'fa-solid fa-code-branch'],
                    $has('logs') ? ['label' => 'Logs', 'path' => route('logs.index'), 'icon' => 'fa-solid fa-clock-rotate-left'] : null,
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
            ? SystemModule::query()->orderBy('sort_order')->get()
            : $user->modules()->orderBy('sort_order')->get();

        return $modules->map(function (SystemModule $module) {
            $descriptions = [
                'dashboard' => 'Painel executivo consolidado do ecossistema Ancora.',
                'propostas' => 'Fluxo completo comercial com proposta premium, anexos e follow-up.',
                'busca' => 'Busca rapida entre usuarios, clientes e propostas.',
                'config' => 'Branding, usuarios, modulos e cadastros auxiliares.',
                'logs' => 'Rastreabilidade e auditoria do sistema.',
                'clientes' => 'Cadastro central de clientes avulsos e area condominial.',
                'cobrancas' => 'OS de cobranca, quotas, andamentos, GED e trilha para judicializacao.',
                'demandas' => 'Solicitacoes do Portal do Cliente com triagem e respostas do escritorio.',
                'processos' => 'Controle processual com fases, anexos e sincronizacao DataJud.',
                'contratos' => 'Templates, contratos, versionamento em PDF e historico documental do escritorio.',
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
                    'financeiro' => 'success',
                    'config' => 'warning',
                    'logs' => 'gray',
                    default => 'blue',
                },
            ];
        })->all();
    }
}
