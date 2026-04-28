<?php

use App\Support\Contracts\ContractVariableCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_variables')) {
            return;
        }

        foreach (ContractVariableCatalog::definitions() as $index => $variable) {
            DB::table('contract_variables')->updateOrInsert(
                ['key' => $variable['key']],
                [
                    'label' => $variable['label'],
                    'description' => $variable['description'] ?? null,
                    'source' => $variable['source'] ?? null,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Mantem o catalogo sincronizado mesmo em rollback para nao perder personalizacoes.
    }
};
