<?php

namespace Tests\Feature;

use App\Models\CrawlerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CrawlerSettingTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Task 01 — model & singleton
    // ---------------------------------------------------------------------

    public function test_current_returns_a_row_reflecting_the_config_default_and_creates_exactly_one(): void
    {
        config(['crawlers.default_enabled' => true]);

        $setting = CrawlerSetting::current();

        $this->assertTrue($setting->enabled);
        $this->assertSame(1, CrawlerSetting::count());
    }

    public function test_current_never_creates_a_second_row(): void
    {
        CrawlerSetting::current();
        CrawlerSetting::current();
        CrawlerSetting::current();

        $this->assertSame(1, CrawlerSetting::count());
    }

    public function test_whitelist_terms_drops_blanks_and_preserves_order(): void
    {
        $setting = CrawlerSetting::current();
        $setting->update(['user_agent_whitelist' => ['Googlebot', '  ', '', 'Bingbot']]);

        $this->assertSame(['Googlebot', 'Bingbot'], $setting->whitelistTerms());
    }

    public function test_casts_round_trip(): void
    {
        $setting = CrawlerSetting::current();
        $setting->update(['enabled' => false, 'user_agent_whitelist' => ['Googlebot']]);

        $fresh = $setting->fresh();
        $this->assertIsBool($fresh->enabled);
        $this->assertFalse($fresh->enabled);
        $this->assertSame(['Googlebot'], $fresh->user_agent_whitelist);
    }

    // ---------------------------------------------------------------------
    // Task 02 — robots.txt route
    // ---------------------------------------------------------------------

    public function test_robots_txt_on_fresh_db_blocks_all_and_lazily_creates_the_row(): void
    {
        $response = $this->get(route('robots.txt'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Disallow: /', false);
        $this->assertSame(1, CrawlerSetting::count());
    }

    public function test_robots_txt_emits_whitelist_allow_groups_when_hidden(): void
    {
        CrawlerSetting::current()->update([
            'enabled' => true,
            'user_agent_whitelist' => ['Googlebot'],
        ]);

        $this->get(route('robots.txt'))->assertSeeInOrder([
            'User-agent: Googlebot',
            'Disallow:',
            'User-agent: *',
            'Disallow: /',
        ], false);
    }

    public function test_robots_txt_allows_all_when_hidden_off(): void
    {
        CrawlerSetting::current()->update(['enabled' => false]);

        $response = $this->get(route('robots.txt'));

        $response->assertSee('User-agent: *', false);
        $response->assertDontSee('Disallow: /', false);
    }

    public function test_robots_txt_is_reachable_by_a_guest(): void
    {
        $this->get(route('robots.txt'))->assertOk();
    }

    public function test_static_robots_file_was_removed(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    // ---------------------------------------------------------------------
    // Task 03 — meta component
    // ---------------------------------------------------------------------

    public function test_pages_carry_the_noindex_tag_when_hidden(): void
    {
        CrawlerSetting::current()->update(['enabled' => true]);

        $response = $this->get('/');

        $response->assertSee('name="robots"', false);
        $response->assertSee('noindex', false);
    }

    public function test_pages_have_no_robots_tag_when_hidden_off(): void
    {
        CrawlerSetting::current()->update(['enabled' => false]);

        $this->get('/')->assertDontSee('name="robots"', false);
    }

    public function test_forced_component_renders_the_tag_even_when_hidden_off(): void
    {
        CrawlerSetting::current()->update(['enabled' => false]);

        $html = Blade::render('<x-robots-meta :force="true" />');

        $this->assertStringContainsString('name="robots"', $html);
        $this->assertStringContainsString('noindex, nofollow', $html);
    }

    // ---------------------------------------------------------------------
    // Task 04 — settings screen
    // ---------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('crawler-settings.edit'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_the_settings_form(): void
    {
        $user = User::factory()->create();
        CrawlerSetting::current()->update([
            'enabled' => true,
            'user_agent_whitelist' => ['Googlebot'],
        ]);

        $response = $this->actingAs($user)->get(route('crawler-settings.edit'));

        $response->assertOk();
        $response->assertSee('Googlebot', false);
    }

    public function test_update_persists_toggle_and_whitelist_dropping_blanks_and_dupes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('crawler-settings.update'), [
            'enabled' => '1',
            'user_agent_whitelist' => "Googlebot\n\nGooglebot\n",
        ]);

        $response->assertRedirect(route('crawler-settings.edit'));

        $setting = CrawlerSetting::current();
        $this->assertTrue($setting->enabled);
        $this->assertSame(['Googlebot'], $setting->user_agent_whitelist);
    }

    public function test_unchecked_box_disables_hidden_mode_end_to_end(): void
    {
        $user = User::factory()->create();
        CrawlerSetting::current()->update(['enabled' => true]);

        $this->actingAs($user)->patch(route('crawler-settings.update'), [
            'user_agent_whitelist' => '',
        ])->assertRedirect(route('crawler-settings.edit'));

        $this->assertFalse(CrawlerSetting::current()->enabled);

        // The dynamic route reflects the update: allow-all, no site-wide block.
        $this->get(route('robots.txt'))->assertDontSee('Disallow: /', false);
    }

    public function test_line_unsafe_term_is_rejected_and_leaves_the_row_unchanged(): void
    {
        $user = User::factory()->create();
        CrawlerSetting::current()->update([
            'enabled' => true,
            'user_agent_whitelist' => ['Googlebot'],
        ]);

        $response = $this->actingAs($user)->patch(route('crawler-settings.update'), [
            'enabled' => '1',
            'user_agent_whitelist' => 'Bad: Bot',
        ]);

        $response->assertSessionHasErrors('user_agent_whitelist.0');
        $this->assertSame(['Googlebot'], CrawlerSetting::current()->user_agent_whitelist);
    }
}
