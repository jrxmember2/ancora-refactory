<?php

use App\Support\AncoraRouteCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncRoutePermission('ia.office-chat.index');
        $this->syncRoutePermission('ia.office-chat.ask');
        $this->syncRoutePermission('ia.office-chat.show');
        $this->syncRoutePermission('ia.office-chat.delete');
    }

    public function down(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        DB::table('route_permissions')
            ->whereIn('route_name', [
                'ia.office-chat.index',
                'ia.office-chat.ask',
                'ia.office-chat.show',
                'ia.office-chat.delete',
            ])
            ->delete();
    }

    private function syncRoutePermission(string $routeName): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        $groups = AncoraRouteCatalog::groups();
        $label = $groups['ia']['routes'][$routeName] ?? $routeName;
        $payload = [
            'group_key' => 'ia',
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
};
