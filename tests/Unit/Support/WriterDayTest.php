<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\WriterDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriterDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_the_users_local_day_in_auckland(): void
    {
        // 2026-08-08 23:30 UTC is already 2026-08-09 in Auckland (UTC+12).
        $this->travelTo(CarbonImmutable::parse('2026-08-08 23:30:00', 'UTC'));

        $user = User::factory()->inTimezone('Pacific/Auckland')->create();

        $this->assertSame('2026-08-09', WriterDay::dateFor($user));
    }

    public function test_resolves_the_users_local_day_in_los_angeles(): void
    {
        // 2026-08-09 03:00 UTC is still 2026-08-08 in Los Angeles (UTC-7).
        $this->travelTo(CarbonImmutable::parse('2026-08-09 03:00:00', 'UTC'));

        $user = User::factory()->inTimezone('America/Los_Angeles')->create();

        $this->assertSame('2026-08-08', WriterDay::dateFor($user));
    }

    public function test_null_timezone_falls_back_to_the_app_default(): void
    {
        config(['app.timezone' => 'Pacific/Auckland']);

        $this->travelTo(CarbonImmutable::parse('2026-08-08 23:30:00', 'UTC'));

        $user = User::factory()->create(['timezone' => null]);

        $this->assertSame('2026-08-09', WriterDay::dateFor($user));
    }
}
