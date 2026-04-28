<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_signature_requests')) {
            Schema::create('document_signature_requests', function (Blueprint $table) {
                $table->id();
                $table->string('signable_type', 160);
                $table->unsignedBigInteger('signable_id');
                $table->string('provider', 40)->default('assinafy');
                $table->string('provider_account_id', 120)->nullable();
                $table->string('provider_document_id', 120)->nullable();
                $table->string('provider_assignment_id', 120)->nullable();
                $table->unsignedBigInteger('document_version_id')->nullable();
                $table->string('document_name', 255);
                $table->string('status', 60)->default('draft');
                $table->string('local_pdf_path', 255)->nullable();
                $table->string('signed_pdf_path', 255)->nullable();
                $table->string('certificate_pdf_path', 255)->nullable();
                $table->string('bundle_pdf_path', 255)->nullable();
                $table->text('signing_url')->nullable();
                $table->text('signer_message')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('summary_json')->nullable();
                $table->json('provider_payload_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['signable_type', 'signable_id'], 'idx_signature_requests_signable');
                $table->index(['provider', 'provider_document_id'], 'idx_signature_requests_provider_doc');
                $table->index(['status', 'requested_at'], 'idx_signature_requests_status_requested');

                $table->foreign('document_version_id')->references('id')->on('contract_versions')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('document_signature_signers')) {
            Schema::create('document_signature_signers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('signature_request_id');
                $table->string('provider_signer_id', 120)->nullable();
                $table->string('name', 180);
                $table->string('email', 180);
                $table->string('phone', 40)->nullable();
                $table->string('document_number', 40)->nullable();
                $table->string('role_label', 120)->nullable();
                $table->unsignedInteger('order_index')->default(0);
                $table->string('status', 40)->default('pending');
                $table->boolean('completed')->default(false);
                $table->text('signing_url')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('last_event_at')->nullable();
                $table->json('provider_payload_json')->nullable();
                $table->timestamps();

                $table->index(['signature_request_id', 'status'], 'idx_signature_signers_request_status');

                $table->foreign('signature_request_id', 'fk_signature_signers_request')
                    ->references('id')
                    ->on('document_signature_requests')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('document_signature_events')) {
            Schema::create('document_signature_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('signature_request_id');
                $table->unsignedBigInteger('signature_signer_id')->nullable();
                $table->string('provider_event_id', 120)->nullable();
                $table->string('event_type', 120);
                $table->text('message')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->unique(['signature_request_id', 'provider_event_id', 'event_type'], 'uq_signature_events_provider');
                $table->index(['signature_request_id', 'received_at'], 'idx_signature_events_request_received');

                $table->foreign('signature_request_id', 'fk_signature_events_request')
                    ->references('id')
                    ->on('document_signature_requests')
                    ->cascadeOnDelete();
                $table->foreign('signature_signer_id', 'fk_signature_events_signer')
                    ->references('id')
                    ->on('document_signature_signers')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('route_permissions')) {
            foreach ($this->routePermissions() as $routeName => $label) {
                $payload = [
                    'group_key' => str_starts_with($routeName, 'cobrancas.') ? 'cobrancas' : 'contratos',
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
    }

    public function down(): void
    {
        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->whereIn('route_name', array_keys($this->routePermissions()))->delete();
        }

        Schema::dropIfExists('document_signature_events');
        Schema::dropIfExists('document_signature_signers');
        Schema::dropIfExists('document_signature_requests');
    }

    private function routePermissions(): array
    {
        return [
            'contratos.signatures.create' => 'Abrir envio de contrato para assinatura digital',
            'contratos.signatures.store' => 'Enviar contrato para assinatura digital',
            'contratos.signatures.sync' => 'Sincronizar assinatura digital de contrato',
            'contratos.signatures.download' => 'Baixar artefatos da assinatura digital do contrato',
            'contratos.settings.signatures-webhook' => 'Sincronizar webhook da Assinafy',
            'cobrancas.signatures.create' => 'Abrir envio de termo para assinatura digital',
            'cobrancas.signatures.store' => 'Enviar termo para assinatura digital',
            'cobrancas.signatures.sync' => 'Sincronizar assinatura digital da OS',
            'cobrancas.signatures.download' => 'Baixar artefatos da assinatura digital da OS',
        ];
    }
};
