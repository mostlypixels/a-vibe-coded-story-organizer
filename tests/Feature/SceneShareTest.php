<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SceneShareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a full project -> act -> chapter -> scene chain owned by the given
     * user and return the leaf scene (share links hang off scenes).
     */
    private function sceneFor(User $user): Scene
    {
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        return Scene::factory()->for($chapter)->create();
    }

    public function test_is_shared_is_true_when_token_set_and_expiry_in_the_future(): void
    {
        $scene = Scene::factory()->create();
        $scene->forceFill([
            'share_token' => 'live-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $this->assertTrue($scene->fresh()->isShared());
    }

    public function test_is_shared_is_false_when_token_is_null(): void
    {
        $scene = Scene::factory()->create();

        $this->assertNull($scene->share_token);
        $this->assertFalse($scene->isShared());
    }

    public function test_is_shared_is_false_when_expiry_is_in_the_past(): void
    {
        $scene = Scene::factory()->create();
        $scene->forceFill([
            'share_token' => 'stale-token',
            'share_expires_at' => now()->subDay(),
        ])->save();

        $this->assertFalse($scene->fresh()->isShared());
    }

    public function test_share_url_is_null_when_the_scene_is_unshared(): void
    {
        $scene = Scene::factory()->create();

        $this->assertNull($scene->shareUrl());
    }

    public function test_share_url_is_the_public_route_when_a_token_is_set(): void
    {
        // Deferred from task 01: the non-null branch needs the
        // `shared.scenes.show` route, which task 03 registers.
        $scene = Scene::factory()->create();
        $scene->forceFill(['share_token' => 'route-token'])->save();

        $this->assertSame(
            route('shared.scenes.show', 'route-token'),
            $scene->shareUrl(),
        );
    }

    public function test_the_migration_stores_a_token_and_expiry(): void
    {
        $expiry = now()->addWeek();

        $scene = Scene::factory()->create();
        $scene->forceFill([
            'share_token' => 'stored-token',
            'share_expires_at' => $expiry,
        ])->save();

        $stored = $scene->fresh();
        $this->assertSame('stored-token', $stored->share_token);
        // The timestamp column stores second precision, so compare formatted.
        $this->assertSame($expiry->toDateTimeString(), $stored->share_expires_at->toDateTimeString());
    }

    public function test_the_unique_index_rejects_a_duplicate_token(): void
    {
        Scene::factory()->create()->forceFill(['share_token' => 'dup'])->save();

        $this->expectException(QueryException::class);

        Scene::factory()->create()->forceFill(['share_token' => 'dup'])->save();
    }

    public function test_share_token_is_not_mass_assignable(): void
    {
        $scene = Scene::factory()->create();

        $scene->fill(['share_token' => 'sneaky']);

        $this->assertNotSame('sneaky', $scene->share_token);
        $this->assertNull($scene->share_token);
    }

    public function test_share_expires_at_is_not_mass_assignable(): void
    {
        $scene = Scene::factory()->create();

        $scene->fill(['share_expires_at' => now()->addDay()]);

        $this->assertNull($scene->share_expires_at);
    }

    // --- Task 02: owner share management (store / destroy) ------------------

    public function test_owner_can_generate_a_share_link_with_a_valid_duration(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)
            ->post(route('scenes.share.store', $scene), ['duration' => '7 days'])
            ->assertRedirect(route('scenes.edit', $scene));

        $scene = $scene->fresh();
        $this->assertNotNull($scene->share_token);
        // Expiry is within a minute of the chosen duration applied to now().
        $this->assertEqualsWithDelta(
            now()->add('7 days')->getTimestamp(),
            $scene->share_expires_at->getTimestamp(),
            60,
        );
    }

    public function test_generating_a_share_link_requires_a_whitelisted_duration(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)
            ->post(route('scenes.share.store', $scene), ['duration' => '10 years'])
            ->assertSessionHasErrors('duration');

        $this->assertNull($scene->fresh()->share_token);
    }

    public function test_generating_a_share_link_requires_a_duration(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)
            ->post(route('scenes.share.store', $scene), [])
            ->assertSessionHasErrors('duration');

        $this->assertNull($scene->fresh()->share_token);
    }

    public function test_a_non_owner_cannot_generate_a_share_link(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scene = $this->sceneFor($owner);

        $this->actingAs($other)
            ->post(route('scenes.share.store', $scene), ['duration' => '7 days'])
            ->assertForbidden();

        $this->assertNull($scene->fresh()->share_token);
    }

    public function test_a_guest_is_redirected_to_login_when_generating_a_share_link(): void
    {
        $scene = $this->sceneFor(User::factory()->create());

        $this->post(route('scenes.share.store', $scene), ['duration' => '7 days'])
            ->assertRedirect(route('login'));

        $this->assertNull($scene->fresh()->share_token);
    }

    public function test_regenerating_rotates_the_token(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $this->actingAs($user)
            ->post(route('scenes.share.store', $scene), ['duration' => '24 hours']);
        $firstToken = $scene->fresh()->share_token;
        $this->assertNotNull($firstToken);

        $this->actingAs($user)
            ->post(route('scenes.share.store', $scene), ['duration' => '24 hours']);
        $secondToken = $scene->fresh()->share_token;

        $this->assertNotNull($secondToken);
        $this->assertNotSame($firstToken, $secondToken);
    }

    public function test_owner_can_revoke_an_active_share_link(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);
        $scene->forceFill([
            'share_token' => 'live-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $this->actingAs($user)
            ->delete(route('scenes.share.destroy', $scene))
            ->assertRedirect(route('scenes.edit', $scene));

        $scene = $scene->fresh();
        $this->assertNull($scene->share_token);
        $this->assertNull($scene->share_expires_at);
    }

    public function test_a_non_owner_cannot_revoke_a_share_link(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scene = $this->sceneFor($owner);
        $scene->forceFill([
            'share_token' => 'live-token',
            'share_expires_at' => now()->addDay(),
        ])->save();

        $this->actingAs($other)
            ->delete(route('scenes.share.destroy', $scene))
            ->assertForbidden();

        $this->assertSame('live-token', $scene->fresh()->share_token);
    }

    // --- Task 03: public display page (show / expired) ----------------------

    /**
     * Build a scene owned by a fresh user with a live share token and the
     * given field values, returning [$scene, $token].
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Scene, 1: string}
     */
    private function sharedScene(array $attributes = [], ?string $token = 'live-token'): array
    {
        $scene = $this->sceneFor(User::factory()->create());
        $scene->forceFill(array_merge($attributes, [
            'share_token' => $token,
            'share_expires_at' => now()->addDay(),
        ]))->save();

        return [$scene->fresh(), $token];
    }

    public function test_a_valid_token_renders_the_public_scene_page(): void
    {
        [$scene, $token] = $this->sharedScene([
            'name' => 'The Reckoning',
            'contents' => 'A **decisive** moment.',
        ]);

        $response = $this->get(route('shared.scenes.show', $token));

        $response->assertOk();
        // Title: "Chapter 1 — {chapter}: {scene}" (Arabic position, em-dash).
        $response->assertSee($scene->chapter->name, escape: false);
        $response->assertSee('The Reckoning');
        $response->assertSee('Chapter 1', escape: false);
        // Markdown contents rendered as HTML (** ** → <strong>).
        $response->assertSee('<strong>decisive</strong>', escape: false);
    }

    public function test_the_public_page_never_exposes_scene_notes(): void
    {
        [, $token] = $this->sharedScene([
            'notes' => 'PRIVATE-AUTHOR-NOTES-DO-NOT-LEAK',
        ]);

        $this->get(route('shared.scenes.show', $token))
            ->assertOk()
            ->assertDontSee('PRIVATE-AUTHOR-NOTES-DO-NOT-LEAK');
    }

    public function test_the_public_page_renders_the_description_markup(): void
    {
        [, $token] = $this->sharedScene([
            'description' => '<p>A tense opening scene.</p>',
        ]);

        // The card is collapsed client-side (Alpine), but the markup is still
        // present in the server-rendered HTML.
        $this->get(route('shared.scenes.show', $token))
            ->assertOk()
            ->assertSee('A tense opening scene.');
    }

    public function test_an_unknown_token_returns_404(): void
    {
        $this->get(route('shared.scenes.show', 'no-such-token'))
            ->assertNotFound();
    }

    public function test_an_expired_token_returns_a_friendly_410_page_without_leaking_data(): void
    {
        $scene = $this->sceneFor(User::factory()->create());
        $scene->forceFill([
            'name' => 'Secret Chapter Finale',
            'share_token' => 'stale-token',
            'share_expires_at' => now()->subDay(),
        ])->save();

        $response = $this->get(route('shared.scenes.show', 'stale-token'));

        $response->assertStatus(410);
        $response->assertSee('This share link has expired.');
        // No data leak: the scene's own content must not appear on the 410 page.
        $response->assertDontSee('Secret Chapter Finale');
    }

    public function test_the_public_page_works_without_authentication(): void
    {
        [, $token] = $this->sharedScene();

        // No actingAs — the token is the only gate (route is outside `auth`).
        $this->get(route('shared.scenes.show', $token))->assertOk();
    }

    public function test_the_public_page_head_contains_the_noindex_robots_meta(): void
    {
        [, $token] = $this->sharedScene();

        $this->get(route('shared.scenes.show', $token))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);
    }

    // --- Task 04: owner share UI on the scene edit page ---------------------

    public function test_the_edit_page_shows_the_generate_control_and_duration_options_when_unshared(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertOk();
        $response->assertSee('Generate share link');

        // Every whitelisted duration label is offered in the <select>.
        foreach (array_keys(config('sharing.scene_link_durations')) as $label) {
            $response->assertSee($label);
        }

        // The unshared state posts to the store route and shows no revoke control.
        $response->assertSee(route('scenes.share.store', $scene), escape: false);
        $response->assertDontSee('Revoke');
    }

    public function test_the_edit_page_preselects_the_default_duration_when_unshared(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);

        $default = config('sharing.scene_link_durations')[config('sharing.scene_link_default_duration')];

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene))
            ->assertOk()
            ->assertSee('value="'.$default.'" selected', escape: false);
    }

    public function test_the_edit_page_shows_the_url_expiry_and_revoke_when_shared(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);
        $scene->forceFill([
            'share_token' => 'edit-live-token',
            'share_expires_at' => now()->addWeek(),
        ])->save();
        $scene = $scene->fresh();

        $response = $this->actingAs($user)->get(route('scenes.edit', $scene));

        $response->assertOk();
        // The public URL is shown in the read-only input.
        $response->assertSee($scene->shareUrl(), escape: false);
        // Absolute expiry timestamp is rendered.
        $response->assertSee($scene->share_expires_at->format('M j, Y H:i'));
        // Revoke + regenerate controls are present; the generate select is gone.
        $response->assertSee('Revoke');
        $response->assertSee('Regenerate');
        $response->assertSee(route('scenes.share.destroy', $scene), escape: false);
        $response->assertDontSee('Generate share link');
    }

    public function test_the_copy_button_has_an_accessible_label_when_shared(): void
    {
        $user = User::factory()->create();
        $scene = $this->sceneFor($user);
        $scene->forceFill([
            'share_token' => 'edit-copy-token',
            'share_expires_at' => now()->addWeek(),
        ])->save();

        $this->actingAs($user)
            ->get(route('scenes.edit', $scene->fresh()))
            ->assertOk()
            ->assertSee('aria-label="Copy share link to clipboard"', escape: false);
    }

    public function test_a_non_owner_cannot_view_the_scene_edit_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scene = $this->sceneFor($owner);

        $this->actingAs($other)
            ->get(route('scenes.edit', $scene))
            ->assertForbidden();
    }
}
