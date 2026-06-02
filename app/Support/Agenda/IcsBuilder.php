<?php

namespace App\Support\Agenda;

use App\Models\AgendaEvent;
use Illuminate\Support\Carbon;

class IcsBuilder
{
    /**
     * Monta um calendario (VCALENDAR) com varios eventos para assinatura (Google/Outlook/Apple).
     */
    public static function calendar(iterable $events, string $calendarName): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Ancora//Agenda//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escape($calendarName),
            'X-WR-TIMEZONE:America/Sao_Paulo',
        ];

        foreach ($events as $event) {
            foreach (self::vevent($event) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        return self::render($lines);
    }

    /**
     * Monta um VCALENDAR com um unico evento (download "adicionar ao calendario").
     */
    public static function singleEvent(AgendaEvent $event): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Ancora//Agenda//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach (self::vevent($event) as $line) {
            $lines[] = $line;
        }

        $lines[] = 'END:VCALENDAR';

        return self::render($lines);
    }

    private static function vevent(AgendaEvent $event): array
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ancora.local';
        $start = $event->start_at instanceof Carbon ? $event->start_at->copy() : Carbon::parse((string) $event->start_at);
        $stamp = Carbon::now('UTC');

        $lines = ['BEGIN:VEVENT'];
        $lines[] = 'UID:agenda-' . $event->id . '@' . $host;
        $lines[] = 'DTSTAMP:' . self::utc($stamp);

        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $end = ($event->end_at instanceof Carbon ? $event->end_at->copy() : $start->copy())->addDay();
            $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
        } else {
            $lines[] = 'DTSTART:' . self::utc($start);
            $end = $event->end_at instanceof Carbon ? $event->end_at->copy() : $start->copy()->addHour();
            $lines[] = 'DTEND:' . self::utc($end);
        }

        $summaryPrefix = $event->is_fatal ? '[PRAZO FATAL] ' : '';
        $lines[] = 'SUMMARY:' . self::escape($summaryPrefix . (string) $event->title);

        $description = trim((string) $event->description);
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape($description);
        }

        $location = trim((string) $event->location);
        if ($location !== '') {
            $lines[] = 'LOCATION:' . self::escape($location);
        }

        $lines[] = 'STATUS:' . ($event->status === 'cancelado' ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private static function utc(Carbon $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private static function escape(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(';', '\\;', $value);

        return $value;
    }

    /**
     * Junta as linhas com CRLF e aplica o dobramento de linhas (max 75 octetos) exigido pelo RFC 5545.
     */
    private static function render(array $lines): string
    {
        $folded = array_map([self::class, 'fold'], $lines);

        return implode("\r\n", $folded) . "\r\n";
    }

    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $current = '';
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if (strlen($current . $char) > 73) {
                $chunks[] = $current;
                $current = ' ' . $char; // continuação começa com espaço
            } else {
                $current .= $char;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return implode("\r\n", $chunks);
    }
}
