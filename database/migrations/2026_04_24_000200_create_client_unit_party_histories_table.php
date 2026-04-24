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
                $table->unsignedInteger('unit_id');
                $table->string('party_type', 20);
                $table->unsignedInteger('entity_id')->nullable();
                $table->string('display_name_snapshot', 180)->nullable();
                $table->string('document_snapshot', 32)->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();

                $table->index(['unit_id', 'party_type', 'ended_at'], 'idx_client_unit_party_histories_open');
                $table->index(['entity_id', 'party_type'], 'idx_client_unit_party_histories_entity');

                $table->foreign('unit_id', 'fk_client_unit_party_histories_unit')
                    ->references('id')->on('client_units')->cascadeOnDelete();
                $table->foreign('entity_id', 'fk_client_unit_party_histories_entity')
                    ->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('changed_by', 'fk_client_unit_party_histories_user')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

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
};
