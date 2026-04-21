<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 120)->unique();
                $table->longText('setting_value')->nullable();
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('system_modules')) {
            Schema::create('system_modules', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 80)->unique();
                $table->string('name', 120);
                $table->string('icon_class', 120)->nullable();
                $table->string('route_prefix', 120);
                $table->boolean('is_enabled')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_module_permissions')) {
            Schema::create('user_module_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('module_id')->constrained('system_modules')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->unique(['user_id', 'module_id'], 'uq_user_module_permissions_user_module');
            });
        }

        if (!Schema::hasTable('client_types')) {
            Schema::create('client_types', function (Blueprint $table) {
                $table->increments('id');
                $table->string('scope', 50);
                $table->string('name', 120);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(999);
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->unique(['scope', 'name'], 'uq_client_types_scope_name');
            });
        }

        if (!Schema::hasTable('client_entities')) {
            Schema::create('client_entities', function (Blueprint $table) {
                $table->increments('id');
                $table->string('entity_type', 10)->default('pf');
                $table->string('profile_scope', 20)->default('avulso');
                $table->string('role_tag', 50)->default('outro');
                $table->string('display_name', 180);
                $table->string('legal_name', 180)->nullable();
                $table->string('cpf_cnpj', 32)->nullable();
                $table->string('rg_ie', 32)->nullable();
                $table->string('gender', 20)->nullable();
                $table->string('nationality', 80)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('profession', 120)->nullable();
                $table->string('marital_status', 50)->nullable();
                $table->string('pis', 32)->nullable();
                $table->string('spouse_name', 180)->nullable();
                $table->string('father_name', 180)->nullable();
                $table->string('mother_name', 180)->nullable();
                $table->text('children_info')->nullable();
                $table->string('ctps', 32)->nullable();
                $table->string('cnae', 32)->nullable();
                $table->string('state_registration', 32)->nullable();
                $table->string('municipal_registration', 32)->nullable();
                $table->date('opening_date')->nullable();
                $table->string('legal_representative', 180)->nullable();
                $table->json('phones_json')->nullable();
                $table->json('emails_json')->nullable();
                $table->json('primary_address_json')->nullable();
                $table->json('billing_address_json')->nullable();
                $table->json('shareholders_json')->nullable();
                $table->text('notes')->nullable();
                $table->longText('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('inactive_reason', 255)->nullable();
                $table->date('contract_end_date')->nullable();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();

                $table->index('profile_scope', 'idx_client_entities_scope');
                $table->index('role_tag', 'idx_client_entities_role');
                $table->index('is_active', 'idx_client_entities_active');
                $table->index('cpf_cnpj', 'idx_client_entities_document');
            });
        }

        if (!Schema::hasTable('client_condominiums')) {
            Schema::create('client_condominiums', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 180);
                $table->integer('condominium_type_id')->nullable();
                $table->boolean('has_blocks')->default(false);
                $table->string('cnpj', 32)->nullable();
                $table->string('cnae', 32)->nullable();
                $table->string('state_registration', 32)->nullable();
                $table->string('municipal_registration', 32)->nullable();
                $table->json('address_json')->nullable();
                $table->integer('syndico_entity_id')->nullable();
                $table->integer('administradora_entity_id')->nullable();
                $table->text('bank_details')->nullable();
                $table->longText('characteristics')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('inactive_reason', 255)->nullable();
                $table->date('contract_end_date')->nullable();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();

                $table->index('name', 'idx_client_condominiums_name');
                $table->index('condominium_type_id', 'idx_client_condominiums_type');
                $table->index('syndico_entity_id', 'idx_client_condominiums_syndic');
                $table->index('administradora_entity_id', 'idx_client_condominiums_admin');

                $table->foreign('condominium_type_id', 'fk_client_condominium_type')
                    ->references('id')->on('client_types')->nullOnDelete();
                $table->foreign('syndico_entity_id', 'fk_client_condominium_syndic')
                    ->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('administradora_entity_id', 'fk_client_condominium_admin')
                    ->references('id')->on('client_entities')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('client_condominium_blocks')) {
            Schema::create('client_condominium_blocks', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('condominium_id');
                $table->string('name', 50);
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index('condominium_id', 'idx_client_blocks_condo');
                $table->foreign('condominium_id', 'fk_client_blocks_condo')
                    ->references('id')->on('client_condominiums')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('client_units')) {
            Schema::create('client_units', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('condominium_id');
                $table->integer('block_id')->nullable();
                $table->integer('unit_type_id')->nullable();
                $table->string('unit_number', 50);
                $table->integer('owner_entity_id')->nullable();
                $table->integer('tenant_entity_id')->nullable();
                $table->text('owner_notes')->nullable();
                $table->text('tenant_notes')->nullable();
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();

                $table->index('condominium_id', 'idx_client_units_condo');
                $table->index('block_id', 'idx_client_units_block');
                $table->index('unit_type_id', 'idx_client_units_type');
                $table->index('owner_entity_id', 'idx_client_units_owner');
                $table->index('tenant_entity_id', 'idx_client_units_tenant');

                $table->foreign('condominium_id', 'fk_client_units_condo')
                    ->references('id')->on('client_condominiums')->cascadeOnDelete();
                $table->foreign('block_id', 'fk_client_units_block')
                    ->references('id')->on('client_condominium_blocks')->nullOnDelete();
                $table->foreign('unit_type_id', 'fk_client_units_type')
                    ->references('id')->on('client_types')->nullOnDelete();
                $table->foreign('owner_entity_id', 'fk_client_units_owner')
                    ->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('tenant_entity_id', 'fk_client_units_tenant')
                    ->references('id')->on('client_entities')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('client_attachments')) {
            Schema::create('client_attachments', function (Blueprint $table) {
                $table->increments('id');
                $table->string('related_type', 40);
                $table->integer('related_id');
                $table->string('file_role', 40)->default('documento');
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('relative_path', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->integer('uploaded_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['related_type', 'related_id'], 'idx_client_attachments_related');
            });
        }

        if (!Schema::hasTable('client_timelines')) {
            Schema::create('client_timelines', function (Blueprint $table) {
                $table->increments('id');
                $table->string('related_type', 40);
                $table->integer('related_id');
                $table->longText('note');
                $table->integer('user_id')->nullable();
                $table->string('user_email', 190)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['related_type', 'related_id'], 'idx_client_timelines_related');
            });
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_email', 190);
                $table->string('action', 100);
                $table->string('entity_type', 80)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['action', 'created_at'], 'idx_audit_logs_action_created');
                $table->index(['entity_type', 'entity_id'], 'idx_audit_logs_entity');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('client_timelines');
        Schema::dropIfExists('client_attachments');
        Schema::dropIfExists('client_units');
        Schema::dropIfExists('client_condominium_blocks');
        Schema::dropIfExists('client_condominiums');
        Schema::dropIfExists('client_entities');
        Schema::dropIfExists('client_types');
        Schema::dropIfExists('user_module_permissions');
        Schema::dropIfExists('system_modules');
        Schema::dropIfExists('app_settings');
    }
};
