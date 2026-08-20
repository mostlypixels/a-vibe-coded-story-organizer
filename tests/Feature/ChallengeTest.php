<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for ChallengeController. Following PlotlineTest: no index
 * here (the Progress page is the list, covered elsewhere), so this covers
 * create/store, edit/update, destroy, authorization, and validation.
 */
class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    // --- Create / store ------------------------------------------------

    public function test_a_user_can_view_the_challenge_create_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->get(route('projects.challenges.create', $project))->assertOk();
    }

    public function test_a_user_can_create_a_fixed_challenge_for_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'Fifty in November',
            'recurrence' => 'none',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
            'target_words' => 50000,
        ]);

        $response->assertRedirect(route('projects.progress', $project));
        $this->assertSame(1, $project->challenges()->count());
        $this->assertSame('Fifty in November', $project->challenges()->first()->name);
    }

    public function test_a_user_can_create_a_monthly_challenge_with_no_end_date(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'Every Month',
            'recurrence' => 'monthly',
            'starts_on' => '2026-01-10',
            'target_words' => 30000,
        ]);

        $response->assertRedirect(route('projects.progress', $project));
        $challenge = $project->challenges()->first();
        $this->assertNotNull($challenge);
        $this->assertNull($challenge->ends_on);
    }

    public function test_a_monthly_challenge_can_carry_a_stop_date(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'Every Month, Stopping',
            'recurrence' => 'monthly',
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-03-31',
            'target_words' => 30000,
        ]);

        $response->assertRedirect(route('projects.progress', $project));
        $this->assertNotNull($project->challenges()->first()->ends_on);
    }

    public function test_a_monthly_challenge_over_366_days_is_accepted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'Long-Running Monthly',
            'recurrence' => 'monthly',
            'starts_on' => '2020-01-01',
            'ends_on' => '2026-12-31',
            'target_words' => 30000,
        ]);

        $response->assertRedirect(route('projects.progress', $project));
        $this->assertSame(1, $project->challenges()->count());
    }

    public function test_a_user_cannot_create_a_challenge_for_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->post(route('projects.challenges.store', $project), [
            'name' => 'Hijacked',
            'recurrence' => 'none',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
            'target_words' => 10000,
        ])->assertForbidden();

        $this->actingAs($other)->get(route('projects.challenges.create', $project))->assertForbidden();
    }

    public function test_ends_on_before_starts_on_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'Backwards',
            'recurrence' => 'none',
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-01',
            'target_words' => 10000,
        ])->assertSessionHasErrors('ends_on');
    }

    public function test_a_fixed_challenge_over_366_days_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'Too Long',
            'recurrence' => 'none',
            'starts_on' => '2026-01-01',
            'ends_on' => '2027-01-05',
            'target_words' => 10000,
        ])->assertSessionHasErrors('ends_on');
    }

    public function test_a_fixed_challenge_with_no_end_date_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'No End',
            'recurrence' => 'none',
            'starts_on' => '2026-01-01',
            'target_words' => 10000,
        ])->assertSessionHasErrors('ends_on');
    }

    public function test_target_words_of_zero_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.challenges.store', $project), [
            'name' => 'No Target',
            'recurrence' => 'none',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
            'target_words' => 0,
        ])->assertSessionHasErrors('target_words');
    }

    // --- Edit / update ---------------------------------------------------

    public function test_a_user_can_view_the_challenge_edit_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $challenge = Challenge::factory()->for($project)->create(['name' => 'Editable Challenge']);

        $this->actingAs($user)->get(route('challenges.edit', $challenge))
            ->assertOk()
            ->assertSee('Editable Challenge');
    }

    public function test_a_user_can_update_a_challenge_in_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $challenge = Challenge::factory()->for($project)->create(['name' => 'Old Name']);

        $this->actingAs($user)->put(route('challenges.update', $challenge), [
            'name' => 'New Name',
            'recurrence' => $challenge->recurrence->value,
            'starts_on' => $challenge->starts_on->toDateString(),
            'ends_on' => $challenge->ends_on->toDateString(),
            'target_words' => $challenge->target_words,
        ])->assertRedirect(route('projects.progress', $project));

        $this->assertSame('New Name', $challenge->fresh()->name);
    }

    public function test_save_and_stay_returns_to_the_challenge_edit_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $challenge = Challenge::factory()->for($project)->create();

        $this->actingAs($user)->put(route('challenges.update', $challenge), [
            'name' => 'Stayed',
            'recurrence' => $challenge->recurrence->value,
            'starts_on' => $challenge->starts_on->toDateString(),
            'ends_on' => $challenge->ends_on->toDateString(),
            'target_words' => $challenge->target_words,
            'stay' => 1,
        ])
            ->assertRedirect(route('challenges.edit', $challenge))
            ->assertSessionHas('status', 'saved');
    }

    public function test_a_user_cannot_update_a_challenge_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $challenge = Challenge::factory()->for($project)->create();

        $this->actingAs($other)->put(route('challenges.update', $challenge), [
            'name' => 'Hijacked',
            'recurrence' => $challenge->recurrence->value,
            'starts_on' => $challenge->starts_on->toDateString(),
            'ends_on' => $challenge->ends_on->toDateString(),
            'target_words' => $challenge->target_words,
        ])->assertForbidden();

        $this->actingAs($other)->get(route('challenges.edit', $challenge))->assertForbidden();
    }

    // --- Destroy -----------------------------------------------------------

    public function test_a_user_can_delete_a_challenge_in_their_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $challenge = Challenge::factory()->for($project)->create();

        $this->actingAs($user)->delete(route('challenges.destroy', $challenge))
            ->assertRedirect(route('projects.progress', $project));

        $this->assertNull($challenge->fresh());
    }

    public function test_a_user_cannot_delete_a_challenge_in_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $challenge = Challenge::factory()->for($project)->create();

        $this->actingAs($other)->delete(route('challenges.destroy', $challenge))->assertForbidden();
        $this->assertNotNull($challenge->fresh());
    }
}
