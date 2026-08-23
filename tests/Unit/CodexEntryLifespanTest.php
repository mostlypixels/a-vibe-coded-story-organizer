<?php

namespace Tests\Unit;

use App\Models\CodexEntry;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodexEntryLifespanTest extends TestCase
{
    use RefreshDatabase;

    public function test_age_at_returns_null_without_an_inception_event(): void
    {
        $entry = CodexEntry::factory()->create();
        $moment = Event::factory()->create();

        $this->assertNull($entry->ageAt($moment));
    }

    public function test_age_at_returns_null_without_a_moment(): void
    {
        $inception = Event::factory()->create(['event_datetime' => '1980-01-01']);
        $entry = CodexEntry::factory()->create(['inception_event_id' => $inception->id]);

        $this->assertNull($entry->ageAt(null));
    }

    public function test_age_at_returns_whole_years(): void
    {
        $inception = Event::factory()->create(['event_datetime' => '1980-01-01']);
        $moment = Event::factory()->create(['event_datetime' => '2000-01-01']);
        $entry = CodexEntry::factory()->create(['inception_event_id' => $inception->id]);

        $this->assertSame(20, $entry->ageAt($moment)->years);
    }

    public function test_has_inverted_lifespan_when_termination_precedes_inception(): void
    {
        $inception = Event::factory()->create(['event_datetime' => '2000-01-01']);
        $termination = Event::factory()->create(['event_datetime' => '1980-01-01']);
        $entry = CodexEntry::factory()->create([
            'inception_event_id' => $inception->id,
            'termination_event_id' => $termination->id,
        ]);

        $this->assertTrue($entry->hasInvertedLifespan());
    }

    public function test_age_at_returns_null_for_an_inverted_lifespan(): void
    {
        $inception = Event::factory()->create(['event_datetime' => '2000-01-01']);
        $termination = Event::factory()->create(['event_datetime' => '1980-01-01']);
        $entry = CodexEntry::factory()->create([
            'inception_event_id' => $inception->id,
            'termination_event_id' => $termination->id,
        ]);
        $moment = Event::factory()->create(['event_datetime' => '1990-01-01']);

        $this->assertNull($entry->ageAt($moment));
    }

    public function test_exists_at_the_existence_window_boundaries(): void
    {
        $inception = Event::factory()->create(['event_datetime' => '1990-01-01']);
        $termination = Event::factory()->create(['event_datetime' => '2000-01-01']);
        $entry = CodexEntry::factory()->create([
            'inception_event_id' => $inception->id,
            'termination_event_id' => $termination->id,
        ]);

        $before = Event::factory()->create(['event_datetime' => '1989-12-31']);
        $atInception = Event::factory()->create(['event_datetime' => '1990-01-01']);
        $between = Event::factory()->create(['event_datetime' => '1995-06-01']);
        $atTermination = Event::factory()->create(['event_datetime' => '2000-01-01']);
        $after = Event::factory()->create(['event_datetime' => '2000-01-02']);

        $this->assertFalse($entry->existsAt($before));
        $this->assertTrue($entry->existsAt($atInception));
        $this->assertTrue($entry->existsAt($between));
        $this->assertTrue($entry->existsAt($atTermination));
        $this->assertFalse($entry->existsAt($after));
    }

    public function test_exists_at_is_true_with_no_lifespan_links(): void
    {
        $entry = CodexEntry::factory()->create();
        $moment = Event::factory()->create();

        $this->assertTrue($entry->existsAt($moment));
    }

    public function test_exists_at_with_only_inception_set_is_open_ended(): void
    {
        $inception = Event::factory()->create(['event_datetime' => '1990-01-01']);
        $entry = CodexEntry::factory()->create(['inception_event_id' => $inception->id]);

        $before = Event::factory()->create(['event_datetime' => '1989-12-31']);
        $farAfter = Event::factory()->create(['event_datetime' => '2100-01-01']);

        $this->assertFalse($entry->existsAt($before));
        $this->assertTrue($entry->existsAt($farAfter));
    }

    public function test_exists_at_with_only_termination_set_is_open_at_the_start(): void
    {
        $termination = Event::factory()->create(['event_datetime' => '2000-01-01']);
        $entry = CodexEntry::factory()->create(['termination_event_id' => $termination->id]);

        $farBefore = Event::factory()->create(['event_datetime' => '1900-01-01']);
        $after = Event::factory()->create(['event_datetime' => '2000-01-02']);

        $this->assertTrue($entry->existsAt($farBefore));
        $this->assertFalse($entry->existsAt($after));
    }

    public function test_exists_at_is_true_for_an_inverted_lifespan_at_any_moment(): void
    {
        $inception = Event::factory()->create(['event_datetime' => '2000-01-01']);
        $termination = Event::factory()->create(['event_datetime' => '1980-01-01']);
        $entry = CodexEntry::factory()->create([
            'inception_event_id' => $inception->id,
            'termination_event_id' => $termination->id,
        ]);

        $moment = Event::factory()->create(['event_datetime' => '1970-01-01']);

        $this->assertTrue($entry->existsAt($moment));
    }

    public function test_exists_at_is_true_with_no_moment(): void
    {
        $entry = CodexEntry::factory()->create();

        $this->assertTrue($entry->existsAt(null));
    }
}
