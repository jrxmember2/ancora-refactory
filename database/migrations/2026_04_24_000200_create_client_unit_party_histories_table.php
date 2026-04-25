<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_unit_party_histories')) {
            Schema::create('client_unit_party_histories', function (Blueprint $table) {
                $table->id();
                $table->integer('unit_id');
                $table->string('party_type', 20);
                $table->integer('entity_id')->nullable();
                $table->string('display_name_snapshot', 180)->nullable();
                $table->string('document_snapshot', 32)->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();

                $table->index(['unit_id', 'party_type', 'ended_at'], 'idx_client_unit_party_histories_open');
                $table->index(['entity_id', 'party_type'], 'idx_client_unit_party_histories_entity');
            });
        }

        $this->repairForeignKeys();
        $this->seedCurrentLinks();
    }

    public function down(): void
    {
        Schema::dropIfExists('client_unit_party_histories');
    }

    private function seedCurrentLinks(): void
    {
        if (!Schema::hasTable('client_unit_party_histories') || !Schema::hasTable('client_units')) {
            return;
        }

        if (DB::table('client_unit_party_histories')->exists()) {
            return;
        }

        $now = now();

        DB::table('client_units')
            ->leftJoin('client_entities as owner_entity', 'owner_entity.id', '=', 'client_units.owner_entity_id')
            ->leftJoin('client_entities as tenant_entity', 'tenant_entity.id', '=', 'client_units.tenant_entity_id')
            ->select([
                'client_units.id',
                'client_units.created_at',
                'client_units.updated_by',
                'client_units.owner_entity_id',
                'owner_entity.display_name as owner_name',
                'owner_entity.cpf_cnpj as owner_document',
                'client_units.tenant_entity_id',
                'tenant_entity.display_name as tenant_name',
                'tenant_entity.cpf_cnpj as tenant_document',
            ])
            ->orderBy('client_units.id')
            ->chunk(200, function ($units) use ($now) {
                $rows = [];

                foreach ($units as $unit) {
                    $startedAt = $unit->created_at ?: $now;

                    if (!empty($unit->owner_entity_id)) {
                        $rows[] = [
                            'unit_id' => (int) $unit->id,
                            'party_type' => 'owner',
                            'entity_id' => (int) $unit->owner_entity_id,
                            'display_name_snapshot' => $unit->owner_name,
                            'document_snapshot' => $unit->owner_document,
                            'started_at' => $startedAt,
                            'ended_at' => null,
                            'changed_by' => $unit->updated_by ?: null,
                            'created_at' => $startedAt,
                            'updated_at' => $startedAt,
                        ];
                    }

                    if (!empty($unit->tenant_entity_id)) {
                        $rows[] = [
                            'unit_id' => (int) $unit->id,
                            'party_type' => 'tenant',
                            'entity_id' => (int) $unit->tenant_entity_id,
                            'display_name_snapshot' => $unit->tenant_name,
                            'document_snapshot' => $unit->tenant_document,
                            'started_at' => $startedAt,
                            'ended_at' => null,
                            'changed_by' => $unit->updated_by ?: null,
                            'created_at' => $startedAt,
                            'updated_at' => $startedAt,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('client_unit_party_histories')->insert($rows);
                }
            });
    }

    private function repairForeignKeys(): void
    {
        if (!Schema::hasTable('client_unit_party_histories')) {
            return;
        }

        $this->dropForeignIfExists('client_unit_party_histories', 'fk_client_unit_party_histories_unit');
        $this->dropForeignIfExists('client_unit_party_histories', 'fk_client_unit_party_histories_entity');
        $this->dropForeignIfExists('client_unit_party_histories', 'fk_client_unit_party_histories_user');

        if (Schema::hasColumn('client_unit_party_histories', 'unit_id')) {
            DB::statement('ALTER TABLE client_unit_party_histories MODIFY unit_id INT NOT NULL');
        }

        if (Schema::hasColumn('client_unit_party_histories', 'entity_id')) {
            DB::statement('ALTER TABLE client_unit_party_histories MODIFY entity_id INT NULL');
        }

        if (Schema::hasColumn('client_unit_party_histories', 'changed_by')) {
            DB::statement('ALTER TABLE client_unit_party_histories MODIFY changed_by BIGINT UNSIGNED NULL');
        }

        if (Schema::hasTable('client_units')
            && Schema::hasColumn('client_unit_party_histories', 'unit_id')
            && !$this->foreignKeyExists('client_unit_party_histories', 'fk_client_unit_party_histories_unit')) {
            DB::statement('ALTER TABLE client_unit_party_histories ADD CONSTRAINT fk_client_unit_party_histories_unit FOREIGN KEY (unit_id) REFERENCES client_units(id) ON DELETE CASCADE');
        }

        if (Schema::hasTable('client_entities')
            && Schema::hasColumn('client_unit_party_histories', 'entity_id')
            && !$this->foreignKeyExists('client_unit_party_histories', 'fk_client_unit_party_histories_entity')) {
            DB::statement('ALTER TABLE client_unit_party_histories ADD CONSTRAINT fk_client_unit_party_histories_entity FOREIGN KEY (entity_id) REFERENCES client_entities(id) ON DELETE SET NULL');
        }

        if (Schema::hasTable('users')
            && Schema::hasColumn('client_unit_party_histories', 'changed_by')
            && !$this->foreignKeyExists('client_unit_party_histories', 'fk_client_unit_party_histories_user')) {
            DB::statement('ALTER TABLE client_unit_party_histories ADD CONSTRAINT fk_client_unit_party_histories_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL');
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if ($this->foreignKeyExists($table, $constraint)) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
