<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts') || Schema::hasColumn('contracts', 'parent_contract_id')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_contract_id')->nullable()->after('template_id');
            $table->index('parent_contract_id', 'idx_contracts_parent');
            $table->foreign('parent_contract_id')->references('id')->on('contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('contracts') || !Schema::hasColumn('contracts', 'parent_contract_id')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['parent_contract_id']);
            $table->dropIndex('idx_contracts_parent');
            $table->dropColumn('parent_contract_id');
        });
    }
};
