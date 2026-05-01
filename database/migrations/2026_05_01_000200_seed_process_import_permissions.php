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
            'processos.import.index' => 'Acessar importacao de processos',
            'processos.import.template' => 'Baixar modelo de importacao de processos',
            'processos.import.preview' => 'Gerar previa da importacao de processos',
            'processos.import.execute' => 'Executar importacao de processos',
        ];

        foreach ($routes as $routeName => $label) {
            DB::table('route_permissions')->updateOrInsert(
                ['route_name' => $routeName],
                ['group_key' => 'processos', 'label' => $label, 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        DB::table('route_permissions')->whereIn('route_name', [
            'processos.import.index',
            'processos.import.template',
            'processos.import.preview',
            'processos.import.execute',
        ])->delete();
    }
};
