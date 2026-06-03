<?php

namespace App\Services;

use App\Models\AgendaEvent;
use App\Models\AgendaEventReminder;
use App\Support\Agenda\AgendaMessageTemplates;
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
     * Lembretes (linhas de agenda_event_reminders) cuja janela ja chegou e que ainda nao
     * foram enviados, de eventos abertos e futuros.
     *
     * @return Collection<int, AgendaEventReminder>
     */
    public function dueReminders(?Carbon $now = null): Collection
    {
        if (!Schema::hasTable('agenda_event_reminders') || !Schema::hasTable('agenda_events')) {
            return collect();
        }

        $now = $now ?: now();

        return AgendaEventReminder::query()
            ->whereNull('sent_at')
            ->whereHas('event', function ($query) use ($now) {
                $query->where('status', 'aberto')
                    ->whereNull('deleted_at')
                    ->whereNotNull('start_at')
                    ->where('start_at', '>', $now)
                    // candidatos dentro de no maximo 30 dias; janela exata filtrada em PHP
                    ->where('start_at', '<=', $now->copy()->addDays(30));
            })
            ->with(['event.responsible'])
            ->orderBy('agenda_event_id')
            ->get()
            ->filter(function (AgendaEventReminder $reminder) use ($now) {
                $event = $reminder->event;
                if (!$event || !$event->start_at) {
                    return false;
                }

                $window = $event->start_at->copy()->subMinutes((int) $reminder->minutes_before);

                return $now->greaterThanOrEqualTo($window);
            })
            ->values();
    }

    /**
     * Processa todos os lembretes devidos. Retorna contadores. Nunca lanca excecao.
     *
     * @return array{sent: int, emailed: int, whatsapped: int}
     */
    public function run(?Carbon $now = null): array
    {
        $sent = 0;
        $emailed = 0;
        $whatsapped = 0;

        foreach ($this->dueReminders($now) as $reminder) {
            $channels = $this->sendFor($reminder->event, (int) $reminder->minutes_before);
            $emailed += $channels['email'] ? 1 : 0;
            $whatsapped += $channels['whatsapp'] ? 1 : 0;

            $reminder->forceFill(['sent_at' => now()])->save();
            // Mantem o campo legado coerente para o feed ICS/compatibilidade.
            $reminder->event?->forceFill(['reminder_sent_at' => now()])->save();
            $sent++;
        }

        return ['sent' => $sent, 'emailed' => $emailed, 'whatsapped' => $whatsapped];
    }

    /**
     * Envia um lembrete pelos canais habilitados no evento.
     *
     * @return array{email: bool, whatsapp: bool}
     */
    public function sendFor(AgendaEvent $event, int $minutesBefore): array
    {
        $responsible = $event->responsible;
        $message = AgendaMessageTemplates::reminder($event, $minutesBefore, $responsible->name ?? null);

        $email = false;
        $whatsapp = false;

        // E-mail (responsavel + participantes + copia), se habilitado no evento.
        if ($event->remind_email) {
            $email = $this->sendEmail($event, $message);
        }

        // WhatsApp (responsavel + copia), se habilitado no evento.
        if ($event->remind_whatsapp) {
            $whatsapp = $this->sendWhatsapp($event, $minutesBefore, $message);
        }

        return ['email' => $email, 'whatsapp' => $whatsapp];
    }

    private function sendEmail(AgendaEvent $event, string $message): bool
    {
        $recipients = collect([$event->responsible->email ?? null]);

        // Participantes (guardado: a tabela pode nao existir em ambientes minimos/testes).
        try {
            if (Schema::hasTable('agenda_event_participants')) {
                $recipients = $recipients->merge($event->participants()->pluck('email'));
            }
        } catch (\Throwable) {
            // participantes sao opcionais
        }

        if ($event->copy_enabled) {
            $recipients->push($event->copy_email);
        }

        $recipients = $recipients
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return false;
        }

        try {
            AncoraMail::applySmtpSettings();
            Mail::raw($message, function ($mail) use ($recipients, $event) {
                $mail->to($recipients->all())->subject(AgendaMessageTemplates::reminderSubject($event));
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('agenda.reminder.email_failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendWhatsapp(AgendaEvent $event, int $minutesBefore, string $message): bool
    {
        $sentAny = false;

        try {
            $settings = $this->evolution->currentSettings();
            if (!$this->evolution->hasReadyConfiguration($settings)) {
                return false;
            }

            // Responsavel.
            $responsiblePhone = $this->normalizePhone($event->responsible->phone ?? '');
            if ($responsiblePhone !== '') {
                $this->evolution->sendTextMessage($settings, $responsiblePhone, $message);
                $sentAny = true;
            }

            // Copia (mensagem personalizada com o nome informado).
            if ($event->copy_enabled) {
                $copyPhone = $this->normalizePhone($event->copy_phone ?? '');
                if ($copyPhone !== '') {
                    $copyMessage = AgendaMessageTemplates::reminder($event, $minutesBefore, $event->copy_name);
                    $this->evolution->sendTextMessage($settings, $copyPhone, $copyMessage);
                    $sentAny = true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('agenda.reminder.whatsapp_failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }

        return $sentAny;
    }

    private function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?: '';
    }
}
