<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts')) {
            return;
        }

        if (!Schema::hasColumn('contracts', 'installment_quantity')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->unsignedInteger('installment_quantity')->nullable()->after('billing_type');
            });
        }

        DB::table('contracts')
            ->where('billing_type', 'unica')
            ->whereNull('installment_quantity')
            ->update(['installment_quantity' => 1]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('contracts') || !Schema::hasColumn('contracts', 'installment_quantity')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('installment_quantity');
        });
    }
};
