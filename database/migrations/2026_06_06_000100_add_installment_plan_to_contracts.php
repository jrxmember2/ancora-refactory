<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts')) {
            return;
        }

        if (!Schema::hasColumn('contracts', 'installment_plan')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->json('installment_plan')->nullable()->after('installment_quantity');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('contracts') || !Schema::hasColumn('contracts', 'installment_plan')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('installment_plan');
        });
    }
};
