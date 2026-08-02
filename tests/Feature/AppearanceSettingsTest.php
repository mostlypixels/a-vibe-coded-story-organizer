<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-user theme preference (theme-switcher spec, task 04): the picker at
 * /admin/appearance and the write that lands on `users.theme_slug` only.
 */
class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_guest_is_redirected_to_login_from_both_routes(): void
    {
        $this->get(route('admin.appearance.edit'))->assertRedirect(route('login'));
        $this->patch(route('admin.appearance.update'), ['theme_slug' => 'daylight'])
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------------
    // The picker
    // ---------------------------------------------------------------------

    public function test_authenticated_user_sees_every_configured_preset_with_the_active_one_marked(): void
    {
        $user = User::factory()->create(['theme_slug' => 'low-glare-dark']);

        $html = $this->actingAs($user)->get(route('admin.appearance.edit'))->getContent();

        foreach (array_keys(config('themes.presets')) as $slug) {
            $this->assertStringContainsString('value="'.$slug.'"', $html);
        }

        $this->assertMatchesRegularExpression(
            '/id="theme-low-glare-dark"[^>]*checked/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="theme-daylight"[^>]*checked/',
            $html,
        );
    }

    public function test_a_user_who_never_picked_sees_the_configured_default_marked_active(): void
    {
        $user = User::factory()->create(['theme_slug' => null]);

        $html = $this->actingAs($user)->get(route('admin.appearance.edit'))->getContent();

        $this->assertMatchesRegularExpression(
            '/id="theme-'.config('themes.default').'"[^>]*checked/',
            $html,
        );
    }

    // ---------------------------------------------------------------------
    // Saving
    // ---------------------------------------------------------------------

    public function test_patch_with_a_valid_slug_updates_that_user_and_redirects_with_the_status_flash(): void
    {
        $user = User::factory()->create(['theme_slug' => null]);

        $response = $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => 'low-glare-dark',
        ]);

        $response->assertRedirect(route('admin.appearance.edit'));
        $response->assertSessionHas('status', 'theme-updated');
        $this->assertSame('low-glare-dark', $user->fresh()->theme_slug);
    }

    public function test_patch_with_a_slug_not_in_config_fails_validation(): void
    {
        $user = User::factory()->create(['theme_slug' => 'daylight']);

        $response = $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => 'not-a-real-preset',
        ]);

        $response->assertSessionHasErrors('theme_slug');
        $this->assertSame('daylight', $user->fresh()->theme_slug);
    }

    public function test_patch_with_null_clears_the_preference_back_to_the_default(): void
    {
        $user = User::factory()->create(['theme_slug' => 'low-glare-dark']);

        $response = $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
        ]);

        $response->assertRedirect(route('admin.appearance.edit'));
        $this->assertNull($user->fresh()->theme_slug);
    }

    public function test_two_users_hold_different_preferences_simultaneously_and_each_renders_their_own(): void
    {
        $daylightUser = User::factory()->create(['theme_slug' => 'daylight']);
        $darkUser = User::factory()->create(['theme_slug' => 'low-glare-dark']);

        $daylightContent = $this->actingAs($daylightUser)->get(route('dashboard'))->getContent();
        $this->assertStringContainsString(
            '--color-primary:'.config('themes.presets.daylight.tokens.primary').';',
            $daylightContent,
        );

        $darkContent = $this->actingAs($darkUser)->get(route('dashboard'))->getContent();
        $this->assertStringContainsString(
            '--color-primary:'.config('themes.presets.low-glare-dark.tokens.primary').';',
            $darkContent,
        );
    }

    /**
     * A config edit that drops a preset must not white-screen every page for a
     * user still holding the removed slug — ThemePreset::resolve() falls back
     * to config('themes.default') instead of throwing.
     */
    public function test_a_stored_slug_no_longer_in_config_falls_back_to_the_default_rather_than_throwing(): void
    {
        $user = User::factory()->create(['theme_slug' => 'a-preset-that-was-removed']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(
            '--color-primary:'.config('themes.presets.'.config('themes.default').'.tokens.primary').';',
            false,
        );
    }
}
