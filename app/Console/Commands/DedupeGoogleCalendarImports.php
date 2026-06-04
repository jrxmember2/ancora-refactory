<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DedupeGoogleCalendarImports extends Command
{
    protected $signature = 'agenda:dedupe-google-imports
        {--apply : Executa a limpeza de fato (sem esta flag e apenas um relatorio / dry-run)}
        {--connection= : Limita a uma conexao especifica (id)}';

    protected $description = 'Remove compromissos duplicados importados do Google/Outlook (mesmo external_event_id) mantendo o mais antigo.';

    public function handle(): int
    {
        if (!Schema::hasTable('agenda_event_syncs') || !Schema::hasTable('agenda_events')) {
            $this->error('Tabelas de sincronizacao nao encontradas.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        // Grupos (connection_id, external_event_id) com mais de um mapeamento = duplicatas.
        $groupsQuery = DB::table('agenda_event_syncs')
            ->select('connection_id', 'external_event_id', DB::raw('COUNT(*) as total'), DB::raw('MIN(agenda_event_id) as keep_id'))
            ->groupBy('connection_id', 'external_event_id')
            ->havingRaw('COUNT(*) > 1');

        if ($this->option('connection')) {
            $groupsQuery->where('connection_id', (int) $this->option('connection'));
        }

        $groups = $groupsQuery->get();

        if ($groups->isEmpty()) {
            $this->info('Nenhuma duplicata encontrada. Nada a fazer.');
            $this->maybeAddUniqueIndex($apply);

            return self::SUCCESS;
        }

        $extraIds = [];
        foreach ($groups as $group) {
            $ids = DB::table('agenda_event_syncs')
                ->where('connection_id', $group->connection_id)
                ->where('external_event_id', $group->external_event_id)
                ->where('agenda_event_id', '!=', $group->keep_id)
                ->pluck('agenda_event_id')
                ->all();
            $extraIds = array_merge($extraIds, $ids);
        }
        $extraIds = array_values(array_unique(array_map('intval', $extraIds)));

        $this->line(sprintf('Grupos duplicados: %d', $groups->count()));
        $this->line(sprintf('Compromissos a remover: %d (mantendo 1 por evento do Google)', count($extraIds)));

        if (!$apply) {
            $this->warn('DRY-RUN: nada foi alterado. Rode novamente com --apply para executar a limpeza.');

            return self::SUCCESS;
        }

        // Apaga os compromissos extras diretamente. O FK cascadeOnDelete remove os agenda_event_syncs,
        // lembretes, participantes e anexos. NAO dispara delete para o Google (sem observer/job aqui),
        // entao o evento original no Google permanece intacto.
        $removed = 0;
        foreach (array_chunk($extraIds, 500) as $chunk) {
            $removed += DB::transaction(fn () => DB::table('agenda_events')->whereIn('id', $chunk)->delete());
        }

        $this->info(sprintf('Removidos %d compromissos duplicados.', $removed));

        $this->maybeAddUniqueIndex(true);

        return self::SUCCESS;
    }

    /**
     * Apos a limpeza, promove o indice para UNICO em (connection_id, external_event_id),
     * garantindo no banco que o mesmo evento externo nunca mais sera importado em duplicidade.
     */
    private function maybeAddUniqueIndex(bool $apply): void
    {
        if (!$apply) {
            return;
        }

        $remaining = DB::table('agenda_event_syncs')
            ->select('connection_id', 'external_event_id', DB::raw('COUNT(*) as total'))
            ->groupBy('connection_id', 'external_event_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($remaining > 0) {
            $this->warn('Ainda ha duplicatas; indice unico nao foi criado.');

            return;
        }

        try {
            Schema::table('agenda_event_syncs', function (Blueprint $table) {
                $table->unique(['connection_id', 'external_event_id'], 'uq_agenda_event_syncs_external');
            });
            // Indice nao-unico equivalente passa a ser redundante.
            try {
                Schema::table('agenda_event_syncs', function (Blueprint $table) {
                    $table->dropIndex('idx_agenda_event_syncs_external');
                });
            } catch (\Throwable) {
                // pode nao existir
            }
            $this->info('Indice unico uq_agenda_event_syncs_external criado.');
        } catch (\Throwable $e) {
            $this->warn('Indice unico ja existia ou nao pode ser criado: ' . $e->getMessage());
        }
    }
}
