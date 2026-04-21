<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_condominiums')) {
            return;
        }

        Schema::table('client_condominiums', function (Blueprint $table) {
            if (!Schema::hasColumn('client_condominiums', 'boleto_fee_amount')) {
                $table->decimal('boleto_fee_amount', 12, 2)->nullable()->after('characteristics');
            }

            if (!Schema::hasColumn('client_condominiums', 'boleto_cancellation_fee_amount')) {
                $table->decimal('boleto_cancellation_fee_amount', 12, 2)->nullable()->after('boleto_fee_amount');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('client_condominiums')) {
            return;
        }

        Schema::table('client_condominiums', function (Blueprint $table) {
            if (Schema::hasColumn('client_condominiums', 'boleto_cancellation_fee_amount')) {
                $table->dropColumn('boleto_cancellation_fee_amount');
            }

            if (Schema::hasColumn('client_condominiums', 'boleto_fee_amount')) {
                $table->dropColumn('boleto_fee_amount');
            }
        });
    }
};
