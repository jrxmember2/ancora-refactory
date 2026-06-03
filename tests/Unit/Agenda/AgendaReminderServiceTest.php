<?php

namespace Tests\Unit\Agenda;

use App\Models\AgendaEvent;
use App\Services\AgendaReminderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaReminderServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Schema::create('agenda_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 220);
            $table->string('type', 40)->default('compromisso');
            $table->string('status', 30)->default('aberto');
            $table->boolean('is_fatal')->default(false);
            $table->boolean('all_day')->default(false);
            $table->string('location', 220)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->boolean('remind_email')->default(true);
            $table->boolean('remind_whatsapp')->default(true);
            $table->boolean('copy_enabled')->default(false);
            $table->string('copy_name', 160)->nullable();
            $table->string('copy_phone', 30)->nullable();
            $table->string('copy_email', 190)->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('agenda_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agenda_event_id');
            $table->unsignedInteger('minutes_before');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('agenda_event_reminders');
        Schema::dropIfExists('agenda_events');
        parent::tearDown();
    }

    private function event(array $attrs): AgendaEvent
    {
        return AgendaEvent::query()->create(array_merge([
            'title' => 'Compromisso',
            'type' => 'prazo',
            'status' => 'aberto',
            'responsible_user_id' => null,
        ], $attrs));
    }

    private function eventWithReminder(array $attrs, int $minutes, ?Carbon $sentAt = null): AgendaEvent
    {
        $event = $this->event($attrs);
        $event->reminders()->create([
            'minutes_before' => $minutes,
            'sent_at' => $sentAt,
        ]);

        return $event;
    }

    public function test_due_reminders_selects_only_reminders_inside_the_window(): void
    {
        $due = $this->eventWithReminder(['start_at' => now()->addMinutes(20)], 30);        // janela ja chegou
        $this->eventWithReminder(['start_at' => now()->addDays(2)], 30);                   // janela ainda longe
        $this->event(['start_at' => now()->addMinutes(20)]);                              // sem lembrete cadastrado
        $this->eventWithReminder(['start_at' => now()->addMinutes(20)], 30, now());        // ja enviado
        $this->eventWithReminder(['start_at' => now()->subMinutes(5)], 30);                // ja passou
        $this->eventWithReminder(['start_at' => now()->addMinutes(20), 'status' => 'concluido'], 30); // concluido

        $dueReminders = $this->service()->dueReminders();

        $this->assertCount(1, $dueReminders);
        $this->assertSame($due->id, $dueReminders->first()->agenda_event_id);
    }

    public function test_multiple_reminders_on_same_event_fire_independently(): void
    {
        $event = $this->event(['start_at' => now()->addMinutes(12)]);
        $event->reminders()->create(['minutes_before' => 15]); // janela ja chegou (start -15min)
        $event->reminders()->create(['minutes_before' => 5]);  // ainda nao (start -5min = +7min no futuro)

        $dueReminders = $this->service()->dueReminders();

        $this->assertCount(1, $dueReminders);
        $this->assertSame(15, (int) $dueReminders->first()->minutes_before);
    }

    public function test_run_marks_reminder_as_sent_and_is_idempotent(): void
    {
        $event = $this->eventWithReminder(['start_at' => now()->addMinutes(10)], 30);

        $result = $this->service()->run();

        $this->assertSame(1, $result['sent']);
        $this->assertNotNull($event->reminders()->first()->sent_at);
        $this->assertNotNull($event->fresh()->reminder_sent_at);

        // Segunda execucao nao reenvia.
        $second = $this->service()->run();
        $this->assertSame(0, $second['sent']);
    }

    private function service(): AgendaReminderService
    {
        return app(AgendaReminderService::class);
    }
}
