<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_payables')) {
            return;
        }

        Schema::table('financial_payables', function (Blueprint $table) {
            if (!Schema::hasColumn('financial_payables', 'series_group')) {
                $table->string('series_group', 80)->nullable()->after('recurrence');
                $table->index('series_group', 'idx_fin_payables_series_group');
            }
            if (!Schema::hasColumn('financial_payables', 'series_index')) {
                $table->unsignedInteger('series_index')->nullable()->after('series_group');
            }
            if (!Schema::hasColumn('financial_payables', 'series_total')) {
                $table->unsignedInteger('series_total')->nullable()->after('series_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('financial_payables')) {
            return;
        }

        Schema::table('financial_payables', function (Blueprint $table) {
            if (Schema::hasColumn('financial_payables', 'series_group')) {
                $table->dropIndex('idx_fin_payables_series_group');
            }
            foreach (['series_total', 'series_index', 'series_group'] as $column) {
                if (Schema::hasColumn('financial_payables', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
