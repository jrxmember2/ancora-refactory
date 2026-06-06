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

        // PASSO 1: mesmo (connection_id, external_event_id) mapeado mais de uma vez na MESMA conexao.
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

        // PASSO 2: mesmo evento do Google (provider + external_event_id) importado como compromissos
        // DISTINTOS por conexoes diferentes (dois usuarios conectaram o mesmo calendario).
        [$crossGroups, $repoints, $crossExtraIds] = $this->detectCrossConnectionDuplicates();

        $this->line(sprintf('Passo 1 — duplicatas na mesma conexao: %d grupos, %d compromissos a remover', $groups->count(), count($extraIds)));
        $this->line(sprintf('Passo 2 — mesmo evento em conexoes diferentes: %d grupos, %d compromissos a remover', $crossGroups, count($crossExtraIds)));

        if ($groups->isEmpty() && $crossGroups === 0) {
            $this->info('Nenhuma duplicata encontrada. Nada a fazer.');
            $this->maybeAddUniqueIndex($apply);

            return self::SUCCESS;
        }

        if (!$apply) {
            $this->warn('DRY-RUN: nada foi alterado. Rode novamente com --apply para executar a limpeza.');

            return self::SUCCESS;
        }

        // Passo 2 primeiro: reaponta os mapeamentos das copias para o compromisso mantido, para que
        // as conexoes continuem mapeadas (sem reimportar) e os webhooks atualizem um unico evento.
        foreach ($repoints as $syncId => $keepId) {
            DB::table('agenda_event_syncs')->where('id', $syncId)->update(['agenda_event_id' => $keepId]);
        }

        // Apaga os compromissos extras diretamente. O FK cascadeOnDelete remove os agenda_event_syncs
        // remanescentes, lembretes, participantes e anexos. NAO dispara delete para o Google (sem
        // observer/job aqui), entao o evento original no Google permanece intacto.
        $allExtraIds = array_values(array_unique(array_merge($extraIds, $crossExtraIds)));
        $removed = 0;
        foreach (array_chunk($allExtraIds, 500) as $chunk) {
            $removed += DB::transaction(fn () => DB::table('agenda_events')->whereIn('id', $chunk)->delete());
        }

        $this->info(sprintf('Removidos %d compromissos duplicados (passo 1 + passo 2).', $removed));

        $this->maybeAddUniqueIndex(true);

        return self::SUCCESS;
    }

    /**
     * Detecta o mesmo evento do Google/Outlook (provider + external_event_id) que foi importado como
     * compromissos distintos por conexoes diferentes. Considera apenas compromissos ativos (nao
     * excluidos). Retorna [qtdGrupos, [sync_id => keep_id], [ids_dos_compromissos_extras]].
     *
     * @return array{0:int,1:array<int,int>,2:array<int,int>}
     */
    private function detectCrossConnectionDuplicates(): array
    {
        $connectionFilter = $this->option('connection') ? (int) $this->option('connection') : null;

        $liveSyncs = DB::table('agenda_event_syncs as s')
            ->join('agenda_events as e', 'e.id', '=', 's.agenda_event_id')
            ->whereNull('e.deleted_at')
            ->when($connectionFilter, fn ($query) => $query->where('s.connection_id', $connectionFilter))
            ->get(['s.id as sync_id', 's.provider', 's.external_event_id', 's.agenda_event_id']);

        $byExternal = $liveSyncs->groupBy(fn ($row) => $row->provider . '|' . $row->external_event_id);

        $groups = 0;
        $repoints = [];
        $extraIds = [];

        foreach ($byExternal as $rows) {
            $distinctEvents = $rows->pluck('agenda_event_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            if ($distinctEvents->count() < 2) {
                continue;
            }

            $groups++;
            $keepId = (int) $distinctEvents->first();

            foreach ($rows as $row) {
                if ((int) $row->agenda_event_id !== $keepId) {
                    $repoints[(int) $row->sync_id] = $keepId;
                    $extraIds[] = (int) $row->agenda_event_id;
                }
            }
        }

        return [$groups, $repoints, array_values(array_unique($extraIds))];
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
