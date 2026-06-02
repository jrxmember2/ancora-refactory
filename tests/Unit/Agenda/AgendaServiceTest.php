<?php

namespace Tests\Unit\Agenda;

use App\Models\AgendaEvent;
use App\Models\User;
use App\Services\AgendaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Teste isolado do resumo de agenda do painel e dos scopes do AgendaEvent.
 * Cria apenas a tabela agenda_events para nao depender do conjunto completo de migrations
 * (incompativel com SQLite por usar information_schema em algumas migrations MySQL).
 */
class AgendaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agenda_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 220);
            $table->string('type', 40)->default('compromisso');
            $table->string('status', 30)->default('aberto');
            $table->boolean('is_fatal')->default(false);
            $table->boolean('all_day')->default(false);
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->unsignedBigInteger('requester_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('agenda_events');
        parent::tearDown();
    }

    private function user(int $id = 1): User
    {
        $user = new User();
        $user->id = $id;

        return $user;
    }

    private function event(array $attrs): AgendaEvent
    {
        return AgendaEvent::query()->create(array_merge([
            'title' => 'Evento',
            'type' => 'prazo',
            'status' => 'aberto',
            'is_fatal' => false,
            'all_day' => false,
            'responsible_user_id' => 1,
        ], $attrs));
    }

    public function test_panel_summary_counts_overdue_upcoming_and_fatal_for_the_user(): void
    {
        $this->event(['start_at' => now()->subDays(2), 'is_fatal' => true]);   // atrasado + fatal
        $this->event(['start_at' => now()->addDays(2)]);                       // proximo
        $this->event(['start_at' => now()->addDays(2), 'status' => 'concluido']); // concluido nao conta
        $this->event(['start_at' => now()->addDays(2), 'responsible_user_id' => 99]); // de outro usuario
        $this->event(['start_at' => now()->addDays(30)]);                      // fora da janela de 7 dias

        $summary = app(AgendaService::class)->panelSummary($this->user(1), 7);

        $this->assertSame(2, $summary['total']);          // 1 atrasado + 1 proximo (do user, em aberto, dentro da janela)
        $this->assertSame(1, $summary['overdue_count']);
        $this->assertSame(1, $summary['upcoming_count']);
        $this->assertSame(1, $summary['fatal_count']);
    }

    public function test_scopes_overdue_and_upcoming(): void
    {
        $this->event(['start_at' => now()->subDay()]);    // atrasado
        $this->event(['start_at' => now()->addDay()]);    // proximo
        $this->event(['start_at' => now()->subDay(), 'status' => 'concluido']); // concluido nao e atrasado

        $this->assertSame(1, AgendaEvent::query()->overdue()->count());
        $this->assertSame(1, AgendaEvent::query()->upcoming(7)->count());
        $this->assertSame(2, AgendaEvent::query()->open()->count());
    }

    public function test_for_user_matches_responsible_or_requester(): void
    {
        $this->event(['start_at' => now(), 'responsible_user_id' => 5, 'requester_user_id' => null]);
        $this->event(['start_at' => now(), 'responsible_user_id' => null, 'requester_user_id' => 5]);
        $this->event(['start_at' => now(), 'responsible_user_id' => 9, 'requester_user_id' => 9]);

        $this->assertSame(2, AgendaEvent::query()->forUser(5)->count());
    }
}
