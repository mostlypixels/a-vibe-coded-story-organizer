<?php

namespace Tests\Feature;

use App\Models\CrawlerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Configuration shell (task 01): the /admin route group, its access
 * posture, the sidebar, and the placeholder section pages.
 */
class AdminConfigurationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every admin route, keyed by its route name. The guest-redirect and
     * all-sections-load tests iterate this so a new section is covered by adding
     * one entry here.
     *
     * @return array<string, string>
     */
    private function sectionRouteNames(): array
    {
        return [
            'admin.index',
            'admin.settings.edit',
            'admin.appearance.edit',
            'admin.data.index',
            'admin.database.edit',
        ];
    }

    // ---------------------------------------------------------------------
    // Authorization posture
    // ---------------------------------------------------------------------

    public function test_guest_is_redirected_to_login_from_every_admin_route(): void
    {
        foreach ($this->sectionRouteNames() as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_index_redirects_to_general_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.index'))
            ->assertRedirect(route('admin.settings.edit'));
    }

    public function test_authenticated_user_can_load_every_section(): void
    {
        $user = User::factory()->create();

        foreach ($this->sectionRouteNames() as $name) {
            if ($name === 'admin.index') {
                continue; // index only redirects; the sections below assert 200.
            }

            $this->actingAs($user)->get(route($name))->assertOk();
        }
    }

    /**
     * The /admin area deliberately continues the CrawlerSetting
     * "any authenticated user" posture (documentation/architecture.md ->
     * Hidden from crawlers): there is no is_admin role. A SECOND, unrelated
     * authenticated user must also get 200 — this is intentional, NOT a missing
     * ownership check. Do not "fix" this into a ProjectPolicy walk.
     */
    public function test_any_authenticated_user_may_access_admin_deliberate_crawler_setting_continuation(): void
    {
        User::factory()->create(); // the "first" user, who owns nothing here.
        $secondUser = User::factory()->create();

        $this->actingAs($secondUser)->get(route('admin.settings.edit'))->assertOk();
        $this->actingAs($secondUser)->get(route('admin.appearance.edit'))->assertOk();
        $this->actingAs($secondUser)->get(route('admin.data.index'))->assertOk();
        $this->actingAs($secondUser)->get(route('admin.database.edit'))->assertOk();
    }

    // ---------------------------------------------------------------------
    // Sidebar active state (documented nav convention — aria-current, not colour)
    // ---------------------------------------------------------------------

    public function test_each_section_marks_only_its_own_sidebar_link_active(): void
    {
        $user = User::factory()->create();

        $sidebarLinks = [
            'admin.settings.edit' => route('admin.settings.edit'),
            'admin.appearance.edit' => route('admin.appearance.edit'),
            'admin.data.index' => route('admin.data.index'),
            'admin.database.edit' => route('admin.database.edit'),
        ];

        foreach ($sidebarLinks as $currentRoute => $currentHref) {
            $html = $this->actingAs($user)->get(route($currentRoute))->getContent();

            // The active link carries aria-current="page".
            $this->assertMatchesRegularExpression(
                '/<a[^>]*href="'.preg_quote($currentHref, '/').'"[^>]*aria-current="page"/',
                $html,
                "Expected {$currentRoute} to mark its own sidebar link active."
            );

            // Exactly one aria-current="page" in the sidebar for this page.
            $this->assertSame(
                1,
                substr_count($html, 'aria-current="page"'),
                "Expected exactly one active sidebar link on {$currentRoute}."
            );
        }
    }

    // ---------------------------------------------------------------------
    // Section content
    // ---------------------------------------------------------------------

    public function test_appearance_page_is_a_headed_placeholder_with_no_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.appearance.edit'));

        $response->assertOk();
        $response->assertSee('Appearance & accessibility');

        // Scope the "no form" assertion to the page's <main> content region: the
        // shared layout always ships a logout <form> + CSRF <input> in the nav,
        // which are outside <main>. The section itself must have neither.
        $this->assertMatchesRegularExpression('/<main>(.*)<\/main>/s', $response->getContent());
        preg_match('/<main>(.*)<\/main>/s', $response->getContent(), $matches);
        $mainContent = $matches[1];

        $this->assertStringNotContainsString('<form', $mainContent);
        $this->assertStringNotContainsString('<input', $mainContent);
    }

    public function test_section_pages_show_their_headings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.settings.edit'))->assertSee('General settings');
        $this->actingAs($user)->get(route('admin.data.index'))->assertSee('Export &amp; import', false);
        $this->actingAs($user)->get(route('admin.database.edit'))->assertSee('Database configuration');
    }

    // ---------------------------------------------------------------------
    // Export & import (task 03) — the two-tab stub shell. The backup/restore
    // engine is a separate future spec, so there is no export/import route to
    // test; the tab switching itself is client-side Alpine. What the server
    // renders is the accessible tab structure and both "coming soon" panels.
    // ---------------------------------------------------------------------

    public function test_export_import_page_renders_both_tabs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.data.index'));

        $response->assertOk();
        $response->assertSee('Export &amp; import', false);

        // Both tab controls, wired to their panels (WAI-ARIA tabs pattern).
        $response->assertSee('id="tab-export"', false);
        $response->assertSee('id="tab-import"', false);
        $response->assertSee('role="tab"', false);
        $response->assertSee('aria-controls="panel-export"', false);
        $response->assertSee('aria-controls="panel-import"', false);
    }

    public function test_export_import_page_shows_export_form_and_import_upload_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.data.index'));

        // Tab switching is client-side; both panels are present in the HTML.
        // The Export panel now carries a real form (or its empty state — this
        // user owns no project, so the empty-state prompt shows). The Import
        // panel now carries its own real upload form (task 08).
        $response->assertSee('Create a project first to export it.');
        $response->assertSee(route('admin.data.import'));
        $response->assertSee('Archive (.zip)');
    }

    // ---------------------------------------------------------------------
    // Database configuration (task 04) — a read-only display of the ACTIVE
    // connection. Never renders the password (invariant 5).
    // ---------------------------------------------------------------------

    public function test_database_page_shows_the_current_driver(): void
    {
        $user = User::factory()->create();

        // The test suite runs on the sqlite connection.
        $response = $this->actingAs($user)->get(route('admin.database.edit'));

        $response->assertOk();
        $response->assertSee('Database configuration');
        $response->assertSee('Driver');
        $response->assertSee('sqlite');
    }

    /**
     * The key guard for invariant 5: the DB password must NEVER reach the HTML.
     *
     * Inject a known password (and username) into the ACTIVE connection's config
     * and assert the rendered page omits them. We do NOT switch the default
     * connection — the app renders through it live (e.g. x-robots-meta reads
     * CrawlerSetting), so pointing it at an unreachable fake host would fail the
     * request itself rather than test the whitelist. The controller's whitelist
     * (driver / database / host only) is what must drop these secrets.
     */
    public function test_database_page_never_renders_the_password(): void
    {
        $user = User::factory()->create();

        $name = config('database.default');
        $secret = 's3cr3t-do-not-show';

        config([
            "database.connections.$name.password" => $secret,
            "database.connections.$name.username" => 'fake_user',
        ]);

        $response = $this->actingAs($user)->get(route('admin.database.edit'));

        $response->assertOk();
        // The whitelisted driver fact is still shown...
        $response->assertSee('sqlite');
        // ...but the password and username never leak into the markup.
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString('fake_user', $response->getContent());
    }

    /**
     * Host is a server-driver fact: the sqlite test connection has no host, so
     * the row is omitted rather than rendered blank.
     */
    public function test_database_page_omits_host_for_sqlite(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.database.edit'));

        $response->assertOk();
        $response->assertDontSee('>Host<', false);
    }

    // ---------------------------------------------------------------------
    // General settings (task 02) — the search-engine (crawler) form, relocated
    // here from the retired crawler-settings.* screen. Behavioural coverage
    // migrated from CrawlerSettingTest's former "settings screen" section.
    // ---------------------------------------------------------------------

    public function test_general_settings_renders_the_search_engine_form(): void
    {
        $user = User::factory()->create();
        CrawlerSetting::current()->update([
            'enabled' => true,
            'user_agent_whitelist' => ['Googlebot'],
        ]);

        $response = $this->actingAs($user)->get(route('admin.settings.edit'));

        $response->assertOk();
        $response->assertSee('General settings');
        // The relocated form: the hidden-mode checkbox and the whitelist textarea.
        $response->assertSee('name="enabled"', false);
        $response->assertSee('name="user_agent_whitelist"', false);
        $response->assertSee('Googlebot', false);
    }

    public function test_saving_general_settings_persists_toggle_and_whitelist_dropping_blanks_and_dupes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('admin.settings.update'), [
            'enabled' => '1',
            'user_agent_whitelist' => "Googlebot\n\nGooglebot\n",
        ]);

        $response->assertRedirect(route('admin.settings.edit'));
        $response->assertSessionHas('status', 'crawler-settings-updated');

        $setting = CrawlerSetting::current();
        $this->assertTrue($setting->enabled);
        $this->assertSame(['Googlebot'], $setting->user_agent_whitelist);
    }

    /**
     * Guards against the relocation silently breaking the singleton wiring: the
     * live /robots.txt route must still reflect a save made through the new
     * admin route.
     */
    public function test_saving_hidden_is_reflected_by_the_live_robots_txt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('admin.settings.update'), [
            'enabled' => '1',
            'user_agent_whitelist' => 'Googlebot',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->get(route('robots.txt'))->assertSeeInOrder([
            'User-agent: Googlebot',
            'Disallow:',
            'User-agent: *',
            'Disallow: /',
        ], false);
    }

    public function test_general_settings_rejects_a_line_unsafe_whitelist_term(): void
    {
        $user = User::factory()->create();
        CrawlerSetting::current()->update([
            'enabled' => true,
            'user_agent_whitelist' => ['Googlebot'],
        ]);

        $response = $this->actingAs($user)->patch(route('admin.settings.update'), [
            'enabled' => '1',
            'user_agent_whitelist' => 'Bad: Bot',
        ]);

        $response->assertSessionHasErrors('user_agent_whitelist.0');
        // The row is left unchanged when validation fails.
        $this->assertSame(['Googlebot'], CrawlerSetting::current()->user_agent_whitelist);
    }

    public function test_general_settings_write_route_redirects_a_guest_to_login(): void
    {
        $this->patch(route('admin.settings.update'), [
            'enabled' => '1',
            'user_agent_whitelist' => '',
        ])->assertRedirect(route('login'));
    }

    /**
     * The old crawler-settings screen is renamed-and-deleted (not aliased):
     * its URL must now 404 so there is exactly one name per screen.
     */
    public function test_the_retired_crawler_settings_url_is_gone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/crawlers')->assertNotFound();
    }
}
