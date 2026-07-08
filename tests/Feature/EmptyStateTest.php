<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the shared x-table-empty component's two states across the index pages:
 * a friendly "nothing here yet" call-to-action when a collection is genuinely
 * empty, versus a "nothing matches" message when a search/filter hid every row.
 */
class EmptyStateTest extends TestCase
{
    use RefreshDatabase;

    // --- Genuinely empty collections show the friendly create prompt ------

    public function test_the_acts_index_shows_a_friendly_empty_state_when_there_are_no_acts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.acts.index', $project));

        $response->assertOk();
        $response->assertSee(__('No acts yet.'));
        $response->assertDontSee(__('No acts match your search or filters.'));
    }

    public function test_the_chapters_index_shows_a_friendly_empty_state_when_there_are_no_chapters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.chapters.index', $project));

        $response->assertOk();
        $response->assertSee(__('No chapters yet.'));
    }

    public function test_the_scenes_index_shows_a_friendly_empty_state_when_there_are_no_scenes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.scenes.index', $project));

        $response->assertOk();
        $response->assertSee(__('No scenes yet.'));
    }

    public function test_the_codex_index_shows_a_friendly_empty_state_per_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $characters = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters']));
        $characters->assertOk();
        $characters->assertSee(__('No characters yet.'));

        // The empty state points at the type-specific create action.
        $characters->assertSee(route('projects.codex.create', [$project, 'characters']), false);

        $locations = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'locations']));
        $locations->assertSee(__('No locations yet.'));
    }

    // --- A search that matches nothing shows the "no match" message --------

    public function test_a_filtered_acts_index_shows_a_no_match_message_not_the_create_prompt(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Act::factory()->for($project)->create(['name' => 'The Gathering']);

        $response = $this->actingAs($user)->get(route('projects.acts.index', [$project, 'search' => 'no-such-act']));

        $response->assertOk();
        $response->assertSee(__('No acts match your search or filters.'));
        $response->assertDontSee(__('No acts yet.'));
    }

    public function test_a_filtered_events_index_shows_a_no_match_message(): void
    {
        // A fresh project always has the Start/End bookend events, so the events
        // index is never genuinely empty — the "no match" branch is the reachable one.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.events.index', [$project, 'search' => 'no-such-event']));

        $response->assertOk();
        $response->assertSee(__('No events match your search or filters.'));
    }

    public function test_a_filtered_codex_index_shows_a_no_match_message(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $response = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters', 'search' => 'no-such-name']));

        $response->assertOk();
        $response->assertSee(__('No characters match your search or filters.'));
        $response->assertDontSee(__('No characters yet.'));
    }
}
