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
                            ['label' => 'Visão geral', 'path' => route('clientes.index')],
                            ['label' => 'Avulsos', 'path' => route('clientes.avulsos')],
                            ['label' => 'Parceiros / fornecedores', 'path' => route('clientes.contatos')],
                            ['label' => 'Condôminos', 'path' => route('clientes.condominos')],
                            ['label' => 'Portal do Cliente', 'path' => route('clientes.portal-users.index')],
                            ['label' => 'Condomínios', 'path' => route('clientes.condominios')],
                            ['label' => 'Unidades', 'path' => route('clientes.unidades')],
                        ],
                    ] : null,
                    $has('cobrancas') ? [
                        'label' => 'Cobrança',
                        'icon' => 'fa-solid fa-money-bill-wave',
                        'subItems' => [
                            ['label' => 'Dashboard', 'path' => route('cobrancas.dashboard')],
                            ['label' => 'Lista de OS', 'path' => route('cobrancas.index')],
                            ['label' => 'Nova OS', 'path' => route('cobrancas.create')],
                            ['label' => 'Faturamento', 'path' => route('cobrancas.billing.report')],
                            ['label' => 'Importar inadimplência', 'path' => route('cobrancas.import.index')],
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
                ])),
            ],
            [
                'title' => 'Administração',
                'items' => array_values(array_filter([
                    $has('busca') ? ['label' => 'Busca', 'path' => route('busca'), 'icon' => 'fa-solid fa-magnifying-glass'] : null,
                    $has('config') ? ['label' => 'Configurações', 'path' => route('config.index'), 'icon' => 'fa-solid fa-gear'] : null,
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
                'dashboard' => 'Painel executivo consolidado do ecossistema Âncora.',
                'propostas' => 'Fluxo completo comercial com proposta premium, anexos e follow-up.',
                'busca' => 'Busca rápida entre usuários, clientes e propostas.',
                'config' => 'Branding, usuários, módulos e cadastros auxiliares.',
                'logs' => 'Rastreabilidade e auditoria do sistema.',
                'clientes' => 'Cadastro central de clientes avulsos e área condominial.',
                'cobrancas' => 'OS de cobrança, quotas, andamentos, GED e trilha para judicialização.',
                'demandas' => 'Solicitações do Portal do Cliente com triagem e respostas do escritório.',
                'processos' => 'Controle processual com fases, anexos e sincronização DataJud.',
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
                    default => '#',
                },
                'description' => $descriptions[$module->slug] ?? 'Módulo em evolução no novo core Laravel.',
                'enabled' => (bool) $module->is_enabled,
                'accent' => match ($module->slug) {
                    'propostas' => 'brand',
                    'clientes' => 'success',
                    'cobrancas' => 'warning',
                    'demandas' => 'blue',
                    'processos' => 'brand',
                    'config' => 'warning',
                    'logs' => 'gray',
                    default => 'blue',
                },
            ];
        })->all();
    }
}
