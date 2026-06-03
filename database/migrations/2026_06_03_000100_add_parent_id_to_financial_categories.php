<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_categories') || Schema::hasColumn('financial_categories', 'parent_id')) {
            return;
        }

        Schema::table('financial_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->index('parent_id', 'idx_fin_categories_parent');
            $table->foreign('parent_id')->references('id')->on('financial_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('financial_categories') || !Schema::hasColumn('financial_categories', 'parent_id')) {
            return;
        }

        Schema::table('financial_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex('idx_fin_categories_parent');
            $table->dropColumn('parent_id');
        });
    }
};
