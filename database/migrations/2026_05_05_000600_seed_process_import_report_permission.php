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

        DB::table('route_permissions')->updateOrInsert(
            ['route_name' => 'processos.import.report'],
            [
                'group_key' => 'processos',
                'label' => 'Exportar relatorio de pendencias da importacao de processos',
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        DB::table('route_permissions')
            ->where('route_name', 'processos.import.report')
            ->delete();
    }
};
