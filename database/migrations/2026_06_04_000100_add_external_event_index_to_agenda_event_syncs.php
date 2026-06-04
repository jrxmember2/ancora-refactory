<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agenda_event_syncs')) {
            return;
        }

        // Indice por (connection_id, external_event_id): acelera o de-dup do inbound e torna o
        // lockForUpdate efetivo (gap lock por chave) para impedir importacao duplicada concorrente.
        // O indice UNICO so e criado pelo comando agenda:dedupe-google-imports apos remover as
        // duplicatas existentes (criar unique aqui quebraria o deploy se ja houver duplicatas).
        try {
            Schema::table('agenda_event_syncs', function (Blueprint $table) {
                $table->index(['connection_id', 'external_event_id'], 'idx_agenda_event_syncs_external');
            });
        } catch (\Throwable) {
            // indice ja existe: ignora
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('agenda_event_syncs')) {
            return;
        }

        try {
            Schema::table('agenda_event_syncs', function (Blueprint $table) {
                $table->dropIndex('idx_agenda_event_syncs_external');
            });
        } catch (\Throwable) {
            // ignora
        }
    }
};
