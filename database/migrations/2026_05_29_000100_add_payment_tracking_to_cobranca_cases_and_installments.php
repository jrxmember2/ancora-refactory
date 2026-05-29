<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cobranca_cases') && !Schema::hasColumn('cobranca_cases', 'entry_paid_at')) {
            Schema::table('cobranca_cases', function (Blueprint $table) {
                $table->date('entry_paid_at')->nullable()->after('entry_due_date');
            });
        }

        if (Schema::hasTable('cobranca_case_installments') && !Schema::hasColumn('cobranca_case_installments', 'paid_at')) {
            Schema::table('cobranca_case_installments', function (Blueprint $table) {
                $table->date('paid_at')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('cobranca_cases') && Schema::hasColumn('cobranca_cases', 'entry_paid_at')) {
            DB::table('cobranca_cases')
                ->whereNull('entry_paid_at')
                ->where('entry_status', 'pago')
                ->whereNotNull('entry_due_date')
                ->update([
                    'entry_paid_at' => DB::raw('entry_due_date'),
                ]);
        }

        if (Schema::hasTable('cobranca_case_installments') && Schema::hasColumn('cobranca_case_installments', 'paid_at')) {
            DB::table('cobranca_case_installments')
                ->whereNull('paid_at')
                ->where('status', 'paga')
                ->whereNotNull('due_date')
                ->update([
                    'paid_at' => DB::raw('due_date'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cobranca_case_installments') && Schema::hasColumn('cobranca_case_installments', 'paid_at')) {
            Schema::table('cobranca_case_installments', function (Blueprint $table) {
                $table->dropColumn('paid_at');
            });
        }

        if (Schema::hasTable('cobranca_cases') && Schema::hasColumn('cobranca_cases', 'entry_paid_at')) {
            Schema::table('cobranca_cases', function (Blueprint $table) {
                $table->dropColumn('entry_paid_at');
            });
        }
    }
};
