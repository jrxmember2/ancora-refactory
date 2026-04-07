<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cobranca_cases')) {
            Schema::create('cobranca_cases', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('charge_year');
                $table->unsignedInteger('charge_seq');
                $table->string('os_number', 30)->unique();
                $table->integer('condominium_id')->nullable();
                $table->integer('block_id')->nullable();
                $table->integer('unit_id')->nullable();
                $table->integer('debtor_entity_id')->nullable();
                $table->string('debtor_role', 20)->default('owner');
                $table->string('debtor_name_snapshot', 180);
                $table->string('debtor_document_snapshot', 30)->nullable();
                $table->string('debtor_email_snapshot', 190)->nullable();
                $table->string('debtor_phone_snapshot', 30)->nullable();
                $table->string('charge_type', 30)->default('extrajudicial');
                $table->decimal('agreement_total', 12, 2)->nullable();
                $table->string('billing_status', 30)->default('a_faturar');
                $table->date('billing_date')->nullable();
                $table->string('alert_message', 255)->nullable();
                $table->text('notes')->nullable();
                $table->string('situation', 60)->default('processo_aberto');
                $table->string('workflow_stage', 60)->default('triagem');
                $table->string('entry_status', 40)->nullable();
                $table->date('entry_due_date')->nullable();
                $table->decimal('entry_amount', 12, 2)->nullable();
                $table->decimal('fees_amount', 12, 2)->nullable();
                $table->string('judicial_case_number', 80)->nullable();
                $table->date('calc_base_date')->nullable();
                $table->dateTime('last_progress_at')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['charge_year', 'charge_seq'], 'uq_cobranca_cases_year_seq');
                $table->index(['workflow_stage', 'situation'], 'idx_cobranca_cases_stage_situation');
                $table->index(['condominium_id', 'unit_id'], 'idx_cobranca_cases_condo_unit');
                $table->index('debtor_name_snapshot', 'idx_cobranca_cases_debtor_name');

                $table->foreign('condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
                $table->foreign('block_id')->references('id')->on('client_condominium_blocks')->nullOnDelete();
                $table->foreign('unit_id')->references('id')->on('client_units')->nullOnDelete();
                $table->foreign('debtor_entity_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_case_contacts')) {
            Schema::create('cobranca_case_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
                $table->string('contact_type', 20);
                $table->string('label', 80)->nullable();
                $table->string('value', 190);
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_whatsapp')->default(false);
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['cobranca_case_id', 'contact_type'], 'idx_cobranca_case_contacts_case_type');
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_case_quotas')) {
            Schema::create('cobranca_case_quotas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
                $table->string('reference_label', 100)->nullable();
                $table->date('due_date');
                $table->decimal('original_amount', 12, 2)->default(0);
                $table->decimal('updated_amount', 12, 2)->nullable();
                $table->string('status', 30)->default('aberta');
                $table->string('notes', 190)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['cobranca_case_id', 'due_date'], 'idx_cobranca_case_quotas_case_due');
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_case_installments')) {
            Schema::create('cobranca_case_installments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
                $table->string('label', 100)->nullable();
                $table->string('installment_type', 20)->default('parcela');
                $table->unsignedInteger('installment_number')->nullable();
                $table->date('due_date');
                $table->decimal('amount', 12, 2);
                $table->string('status', 30)->default('pendente');
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['cobranca_case_id', 'due_date'], 'idx_cobranca_case_installments_case_due');
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_case_timelines')) {
            Schema::create('cobranca_case_timelines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
                $table->string('event_type', 40)->default('manual');
                $table->text('description');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email', 190)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['cobranca_case_id', 'created_at'], 'idx_cobranca_case_timelines_case_created');
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_case_attachments')) {
            Schema::create('cobranca_case_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
                $table->string('file_role', 40)->default('documento');
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('relative_path', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index('cobranca_case_id', 'idx_cobranca_case_attachments_case');
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->cascadeOnDelete();
                $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_case_attachments');
        Schema::dropIfExists('cobranca_case_timelines');
        Schema::dropIfExists('cobranca_case_installments');
        Schema::dropIfExists('cobranca_case_quotas');
        Schema::dropIfExists('cobranca_case_contacts');
        Schema::dropIfExists('cobranca_cases');
    }
};
