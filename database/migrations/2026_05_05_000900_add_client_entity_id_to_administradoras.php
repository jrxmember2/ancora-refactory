<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('administradoras') || !Schema::hasTable('client_entities')) {
            return;
        }

        $this->dropForeignIfExists('administradoras', 'fk_administradoras_client_entity');

        if (!Schema::hasColumn('administradoras', 'client_entity_id')) {
            Schema::table('administradoras', function (Blueprint $table) {
                $table->integer('client_entity_id')->nullable()->after('id');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $referenceType = $this->referenceColumnType('client_entities', 'id') ?: 'INT';
            DB::statement("ALTER TABLE administradoras MODIFY client_entity_id {$referenceType} NULL");
        }

        if (!$this->indexExists('administradoras', 'idx_administradoras_client_entity')) {
            Schema::table('administradoras', function (Blueprint $table) {
                $table->index('client_entity_id', 'idx_administradoras_client_entity');
            });
        }

        if (!$this->foreignKeyExists('administradoras', 'fk_administradoras_client_entity')) {
            DB::statement('ALTER TABLE administradoras ADD CONSTRAINT fk_administradoras_client_entity FOREIGN KEY (client_entity_id) REFERENCES client_entities(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('administradoras') || !Schema::hasColumn('administradoras', 'client_entity_id')) {
            return;
        }

        Schema::table('administradoras', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_administradoras_client_entity');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('idx_administradoras_client_entity');
            } catch (\Throwable) {
            }

            $table->dropColumn('client_entity_id');
        });
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true; // fora do MySQL, evita ADD CONSTRAINT (DDL especifico)
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->foreignKeyExists($table, $constraint)) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true; // fora do MySQL, pula a criacao de indice via reconciliacao
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    private function referenceColumnType(string $table, string $column): ?string
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('COLUMN_TYPE');
    }
};
