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
        if (!Schema::hasTable('client_portal_users')) {
            Schema::create('client_portal_users', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('login_key', 80)->unique();
                $table->string('email', 190)->nullable()->index();
                $table->string('phone', 40)->nullable();
                $table->string('password_hash');
                $table->string('portal_role', 40)->default('sindico');
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('must_change_password')->default(true);
                $table->dateTime('last_login_at')->nullable();
                $table->integer('client_entity_id')->nullable();
                $table->integer('client_condominium_id')->nullable();
                $table->boolean('can_view_processes')->default(true);
                $table->boolean('can_view_cobrancas')->default(true);
                $table->boolean('can_open_demands')->default(true);
                $table->boolean('can_view_demands')->default(true);
                $table->boolean('can_view_documents')->default(false);
                $table->boolean('can_view_financial_summary')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['client_condominium_id', 'is_active'], 'idx_portal_users_condo_active');
                $table->index(['client_entity_id', 'is_active'], 'idx_portal_users_entity_active');

                $table->foreign('client_entity_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('client_condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('demand_categories')) {
            Schema::create('demand_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('color_hex', 7)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'idx_demand_categories_active_order');
            });
        }

        if (!Schema::hasTable('demands')) {
            Schema::create('demands', function (Blueprint $table) {
                $table->id();
                $table->string('protocol', 30)->unique();
                $table->string('origin', 20)->default('portal');
                $table->foreignId('client_portal_user_id')->nullable()->constrained('client_portal_users')->nullOnDelete();
                $table->integer('client_entity_id')->nullable();
                $table->integer('client_condominium_id')->nullable();
                $table->foreignId('process_case_id')->nullable()->constrained('process_cases')->nullOnDelete();
                $table->foreignId('cobranca_case_id')->nullable()->constrained('cobranca_cases')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('demand_categories')->nullOnDelete();
                $table->string('subject', 180);
                $table->longText('description');
                $table->string('priority', 30)->default('normal');
                $table->string('status', 40)->default('aberta');
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('last_external_message_at')->nullable();
                $table->dateTime('last_internal_message_at')->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'priority'], 'idx_demands_status_priority');
                $table->index(['client_condominium_id', 'status'], 'idx_demands_condo_status');
                $table->index(['client_entity_id', 'status'], 'idx_demands_entity_status');
                $table->index(['assigned_user_id', 'status'], 'idx_demands_assigned_status');

                $table->foreign('client_entity_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('client_condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('demand_messages')) {
            Schema::create('demand_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('demand_id')->constrained('demands')->cascadeOnDelete();
                $table->string('sender_type', 20);
                $table->foreignId('client_portal_user_id')->nullable()->constrained('client_portal_users')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->longText('message');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();

                $table->index(['demand_id', 'is_internal', 'created_at'], 'idx_demand_messages_external_timeline');
            });
        }

        if (!Schema::hasTable('demand_attachments')) {
            Schema::create('demand_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('demand_id')->constrained('demands')->cascadeOnDelete();
                $table->foreignId('message_id')->nullable()->constrained('demand_messages')->nullOnDelete();
                $table->string('uploaded_by_type', 20);
                $table->foreignId('client_portal_user_id')->nullable()->constrained('client_portal_users')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('relative_path', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->boolean('is_internal')->default(false);
                $table->timestamps();

                $table->index(['demand_id', 'is_internal'], 'idx_demand_attachments_demand_internal');
            });
        }

        $this->ensureProcessCondominiumLink();
        $this->seedDemandCategories();
        $this->seedDemandModule();
        $this->seedRoutePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_attachments');
        Schema::dropIfExists('demand_messages');
        Schema::dropIfExists('demands');
        Schema::dropIfExists('demand_categories');
        Schema::dropIfExists('client_portal_users');

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')->where('slug', 'demandas')->delete();
        }

        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->whereIn('route_name', array_keys($this->routePermissions()))->delete();
        }

        if (Schema::hasTable('process_cases') && Schema::hasColumn('process_cases', 'client_condominium_id')) {
            $this->dropForeignIfExists('process_cases', 'process_cases_client_condominium_id_foreign');
            Schema::table('process_cases', function (Blueprint $table) {
                $table->dropIndex('idx_process_cases_condominium');
                $table->dropColumn('client_condominium_id');
            });
        }
    }

    private function ensureProcessCondominiumLink(): void
    {
        if (!Schema::hasTable('process_cases') || Schema::hasColumn('process_cases', 'client_condominium_id')) {
            return;
        }

        Schema::table('process_cases', function (Blueprint $table) {
            $table->integer('client_condominium_id')->nullable()->after('client_entity_id');
            $table->index('client_condominium_id', 'idx_process_cases_condominium');
            $table->foreign('client_condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
        });
    }

    private function seedDemandCategories(): void
    {
        if (!Schema::hasTable('demand_categories')) {
            return;
        }

        $items = [
            ['Jurídico consultivo', '#941415'],
            ['Cobrança', '#F59E0B'],
            ['Assembleia', '#465FFF'],
            ['Convenção e regimento', '#10B981'],
            ['Documentos', '#6366F1'],
            ['Financeiro', '#0EA5E9'],
            ['Outros', '#6B7280'],
        ];

        foreach ($items as $index => [$name, $color]) {
            DB::table('demand_categories')->updateOrInsert(
                ['slug' => Str::slug($name)],
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

    private function seedDemandModule(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'demandas'],
            [
                'name' => 'Demandas',
                'icon_class' => 'fa-solid fa-inbox',
                'route_prefix' => '/demandas',
                'is_enabled' => true,
                'sort_order' => 35,
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
            $payload = [
                'group_key' => str_starts_with($routeName, 'clientes.portal') ? 'clientes' : 'demandas',
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

    private function routePermissions(): array
    {
        return [
            'demandas.index' => 'Listar demandas',
            'demandas.show' => 'Visualizar demanda',
            'demandas.update' => 'Atualizar demanda',
            'demandas.reply' => 'Responder demanda',
            'demandas.attachments.download' => 'Baixar anexo de demanda',
            'clientes.portal-users.index' => 'Listar usuários do portal',
            'clientes.portal-users.store' => 'Cadastrar usuário do portal',
            'clientes.portal-users.update' => 'Atualizar usuário do portal',
            'clientes.portal-users.delete' => 'Excluir usuário do portal',
        ];
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        $database = DB::getDatabaseName();
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }
};
