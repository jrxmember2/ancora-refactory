<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('process_case_options')) {
            Schema::create('process_case_options', function (Blueprint $table) {
                $table->id();
                $table->string('group_key', 60);
                $table->string('name', 160);
                $table->string('slug', 160);
                $table->string('color_hex', 7)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['group_key', 'slug'], 'uq_process_case_options_group_slug');
                $table->index(['group_key', 'is_active', 'sort_order'], 'idx_process_case_options_group_active');
            });
        }

        if (!Schema::hasTable('process_cases')) {
            Schema::create('process_cases', function (Blueprint $table) {
                $table->id();
                $table->string('responsible_lawyer', 160)->nullable();
                $table->date('opened_at')->nullable();
                $table->string('process_number', 80)->nullable();
                $table->string('datajud_court', 80)->nullable();
                $table->foreignId('status_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->foreignId('action_type_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->foreignId('process_type_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->integer('client_entity_id')->nullable();
                $table->string('client_name_snapshot', 190)->nullable();
                $table->integer('adverse_entity_id')->nullable();
                $table->string('adverse_name', 190)->nullable();
                $table->foreignId('client_position_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->foreignId('adverse_position_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->string('client_lawyer', 160)->nullable();
                $table->string('adverse_lawyer', 160)->nullable();
                $table->foreignId('nature_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->boolean('is_private')->default(false);
                $table->decimal('claim_amount', 14, 2)->nullable();
                $table->date('claim_amount_date')->nullable();
                $table->decimal('provisioned_amount', 14, 2)->nullable();
                $table->date('provisioned_amount_date')->nullable();
                $table->decimal('court_paid_amount', 14, 2)->nullable();
                $table->date('court_paid_amount_date')->nullable();
                $table->decimal('process_cost_amount', 14, 2)->nullable();
                $table->date('process_cost_amount_date')->nullable();
                $table->decimal('sentence_amount', 14, 2)->nullable();
                $table->date('sentence_amount_date')->nullable();
                $table->foreignId('win_probability_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->longText('notes')->nullable();
                $table->date('closed_at')->nullable();
                $table->string('closed_by', 160)->nullable();
                $table->foreignId('closure_type_option_id')->nullable()->constrained('process_case_options')->nullOnDelete();
                $table->longText('closure_notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_datajud_sync_at')->nullable();
                $table->string('datajud_last_hash', 80)->nullable();
                $table->timestamps();

                $table->index('process_number', 'idx_process_cases_number');
                $table->index(['status_option_id', 'process_type_option_id'], 'idx_process_cases_status_type');
                $table->index(['client_entity_id', 'adverse_entity_id'], 'idx_process_cases_parties');
                $table->index(['is_private', 'created_by'], 'idx_process_cases_private_creator');

                $table->foreign('client_entity_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('adverse_entity_id')->references('id')->on('client_entities')->nullOnDelete();
            });
        }

        $this->repairPartialProcessCasesTable();

        if (!Schema::hasTable('process_case_phases')) {
            Schema::create('process_case_phases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_case_id')->constrained('process_cases')->cascadeOnDelete();
                $table->date('phase_date')->nullable();
                $table->time('phase_time')->nullable();
                $table->string('description', 255);
                $table->boolean('is_private')->default(false);
                $table->boolean('is_reviewed')->default(false);
                $table->longText('notes')->nullable();
                $table->longText('legal_opinion')->nullable();
                $table->longText('conference')->nullable();
                $table->string('source', 30)->default('manual');
                $table->string('datajud_movement_id', 120)->nullable();
                $table->json('datajud_payload_json')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['process_case_id', 'datajud_movement_id'], 'uq_process_case_phases_datajud');
                $table->index(['process_case_id', 'phase_date', 'phase_time'], 'idx_process_case_phases_case_date');
            });
        }

        if (!Schema::hasTable('process_case_attachments')) {
            Schema::create('process_case_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_case_id')->constrained('process_cases')->cascadeOnDelete();
                $table->foreignId('phase_id')->nullable()->constrained('process_case_phases')->nullOnDelete();
                $table->string('file_role', 50)->default('documento');
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('relative_path', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['process_case_id', 'phase_id'], 'idx_process_case_attachments_case_phase');
            });
        }

        $this->seedOptions();
        $this->seedModule();
        $this->seedRoutePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('process_case_attachments');
        Schema::dropIfExists('process_case_phases');
        Schema::dropIfExists('process_cases');
        Schema::dropIfExists('process_case_options');

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')->where('slug', 'processos')->delete();
        }

        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->whereIn('route_name', array_keys($this->routePermissions()))->delete();
        }
    }

    private function seedOptions(): void
    {
        if (!Schema::hasTable('process_case_options')) {
            return;
        }

        $groups = [
            'status' => [
                ['Ativo', '#10B981'],
                ['Suspenso', '#F59E0B'],
                ['Encerrado', '#6B7280'],
            ],
            'action_type' => [
                ['Execucao'],
                ['Cobranca'],
                ['Familia'],
                ['Obrigacao de Fazer'],
                ['Administrativo'],
            ],
            'process_type' => [
                ['Administrativo'],
                ['Judicial'],
            ],
            'client_position' => [
                ['Autor'],
                ['Requerente'],
                ['Exequente'],
                ['Reu'],
                ['Requerido'],
                ['Executado'],
            ],
            'adverse_position' => [
                ['Autor'],
                ['Requerente'],
                ['Exequente'],
                ['Reu'],
                ['Requerido'],
                ['Executado'],
            ],
            'nature' => [
                ['Civel'],
                ['Condominial'],
                ['Familia'],
                ['Trabalhista'],
                ['Administrativa'],
            ],
            'win_probability' => [
                ['Provavel', '#10B981'],
                ['Possivel', '#F59E0B'],
                ['Remota', '#EF4444'],
            ],
            'closure_type' => [
                ['Acordo'],
                ['Sentenca'],
                ['Arquivamento'],
                ['Desistencia'],
                ['Outro'],
            ],
            'datajud_court' => [
                ['TJES - Tribunal de Justica do Espirito Santo', null, 'api_publica_tjes'],
                ['TJRJ - Tribunal de Justica do Rio de Janeiro', null, 'api_publica_tjrj'],
                ['TJSP - Tribunal de Justica de Sao Paulo', null, 'api_publica_tjsp'],
                ['TJMG - Tribunal de Justica de Minas Gerais', null, 'api_publica_tjmg'],
                ['TRF2 - Tribunal Regional Federal da 2a Regiao', null, 'api_publica_trf2'],
                ['STJ - Superior Tribunal de Justica', null, 'api_publica_stj'],
                ['TST - Tribunal Superior do Trabalho', null, 'api_publica_tst'],
            ],
        ];

        foreach ($groups as $groupKey => $items) {
            foreach ($items as $index => $item) {
                [$name, $color, $slug] = [$item[0], $item[1] ?? null, $item[2] ?? null];
                DB::table('process_case_options')->updateOrInsert(
                    ['group_key' => $groupKey, 'slug' => $slug ?: Str::slug($name)],
                    [
                        'name' => $name,
                        'color_hex' => $color,
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    private function repairPartialProcessCasesTable(): void
    {
        if (!Schema::hasTable('process_cases')) {
            return;
        }

        if (Schema::hasColumn('process_cases', 'client_entity_id')) {
            $this->dropForeignIfExists('process_cases', 'process_cases_client_entity_id_foreign');
            DB::statement('ALTER TABLE process_cases MODIFY client_entity_id INT NULL');
        }

        if (Schema::hasColumn('process_cases', 'adverse_entity_id')) {
            $this->dropForeignIfExists('process_cases', 'process_cases_adverse_entity_id_foreign');
            DB::statement('ALTER TABLE process_cases MODIFY adverse_entity_id INT NULL');
        }

        if (Schema::hasTable('client_entities') && Schema::hasColumn('process_cases', 'client_entity_id') && !$this->foreignKeyExists('process_cases', 'process_cases_client_entity_id_foreign')) {
            DB::statement('ALTER TABLE process_cases ADD CONSTRAINT process_cases_client_entity_id_foreign FOREIGN KEY (client_entity_id) REFERENCES client_entities(id) ON DELETE SET NULL');
        }

        if (Schema::hasTable('client_entities') && Schema::hasColumn('process_cases', 'adverse_entity_id') && !$this->foreignKeyExists('process_cases', 'process_cases_adverse_entity_id_foreign')) {
            DB::statement('ALTER TABLE process_cases ADD CONSTRAINT process_cases_adverse_entity_id_foreign FOREIGN KEY (adverse_entity_id) REFERENCES client_entities(id) ON DELETE SET NULL');
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if ($this->foreignKeyExists($table, $constraint)) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function seedModule(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'processos'],
            [
                'name' => 'Processos',
                'icon_class' => 'fa-solid fa-scale-balanced',
                'route_prefix' => '/processos',
                'is_enabled' => true,
                'sort_order' => 30,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function seedRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        foreach ($this->routePermissions() as $routeName => $label) {
            DB::table('route_permissions')->updateOrInsert(
                ['route_name' => $routeName],
                [
                    'group_key' => str_starts_with($routeName, 'config.') ? 'config' : 'processos',
                    'label' => $label,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function routePermissions(): array
    {
        return [
            'processos.index' => 'Listar processos',
            'processos.create' => 'Novo processo',
            'processos.store' => 'Salvar processo',
            'processos.show' => 'Visualizar processo',
            'processos.edit' => 'Editar processo',
            'processos.update' => 'Atualizar processo',
            'processos.delete' => 'Excluir processo',
            'processos.phases.store' => 'Cadastrar fase do processo',
            'processos.attachments.upload' => 'Enviar anexo do processo',
            'processos.attachments.download' => 'Baixar anexo do processo',
            'processos.attachments.delete' => 'Excluir anexo do processo',
            'processos.datajud.sync' => 'Sincronizar processo com DataJud',
            'config.process-options.store' => 'Cadastrar configuracao de processo',
            'config.process-options.update' => 'Editar configuracao de processo',
            'config.process-options.delete' => 'Excluir configuracao de processo',
        ];
    }
};
