<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cobranca_monetary_updates')) {
            return;
        }

        Schema::table('cobranca_monetary_updates', function (Blueprint $table) {
            if (!Schema::hasColumn('cobranca_monetary_updates', 'boleto_fee_total')) {
                $table->decimal('boleto_fee_total', 12, 2)->default(0)->after('costs_corrected_amount');
            }

            if (!Schema::hasColumn('cobranca_monetary_updates', 'boleto_cancellation_fee_total')) {
                $table->decimal('boleto_cancellation_fee_total', 12, 2)->default(0)->after('boleto_fee_total');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cobranca_monetary_updates')) {
            return;
        }

        Schema::table('cobranca_monetary_updates', function (Blueprint $table) {
            if (Schema::hasColumn('cobranca_monetary_updates', 'boleto_cancellation_fee_total')) {
                $table->dropColumn('boleto_cancellation_fee_total');
            }

            if (Schema::hasColumn('cobranca_monetary_updates', 'boleto_fee_total')) {
                $table->dropColumn('boleto_fee_total');
            }
        });
    }
};
