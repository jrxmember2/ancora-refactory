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
            'cobrancas.import.index' => 'Acessar importação de inadimplência',
            'cobrancas.import.preview' => 'Analisar planilha de inadimplência',
            'cobrancas.import.show' => 'Visualizar lote de importação de inadimplência',
            'cobrancas.import.process' => 'Processar lote de inadimplência',
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
            'cobrancas.import.index',
            'cobrancas.import.preview',
            'cobrancas.import.show',
            'cobrancas.import.process',
        ])->delete();
    }
};
