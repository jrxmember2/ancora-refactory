<?php

namespace Tests\Unit\Agenda;

use App\Http\Controllers\AgendaController;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AgendaRecurrenceTest extends TestCase
{
    private function expand(array $payload): array
    {
        $method = new ReflectionMethod(AgendaController::class, 'expandRecurrence');
        $method->setAccessible(true);

        return $method->invoke(new AgendaController(), $payload);
    }

    public function test_no_recurrence_returns_single_date(): void
    {
        $dates = $this->expand(['start_at' => '2026-06-10 10:00:00', 'recurrence' => '', 'recurrence_until' => null]);
        $this->assertCount(1, $dates);
    }

    public function test_weekly_expands_until_limit(): void
    {
        $dates = $this->expand([
            'start_at' => '2026-06-01 09:00:00',
            'recurrence' => 'weekly',
            'recurrence_until' => '2026-06-22', // 01, 08, 15, 22 => 4
        ]);

        $this->assertCount(4, $dates);
        $this->assertSame('2026-06-01', $dates[0]->format('Y-m-d'));
        $this->assertSame('2026-06-22', $dates[3]->format('Y-m-d'));
    }

    public function test_daily_expands_each_day(): void
    {
        $dates = $this->expand([
            'start_at' => '2026-06-10 08:00:00',
            'recurrence' => 'daily',
            'recurrence_until' => '2026-06-12', // 10, 11, 12 => 3
        ]);

        $this->assertCount(3, $dates);
    }

    public function test_until_before_start_returns_single(): void
    {
        $dates = $this->expand([
            'start_at' => '2026-06-10 08:00:00',
            'recurrence' => 'weekly',
            'recurrence_until' => '2026-06-01',
        ]);

        $this->assertCount(1, $dates);
    }
}
