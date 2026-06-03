<?php

namespace Tests\Unit\Calendar;

use App\Models\AgendaEvent;
use App\Models\AgendaEventSync;
use App\Models\CalendarConnection;
use App\Services\Calendar\CalendarProviderInterface;
use App\Services\Calendar\CalendarProviders;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CalendarSyncServiceTest extends TestCase
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 30);
            $table->string('account_email', 190)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->string('calendar_id', 190)->nullable();
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

    private function fakeProvider(): CalendarProviderInterface
    {
        return new class implements CalendarProviderInterface {
            public array $pushed = [];
            public array $deleted = [];
            public bool $shouldFail = false;

            public function key(): string { return 'google'; }
            public function label(): string { return 'Fake'; }
            public function isConfigured(): bool { return true; }
            public function authorizationUrl(string $state, string $redirectUri): string { return 'https://fake/auth'; }
            public function exchangeCode(string $code, string $redirectUri): array { return []; }
            public function refreshToken(string $refreshToken): array { return ['access_token' => 'new', 'expires_in' => 3600]; }

            public function pushEvent(CalendarConnection $connection, string $accessToken, AgendaEvent $event, ?string $externalId): string
            {
                if ($this->shouldFail) {
                    throw new \RuntimeException('falha simulada');
                }
                $this->pushed[] = $event->id;

                return 'ext-' . $event->id;
            }

            public function deleteEvent(CalendarConnection $connection, string $accessToken, string $externalId): void
            {
                $this->deleted[] = $externalId;
            }
        };
    }

    private function makeConnection(): CalendarConnection
    {
        return CalendarConnection::query()->create([
            'user_id' => 1,
            'provider' => 'google',
            'access_token' => 'token-abc',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
    }

    private function makeEvent(int $responsible = 1): AgendaEvent
    {
        return AgendaEvent::query()->create([
            'title' => 'Audiencia',
            'type' => 'audiencia',
            'status' => 'aberto',
            'start_at' => now()->addDay(),
            'responsible_user_id' => $responsible,
        ]);
    }

    private function service(CalendarProviderInterface $provider): CalendarSyncService
    {
        return new CalendarSyncService(new CalendarProviders(['google' => $provider]));
    }

    public function test_upsert_creates_sync_record_and_pushes(): void
    {
        $this->makeConnection();
        $event = $this->makeEvent();
        $fake = $this->fakeProvider();

        $this->service($fake)->syncEvent($event, 'upsert');

        $this->assertCount(1, $fake->pushed);
        $this->assertDatabaseHas('agenda_event_syncs', [
            'agenda_event_id' => $event->id,
            'external_event_id' => 'ext-' . $event->id,
        ]);
    }

    public function test_delete_removes_sync_and_calls_provider(): void
    {
        $connection = $this->makeConnection();
        $event = $this->makeEvent();
        AgendaEventSync::query()->create([
            'agenda_event_id' => $event->id,
            'connection_id' => $connection->id,
            'provider' => 'google',
            'external_event_id' => 'ext-99',
        ]);
        $fake = $this->fakeProvider();

        $this->service($fake)->syncEvent($event, 'delete');

        $this->assertSame(['ext-99'], $fake->deleted);
        $this->assertDatabaseMissing('agenda_event_syncs', ['agenda_event_id' => $event->id]);
    }

    public function test_event_without_responsible_is_skipped(): void
    {
        $this->makeConnection();
        $event = $this->makeEvent(0);
        $fake = $this->fakeProvider();

        $this->service($fake)->syncEvent($event, 'upsert');

        $this->assertCount(0, $fake->pushed);
    }

    public function test_provider_failure_is_isolated_and_does_not_throw(): void
    {
        $this->makeConnection();
        $event = $this->makeEvent();
        $fake = $this->fakeProvider();
        $fake->shouldFail = true;

        $this->service($fake)->syncEvent($event, 'upsert');

        $this->assertDatabaseMissing('agenda_event_syncs', ['agenda_event_id' => $event->id]);
    }
}
