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

        foreach ([
            'demandas.create' => 'Nova demanda',
            'demandas.store' => 'Salvar demanda',
        ] as $routeName => $label) {
            $payload = [
                'group_key' => 'demandas',
                'label' => $label,
            ];

            if (Schema::hasColumn('route_permissions', 'created_at')) {
                $payload['created_at'] = now();
            }

            if (Schema::hasColumn('route_permissions', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('route_permissions')->updateOrInsert(
                ['route_name' => $routeName],
                $payload
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        DB::table('route_permissions')
            ->whereIn('route_name', ['demandas.create', 'demandas.store'])
            ->delete();
    }
};
