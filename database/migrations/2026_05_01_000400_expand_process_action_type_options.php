<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('process_case_options')) {
            return;
        }

        $definitions = $this->actionTypeDefinitions();
        $canonicalIds = [];

        foreach ($definitions as $slug => $definition) {
            $option = $this->findOption($slug, $definition['name'], $definition['aliases']);

            if ($option) {
                DB::table('process_case_options')
                    ->where('id', $option->id)
                    ->update([
                        'name' => $definition['name'],
                        'slug' => $slug,
                        'is_active' => 1,
                        'updated_at' => now(),
                    ]);

                $canonicalIds[$slug] = (int) $option->id;
                continue;
            }

            $canonicalIds[$slug] = (int) DB::table('process_case_options')->insertGetId([
                'group_key' => 'action_type',
                'name' => $definition['name'],
                'slug' => $slug,
                'color_hex' => null,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($definitions as $slug => $definition) {
            $canonicalId = $canonicalIds[$slug] ?? null;
            if (!$canonicalId) {
                continue;
            }

            foreach ($definition['aliases'] as $alias) {
                $duplicate = $this->findOptionBySlug($alias);
                if (!$duplicate || (int) $duplicate->id === (int) $canonicalId) {
                    continue;
                }

                $this->reassignProcessCases((int) $duplicate->id, (int) $canonicalId);
                DB::table('process_case_options')->where('id', $duplicate->id)->delete();
            }
        }

        foreach (['novo', 'familia'] as $legacySlug) {
            $legacy = $this->findOptionBySlug($legacySlug);
            if (!$legacy) {
                continue;
            }

            DB::table('process_case_options')
                ->where('id', $legacy->id)
                ->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);
        }

        $this->reorderActionTypes();
    }

    public function down(): void
    {
        if (!Schema::hasTable('process_case_options')) {
            return;
        }

        $baseline = [
            'Administrativo',
            'Cobranca',
            'Execucao',
            'Familia',
            'Obrigacao de Fazer',
        ];

        foreach ($baseline as $index => $name) {
            DB::table('process_case_options')->updateOrInsert(
                ['group_key' => 'action_type', 'slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => 1,
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function actionTypeDefinitions(): array
    {
        return [
            'acao-civil-publica' => ['name' => 'Acao Civil Publica', 'aliases' => []],
            'acao-declaratoria' => ['name' => 'Acao Declaratoria', 'aliases' => []],
            'acao-de-alimentos' => ['name' => 'Acao de Alimentos', 'aliases' => []],
            'acao-de-curatela' => ['name' => 'Acao de Curatela', 'aliases' => []],
            'acao-de-exibicao-de-documentos' => ['name' => 'Acao de Exibicao de Documentos', 'aliases' => []],
            'acao-monitoria' => ['name' => 'Acao Monitoria', 'aliases' => []],
            'acao-penal' => ['name' => 'Acao Penal', 'aliases' => []],
            'acao-possessoria' => ['name' => 'Acao Possessoria', 'aliases' => []],
            'administrativo-prefeitura' => ['name' => 'Administrativo Prefeitura', 'aliases' => []],
            'alvara-judicial' => ['name' => 'Alvara Judicial', 'aliases' => []],
            'anulatoria-de-debito-fiscal' => ['name' => 'Anulatoria de Debito Fiscal', 'aliases' => []],
            'aposentadoria-por-tempo-de-servico' => ['name' => 'Aposentadoria por Tempo de Servico', 'aliases' => []],
            'arrolamento' => ['name' => 'Arrolamento', 'aliases' => []],
            'auto-de-infracao' => ['name' => 'Auto de Infracao', 'aliases' => []],
            'busca-e-apreensao' => ['name' => 'Busca e Apreensao', 'aliases' => []],
            'carta-precatoria' => ['name' => 'Carta Precatoria', 'aliases' => []],
            'cautelar-em-geral' => ['name' => 'Cautelar em Geral', 'aliases' => []],
            'cobranca' => ['name' => 'Cobranca', 'aliases' => ['acao-de-cobranca']],
            'concordata-preventiva' => ['name' => 'Concordata Preventiva', 'aliases' => []],
            'consignacao' => ['name' => 'Consignacao', 'aliases' => []],
            'demolitoria' => ['name' => 'Demolitoria', 'aliases' => []],
            'desapropriacao' => ['name' => 'Desapropriacao', 'aliases' => []],
            'despejo' => ['name' => 'Despejo', 'aliases' => ['acao-de-despejo']],
            'despejo-por-falta-de-pagamento' => ['name' => 'Despejo por Falta de Pagamento', 'aliases' => []],
            'divorcio' => ['name' => 'Divorcio', 'aliases' => []],
            'embargos-a-execucao' => ['name' => 'Embargos a Execucao', 'aliases' => []],
            'embargos-a-execucao-fiscal' => ['name' => 'Embargos a Execucao Fiscal', 'aliases' => []],
            'execucao' => ['name' => 'Execucao', 'aliases' => []],
            'execucao-de-pensao-alimenticia' => ['name' => 'Execucao de Pensao Alimenticia', 'aliases' => []],
            'execucao-fiscal' => ['name' => 'Execucao Fiscal', 'aliases' => []],
            'exoneracao-com-liminar' => ['name' => 'Exoneracao com Liminar', 'aliases' => []],
            'falencia' => ['name' => 'Falencia', 'aliases' => []],
            'habeas-corpus' => ['name' => 'Habeas Corpus', 'aliases' => []],
            'habilitacao-de-credito' => ['name' => 'Habilitacao de Credito', 'aliases' => []],
            'indenizacao-danos-materiais' => ['name' => 'Indenizacao Danos Materiais', 'aliases' => []],
            'indenizacao-por-danos-morais' => ['name' => 'Indenizacao por Danos Morais', 'aliases' => []],
            'inquerito-policial' => ['name' => 'Inquerito Policial', 'aliases' => []],
            'inventario' => ['name' => 'Inventario', 'aliases' => []],
            'investigacao-de-paternidade' => ['name' => 'Investigacao de Paternidade', 'aliases' => []],
            'investigacao-mpt' => ['name' => 'Investigacao MPT', 'aliases' => []],
            'mandado-de-seguranca-civel' => ['name' => 'Mandado de Seguranca - Civel', 'aliases' => []],
            'mandado-de-seguranca-penal' => ['name' => 'Mandado de Seguranca - Penal', 'aliases' => []],
            'mandado-de-seguranca-tributario' => ['name' => 'Mandado de Seguranca - Tributario', 'aliases' => []],
            'modificacao-de-clausula' => ['name' => 'Modificacao de Clausula', 'aliases' => []],
            'notificacao-extrajudicial' => ['name' => 'Notificacao Extrajudicial', 'aliases' => []],
            'notificacao-judicial' => ['name' => 'Notificacao Judicial', 'aliases' => []],
            'obrigacao-de-fazer' => ['name' => 'Obrigacao de Fazer', 'aliases' => ['acao-de-obrigacao-de-fazer']],
            'obrigacao-de-nao-fazer' => ['name' => 'Obrigacao de Nao Fazer', 'aliases' => []],
            'ordinario-c-antecipacao-de-tutela' => ['name' => 'Ordinario c/ Antecipacao de Tutela', 'aliases' => []],
            'pedido-de-restituicao-em-dinheiro' => ['name' => 'Pedido de Restituicao em Dinheiro', 'aliases' => []],
            'prestacao-de-contas' => ['name' => 'Prestacao de Contas', 'aliases' => []],
            'previdenciario' => ['name' => 'Previdenciario', 'aliases' => []],
            'procedimento-ordinario' => ['name' => 'Procedimento Ordinario', 'aliases' => ['ordinario-em-geral']],
            'processo-administrativo' => ['name' => 'Processo Administrativo', 'aliases' => ['administrativo']],
            'queixa-crime' => ['name' => 'Queixa Crime', 'aliases' => []],
            'reclamatoria-trabalhista' => ['name' => 'Reclamatoria Trabalhista', 'aliases' => []],
            'reconhecimento-de-sociedade-de-fato' => ['name' => 'Reconhecimento de Sociedade de Fato', 'aliases' => ['reconhecimento-sociedade-de-fato']],
            'reintegracao-de-posse' => ['name' => 'Reintegracao de Posse', 'aliases' => []],
            'reparacao-de-danos' => ['name' => 'Reparacao de Danos', 'aliases' => []],
            'rescisao-do-negocio-juridico-com-indenizacao' => ['name' => 'Rescisao do Negocio Juridico com Indenizacao', 'aliases' => []],
            'revisional-de-alimentos' => ['name' => 'Revisional de Alimentos', 'aliases' => []],
            'separacao-de-corpos' => ['name' => 'Separacao de Corpos', 'aliases' => []],
            'separacao-judicial' => ['name' => 'Separacao Judicial', 'aliases' => []],
            'suscitacao-de-duvida' => ['name' => 'Suscitacao de Duvida', 'aliases' => []],
            'sustacao-de-protesto' => ['name' => 'Sustacao de Protesto', 'aliases' => []],
            'testamento' => ['name' => 'Testamento', 'aliases' => []],
            'usucapiao' => ['name' => 'Usucapiao', 'aliases' => ['usocapiao']],
        ];
    }

    private function findOption(string $canonicalSlug, string $canonicalName, array $aliases): ?object
    {
        $options = $this->actionTypeOptions();
        $slugs = collect(array_merge([$canonicalSlug], $aliases))
            ->map(fn (string $slug) => Str::slug($slug))
            ->unique()
            ->values();

        return $options->first(function (object $option) use ($slugs, $canonicalName) {
            $optionSlug = Str::slug((string) $option->slug);
            $optionNameSlug = Str::slug((string) $option->name);

            return $slugs->contains($optionSlug)
                || $slugs->contains($optionNameSlug)
                || Str::slug((string) $canonicalName) === $optionNameSlug;
        });
    }

    private function findOptionBySlug(string $slug): ?object
    {
        return $this->actionTypeOptions()->first(function (object $option) use ($slug) {
            return Str::slug((string) $option->slug) === Str::slug($slug)
                || Str::slug((string) $option->name) === Str::slug($slug);
        });
    }

    private function actionTypeOptions(): Collection
    {
        return DB::table('process_case_options')
            ->where('group_key', 'action_type')
            ->get(['id', 'name', 'slug', 'is_active']);
    }

    private function reassignProcessCases(int $fromId, int $toId): void
    {
        if (!Schema::hasTable('process_cases') || !Schema::hasColumn('process_cases', 'action_type_option_id')) {
            return;
        }

        DB::table('process_cases')
            ->where('action_type_option_id', $fromId)
            ->update(['action_type_option_id' => $toId]);
    }

    private function reorderActionTypes(): void
    {
        $active = DB::table('process_case_options')
            ->where('group_key', 'action_type')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id']);

        foreach ($active as $index => $option) {
            DB::table('process_case_options')
                ->where('id', $option->id)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                ]);
        }

        $inactive = DB::table('process_case_options')
            ->where('group_key', 'action_type')
            ->where('is_active', 0)
            ->orderBy('name')
            ->get(['id']);

        foreach ($inactive as $index => $option) {
            DB::table('process_case_options')
                ->where('id', $option->id)
                ->update([
                    'sort_order' => $active->count() + $index + 1,
                    'updated_at' => now(),
                ]);
        }
    }
};
