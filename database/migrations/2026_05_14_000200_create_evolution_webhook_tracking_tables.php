<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('evolution_webhook_events')) {
            Schema::create('evolution_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 30)->default('evolution');
                $table->string('event_name', 80)->nullable()->index();
                $table->string('instance_name', 120)->nullable()->index();
                $table->string('processing_status', 30)->default('pending')->index();
                $table->string('message_direction', 20)->nullable()->index();
                $table->string('message_id', 120)->nullable()->index();
                $table->string('remote_jid', 190)->nullable()->index();
                $table->string('phone', 40)->nullable()->index();
                $table->string('message_status', 60)->nullable()->index();
                $table->text('processing_message')->nullable();
                $table->json('payload');
                $table->json('context')->nullable();
                $table->timestamp('received_at')->nullable()->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['event_name', 'received_at'], 'idx_evolution_webhook_events_event_received');
            });
        }

        if (!Schema::hasTable('evolution_message_logs')) {
            Schema::create('evolution_message_logs', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 30)->default('evolution');
                $table->string('module', 40)->default('system')->index();
                $table->string('direction', 20)->index();
                $table->string('status', 40)->default('pending')->index();
                $table->string('message_type', 40)->nullable();
                $table->string('external_message_id', 120)->nullable();
                $table->string('external_contact_id', 190)->nullable()->index();
                $table->string('phone', 40)->nullable()->index();
                $table->string('remote_jid', 190)->nullable()->index();
                $table->text('body_text')->nullable();
                $table->json('payload')->nullable();
                $table->json('metadata')->nullable();
                $table->string('last_event_name', 80)->nullable();
                $table->foreignId('automation_session_id')->nullable()->constrained('automation_sessions')->nullOnDelete();
                $table->foreignId('process_case_id')->nullable()->constrained('process_cases')->nullOnDelete();
                $table->foreignId('process_case_phase_id')->nullable()->constrained('process_case_phases')->nullOnDelete();
                $table->foreignId('cobranca_case_id')->nullable()->constrained('cobranca_cases')->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('last_status_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->index(['module', 'direction', 'status'], 'idx_evolution_message_logs_module_direction_status');
                $table->unique(['provider', 'external_message_id'], 'uq_evolution_message_logs_provider_external');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evolution_message_logs');
        Schema::dropIfExists('evolution_webhook_events');
    }
};
