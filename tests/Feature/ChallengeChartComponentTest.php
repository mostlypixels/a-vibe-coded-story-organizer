<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Project;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Services\ChallengeProgress;
use App\Support\ChallengeStanding;
use App\Support\WriterDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Guard the server markup consumed by the challenge chart module. */
class ChallengeChartComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** A five-day challenge, three days in, scored from real snapshot rows. */
    private function standing(): ChallengeStanding
    {
        $user = User::factory()->create(['timezone' => 'Pacific/Auckland']);
        $project = Project::factory()->for($user)->create();

        foreach (['2026-03-01' => 800, '2026-03-02' => 800, '2026-03-03' => 680] as $date => $total) {
            WordCountSnapshot::factory()->for($project)->create([
                'recorded_on' => $date,
                'word_count' => $total,
            ]);
        }

        $challenge = Challenge::factory()->for($project)->create([
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-05',
            'target_words' => 1000,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-03 09:00', $user->timezone));

        return app(ChallengeProgress::class)->standing($challenge, WriterDay::for($user));
    }

    private function render(ChallengeStanding $standing): string
    {
        return Blade::render('<x-challenge-chart :standing="$standing" />', ['standing' => $standing]);
    }

    public function test_it_renders_a_canvas_the_alpine_component_can_mount_on(): void
    {
        $rendered = $this->render($this->standing());

        $this->assertStringContainsString('x-data="challengeChart(', $rendered);
        $this->assertStringContainsString('x-ref="canvas"', $rendered);
        $this->assertStringContainsString('<canvas', $rendered);
        // A canvas carries no text of its own.
        $this->assertStringContainsString('role="img"', $rendered);
    }

    public function test_it_serialises_the_three_series_and_the_target(): void
    {
        $rendered = $this->render($this->standing());

        $this->assertStringContainsString('1 Mar', $rendered);
        $this->assertStringContainsString('5 Mar', $rendered);
        $this->assertStringContainsString('target: 1000', $rendered);
        // The line must stop at today, so the component needs the elapsed count.
        $this->assertStringContainsString('elapsedDays: 3', $rendered);
        $this->assertStringContainsString("par: JSON.parse('[200,400,600,800,1000]')", $rendered);
    }
}
