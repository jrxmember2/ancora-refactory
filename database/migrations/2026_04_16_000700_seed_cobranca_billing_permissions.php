<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        $routes = [
            'cobrancas.billing.report' => 'Acessar relatório de faturamento de cobrança',
            'cobrancas.billing.report.pdf' => 'Exportar PDF do relatório de faturamento',
        ];

        foreach ($routes as $routeName => $label) {
            DB::table('route_permissions')->updateOrInsert(
                ['route_name' => $routeName],
                ['group_key' => 'cobrancas', 'label' => $label, 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        DB::table('route_permissions')->whereIn('route_name', [
            'cobrancas.billing.report',
            'cobrancas.billing.report.pdf',
        ])->delete();
    }
};
