<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The quick switcher panel: a layout-free fragment of the signed-in user's own settings. */
class AppearanceSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.appearance.switcher'))->assertRedirect(route('login'));
    }

    public function test_the_panel_is_a_fragment_without_layout(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.appearance.switcher'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('<nav', $html);
        $this->assertStringContainsString('x-data="appearanceSwitcher(', $html);
    }

    public function test_every_preset_is_a_swatch_and_the_active_one_is_checked(): void
    {
        $html = $this->actingAs(User::factory()->create(['theme_slug' => 'low-glare-dark']))
            ->get(route('admin.appearance.switcher'))
            ->getContent();

        foreach (array_keys(config('themes.presets')) as $slug) {
            $this->assertMatchesRegularExpression('/<input[^>]*name="theme_slug"[^>]*value="'.$slug.'"/', $html);
        }

        $this->assertMatchesRegularExpression('/value="low-glare-dark"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="daylight"[^>]*checked/', $html);
    }

    public function test_null_columns_check_the_configured_defaults(): void
    {
        $user = User::factory()->create([
            'theme_slug' => null,
            'ui_font' => null,
            'manuscript_font' => null,
        ]);

        $html = $this->actingAs($user)->get(route('admin.appearance.switcher'))->getContent();

        $default = config('themes.default');
        $this->assertMatchesRegularExpression('/value="'.$default.'"[^>]*checked/', $html);
        $this->assertSelectedOption($html, 'ui_font', config('fonts.default_ui'));
        $this->assertSelectedOption($html, 'manuscript_font', config('fonts.default_manuscript'));
    }

    public function test_every_family_is_in_both_selects_and_the_active_ones_are_selected(): void
    {
        $user = User::factory()->create(['ui_font' => 'lexend', 'manuscript_font' => 'literata']);

        $html = $this->actingAs($user)->get(route('admin.appearance.switcher'))->getContent();

        foreach (config('fonts.families') as $slug => $family) {
            $this->assertSame(
                2,
                substr_count($html, 'value="'.$slug.'"'."\n".'                        style="font-family: '.e($family['stack']).';"'),
                "{$slug} must appear in both selects with its own face",
            );
        }

        $this->assertSelectedOption($html, 'ui_font', 'lexend');
        $this->assertSelectedOption($html, 'manuscript_font', 'literata');
    }

    public function test_config_carries_the_ordered_step_lists_and_the_active_slugs(): void
    {
        $user = User::factory()->create([
            'ui_scale' => 'large',
            'manuscript_scale' => 'bigger',
            'ui_leading' => 'roomy',
            'manuscript_leading' => 'airy',
        ]);

        $config = $this->panelConfig($this->actingAs($user)->get(route('admin.appearance.switcher'))->getContent());

        $this->assertSame(array_keys(config('fonts.ui_scales')), $config['steps']['ui_scale']);
        $this->assertSame(array_keys(config('fonts.manuscript_scales')), $config['steps']['manuscript_scale']);
        $this->assertSame(array_keys(config('fonts.leading')), $config['steps']['ui_leading']);
        $this->assertSame(array_keys(config('fonts.leading')), $config['steps']['manuscript_leading']);
        $this->assertSame('18px', $config['labels']['ui_scale']['large']);
        $this->assertSame('1.15×', $config['labels']['manuscript_scale']['bigger']);
        $this->assertSame('1.5×', $config['labels']['ui_leading']['roomy']);
        $this->assertSame(route('admin.appearance.update'), $config['url']);
        $this->assertSame('large', $config['active']['ui_scale']);
        $this->assertSame('bigger', $config['active']['manuscript_scale']);
        $this->assertSame('roomy', $config['active']['ui_leading']);
        $this->assertSame('airy', $config['active']['manuscript_leading']);
        $this->assertArrayHasKey('theme_slug', $config['previewMap']);
        $this->assertArrayHasKey('failed', $config['messages']);
    }

    public function test_stored_slugs_no_longer_in_config_fall_back_without_error(): void
    {
        $user = User::factory()->create([
            'theme_slug' => 'removed-theme',
            'ui_font' => 'removed-font',
            'manuscript_font' => 'removed-font',
            'ui_scale' => 'removed',
            'manuscript_scale' => 'removed',
            'ui_leading' => 'removed',
            'manuscript_leading' => 'removed',
        ]);

        $response = $this->actingAs($user)->get(route('admin.appearance.switcher'))->assertOk();
        $config = $this->panelConfig($response->getContent());

        $this->assertSame(config('themes.default'), $config['active']['theme_slug']);
        $this->assertSame(config('fonts.default_ui'), $config['active']['ui_font']);
        $this->assertSame(config('fonts.default_ui_scale'), $config['active']['ui_scale']);
        $this->assertSame(config('fonts.default_manuscript_scale'), $config['active']['manuscript_scale']);
        $this->assertSame(config('fonts.default_ui_leading'), $config['active']['ui_leading']);
        $this->assertSame(config('fonts.default_leading'), $config['active']['manuscript_leading']);
    }

    public function test_the_panel_has_step_rows_and_a_link_but_no_locale_or_reset_control(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.appearance.switcher'))
            ->getContent();

        $this->assertStringNotContainsString('name="locale"', $html);
        $this->assertStringNotContainsStringIgnoringCase('reset', $html);
        $this->assertStringContainsString('aria-label="Smaller interface text"', $html);
        $this->assertStringContainsString('aria-label="Roomier manuscript spacing"', $html);
        $this->assertStringContainsString('href="'.route('admin.appearance.edit').'"', $html);
    }

    public function test_an_app_page_has_the_palette_button_that_points_at_the_panel(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<button[^>]*aria-label="Appearance"[^>]*>/', $html, $button));
        $this->assertSame(1, preg_match('/aria-controls="(dropdown-[a-z0-9]+)"/', $button[0], $controls));
        $this->assertStringContainsString('id="'.$controls[1].'"', $html);

        $loader = $this->loaderConfig($html);
        $this->assertSame($controls[1], $loader['id']);
        $this->assertSame(route('admin.appearance.switcher'), $loader['url']);
    }

    public function test_the_page_does_not_inline_the_panel(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('profile.edit'))->getContent();

        $this->assertStringNotContainsString('name="theme_slug"', $html);
        $this->assertStringNotContainsString('x-data="appearanceSwitcher(', $html);
    }

    public function test_the_appearance_page_has_no_palette_button(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('admin.appearance.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-label="Appearance"', $html);
        $this->assertStringNotContainsString('appearanceSwitcherLoader', $html);
    }

    public function test_an_error_page_has_no_palette_button(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/this-page-does-not-exist')->assertNotFound()->getContent();

        $this->assertStringNotContainsString('aria-label="Appearance"', $html);
    }

    private function assertSelectedOption(string $html, string $select, string $slug): void
    {
        $this->assertSame(1, preg_match('/<select[^>]*name="'.$select.'".*?<\/select>/s', $html, $match));
        $this->assertMatchesRegularExpression('/value="'.$slug.'"[^>]*selected/s', $match[0]);
        $this->assertSame(1, substr_count($match[0], 'selected'), 'Exactly one option is selected.');
    }

    /** @return array<string, mixed> */
    private function loaderConfig(string $html): array
    {
        $this->assertSame(1, preg_match('/x-data="appearanceSwitcherLoader\(JSON\.parse\(\'(.*?)\'\)\)"/s', $html, $match));

        $json = json_decode('"'.html_entity_decode($match[1]).'"', false, flags: JSON_THROW_ON_ERROR);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function panelConfig(string $html): array
    {
        $this->assertSame(1, preg_match('/x-data="appearanceSwitcher\(JSON\.parse\(\'(.*?)\'\)\)"/s', $html, $match));

        // Js::from() emits a JS string literal that holds JSON; a JSON string decode unwraps it.
        $json = json_decode('"'.html_entity_decode($match[1]).'"', false, flags: JSON_THROW_ON_ERROR);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
