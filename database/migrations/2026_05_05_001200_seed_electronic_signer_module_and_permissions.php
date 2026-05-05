<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->seedModule();
        $this->seedRoutePermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->whereIn('route_name', array_keys($this->routePermissions()))->delete();
        }

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')->where('slug', 'assinador')->delete();
        }
    }

    private function seedModule(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'assinador'],
            [
                'name' => 'Assinador Eletrônico',
                'icon_class' => 'fa-solid fa-signature',
                'route_prefix' => '/assinador-eletronico',
                'is_enabled' => true,
                'sort_order' => 38,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        foreach ($this->routePermissions() as $routeName => $label) {
            $payload = [
                'group_key' => 'assinador',
                'label' => $label,
            ];

            if (Schema::hasColumn('route_permissions', 'created_at')) {
                $payload['created_at'] = now();
            }

            if (Schema::hasColumn('route_permissions', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('route_permissions')->updateOrInsert(['route_name' => $routeName], $payload);
        }
    }

    private function routePermissions(): array
    {
        return [
            'assinador.dashboard' => 'Acessar dashboard do Assinador Eletrônico',
            'assinador.index' => 'Listar documentos do Assinador Eletrônico',
            'assinador.create' => 'Abrir nova assinatura avulsa',
            'assinador.store' => 'Enviar documento avulso para assinatura',
            'assinador.show' => 'Visualizar assinatura avulsa',
            'assinador.signatures.sync' => 'Sincronizar assinatura avulsa',
            'assinador.signatures.download' => 'Baixar artefatos da assinatura avulsa',
        ];
    }
};
