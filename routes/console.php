<?php

use App\Services\Ai\AiUsageLimiter;
use App\Services\AgendaDailyDigestService;
use App\Services\AgendaReminderService;
use App\Services\Calendar\CalendarSubscriptionManager;
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

Artisan::command('ai:reset-monthly-usage', function (AiUsageLimiter $limiter) {
    $resetUsers = $limiter->resetEligibleActiveUsers();

    if ($resetUsers === 0) {
        $this->info('Nenhum usuario ativo do portal precisava de reset mensal neste momento.');

        return 0;
    }

    $this->info(sprintf(
        'Reset mensal de IA concluido para %d usuario(s) ativo(s) do portal em %s.',
        $resetUsers,
        now()->format('d/m/Y')
    ));

    return 0;
})->purpose('Reseta o uso mensal de IA dos usuarios ativos do Portal do Cliente');

Artisan::command('agenda:send-reminders', function (AgendaReminderService $service) {
    $result = $service->run();
    $this->info(sprintf(
        'Agenda: %d lembrete(s) enviado(s) (%d e-mail, %d WhatsApp).',
        $result['sent'],
        $result['emailed'],
        $result['whatsapped']
    ));

    return 0;
})->purpose('Envia lembretes de prazos e compromissos da agenda por e-mail e WhatsApp');

Schedule::command('processos:datajud-sync')
    ->dailyAt('06:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/datajud-sync.log'));

Schedule::command('agenda:send-reminders')
    ->everyFiveMinutes()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(10);

Artisan::command('agenda:renew-calendar-subscriptions', function (CalendarSubscriptionManager $manager) {
    $renewed = $manager->renewExpiring();
    $this->info(sprintf('Agenda: %d inscricao(oes) de webhook renovada(s).', $renewed));

    return 0;
})->purpose('Renova as inscricoes de webhooks da agenda (Google/Microsoft) proximas de expirar');

Schedule::command('agenda:renew-calendar-subscriptions')
    ->everySixHours()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30);

Artisan::command('agenda:daily-digest', function (AgendaDailyDigestService $service) {
    $result = $service->run();
    $this->info(sprintf(
        'Agenda: resumo diario enviado para %d de %d responsavel(eis) com compromisso hoje.',
        $result['sent'],
        $result['users']
    ));

    if (!empty($result['skipped'])) {
        $this->warn('Ignorado: ' . $result['skipped']);
    }

    return 0;
})->purpose('Envia por WhatsApp o resumo da agenda do dia para cada responsavel');

// Dias uteis as 05h: resumo da agenda do dia no WhatsApp do responsavel.
Schedule::command('agenda:daily-digest')
    ->weekdays()
    ->at('05:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(30);
