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
        if (!Schema::hasTable('cobranca_monetary_index_factors')) {
            Schema::create('cobranca_monetary_index_factors', function (Blueprint $table) {
                $table->id();
                $table->string('index_code', 20)->default('ATM');
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->decimal('factor', 22, 10);
                $table->string('source_label', 180)->nullable();
                $table->timestamps();

                $table->unique(['index_code', 'year', 'month'], 'uq_cobranca_index_factor_month');
                $table->index(['index_code', 'year', 'month'], 'idx_cobranca_index_factor_lookup');
            });
        }

        if (!Schema::hasTable('cobranca_monetary_updates')) {
            Schema::create('cobranca_monetary_updates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
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
                $table->decimal('abatement_amount', 12, 2)->default(0);
                $table->decimal('original_total', 12, 2)->default(0);
                $table->decimal('corrected_total', 12, 2)->default(0);
                $table->decimal('interest_total', 12, 2)->default(0);
                $table->decimal('fine_total', 12, 2)->default(0);
                $table->decimal('debit_total', 12, 2)->default(0);
                $table->decimal('attorney_fee_amount', 12, 2)->default(0);
                $table->decimal('grand_total', 12, 2)->default(0);
                $table->json('payload_json')->nullable();
                $table->boolean('applied_to_case')->default(false);
                $table->timestamp('applied_at')->nullable();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->unsignedBigInteger('applied_by')->nullable();
                $table->timestamps();

                $table->index(['cobranca_case_id', 'created_at'], 'idx_cobranca_monetary_updates_case_created');
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->cascadeOnDelete();
                $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('applied_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_monetary_update_items')) {
            Schema::create('cobranca_monetary_update_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_monetary_update_id');
                $table->unsignedBigInteger('cobranca_case_quota_id')->nullable();
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

                $table->index('cobranca_monetary_update_id', 'idx_cobranca_update_items_update');
                $table->index('cobranca_case_quota_id', 'idx_cobranca_update_items_quota');
                $table->foreign('cobranca_monetary_update_id', 'fk_cobranca_update_items_update')
                    ->references('id')->on('cobranca_monetary_updates')->cascadeOnDelete();
                $table->foreign('cobranca_case_quota_id', 'fk_cobranca_update_items_quota')
                    ->references('id')->on('cobranca_case_quotas')->nullOnDelete();
            });
        }

        $this->seedTjesFactors();
        $this->syncRoutePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_monetary_update_items');
        Schema::dropIfExists('cobranca_monetary_updates');
        Schema::dropIfExists('cobranca_monetary_index_factors');
    }

    private function seedTjesFactors(): void
    {
        if (!Schema::hasTable('cobranca_monetary_index_factors')) {
            return;
        }

        $path = database_path('data/tjes_atm_factors.php');
        if (!is_file($path)) {
            return;
        }

        $now = now();
        foreach ((array) require $path as $row) {
            DB::table('cobranca_monetary_index_factors')->updateOrInsert(
                [
                    'index_code' => 'ATM',
                    'year' => (int) $row['year'],
                    'month' => (int) $row['month'],
                ],
                [
                    'factor' => (string) $row['factor'],
                    'source_label' => 'CGJES - Fatores de Atualizacao Monetaria, relatorio 15/04/2026',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
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
