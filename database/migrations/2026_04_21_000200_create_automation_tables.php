<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automation_sessions')) {
            Schema::create('automation_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('protocol', 30)->unique();
                $table->string('channel', 30)->default('whatsapp');
                $table->string('provider', 30)->default('evolution');
                $table->string('external_contact_id', 120)->nullable()->index();
                $table->string('phone', 40)->index();
                $table->string('current_flow', 60)->default('menu');
                $table->string('current_step', 80)->default('menu');
                $table->string('status', 40)->default('active')->index();
                $table->integer('condominium_id')->nullable();
                $table->integer('block_id')->nullable();
                $table->integer('unit_id')->nullable();
                $table->unsignedBigInteger('cobranca_case_id')->nullable();
                $table->integer('validated_person_id')->nullable();
                $table->string('interlocutor_name', 180)->nullable();
                $table->dateTime('interlocutor_confirmed_at')->nullable();
                $table->dateTime('started_at');
                $table->dateTime('last_interaction_at');
                $table->dateTime('expires_at');
                $table->dateTime('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['channel', 'phone', 'status'], 'idx_automation_sessions_channel_phone_status');
                $table->index(['external_contact_id', 'status'], 'idx_automation_sessions_contact_status');

                $table->foreign('condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
                $table->foreign('block_id')->references('id')->on('client_condominium_blocks')->nullOnDelete();
                $table->foreign('unit_id')->references('id')->on('client_units')->nullOnDelete();
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->nullOnDelete();
                $table->foreign('validated_person_id')->references('id')->on('client_entities')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('automation_session_messages')) {
            Schema::create('automation_session_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('automation_sessions')->cascadeOnDelete();
                $table->string('direction', 20);
                $table->string('provider', 30);
                $table->string('external_message_id', 120)->nullable();
                $table->json('payload');
                $table->text('normalized_text')->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['session_id', 'created_at'], 'idx_automation_session_messages_session_created');
                $table->unique(['provider', 'external_message_id'], 'uq_automation_messages_provider_external');
            });
        }

        if (!Schema::hasTable('automation_validation_challenges')) {
            Schema::create('automation_validation_challenges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('automation_sessions')->cascadeOnDelete();
                $table->string('type', 30);
                $table->string('correct_value_hash', 255);
                $table->json('displayed_options');
                $table->unsignedTinyInteger('correct_option_index');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(3);
                $table->dateTime('solved_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->timestamps();

                $table->index(['session_id', 'type'], 'idx_automation_challenges_session_type');
            });
        }

        if (!Schema::hasTable('automation_debt_snapshots')) {
            Schema::create('automation_debt_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('automation_sessions')->cascadeOnDelete();
                $table->integer('unit_id');
                $table->unsignedBigInteger('cobranca_case_id')->nullable();
                $table->json('snapshot_payload');
                $table->decimal('base_total', 12, 2)->default(0);
                $table->decimal('updated_total', 12, 2)->default(0);
                $table->json('calculation_memory')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->foreign('unit_id')->references('id')->on('client_units')->cascadeOnDelete();
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('automation_agreement_proposals')) {
            Schema::create('automation_agreement_proposals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('automation_sessions')->cascadeOnDelete();
                $table->string('payment_mode', 20);
                $table->unsignedSmallInteger('installments')->nullable();
                $table->date('first_due_date');
                $table->decimal('base_total', 12, 2)->default(0);
                $table->decimal('updated_total', 12, 2)->default(0);
                $table->json('calculation_memory')->nullable();
                $table->json('rules_snapshot')->nullable();
                $table->string('status', 40)->default('accepted');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('automation_audit_logs')) {
            Schema::create('automation_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->nullable()->constrained('automation_sessions')->nullOnDelete();
                $table->string('level', 20)->default('info');
                $table->string('event', 100);
                $table->string('message', 255);
                $table->json('context')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['level', 'event', 'created_at'], 'idx_automation_audit_logs_level_event_created');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_audit_logs');
        Schema::dropIfExists('automation_agreement_proposals');
        Schema::dropIfExists('automation_debt_snapshots');
        Schema::dropIfExists('automation_validation_challenges');
        Schema::dropIfExists('automation_session_messages');
        Schema::dropIfExists('automation_sessions');
    }
};
