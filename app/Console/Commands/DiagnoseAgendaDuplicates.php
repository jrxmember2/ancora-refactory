<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseAgendaDuplicates extends Command
{
    protected $signature = 'agenda:diagnose-duplicates
        {--limit=40 : Quantos grupos duplicados detalhar no relatorio}';

    protected $description = 'Relatorio (somente leitura) de compromissos duplicados na agenda agrupando por titulo + inicio, mostrando a origem (Ancora x importado do Google/Outlook).';

    public function handle(): int
    {
        if (!Schema::hasTable('agenda_events')) {
            $this->error('Tabela agenda_events nao encontrada.');

            return self::FAILURE;
        }

        $hasSync = Schema::hasTable('agenda_event_syncs');

        // Agrupa eventos NAO excluidos por (responsavel, titulo, inicio). Mesmo titulo no mesmo
        // horario para o mesmo responsavel = forte indicio de duplicata real.
        $groups = DB::table('agenda_events')
            ->select('responsible_user_id', 'title', 'start_at', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->groupBy('responsible_user_id', 'title', 'start_at')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        if ($groups->isEmpty()) {
            $this->info('Nenhuma duplicata por (titulo + inicio) encontrada na agenda.');

            return self::SUCCESS;
        }

        $totalExtras = $groups->sum(fn ($g) => (int) $g->total - 1);
        $this->line(sprintf('Grupos duplicados (mesmo titulo + inicio): %d', $groups->count()));
        $this->line(sprintf('Compromissos excedentes (alem de 1 por grupo): %d', $totalExtras));
        $this->newLine();

        $limit = max(1, (int) $this->option('limit'));
        $withSyncCount = 0;
        $withoutSyncCount = 0;

        foreach ($groups->take($limit) as $group) {
            $events = DB::table('agenda_events')
                ->where('responsible_user_id', $group->responsible_user_id)
                ->where('title', $group->title)
                ->where('start_at', $group->start_at)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'status', 'type', 'created_at']);

            $this->line(sprintf(
                '• "%s" @ %s (responsavel %s) — %d copias',
                $group->title,
                $group->start_at,
                $group->responsible_user_id ?? 'null',
                $group->total
            ));

            foreach ($events as $event) {
                $syncInfo = '';
                if ($hasSync) {
                    $syncs = DB::table('agenda_event_syncs')
                        ->where('agenda_event_id', $event->id)
                        ->get(['provider', 'external_event_id']);

                    if ($syncs->isNotEmpty()) {
                        $withSyncCount++;
                        $syncInfo = ' [importado/sincronizado: '
                            . $syncs->map(fn ($s) => $s->provider . ':' . $s->external_event_id)->implode(', ') . ']';
                    } else {
                        $withoutSyncCount++;
                        $syncInfo = ' [criado no Ancora / sem mapeamento externo]';
                    }
                }

                $this->line(sprintf(
                    '    id=%d  status=%s  tipo=%s  criado=%s%s',
                    $event->id,
                    $event->status,
                    $event->type,
                    $event->created_at,
                    $syncInfo
                ));
            }
            $this->newLine();
        }

        if ($groups->count() > $limit) {
            $this->warn(sprintf('... e mais %d grupos nao exibidos (use --limit para ver mais).', $groups->count() - $limit));
        }

        if ($hasSync) {
            $this->newLine();
            $this->line('Resumo das copias detalhadas:');
            $this->line(sprintf('  - com mapeamento externo (Google/Outlook): %d', $withSyncCount));
            $this->line(sprintf('  - sem mapeamento (criadas no Ancora): %d', $withoutSyncCount));
        }

        $this->newLine();
        $this->info('Relatorio somente leitura: nada foi alterado.');

        return self::SUCCESS;
    }
}
