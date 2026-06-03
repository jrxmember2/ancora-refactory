<?php

namespace App\Support\Agenda;

use App\Models\AgendaEvent;
use App\Models\User;
use App\Support\AncoraSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Textos das notificacoes da agenda (WhatsApp/e-mail).
 *
 * - Lembrete de compromisso: template "Amigavel" (escolhido pelo escritorio).
 * - Resumo diario das 05h: template "Bom dia" (escolhido pelo escritorio).
 *
 * Mantido centralizado para que e-mail e WhatsApp usem exatamente o mesmo texto.
 */
class AgendaMessageTemplates
{
    /**
     * Lembrete de um compromisso, X minutos antes. Template B (Amigavel):
     * "Oi, {nome}! ⏰ Faltam {antecedencia} para: {titulo} 🗓 {data} às {hora}{local}"
     */
    public static function reminder(AgendaEvent $event, int $minutesBefore, ?string $recipientName = null): string
    {
        $name = self::firstName($recipientName ?? $event->responsible?->name);
        $greeting = $name !== '' ? "Oi, {$name}!" : 'Oi!';

        $when = self::eventWhen($event);
        $antecedencia = self::humanizeMinutes($minutesBefore);

        $message = "{$greeting} \u{23F0} Faltam {$antecedencia} para: {$event->title}\n\u{1F5D3} {$when}";
        $message .= self::locationSuffix($event);

        return $message;
    }

    /**
     * Resumo do dia (WhatsApp das 05h). Template A (Bom dia):
     * "Bom dia, {nome}! ☀️ Sua agenda de hoje ({data}): {lista} Tenha um ótimo dia!"
     *
     * @param Collection<int, AgendaEvent> $events
     */
    public static function dailyDigest(User $user, Collection $events, Carbon $date): string
    {
        $name = self::firstName($user->name);
        $greeting = $name !== '' ? "Bom dia, {$name}!" : 'Bom dia!';

        $lines = $events
            ->sortBy(fn (AgendaEvent $e) => $e->start_at?->timestamp ?? 0)
            ->map(function (AgendaEvent $e) {
                $time = $e->all_day ? 'Dia inteiro' : ($e->start_at?->format('H:i') ?? '');
                $flag = $e->is_fatal ? "\u{26A0}\u{FE0F} " : '';

                return "\u{2022} {$time} \u{2014} {$flag}{$e->title}";
            })
            ->implode("\n");

        return "{$greeting} \u{2600}\u{FE0F} Sua agenda de hoje ({$date->format('d/m')}):\n\n{$lines}\n\nTenha um \u{00F3}timo dia!";
    }

    /**
     * Assunto do e-mail de lembrete.
     */
    public static function reminderSubject(AgendaEvent $event): string
    {
        return ($event->is_fatal ? '[PRAZO FATAL] ' : '') . 'Lembrete de agenda: ' . $event->title;
    }

    public static function firstName(?string $fullName): string
    {
        $name = trim((string) $fullName);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);

        return $parts[0] ?? '';
    }

    private static function eventWhen(AgendaEvent $event): string
    {
        if (!$event->start_at) {
            return '';
        }

        $date = $event->start_at->format('d/m/Y');

        if ($event->all_day) {
            return "{$date} (dia inteiro)";
        }

        return "{$date} \u{00E0}s " . $event->start_at->format('H:i');
    }

    private static function locationSuffix(AgendaEvent $event): string
    {
        $location = trim((string) $event->location);

        return $location !== '' ? " | Local: {$location}" : '';
    }

    /**
     * Humaniza a antecedencia do lembrete em pt-BR (5 minutos, 1 hora, 2 dias, 1 semana).
     */
    private static function humanizeMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' ' . ($minutes === 1 ? 'minuto' : 'minutos');
        }

        if ($minutes < 1440) {
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;
            $base = $hours . ' ' . ($hours === 1 ? 'hora' : 'horas');

            return $rest === 0 ? $base : $base . ' e ' . $rest . ($rest === 1 ? ' minuto' : ' minutos');
        }

        if ($minutes % 10080 === 0) {
            $weeks = intdiv($minutes, 10080);

            return $weeks . ' ' . ($weeks === 1 ? 'semana' : 'semanas');
        }

        $days = intdiv($minutes, 1440);
        $rest = $minutes % 1440;
        $base = $days . ' ' . ($days === 1 ? 'dia' : 'dias');

        if ($rest === 0) {
            return $base;
        }

        $hours = intdiv($rest, 60);

        return $hours > 0
            ? $base . ' e ' . $hours . ($hours === 1 ? ' hora' : ' horas')
            : $base;
    }

    public static function officeName(): string
    {
        return (string) (AncoraSettings::brand()['company_name'] ?? 'Âncora');
    }
}
