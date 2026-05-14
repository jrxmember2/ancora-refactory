<?php

use App\Support\AncoraRouteCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('process_cases') && !Schema::hasColumn('process_cases', 'push_automatic')) {
            Schema::table('process_cases', function (Blueprint $table) {
                $table->boolean('push_automatic')->default(false)->after('is_private');
            });
        }

        $this->syncRoutePermission('cobrancas.notifications.send');
    }

    public function down(): void
    {
        if (Schema::hasTable('process_cases') && Schema::hasColumn('process_cases', 'push_automatic')) {
            Schema::table('process_cases', function (Blueprint $table) {
                $table->dropColumn('push_automatic');
            });
        }

        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->where('route_name', 'cobrancas.notifications.send')->delete();
        }
    }

    private function syncRoutePermission(string $routeName): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        $groups = AncoraRouteCatalog::groups();
        $label = $groups['cobrancas']['routes'][$routeName] ?? $routeName;
        $payload = [
            'group_key' => 'cobrancas',
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
