<?php

namespace App\Services;

use App\Models\AgendaEvent;
use App\Support\Agenda\AgendaMessageTemplates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resumo diario da agenda enviado por WhatsApp ao responsavel (rotina das 05h, seg-sex).
 * Envia apenas para responsaveis que tem compromisso aberto no dia e telefone cadastrado.
 */
class AgendaDailyDigestService
{
    public function __construct(private readonly EvolutionApiService $evolution)
    {
    }

    /**
     * @return array{sent: int, users: int, skipped: string|null}
     */
    public function run(?Carbon $date = null): array
    {
        $date = $date ?: now();

        if (!Schema::hasTable('agenda_events')) {
            return ['sent' => 0, 'users' => 0, 'skipped' => 'no_table'];
        }

        $eventsByUser = AgendaEvent::query()
            ->with('responsible')
            ->where('status', 'aberto')
            ->whereNull('deleted_at')
            ->whereNotNull('responsible_user_id')
            ->whereBetween('start_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('start_at')
            ->get()
            ->groupBy('responsible_user_id');

        if ($eventsByUser->isEmpty()) {
            return ['sent' => 0, 'users' => 0, 'skipped' => null];
        }

        $settings = $this->evolution->currentSettings();
        if (!$this->evolution->hasReadyConfiguration($settings)) {
            return ['sent' => 0, 'users' => $eventsByUser->count(), 'skipped' => 'evolution_not_ready'];
        }

        $sent = 0;
        $users = 0;

        foreach ($eventsByUser as $group) {
            $user = $group->first()?->responsible;
            if (!$user) {
                continue;
            }

            $users++;
            $phone = preg_replace('/\D+/', '', (string) ($user->phone ?? '')) ?: '';
            if ($phone === '') {
                continue;
            }

            try {
                $message = AgendaMessageTemplates::dailyDigest($user, $group, $date);
                $this->evolution->sendTextMessage($settings, $phone, $message);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('agenda.digest.whatsapp_failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'users' => $users, 'skipped' => null];
    }
}
