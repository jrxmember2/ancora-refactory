<?php

namespace Tests\Unit\Agenda;

use App\Models\AgendaEvent;
use App\Support\Agenda\IcsBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IcsBuilderTest extends TestCase
{
    private function makeEvent(array $attrs = []): AgendaEvent
    {
        $event = new AgendaEvent();
        $event->id = $attrs['id'] ?? 10;
        $event->title = $attrs['title'] ?? 'Audiencia';
        $event->description = $attrs['description'] ?? null;
        $event->location = $attrs['location'] ?? null;
        $event->status = $attrs['status'] ?? 'aberto';
        $event->is_fatal = $attrs['is_fatal'] ?? false;
        $event->all_day = $attrs['all_day'] ?? false;
        $event->start_at = $attrs['start_at'] ?? Carbon::parse('2026-06-10 14:30:00', 'America/Sao_Paulo');
        $event->end_at = $attrs['end_at'] ?? null;

        return $event;
    }

    public function test_single_event_produces_valid_vcalendar_with_crlf(): void
    {
        $ics = IcsBuilder::singleEvent($this->makeEvent());

        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('DTSTART:', $ics);
        $this->assertStringContainsString('STATUS:CONFIRMED', $ics);
        $this->assertStringContainsString('UID:agenda-10@', $ics);
    }

    public function test_summary_escapes_special_characters_and_marks_fatal(): void
    {
        $ics = IcsBuilder::singleEvent($this->makeEvent([
            'title' => 'Audiencia, com; caracteres',
            'is_fatal' => true,
        ]));

        $this->assertStringContainsString('SUMMARY:[PRAZO FATAL] Audiencia\\, com\\; caracteres', $ics);
    }

    public function test_all_day_event_uses_date_value(): void
    {
        $ics = IcsBuilder::singleEvent($this->makeEvent([
            'all_day' => true,
            'start_at' => Carbon::parse('2026-06-10 00:00:00', 'America/Sao_Paulo'),
        ]));

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260610', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260611', $ics);
    }

    public function test_cancelled_event_marked_cancelled(): void
    {
        $ics = IcsBuilder::singleEvent($this->makeEvent(['status' => 'cancelado']));
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
    }

    public function test_calendar_wraps_multiple_events(): void
    {
        $events = collect([
            $this->makeEvent(['id' => 1, 'title' => 'Evento 1']),
            $this->makeEvent(['id' => 2, 'title' => 'Evento 2']),
        ]);

        $ics = IcsBuilder::calendar($events, 'Agenda Teste');

        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('X-WR-CALNAME:Agenda Teste', $ics);
    }
}
