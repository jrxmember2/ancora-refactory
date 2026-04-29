<?php

use App\Services\ProcessDataJudService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('processos:datajud-sync {--case=}', function () {
    $service = app(ProcessDataJudService::class);
    $caseId = (int) $this->option('case');

    if ($caseId > 0) {
        $case = \App\Models\ProcessCase::query()->find($caseId);
        if (!$case) {
            $this->error('Processo nao encontrado.');
            return 1;
        }

        $result = $service->syncCase($case);
        $this->info('Processo verificado. Movimentos novos: ' . ($result['created'] ?? 0) . '. Movimentos atualizados: ' . ($result['refreshed'] ?? 0));
        if (!empty($result['error'])) {
            $this->warn('Aviso: ' . $result['error']);
        }

        return empty($result['error']) ? 0 : 1;
    }

    $summary = $service->syncAll();
    $this->info(sprintf(
        'DataJud: %d processo(s) verificado(s), %d atualizado(s), %d movimento(s) criado(s), %d movimento(s) revisado(s), %d ignorado(s).',
        $summary['checked'],
        $summary['updated'],
        $summary['created'],
        $summary['refreshed'],
        $summary['skipped']
    ));

    foreach ($summary['errors'] as $error) {
        $this->warn($error);
    }

    return empty($summary['errors']) ? 0 : 1;
})->purpose('Sincroniza movimentacoes de processos com a API publica do DataJud');

Schedule::command('processos:datajud-sync')
    ->dailyAt('06:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/datajud-sync.log'));
