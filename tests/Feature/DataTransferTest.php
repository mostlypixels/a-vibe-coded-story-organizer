<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Export & import area (task 03): three server-rendered pages reached by
 * ordinary sub-nav links — NOT JavaScript tabs — replacing the old single
 * Alpine-tabbed admin/data/index.blade.php. Covers the page split itself
 * (routes, redirect, sub-nav active state, sidebar stays active); each page's
 * own form content is covered in depth by ExportTest / EpubExportTest /
 * ImportTest.
 */
class DataTransferTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The three pages each render for an authenticated user
    // ---------------------------------------------------------------------

    public function test_export_project_page_renders_its_own_controls(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Exportable Tale']);

        $response = $this->actingAs($user)->get(route('admin.data.export-project'));

        $response->assertOk();
        $response->assertSee('name="project_id"', false);
        $response->assertSee('Include images & files');
        $response->assertSee('Exportable Tale');
        // Not this page's controls.
        $response->assertDontSee('id="epub_project_id"', false);
        $response->assertDontSee('Archive (.zip)');
    }

    public function test_export_ebook_page_renders_its_own_controls(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Epub-able Tale']);

        $response = $this->actingAs($user)->get(route('admin.data.export-ebook'));

        $response->assertOk();
        $response->assertSee('id="epub_project_id"', false);
        $response->assertSee('Download EPUB');
        $response->assertSee('Epub-able Tale');
        // Not this page's controls.
        $response->assertDontSee('Include images & files');
        $response->assertDontSee('Archive (.zip)');
    }

    public function test_import_page_renders_its_own_controls(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.data.import.index'));

        $response->assertOk();
        $response->assertSee('Archive (.zip)');
        $response->assertSee(route('admin.data.import'));
        $response->assertSee('Import settings');
        // Not this page's controls.
        $response->assertDontSee('name="project_id"', false);
        $response->assertDontSee('id="epub_project_id"', false);
    }

    // ---------------------------------------------------------------------
    // /admin/data redirects to the first sub-page
    // ---------------------------------------------------------------------

    public function test_data_index_redirects_to_export_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.data.index'))
            ->assertRedirect(route('admin.data.export-project'));
    }

    // ---------------------------------------------------------------------
    // Sub-nav active state (aria-current, not colour-only — matches the
    // sidebar's documented active-state convention)
    // ---------------------------------------------------------------------

    public function test_subnav_marks_only_the_current_page_active(): void
    {
        $user = User::factory()->create();

        $pages = [
            'admin.data.export-project' => route('admin.data.export-project'),
            'admin.data.export-ebook' => route('admin.data.export-ebook'),
            'admin.data.import.index' => route('admin.data.import.index'),
        ];

        foreach ($pages as $currentRoute => $currentHref) {
            $html = $this->actingAs($user)->get(route($currentRoute))->getContent();

            $this->assertMatchesRegularExpression(
                '/<a[^>]*href="'.preg_quote($currentHref, '/').'"[^>]*aria-current="page"/',
                $html,
                "Expected {$currentRoute} to mark its own sub-nav link active."
            );
        }
    }

    // ---------------------------------------------------------------------
    // The sidebar "Export & import" entry stays active on all three pages
    // ---------------------------------------------------------------------

    public function test_sidebar_export_and_import_entry_stays_active_on_all_three_pages(): void
    {
        $user = User::factory()->create();
        $sidebarHref = route('admin.data.index');

        foreach (['admin.data.export-project', 'admin.data.export-ebook', 'admin.data.import.index'] as $route) {
            $html = $this->actingAs($user)->get(route($route))->getContent();

            $this->assertMatchesRegularExpression(
                '/<a[^>]*href="'.preg_quote($sidebarHref, '/').'"[^>]*aria-current="page"/',
                $html,
                "Expected the sidebar Export & import link to be active on {$route}."
            );
        }
    }

    // ---------------------------------------------------------------------
    // No JS-tab machinery survives the split
    // ---------------------------------------------------------------------

    public function test_the_old_tab_markup_is_gone(): void
    {
        $user = User::factory()->create();

        foreach (['admin.data.export-project', 'admin.data.export-ebook', 'admin.data.import.index'] as $route) {
            $response = $this->actingAs($user)->get(route($route));

            $response->assertDontSee('role="tablist"', false);
            $response->assertDontSee('role="tab"', false);
            $response->assertDontSee('id="tab-export"', false);
            $response->assertDontSee('id="tab-import"', false);
        }
    }
}
