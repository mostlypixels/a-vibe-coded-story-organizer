<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-user theme preference: the picker at /admin/appearance and the write
 * that lands on `users.theme_slug` only.
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
        $this->patch(route('admin.appearance.update'), [
            'theme_slug' => 'daylight',
            'ui_font' => 'atkinson',
        ])->assertRedirect(route('login'));
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

    // ---------------------------------------------------------------------
    // Fonts
    // ---------------------------------------------------------------------

    public function test_patch_with_valid_font_values_persists_all_five_columns(): void
    {
        $user = User::factory()->create([
            'ui_font' => null,
            'manuscript_font' => null,
            'ui_scale' => null,
            'manuscript_scale' => null,
            'manuscript_leading' => null,
        ]);

        $response = $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_font' => 'atkinson',
            'manuscript_font' => 'literata',
            'ui_scale' => 'large',
            'manuscript_scale' => 'larger',
            'manuscript_leading' => 'loose',
        ]);

        $response->assertRedirect(route('admin.appearance.edit'));

        $fresh = $user->fresh();
        $this->assertSame('atkinson', $fresh->ui_font);
        $this->assertSame('literata', $fresh->manuscript_font);
        $this->assertSame('large', $fresh->ui_scale);
        $this->assertSame('larger', $fresh->manuscript_scale);
        $this->assertSame('loose', $fresh->manuscript_leading);
    }

    public function test_patch_with_null_font_fields_clears_them_back_to_the_default(): void
    {
        $user = User::factory()->create([
            'ui_font' => 'atkinson',
            'manuscript_font' => 'literata',
            'ui_scale' => 'large',
            'manuscript_scale' => 'larger',
            'manuscript_leading' => 'loose',
        ]);

        $response = $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_font' => null,
            'manuscript_font' => null,
            'ui_scale' => null,
            'manuscript_scale' => null,
            'manuscript_leading' => null,
        ]);

        $response->assertRedirect(route('admin.appearance.edit'));

        $fresh = $user->fresh();
        $this->assertNull($fresh->ui_font);
        $this->assertNull($fresh->manuscript_font);
        $this->assertNull($fresh->ui_scale);
        $this->assertNull($fresh->manuscript_scale);
        $this->assertNull($fresh->manuscript_leading);
    }

    public function test_patch_with_a_tampered_ui_font_fails_validation_and_leaves_the_column_unchanged(): void
    {
        $user = User::factory()->create(['ui_font' => 'atkinson']);

        $response = $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_font' => 'not-a-real-font',
        ]);

        $response->assertSessionHasErrors('ui_font');
        $this->assertSame('atkinson', $user->fresh()->ui_font);
    }

    public function test_patch_with_a_tampered_ui_scale_fails_validation_and_leaves_the_column_unchanged(): void
    {
        $user = User::factory()->create(['ui_scale' => 'large']);

        $response = $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_scale' => 'huge',
        ]);

        $response->assertSessionHasErrors('ui_scale');
        $this->assertSame('large', $user->fresh()->ui_scale);
    }

    public function test_the_interface_line_spacing_persists_and_is_validated(): void
    {
        $user = User::factory()->create(['ui_leading' => 'loose']);

        $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_leading' => 'tight',
        ])->assertRedirect(route('admin.appearance.edit'));

        $this->assertSame('tight', $user->fresh()->ui_leading);

        $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_leading' => 'airy',
        ])->assertSessionHasErrors('ui_leading');

        $this->assertSame('tight', $user->fresh()->ui_leading);
    }

    public function test_the_interface_line_spacing_reaches_the_rendered_style_block(): void
    {
        $user = User::factory()->create(['ui_leading' => 'tight']);

        $this->actingAs($user)
            ->get(route('admin.appearance.edit'))
            ->assertSee('--tw-leading:'.config('fonts.leading.tight'), false);
    }

    public function test_a_rejected_font_value_never_appears_in_a_subsequent_page_render(): void
    {
        $user = User::factory()->create(['ui_font' => 'atkinson']);

        $this->actingAs($user)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_font' => 'malicious-slug"></style><script>alert(1)</script>',
        ]);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        $this->assertStringNotContainsString('malicious-slug', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // ---------------------------------------------------------------------
    // The font picker
    // ---------------------------------------------------------------------

    public function test_the_picker_lists_every_configured_family_with_the_active_one_checked_for_each_field(): void
    {
        $user = User::factory()->create([
            'ui_font' => 'atkinson',
            'manuscript_font' => 'literata',
            'ui_scale' => 'large',
            'manuscript_scale' => 'larger',
            'manuscript_leading' => 'loose',
        ]);

        $html = $this->actingAs($user)->get(route('admin.appearance.edit'))->getContent();

        foreach (array_keys(config('fonts.families')) as $slug) {
            $this->assertStringContainsString('value="'.$slug.'"', $html);
        }

        $this->assertMatchesRegularExpression('/id="ui-font-atkinson"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="manuscript-font-literata"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="ui-scale-large"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="manuscript-scale-larger"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="manuscript-leading-loose"[^>]*checked/', $html);
    }

    /**
     * The preview map is what the JS looks a picked slug up in, so a family
     * missing from it silently stops previewing while the page still looks
     * right. Only the authored stack ever reaches the browser.
     */
    public function test_the_form_carries_a_preview_map_of_every_configured_family_and_scale(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('admin.appearance.edit'))->getContent();

        // Js::from() renders JSON.parse('<escaped JSON>'): unwrap the JS
        // string literal, then the JSON inside it.
        $this->assertSame(
            1,
            preg_match("/x-data=\"fontPreview\(JSON\.parse\('(.*?)'\)\)\"/", $html, $matches),
        );

        $map = json_decode(json_decode('"'.$matches[1].'"'), associative: true);

        $stacks = array_map(fn (array $family) => $family['stack'], config('fonts.families'));

        $this->assertSame($stacks, $map['ui_font']);
        $this->assertSame($stacks, $map['manuscript_font']);
        $this->assertSame(config('fonts.ui_scales'), $map['ui_scale']);
        $this->assertSame(config('fonts.manuscript_scales'), $map['manuscript_scale']);
        $this->assertSame(config('fonts.leading'), $map['manuscript_leading']);
    }

    public function test_a_user_who_never_picked_a_font_sees_the_configured_defaults_marked_active(): void
    {
        $user = User::factory()->create([
            'ui_font' => null,
            'manuscript_font' => null,
            'ui_scale' => null,
            'manuscript_scale' => null,
            'manuscript_leading' => null,
        ]);

        $html = $this->actingAs($user)->get(route('admin.appearance.edit'))->getContent();

        $this->assertMatchesRegularExpression(
            '/id="ui-font-'.config('fonts.default_ui').'"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/id="manuscript-font-'.config('fonts.default_manuscript').'"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/id="ui-scale-'.config('fonts.default_ui_scale').'"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/id="manuscript-scale-'.config('fonts.default_manuscript_scale').'"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/id="manuscript-leading-'.config('fonts.default_leading').'"[^>]*checked/',
            $html,
        );
    }

    public function test_one_users_patch_leaves_another_users_font_columns_untouched(): void
    {
        $userA = User::factory()->create(['ui_font' => 'inter']);
        $userB = User::factory()->create(['ui_font' => 'literata']);

        $this->actingAs($userA)->patch(route('admin.appearance.update'), [
            'theme_slug' => null,
            'ui_font' => 'atkinson',
        ]);

        $this->assertSame('atkinson', $userA->fresh()->ui_font);
        $this->assertSame('literata', $userB->fresh()->ui_font);
    }
}
