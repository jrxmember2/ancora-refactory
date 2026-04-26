<?php

use App\Support\Contracts\ContractCatalog;
use App\Support\Contracts\ContractVariableCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_categories')) {
            Schema::create('contract_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160)->unique();
                $table->string('description', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contract_templates')) {
            Schema::create('contract_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 180);
                $table->string('document_type', 120);
                $table->foreignId('category_id')->nullable()->constrained('contract_categories')->nullOnDelete();
                $table->string('description', 255)->nullable();
                $table->longText('content_html')->nullable();
                $table->longText('header_html')->nullable();
                $table->longText('footer_html')->nullable();
                $table->string('page_orientation', 20)->default('portrait');
                $table->json('margins_json')->nullable();
                $table->json('available_variables_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['document_type', 'is_active'], 'idx_contract_templates_type_active');
            });
        }

        if (!Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->string('title', 220);
                $table->string('type', 120);
                $table->foreignId('category_id')->nullable()->constrained('contract_categories')->nullOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
                $table->integer('client_id')->nullable();
                $table->integer('condominium_id')->nullable();
                $table->integer('unit_id')->nullable();
                $table->unsignedBigInteger('proposal_id')->nullable();
                $table->unsignedBigInteger('process_id')->nullable();
                $table->string('status', 60)->default('rascunho');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('indefinite_term')->default(false);
                $table->decimal('contract_value', 14, 2)->nullable();
                $table->decimal('monthly_value', 14, 2)->nullable();
                $table->decimal('total_value', 14, 2)->nullable();
                $table->string('billing_type', 60)->nullable();
                $table->unsignedTinyInteger('due_day')->nullable();
                $table->string('recurrence', 60)->nullable();
                $table->string('adjustment_index', 80)->nullable();
                $table->string('adjustment_periodicity', 60)->nullable();
                $table->date('next_adjustment_date')->nullable();
                $table->decimal('penalty_value', 14, 2)->nullable();
                $table->decimal('penalty_percentage', 7, 2)->nullable();
                $table->boolean('generate_financial_entries')->default(false);
                $table->string('cost_center_future', 120)->nullable();
                $table->string('financial_category_future', 120)->nullable();
                $table->text('financial_notes')->nullable();
                $table->longText('content_html')->nullable();
                $table->string('final_pdf_path', 255)->nullable();
                $table->timestamp('final_pdf_generated_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'type'], 'idx_contracts_status_type');
                $table->index(['start_date', 'end_date'], 'idx_contracts_dates');
                $table->index(['client_id', 'condominium_id', 'unit_id'], 'idx_contracts_links');

                $table->foreign('client_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
                $table->foreign('unit_id')->references('id')->on('client_units')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('contract_versions')) {
            Schema::create('contract_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->longText('content_html');
                $table->string('pdf_path', 255)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();

                $table->unique(['contract_id', 'version_number'], 'uq_contract_versions_number');
            });
        }

        if (!Schema::hasTable('contract_attachments')) {
            Schema::create('contract_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('relative_path', 255);
                $table->string('file_type', 50)->default('outro');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('description', 255)->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['contract_id', 'file_type'], 'idx_contract_attachments_contract_type');
            });
        }

        if (!Schema::hasTable('contract_settings')) {
            Schema::create('contract_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contract_variables')) {
            Schema::create('contract_variables', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->string('label', 180);
                $table->string('description', 255)->nullable();
                $table->string('source', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $this->seedModule();
        $this->seedRoutePermissions();
        $this->seedSettings();
        $this->seedCategories();
        $this->seedVariables();
        $this->seedTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_attachments');
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('contract_variables');
        Schema::dropIfExists('contract_settings');
        Schema::dropIfExists('contract_categories');

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')->where('slug', 'contratos')->delete();
        }

        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->whereIn('route_name', array_keys($this->routePermissions()))->delete();
        }
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
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
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
            'contratos.versions.view' => 'Visualizar versoes do contrato',
            'contratos.versions.download' => 'Baixar PDF de versao do contrato',
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
            'contratos.reports.export.csv' => 'Exportar relatorio CSV de contratos',
            'contratos.reports.export.pdf' => 'Exportar relatorio PDF de contratos',
            'contratos.settings.index' => 'Acessar configuracoes de contratos',
            'contratos.settings.save' => 'Salvar configuracoes de contratos',
        ];
    }
};
