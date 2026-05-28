<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_receivables')) {
            return;
        }

        Schema::table('financial_receivables', function (Blueprint $table) {
            if (!Schema::hasColumn('financial_receivables', 'recurrence')) {
                $table->string('recurrence', 60)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('financial_receivables', 'series_group')) {
                $table->string('series_group', 80)->nullable()->after('recurrence');
                $table->index('series_group', 'idx_fin_receivables_series_group');
            }
            if (!Schema::hasColumn('financial_receivables', 'series_index')) {
                $table->unsignedInteger('series_index')->nullable()->after('series_group');
            }
            if (!Schema::hasColumn('financial_receivables', 'series_total')) {
                $table->unsignedInteger('series_total')->nullable()->after('series_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('financial_receivables')) {
            return;
        }

        Schema::table('financial_receivables', function (Blueprint $table) {
            if (Schema::hasColumn('financial_receivables', 'series_group')) {
                $table->dropIndex('idx_fin_receivables_series_group');
            }
            foreach (['series_total', 'series_index', 'series_group', 'recurrence'] as $column) {
                if (Schema::hasColumn('financial_receivables', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
