<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('demand_tags')) {
            Schema::create('demand_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('color_hex', 7)->default('#2563EB');
                $table->string('status_key', 40)->default('em_andamento');
                $table->string('portal_label', 120)->nullable();
                $table->boolean('show_on_portal')->default(true);
                $table->unsignedSmallInteger('sla_hours')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_closing')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'idx_demand_tags_active_order');
                $table->index(['status_key', 'is_active'], 'idx_demand_tags_status_active');
            });
        }

        $this->ensureDemandFields();
        $this->seedTags();
        $this->backfillDemands();
        $this->seedRoutePermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('demands')) {
            Schema::table('demands', function (Blueprint $table) {
                if (Schema::hasColumn('demands', 'demand_tag_id')) {
                    $this->dropForeignIfExists('demands', 'fk_demands_tag');
                    $table->dropColumn('demand_tag_id');
                }
                if (Schema::hasColumn('demands', 'sla_started_at')) {
                    $table->dropColumn('sla_started_at');
                }
            });
        }

        Schema::dropIfExists('demand_tags');

        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')
                ->whereIn('route_name', [
                    'demandas.dashboard',
                    'demandas.kanban',
                    'demandas.tag.update',
                    'config.demand-tags.store',
                    'config.demand-tags.update',
                    'config.demand-tags.delete',
                ])
                ->delete();
        }
    }

    private function ensureDemandFields(): void
    {
        if (!Schema::hasTable('demands')) {
            return;
        }

        Schema::table('demands', function (Blueprint $table) {
            if (!Schema::hasColumn('demands', 'demand_tag_id')) {
                $table->unsignedBigInteger('demand_tag_id')->nullable()->after('category_id');
                $table->index('demand_tag_id', 'idx_demands_tag');
                $table->foreign('demand_tag_id', 'fk_demands_tag')->references('id')->on('demand_tags')->nullOnDelete();
            }
            if (!Schema::hasColumn('demands', 'sla_started_at')) {
                $table->dateTime('sla_started_at')->nullable()->after('closed_at');
            }
            if (!Schema::hasColumn('demands', 'sla_due_at')) {
                $table->dateTime('sla_due_at')->nullable()->after('sla_started_at');
            }
        });
    }

    private function seedTags(): void
    {
        if (!Schema::hasTable('demand_tags')) {
            return;
        }

        $items = [
            ['Aberta', 'aberta', '#2563EB', true, 24, true, false, 10],
            ['Triagem', 'em_triagem', '#F59E0B', true, 12, false, false, 20],
            ['Em andamento', 'em_andamento', '#7C3AED', true, 48, false, false, 30],
            ['Aguardando cliente', 'aguardando_cliente', '#0EA5E9', true, null, false, false, 40],
            ['Aguardando formalizacao', 'aguardando_formalizacao_acordo', '#14B8A6', true, 72, false, false, 50],
            ['Interno estrategico', 'em_andamento', '#475569', false, null, false, false, 60],
            ['Concluida', 'concluida', '#10B981', true, null, false, true, 90],
            ['Cancelada', 'cancelada', '#EF4444', true, null, false, true, 100],
        ];

        foreach ($items as [$name, $status, $color, $showPortal, $slaHours, $default, $closing, $sort]) {
            DB::table('demand_tags')->updateOrInsert(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'color_hex' => $color,
                    'status_key' => $status,
                    'portal_label' => $showPortal ? $name : null,
                    'show_on_portal' => $showPortal,
                    'sla_hours' => $slaHours,
                    'is_default' => $default,
                    'is_closing' => $closing,
                    'is_active' => true,
                    'sort_order' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function backfillDemands(): void
    {
        if (!Schema::hasTable('demands') || !Schema::hasColumn('demands', 'demand_tag_id')) {
            return;
        }

        $tagsByStatus = DB::table('demand_tags')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('status_key');

        DB::table('demands')
            ->whereNull('demand_tag_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($tagsByStatus) {
                foreach ($rows as $demand) {
                    $tag = ($tagsByStatus[$demand->status] ?? collect())->first()
                        ?: ($tagsByStatus['aberta'] ?? collect())->first();
                    if (!$tag) {
                        continue;
                    }

                    $payload = ['demand_tag_id' => $tag->id];
                    if ($tag->sla_hours && !$demand->sla_due_at) {
                        $startedAt = $demand->created_at ?: now();
                        $payload['sla_started_at'] = $startedAt;
                        $payload['sla_due_at'] = \Illuminate\Support\Carbon::parse($startedAt)->addHours((int) $tag->sla_hours);
                    }

                    DB::table('demands')->where('id', $demand->id)->update($payload);
                }
            });
    }

    private function seedRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        $items = [
            'demandas.dashboard' => ['demandas', 'Acessar dashboard de demandas'],
            'demandas.kanban' => ['demandas', 'Acessar kanban de demandas'],
            'demandas.tag.update' => ['demandas', 'Mover demanda no kanban'],
            'config.demand-tags.store' => ['config', 'Cadastrar tag de demanda'],
            'config.demand-tags.update' => ['config', 'Editar tag de demanda'],
            'config.demand-tags.delete' => ['config', 'Excluir tag de demanda'],
        ];

        foreach ($items as $routeName => [$groupKey, $label]) {
            $payload = ['group_key' => $groupKey, 'label' => $label];
            if (Schema::hasColumn('route_permissions', 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn('route_permissions', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('route_permissions')->updateOrInsert(['route_name' => $routeName], $payload);
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }
};
