<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('administradoras')) {
            return;
        }

        if (!Schema::hasColumn('administradoras', 'client_entity_id')) {
            Schema::table('administradoras', function (Blueprint $table) {
                $table->unsignedInteger('client_entity_id')->nullable()->after('id');
            });
        } else {
            DB::statement('ALTER TABLE administradoras MODIFY client_entity_id INT UNSIGNED NULL');
        }

        if (!$this->indexExists('administradoras', 'idx_administradoras_client_entity')) {
            Schema::table('administradoras', function (Blueprint $table) {
                $table->index('client_entity_id', 'idx_administradoras_client_entity');
            });
        }

        if (Schema::hasTable('client_entities') && !$this->foreignKeyExists('administradoras', 'fk_administradoras_client_entity')) {
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
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
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
