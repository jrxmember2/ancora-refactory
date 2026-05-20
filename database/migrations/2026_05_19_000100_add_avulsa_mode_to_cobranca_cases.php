<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cobranca_cases')) {
            return;
        }

        Schema::table('cobranca_cases', function (Blueprint $table) {
            if (!Schema::hasColumn('cobranca_cases', 'case_mode')) {
                $table->string('case_mode', 20)->default('condominial')->after('os_number');
            }
            if (!Schema::hasColumn('cobranca_cases', 'debtor_cpf_snapshot')) {
                $table->string('debtor_cpf_snapshot', 20)->nullable()->after('debtor_document_snapshot');
            }
            if (!Schema::hasColumn('cobranca_cases', 'debtor_cnh_snapshot')) {
                $table->string('debtor_cnh_snapshot', 40)->nullable()->after('debtor_cpf_snapshot');
            }
            if (!Schema::hasColumn('cobranca_cases', 'debtor_rg_snapshot')) {
                $table->string('debtor_rg_snapshot', 40)->nullable()->after('debtor_cnh_snapshot');
            }
            if (!Schema::hasColumn('cobranca_cases', 'debtor_birth_date')) {
                $table->date('debtor_birth_date')->nullable()->after('debtor_rg_snapshot');
            }
            if (!Schema::hasColumn('cobranca_cases', 'debtor_address_json')) {
                $table->json('debtor_address_json')->nullable()->after('debtor_birth_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cobranca_cases')) {
            return;
        }

        Schema::table('cobranca_cases', function (Blueprint $table) {
            foreach ([
                'debtor_address_json',
                'debtor_birth_date',
                'debtor_rg_snapshot',
                'debtor_cnh_snapshot',
                'debtor_cpf_snapshot',
                'case_mode',
            ] as $column) {
                if (Schema::hasColumn('cobranca_cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
