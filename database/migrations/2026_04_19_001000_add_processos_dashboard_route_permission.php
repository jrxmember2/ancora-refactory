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

        $payload = [
            'group_key' => 'processos',
            'label' => 'Acessar dashboard de processos',
        ];

        if (Schema::hasColumn('route_permissions', 'created_at')) {
            $payload['created_at'] = now();
        }

        if (Schema::hasColumn('route_permissions', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('route_permissions')->updateOrInsert(
            ['route_name' => 'processos.dashboard'],
            $payload
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        DB::table('route_permissions')->where('route_name', 'processos.dashboard')->delete();
    }
};
