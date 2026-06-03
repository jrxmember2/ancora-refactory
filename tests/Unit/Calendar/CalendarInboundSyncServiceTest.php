<?php

namespace Tests\Unit\Calendar;

use App\Models\AgendaEvent;
use App\Models\AgendaEventSync;
use App\Models\CalendarConnection;
use App\Services\Calendar\CalendarInboundSyncService;
use App\Services\Calendar\CalendarProviders;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CalendarInboundSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agenda_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 220);
            $table->text('description')->nullable();
            $table->string('location', 220)->nullable();
            $table->string('status', 30)->default('aberto');
            $table->boolean('all_day')->default(false);
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agenda_event_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agenda_event_id');
            $table->unsignedBigInteger('connection_id');
            $table->string('provider', 30);
            $table->string('external_event_id', 255);
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('agenda_event_syncs');
        Schema::dropIfExists('calendar_connections');
        Schema::dropIfExists('agenda_events');
        parent::tearDown();
    }

    private function service(): CalendarInboundSyncService
    {
        return new CalendarInboundSyncService(new CalendarProviders([]), new CalendarSyncService(new CalendarProviders([])));
    }

    private function scenario(): array
    {
        $connection = CalendarConnection::query()->create(['user_id' => 1, 'provider' => 'google', 'is_active' => true]);
        $event = AgendaEvent::query()->create([
            'title' => 'Original',
            'status' => 'aberto',
            'start_at' => Carbon::parse('2026-06-10 10:00:00'),
        ]);
        AgendaEventSync::query()->create([
            'agenda_event_id' => $event->id,
            'connection_id' => $connection->id,
            'provider' => 'google',
            'external_event_id' => 'ext-1',
        ]);

        return [$connection, $event];
    }

    public function test_update_applies_changes_to_owned_event(): void
    {
        [$connection, $event] = $this->scenario();

        $ok = $this->service()->applyOwnedEvent($connection, 'ext-1', [
            'title' => 'Alterado no Google',
            'location' => 'Sala 2',
        ]);

        $this->assertTrue($ok);
        $this->assertSame('Alterado no Google', $event->fresh()->title);
        $this->assertSame('Sala 2', $event->fresh()->location);
    }

    public function test_external_delete_cancels_event_instead_of_removing(): void
    {
        [$connection, $event] = $this->scenario();

        $this->service()->applyOwnedEvent($connection, 'ext-1', ['deleted' => true]);

        $fresh = $event->fresh();
        $this->assertNotNull($fresh, 'o evento nao deve ser apagado fisicamente');
        $this->assertSame('cancelado', $fresh->status);
    }

    public function test_unknown_external_event_is_ignored(): void
    {
        [$connection, $event] = $this->scenario();

        $ok = $this->service()->applyOwnedEvent($connection, 'ext-DESCONHECIDO', ['title' => 'Invasor']);

        $this->assertFalse($ok);
        $this->assertSame('Original', $event->fresh()->title);
    }
}
