<?php

namespace Database\Seeders;

use App\Support\Contracts\ContractCatalog;
use App\Support\Contracts\ContractVariableCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContractModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedModule();
        $this->seedRoutePermissions();
        $this->seedSettings();
        $this->seedCategories();
        $this->seedVariables();
        $this->seedTemplates();
    }

    private function seedModule(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'contratos'],
            [
                'name' => 'Contratos',
                'icon_class' => 'fa-solid fa-file-contract',
                'route_prefix' => '/contratos',
                'is_enabled' => true,
                'sort_order' => 37,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        foreach ($this->routePermissions() as $routeName => $label) {
            $payload = [
                'group_key' => 'contratos',
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

    private function seedSettings(): void
    {
        if (!Schema::hasTable('contract_settings')) {
            return;
        }

        foreach (ContractCatalog::defaultSettings() as $key => $value) {
            DB::table('contract_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedCategories(): void
    {
        if (!Schema::hasTable('contract_categories')) {
            return;
        }

        foreach (ContractCatalog::initialCategories() as $category) {
            DB::table('contract_categories')->updateOrInsert(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedVariables(): void
    {
        if (!Schema::hasTable('contract_variables')) {
            return;
        }

        foreach (ContractVariableCatalog::definitions() as $index => $variable) {
            DB::table('contract_variables')->updateOrInsert(
                ['key' => $variable['key']],
                [
                    'label' => $variable['label'],
                    'description' => $variable['description'],
                    'source' => $variable['source'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedTemplates(): void
    {
        if (!Schema::hasTable('contract_templates') || !Schema::hasTable('contract_categories')) {
            return;
        }

        $categories = DB::table('contract_categories')->pluck('id', 'name');

        foreach (ContractCatalog::initialTemplates() as $template) {
            DB::table('contract_templates')->updateOrInsert(
                ['name' => $template['name']],
                [
                    'document_type' => $template['document_type'],
                    'category_id' => $categories[$template['category_name']] ?? null,
                    'description' => $template['description'],
                    'content_html' => $template['content_html'],
                    'header_html' => $template['header_html'] ?? null,
                    'footer_html' => $template['footer_html'] ?? null,
                    'page_orientation' => $template['page_orientation'] ?? 'portrait',
                    'margins_json' => json_encode($template['margins_json'] ?? ['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3], JSON_UNESCAPED_UNICODE),
                    'available_variables_json' => json_encode($template['available_variables'] ?? [], JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function routePermissions(): array
    {
        return [
            'contratos.dashboard' => 'Acessar dashboard de contratos',
            'contratos.index' => 'Listar contratos',
            'contratos.create' => 'Novo contrato',
            'contratos.store' => 'Salvar contrato',
            'contratos.show' => 'Visualizar contrato',
            'contratos.edit' => 'Editar contrato',
            'contratos.update' => 'Atualizar contrato',
            'contratos.delete' => 'Excluir contrato',
            'contratos.duplicate' => 'Duplicar contrato',
            'contratos.archive' => 'Arquivar contrato',
            'contratos.rescind' => 'Rescindir contrato',
            'contratos.preview.resolve' => 'Carregar preview editavel do contrato',
            'contratos.generate-pdf' => 'Gerar PDF do contrato',
            'contratos.download-pdf' => 'Baixar PDF do contrato',
            'contratos.versions.view' => 'Visualizar versao do contrato',
            'contratos.versions.download' => 'Baixar PDF da versao do contrato',
            'contratos.attachments.upload' => 'Enviar anexo de contrato',
            'contratos.attachments.download' => 'Baixar anexo de contrato',
            'contratos.attachments.delete' => 'Excluir anexo de contrato',
            'contratos.templates.index' => 'Listar templates de contrato',
            'contratos.templates.create' => 'Novo template de contrato',
            'contratos.templates.store' => 'Salvar template de contrato',
            'contratos.templates.edit' => 'Editar template de contrato',
            'contratos.templates.update' => 'Atualizar template de contrato',
            'contratos.templates.delete' => 'Excluir template de contrato',
            'contratos.categories.index' => 'Listar categorias de contrato',
            'contratos.categories.store' => 'Salvar categoria de contrato',
            'contratos.categories.update' => 'Atualizar categoria de contrato',
            'contratos.categories.delete' => 'Excluir categoria de contrato',
            'contratos.variables.index' => 'Listar variaveis de contrato',
            'contratos.variables.update' => 'Atualizar variavel de contrato',
            'contratos.reports.index' => 'Acessar relatorios de contratos',
            'contratos.reports.export.csv' => 'Exportar CSV de contratos',
            'contratos.reports.export.pdf' => 'Exportar PDF de contratos',
            'contratos.settings.index' => 'Acessar configuracoes de contratos',
            'contratos.settings.save' => 'Salvar configuracoes de contratos',
        ];
    }
}
