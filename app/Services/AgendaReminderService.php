<?php

namespace App\Services;

use App\Models\AgendaEvent;
use App\Support\AncoraMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AgendaReminderService
{
    public function __construct(private readonly EvolutionApiService $evolution)
    {
    }

    /**
     * Compromissos abertos cuja janela de lembrete ja chegou e que ainda nao foram avisados.
     */
    public function dueReminders(?Carbon $now = null): Collection
    {
        if (!Schema::hasTable('agenda_events') || !Schema::hasColumn('agenda_events', 'reminder_sent_at')) {
            return collect();
        }

        $now = $now ?: now();

        return AgendaEvent::query()
            ->with('responsible')
            ->where('status', 'aberto')
            ->whereNotNull('reminder_minutes')
            ->where('reminder_minutes', '>', 0)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('start_at')
            ->where('start_at', '>', $now)
            // candidatos dentro de no maximo 30 dias; a janela exata por evento e filtrada em PHP
            ->where('start_at', '<=', $now->copy()->addDays(30))
            ->orderBy('start_at')
            ->get()
            ->filter(function (AgendaEvent $event) use ($now) {
                $window = $event->start_at->copy()->subMinutes((int) $event->reminder_minutes);

                return $now->greaterThanOrEqualTo($window);
            })
            ->values();
    }

    /**
     * Processa todos os lembretes devidos. Retorna contadores. Nunca lanca excecao.
     */
    public function run(?Carbon $now = null): array
    {
        $sent = 0;
        $emailed = 0;
        $whatsapped = 0;

        foreach ($this->dueReminders($now) as $event) {
            $channels = $this->sendFor($event);
            $emailed += $channels['email'] ? 1 : 0;
            $whatsapped += $channels['whatsapp'] ? 1 : 0;

            $event->forceFill(['reminder_sent_at' => now()])->save();
            $sent++;
        }

        return ['sent' => $sent, 'emailed' => $emailed, 'whatsapped' => $whatsapped];
    }

    /**
     * @return array{email: bool, whatsapp: bool}
     */
    public function sendFor(AgendaEvent $event): array
    {
        $responsible = $event->responsible;
        $message = $this->message($event);

        $email = false;
        $whatsapp = false;

        $recipients = collect([$responsible->email ?? null]);

        // Inclui os participantes (guardado: a tabela pode nao existir em ambientes minimos/testes).
        try {
            if (Schema::hasTable('agenda_event_participants')) {
                $recipients = $recipients->merge($event->participants()->pluck('email'));
            }
        } catch (\Throwable) {
            // ignora; participantes sao opcionais
        }

        $recipients = $recipients
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($recipients->isNotEmpty()) {
            try {
                AncoraMail::applySmtpSettings();
                Mail::raw($message, function ($mail) use ($recipients, $event) {
                    $mail->to($recipients->all())->subject('Lembrete de agenda: ' . $event->title);
                });
                $email = true;
            } catch (\Throwable $e) {
                Log::warning('agenda.reminder.email_failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            }
        }

        $phone = preg_replace('/\D+/', '', (string) ($responsible->phone ?? '')) ?: '';
        if ($phone !== '') {
            try {
                $settings = $this->evolution->currentSettings();
                if ($this->evolution->hasReadyConfiguration($settings)) {
                    $this->evolution->sendTextMessage($settings, $phone, $message);
                    $whatsapp = true;
                }
            } catch (\Throwable $e) {
                Log::warning('agenda.reminder.whatsapp_failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            }
        }

        return ['email' => $email, 'whatsapp' => $whatsapp];
    }

    private function message(AgendaEvent $event): string
    {
        $when = $event->start_at->format('d/m/Y') . ($event->all_day ? '' : ' as ' . $event->start_at->format('H:i'));
        $lines = [
            ($event->is_fatal ? '[PRAZO FATAL] ' : '') . 'Lembrete: ' . $event->title,
            'Quando: ' . $when,
        ];

        if (trim((string) $event->location) !== '') {
            $lines[] = 'Local: ' . $event->location;
        }
        if (trim((string) $event->description) !== '') {
            $lines[] = '';
            $lines[] = (string) $event->description;
        }

        return implode("\n", $lines);
    }
}
