<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->seedDemandCategories();
        $this->seedRoutePermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')
                ->whereIn('route_name', [
                    'config.demand-categories.store',
                    'config.demand-categories.update',
                    'config.demand-categories.delete',
                ])
                ->delete();
        }
    }

    private function seedDemandCategories(): void
    {
        if (!Schema::hasTable('demand_categories')) {
            return;
        }

        $items = [
            ['name' => 'Acompanhamento de Inquérito Policial', 'slug' => 'acompanhamento-de-inquerito-policial', 'color_hex' => '#7C3AED'],
            ['name' => 'Acompanhamento de Perícia', 'slug' => 'acompanhamento-de-pericia', 'color_hex' => '#8B5CF6'],
            ['name' => 'Acompanhamento de Processo Administrativo', 'slug' => 'acompanhamento-de-processo-administrativo', 'color_hex' => '#2563EB'],
            ['name' => 'Acompanhamento em Processos Administrativos', 'slug' => 'acompanhamento-em-processos-administrativos', 'color_hex' => '#0F766E'],
            ['name' => 'Administração de Processos Judiciais', 'slug' => 'administracao-de-processos-judiciais', 'color_hex' => '#1D4ED8'],
            ['name' => 'Ajuizar Ação', 'slug' => 'ajuizar-acao', 'color_hex' => '#DC2626'],
            ['name' => 'Análise de Alterações de Contrato e Estatuto', 'slug' => 'analise-de-alteracoes-de-contrato-e-estatuto', 'color_hex' => '#9333EA'],
            ['name' => 'Análise de Auto de Infração', 'slug' => 'analise-de-auto-de-infracao', 'color_hex' => '#B45309'],
            ['name' => 'Análise de Contrato', 'slug' => 'analise-de-contrato', 'color_hex' => '#4F46E5'],
            ['name' => 'Análise de Documentos', 'slug' => 'analise-de-documentos', 'color_hex' => '#6366F1'],
            ['name' => 'Análise de Editais de Licitação', 'slug' => 'analise-de-editais-de-licitacao', 'color_hex' => '#0EA5E9'],
            ['name' => 'Análise de Estatuto', 'slug' => 'analise-de-estatuto', 'color_hex' => '#06B6D4'],
            ['name' => 'Análise de Procuração', 'slug' => 'analise-de-procuracao', 'color_hex' => '#0284C7'],
            ['name' => 'Análise de Protocolo de Intenções', 'slug' => 'analise-de-protocolo-de-intencoes', 'color_hex' => '#2563EB'],
            ['name' => 'Análise de Termo Aditivo de Contrato', 'slug' => 'analise-de-termo-aditivo-de-contrato', 'color_hex' => '#4F46E5'],
            ['name' => 'Análise de Termo Aditivo de Convênio', 'slug' => 'analise-de-termo-aditivo-de-convenio', 'color_hex' => '#7C3AED'],
            ['name' => 'Análise de Termo de Ajuste de Conduta', 'slug' => 'analise-de-termo-de-ajuste-de-conduta', 'color_hex' => '#C026D3'],
            ['name' => 'Análise de Termo de Compromisso', 'slug' => 'analise-de-termo-de-compromisso', 'color_hex' => '#9333EA'],
            ['name' => 'Audiência', 'slug' => 'audiencia', 'color_hex' => '#EA580C'],
            ['name' => 'Cobrança Extrajudicial', 'slug' => 'cobranca', 'color_hex' => '#F59E0B'],
            ['name' => 'Consulta por E-mail', 'slug' => 'consulta-por-e-mail', 'color_hex' => '#0EA5E9'],
            ['name' => 'Consulta por Telefone', 'slug' => 'consulta-por-telefone', 'color_hex' => '#14B8A6'],
            ['name' => 'Diligência em Órgãos Administrativos', 'slug' => 'diligencia-em-orgaos-administrativos', 'color_hex' => '#0891B2'],
            ['name' => 'Diligência em Órgãos Judiciários', 'slug' => 'diligencia-em-orgaos-judiciarios', 'color_hex' => '#2563EB'],
            ['name' => 'Elaboração de Contrato', 'slug' => 'elaboracao-de-contrato', 'color_hex' => '#4F46E5'],
            ['name' => 'Elaboração de Convênio', 'slug' => 'elaboracao-de-convenio', 'color_hex' => '#7C3AED'],
            ['name' => 'Elaboração de Defesa Administrativa', 'slug' => 'elaboracao-de-defesa-administrativa', 'color_hex' => '#0F766E'],
            ['name' => 'Elaboração de Defesa Judicial', 'slug' => 'elaboracao-de-defesa-judicial', 'color_hex' => '#1D4ED8'],
            ['name' => 'Elaboração de Distrato', 'slug' => 'elaboracao-de-distrato', 'color_hex' => '#6D28D9'],
            ['name' => 'Elaboração de Notificação', 'slug' => 'elaboracao-de-notificacao', 'color_hex' => '#DB2777'],
            ['name' => 'Elaboração de Ofício', 'slug' => 'elaboracao-de-oficio', 'color_hex' => '#0369A1'],
            ['name' => 'Elaboração de Ofício para Administração Pública', 'slug' => 'elaboracao-de-oficio-para-administracao-publica', 'color_hex' => '#0284C7'],
            ['name' => 'Elaboração de Parecer', 'slug' => 'elaboracao-de-parecer', 'color_hex' => '#7C2D12'],
            ['name' => 'Elaboração de Procuração', 'slug' => 'elaboracao-de-procuracao', 'color_hex' => '#1D4ED8'],
            ['name' => 'Elaboração de Protocolo de Intenções', 'slug' => 'elaboracao-de-protocolo-de-intencoes', 'color_hex' => '#4338CA'],
            ['name' => 'Elaboração de Recurso em Licitação', 'slug' => 'elaboracao-de-recurso-em-licitacao', 'color_hex' => '#2563EB'],
            ['name' => 'Elaboração de Termo Aditivo de Contrato', 'slug' => 'elaboracao-de-termo-aditivo-de-contrato', 'color_hex' => '#4F46E5'],
            ['name' => 'Elaboração de Termo Aditivo de Convênio', 'slug' => 'elaboracao-de-termo-aditivo-de-convenio', 'color_hex' => '#8B5CF6'],
            ['name' => 'Elaboração de Termo de Compromisso', 'slug' => 'elaboracao-de-termo-de-compromisso', 'color_hex' => '#A855F7'],
            ['name' => 'Encaminhamento Interno', 'slug' => 'encaminhamento-interno', 'color_hex' => '#64748B'],
            ['name' => 'Levantamento de Documentação', 'slug' => 'levantamento-de-documentacao', 'color_hex' => '#475569'],
            ['name' => 'Negociação com a Administração Pública', 'slug' => 'negociacao-com-a-administracao-publica', 'color_hex' => '#0F766E'],
            ['name' => 'Negociação entre Particulares', 'slug' => 'negociacao-entre-particulares', 'color_hex' => '#0D9488'],
            ['name' => 'Participação em Audiências', 'slug' => 'participacao-em-audiencias', 'color_hex' => '#EA580C'],
            ['name' => 'Participação em Reunião', 'slug' => 'participacao-em-reuniao', 'color_hex' => '#2563EB'],
            ['name' => 'Participação em Sessões', 'slug' => 'participacao-em-sessoes', 'color_hex' => '#7C3AED'],
            ['name' => 'Recurso em Processo Administrativo', 'slug' => 'recurso-em-processo-administrativo', 'color_hex' => '#0284C7'],
            ['name' => 'Recurso Judicial', 'slug' => 'recurso-judicial', 'color_hex' => '#1D4ED8'],
            ['name' => 'Reunião', 'slug' => 'reuniao', 'color_hex' => '#2563EB'],
        ];

        foreach ($items as $index => $item) {
            DB::table('demand_categories')->updateOrInsert(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'color_hex' => $item['color_hex'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('demand_categories')
            ->whereIn('slug', [
                'juridico-consultivo',
                'assembleia',
                'convencao-e-regimento',
                'documentos',
                'financeiro',
                'outros',
            ])
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    private function seedRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        $routes = [
            'config.demand-categories.store' => ['config', 'Cadastrar categoria de demanda'],
            'config.demand-categories.update' => ['config', 'Editar categoria de demanda'],
            'config.demand-categories.delete' => ['config', 'Excluir categoria de demanda'],
        ];

        foreach ($routes as $routeName => [$groupKey, $label]) {
            $payload = [
                'group_key' => $groupKey,
                'label' => $label,
            ];

            if (Schema::hasColumn('route_permissions', 'created_at')) {
                $payload['created_at'] = now();
            }

            if (Schema::hasColumn('route_permissions', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('route_permissions')->updateOrInsert(['route_name' => $routeName], $payload);
        }
    }
};
