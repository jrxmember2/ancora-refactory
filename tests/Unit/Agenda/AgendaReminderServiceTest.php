<?php

namespace Tests\Unit\Agenda;

use App\Models\AgendaEvent;
use App\Services\AgendaReminderService;
use Illuminate\Database\Schema\Blueprint;
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
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
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

    private function service(): AgendaReminderService
    {
        return app(AgendaReminderService::class);
    }

    public function test_due_reminders_selects_only_events_inside_the_reminder_window(): void
    {
        $due = $this->event(['start_at' => now()->addMinutes(20), 'reminder_minutes' => 30]);        // janela ja chegou
        $this->event(['start_at' => now()->addDays(2), 'reminder_minutes' => 30]);                   // janela ainda longe
        $this->event(['start_at' => now()->addMinutes(20), 'reminder_minutes' => null]);             // sem lembrete
        $this->event(['start_at' => now()->addMinutes(20), 'reminder_minutes' => 30, 'reminder_sent_at' => now()]); // ja enviado
        $this->event(['start_at' => now()->subMinutes(5), 'reminder_minutes' => 30]);                // ja passou
        $this->event(['start_at' => now()->addMinutes(20), 'reminder_minutes' => 30, 'status' => 'concluido']); // concluido

        $dueReminders = $this->service()->dueReminders();

        $this->assertCount(1, $dueReminders);
        $this->assertSame($due->id, $dueReminders->first()->id);
    }

    public function test_run_marks_reminder_as_sent_and_is_idempotent(): void
    {
        $event = $this->event(['start_at' => now()->addMinutes(10), 'reminder_minutes' => 30]);

        $result = $this->service()->run();

        $this->assertSame(1, $result['sent']);
        $this->assertNotNull($event->fresh()->reminder_sent_at);

        // Segunda execucao nao reenvia.
        $second = $this->service()->run();
        $this->assertSame(0, $second['sent']);
    }
}
