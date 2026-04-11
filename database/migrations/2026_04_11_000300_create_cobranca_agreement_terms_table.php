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
        if (!Schema::hasTable('cobranca_agreement_terms')) {
            Schema::create('cobranca_agreement_terms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
                $table->string('template_type', 30)->default('extrajudicial');
                $table->string('title', 255);
                $table->longText('body_text');
                $table->json('payload_json')->nullable();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('printed_at')->nullable();
                $table->timestamps();

                $table->unique('cobranca_case_id', 'uq_cobranca_agreement_terms_case');
                $table->foreign('cobranca_case_id')->references('id')->on('cobranca_cases')->cascadeOnDelete();
                $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('route_permissions')) {
            foreach (AncoraRouteCatalog::groups() as $groupKey => $group) {
                foreach ($group['routes'] as $routeName => $label) {
                    DB::table('route_permissions')->updateOrInsert(
                        ['route_name' => $routeName],
                        ['group_key' => $groupKey, 'label' => $label, 'created_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_agreement_terms');
    }
};
