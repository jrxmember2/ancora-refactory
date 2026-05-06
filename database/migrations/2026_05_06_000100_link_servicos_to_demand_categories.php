<?php

use App\Models\DemandCategory;
use App\Models\Servico;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('servicos')) {
            return;
        }

        $this->dropForeignIfExists('servicos', 'fk_servicos_demand_category');

        if (!Schema::hasColumn('servicos', 'demand_category_id')) {
            Schema::table('servicos', function (Blueprint $table) {
                $table->unsignedBigInteger('demand_category_id')->nullable()->after('id');
            });
        }

        if (!$this->indexExists('servicos', 'uq_servicos_demand_category')) {
            Schema::table('servicos', function (Blueprint $table) {
                $table->unique('demand_category_id', 'uq_servicos_demand_category');
            });
        }

        if (!$this->indexExists('servicos', 'idx_servicos_demand_category')) {
            Schema::table('servicos', function (Blueprint $table) {
                $table->index('demand_category_id', 'idx_servicos_demand_category');
            });
        }

        if (Schema::hasTable('demand_categories') && !$this->foreignKeyExists('servicos', 'fk_servicos_demand_category')) {
            DB::statement('ALTER TABLE servicos ADD CONSTRAINT fk_servicos_demand_category FOREIGN KEY (demand_category_id) REFERENCES demand_categories(id) ON DELETE SET NULL');
        }

        if (!Schema::hasTable('demand_categories')) {
            return;
        }

        DemandCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (DemandCategory $category) {
                $service = Servico::query()
                    ->where('demand_category_id', $category->id)
                    ->first();

                if (!$service) {
                    $service = Servico::query()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim((string) $category->name))])
                        ->first();
                }

                $payload = [
                    'demand_category_id' => $category->id,
                    'name' => trim((string) $category->name),
                    'description' => 'Sincronizado automaticamente de Configuracoes > Demandas > Servicos.',
                    'is_active' => (bool) $category->is_active,
                    'sort_order' => (int) $category->sort_order,
                ];

                if ($service) {
                    $service->update($payload);
                    return;
                }

                Servico::query()->create($payload);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('servicos') || !Schema::hasColumn('servicos', 'demand_category_id')) {
            return;
        }

        $this->dropForeignIfExists('servicos', 'fk_servicos_demand_category');

        Schema::table('servicos', function (Blueprint $table) {
            try {
                $table->dropUnique('uq_servicos_demand_category');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('idx_servicos_demand_category');
            } catch (\Throwable) {
            }

            $table->dropColumn('demand_category_id');
        });
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if ($this->foreignKeyExists($table, $constraint)) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
