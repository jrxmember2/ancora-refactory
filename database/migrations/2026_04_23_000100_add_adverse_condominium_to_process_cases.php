<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('process_cases') || Schema::hasColumn('process_cases', 'adverse_condominium_id')) {
            return;
        }

        Schema::table('process_cases', function (Blueprint $table) {
            $table->integer('adverse_condominium_id')->nullable()->after('adverse_entity_id');
            $table->index('adverse_condominium_id', 'idx_process_cases_adverse_condo');
            $table->foreign('adverse_condominium_id', 'fk_process_cases_adverse_condo')
                ->references('id')
                ->on('client_condominiums')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('process_cases') || !Schema::hasColumn('process_cases', 'adverse_condominium_id')) {
            return;
        }

        $this->dropForeignIfExists('process_cases', 'fk_process_cases_adverse_condo');

        Schema::table('process_cases', function (Blueprint $table) {
            $table->dropIndex('idx_process_cases_adverse_condo');
            $table->dropColumn('adverse_condominium_id');
        });
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
