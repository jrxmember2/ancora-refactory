<?php

use App\Support\AncoraRouteCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cobranca_standalone_monetary_updates')) {
            Schema::create('cobranca_standalone_monetary_updates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_entity_id')->nullable();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('debtor_name_snapshot', 180);
                $table->string('debtor_document_snapshot', 40)->nullable();
                $table->string('debtor_email_snapshot', 190)->nullable();
                $table->string('debtor_phone_snapshot', 40)->nullable();
                $table->string('index_code', 20)->default('ATM');
                $table->date('calculation_date');
                $table->date('final_date');
                $table->string('interest_type', 20)->default('legal');
                $table->decimal('interest_rate_monthly', 8, 4)->nullable();
                $table->decimal('fine_percent', 8, 4)->default(0);
                $table->string('attorney_fee_type', 20)->default('percent');
                $table->decimal('attorney_fee_value', 12, 4)->default(0);
                $table->decimal('costs_amount', 12, 2)->default(0);
                $table->date('costs_date')->nullable();
                $table->decimal('costs_corrected_amount', 12, 2)->default(0);
                $table->decimal('boleto_fee_total', 12, 2)->default(0);
                $table->decimal('boleto_cancellation_fee_total', 12, 2)->default(0);
                $table->decimal('abatement_amount', 12, 2)->default(0);
                $table->decimal('original_total', 12, 2)->default(0);
                $table->decimal('corrected_total', 12, 2)->default(0);
                $table->decimal('interest_total', 12, 2)->default(0);
                $table->decimal('fine_total', 12, 2)->default(0);
                $table->decimal('debit_total', 12, 2)->default(0);
                $table->decimal('attorney_fee_amount', 12, 2)->default(0);
                $table->decimal('grand_total', 12, 2)->default(0);
                $table->json('payload_json')->nullable();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->timestamps();

                $table->index(['client_entity_id', 'created_at'], 'idx_cobranca_standalone_updates_client_created');
                $table->foreign('client_entity_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_standalone_monetary_update_items')) {
            Schema::create('cobranca_standalone_monetary_update_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_standalone_monetary_update_id');
                $table->unsignedSmallInteger('item_order')->default(1);
                $table->string('reference_label', 100)->nullable();
                $table->date('due_date');
                $table->decimal('original_amount', 12, 2)->default(0);
                $table->decimal('correction_factor', 22, 10)->default(1);
                $table->decimal('corrected_amount', 12, 2)->default(0);
                $table->decimal('interest_months', 10, 4)->default(0);
                $table->decimal('interest_percent', 10, 4)->default(0);
                $table->decimal('interest_amount', 12, 2)->default(0);
                $table->decimal('fine_percent', 8, 4)->default(0);
                $table->decimal('fine_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index('cobranca_standalone_monetary_update_id', 'idx_cobranca_standalone_update_items_update');
                $table->foreign('cobranca_standalone_monetary_update_id', 'fk_cobranca_standalone_update_items_update')
                    ->references('id')->on('cobranca_standalone_monetary_updates')->cascadeOnDelete();
            });
        }

        $this->syncRoutePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_standalone_monetary_update_items');
        Schema::dropIfExists('cobranca_standalone_monetary_updates');
    }

    private function syncRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        foreach (AncoraRouteCatalog::groups() as $groupKey => $group) {
            foreach ($group['routes'] as $routeName => $label) {
                DB::table('route_permissions')->updateOrInsert(
                    ['route_name' => $routeName],
                    ['group_key' => $groupKey, 'label' => $label, 'created_at' => now()]
                );
            }
        }
    }
};
