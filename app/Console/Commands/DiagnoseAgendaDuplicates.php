<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DiagnoseAgendaDuplicates extends Command
{
    protected $signature = 'agenda:diagnose-duplicates
        {--limit=40 : Quantos grupos duplicados detalhar no relatorio}';

    protected $description = 'Relatorio (somente leitura) de compromissos duplicados na agenda. Agrupa de forma tolerante (titulo normalizado + mesmo dia) e mostra as diferencas (horario, responsavel, origem Google x Ancora).';

    public function handle(): int
    {
        if (!Schema::hasTable('agenda_events')) {
            $this->error('Tabela agenda_events nao encontrada.');

            return self::FAILURE;
        }

        $hasSync = Schema::hasTable('agenda_event_syncs');

        $totalEvents = DB::table('agenda_events')->whereNull('deleted_at')->count();
        $trashed = DB::table('agenda_events')->whereNotNull('deleted_at')->count();
        $this->line(sprintf('Eventos ativos: %d  |  excluidos (soft-delete): %d', $totalEvents, $trashed));
        if ($hasSync) {
            $this->line(sprintf('Mapeamentos de sync (agenda_event_syncs): %d', DB::table('agenda_event_syncs')->count()));
        }
        $this->newLine();

        // Carrega todos os eventos ativos e agrupa em memoria por titulo normalizado + dia.
        // Tolerante a fuso horario (mesmo dia, horarios diferentes), espacos e caixa no titulo.
        $events = DB::table('agenda_events')
            ->whereNull('deleted_at')
            ->orderBy('start_at')
            ->get(['id', 'title', 'start_at', 'status', 'type', 'responsible_user_id', 'created_at']);

        $groups = $events->groupBy(function ($event) {
            $title = Str::of(Str::ascii((string) $event->title))->lower()->squish()->toString();
            $day = $event->start_at ? Carbon::parse($event->start_at)->format('Y-m-d') : 'sem-data';

            return $title . '|' . $day;
        })->filter(fn ($bucket) => $bucket->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('Nenhuma duplicata encontrada (titulo normalizado + mesmo dia). Os eventos parecem unicos.');
            $this->newLine();
            $this->comment('Se ainda ve duplicatas na tela: confirme se esta no mesmo ambiente/banco e descreva como aparecem (ex.: dois cards iguais no mesmo dia, ou o mesmo evento em dias diferentes).');

            return self::SUCCESS;
        }

        $totalExtras = $groups->sum(fn ($bucket) => $bucket->count() - 1);
        $this->line(sprintf('Grupos duplicados (titulo + mesmo dia): %d', $groups->count()));
        $this->line(sprintf('Compromissos excedentes (alem de 1 por grupo): %d', $totalExtras));
        $this->newLine();

        $limit = max(1, (int) $this->option('limit'));
        $withSync = 0;
        $withoutSync = 0;

        foreach ($groups->take($limit) as $bucket) {
            $first = $bucket->first();
            $this->line(sprintf('• "%s" — %d copias no dia %s',
                $first->title,
                $bucket->count(),
                Carbon::parse($first->start_at)->format('d/m/Y')
            ));

            foreach ($bucket as $event) {
                $origin = '';
                if ($hasSync) {
                    $syncs = DB::table('agenda_event_syncs')
                        ->where('agenda_event_id', $event->id)
                        ->get(['provider', 'external_event_id']);
                    if ($syncs->isNotEmpty()) {
                        $withSync++;
                        $origin = ' [Google/Outlook: ' . $syncs->map(fn ($s) => $s->provider . ':' . Str::limit($s->external_event_id, 24))->implode(', ') . ']';
                    } else {
                        $withoutSync++;
                        $origin = ' [criado no Ancora / sem sync]';
                    }
                }

                $this->line(sprintf('    id=%d  inicio=%s  resp=%s  status=%s  tipo=%s  criado=%s%s',
                    $event->id,
                    Carbon::parse($event->start_at)->format('d/m/Y H:i'),
                    $event->responsible_user_id ?? 'null',
                    $event->status,
                    $event->type,
                    $event->created_at,
                    $origin
                ));
            }
            $this->newLine();
        }

        if ($groups->count() > $limit) {
            $this->warn(sprintf('... e mais %d grupos nao exibidos (use --limit).', $groups->count() - $limit));
        }

        if ($hasSync) {
            $this->newLine();
            $this->line('Resumo das copias detalhadas:');
            $this->line(sprintf('  - com sync Google/Outlook: %d', $withSync));
            $this->line(sprintf('  - sem sync (criadas no Ancora): %d', $withoutSync));
        }

        $this->newLine();
        $this->info('Relatorio somente leitura: nada foi alterado.');

        return self::SUCCESS;
    }
}
