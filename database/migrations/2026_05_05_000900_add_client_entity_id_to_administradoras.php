<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('administradoras') || Schema::hasColumn('administradoras', 'client_entity_id')) {
            return;
        }

        Schema::table('administradoras', function (Blueprint $table) {
            $table->unsignedBigInteger('client_entity_id')->nullable()->after('id');
            $table->index('client_entity_id', 'idx_administradoras_client_entity');
            $table->foreign('client_entity_id', 'fk_administradoras_client_entity')
                ->references('id')
                ->on('client_entities')
                ->nullOnDelete();
        });
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
};
