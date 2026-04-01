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
        Schema::create('route_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 60)->index();
            $table->string('route_name', 120)->unique();
            $table->string('label', 180);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('user_route_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('route_permission_id');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->unique(['user_id', 'route_permission_id'], 'uq_user_route_permissions');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('route_permission_id')->references('id')->on('route_permissions')->cascadeOnDelete();
        });

        foreach (AncoraRouteCatalog::groups() as $groupKey => $group) {
            foreach ($group['routes'] as $routeName => $label) {
                DB::table('route_permissions')->updateOrInsert(
                    ['route_name' => $routeName],
                    ['group_key' => $groupKey, 'label' => $label, 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_route_permissions');
        Schema::dropIfExists('route_permissions');
    }
};
