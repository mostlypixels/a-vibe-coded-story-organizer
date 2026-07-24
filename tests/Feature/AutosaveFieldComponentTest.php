<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Covers task 08 — the `<x-autosave-field>` component itself. Not yet wired into
 * any real edit view (task 09's job); rendered standalone via Blade::render(),
 * matching CrawlerSettingTest's precedent for isolated component assertions.
 */
class AutosaveFieldComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // x-input-error reads $errors, normally shared by the ShareErrorsFromSession
        // middleware on a real HTTP request. Blade::render() bypasses the kernel
        // entirely, so it must be shared explicitly or the component throws.
        View::share('errors', new ViewErrorBag);
    }

    public function test_rich_kind_renders_the_wysiwyg_editor(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['description' => 'Hello world']);

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $this->assertStringContainsString('x-data="wysiwyg(', $html);
        $this->assertStringContainsString('name="description"', $html);
    }

    public function test_plain_kind_renders_a_bare_textarea_not_the_rich_editor(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['rights' => 'All rights reserved.']);

        $html = Blade::render(
            '<x-autosave-field entity="project" :model="$project" field="rights" :label="__(\'Rights\')" />',
            ['project' => $project],
        );

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringNotContainsString('x-data="wysiwyg(', $html);
        $this->assertStringContainsString('name="rights"', $html);
    }

    public function test_markdown_kind_renders_the_wysiwyg_editor_in_markdown_mode(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['dedication' => 'For my cat.']);

        $html = Blade::render(
            '<x-autosave-field entity="project" :model="$project" field="dedication" :label="__(\'Dedication\')" />',
            ['project' => $project],
        );

        $this->assertStringContainsString('data-format="markdown"', $html);
    }

    public function test_data_hash_matches_the_currently_stored_value(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['description' => 'A specific value']);

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $expectedHash = hash('sha256', 'A specific value');

        $this->assertStringContainsString('data-hash="'.$expectedHash.'"', $html);
    }

    public function test_data_hash_of_an_empty_field_matches_the_empty_string_hash(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['description' => null]);

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $this->assertStringContainsString('data-hash="'.hash('sha256', '').'"', $html);
    }

    public function test_history_link_renders_now_that_the_revisions_route_exists(): void
    {
        // Task 10 registered revisions.index/revisions.compare — the Route::has()
        // guard in autosave-field.blade.php (task 8) now resolves true, so the
        // per-field History link renders instead of being omitted.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $expectedUrl = route('revisions.index', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']);

        // historyUrl is rendered via plain Blade `{{ }}` output (a real <a href>,
        // not @js()'s JSON-escaped attribute), so it appears verbatim.
        $this->assertStringContainsString('History', $html);
        $this->assertStringContainsString($expectedUrl, $html);
    }

    public function test_the_autosave_url_targets_the_generic_patch_endpoint(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $expectedUrl = route('autosave.update', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']);

        // @js() encodes for safe embedding in an HTML attribute (Illuminate\Support\Js
        // escapes forward slashes), so the URL appears JSON-escaped, not verbatim.
        $this->assertStringContainsString(str_replace('/', '\/', $expectedUrl), $html);
    }

    public function test_the_inline_draft_banner_no_longer_renders(): void
    {
        // Task 03 (autosave-storage-improvements) removed the old inline per-field
        // banner entirely — draft recovery now lives in the page-level
        // <x-autosave-draft-recovery-modal> mounted once in layouts/app.blade.php.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['description' => 'Hello world']);

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $this->assertStringNotContainsString('data-autosave-draft-banner', $html);
        $this->assertStringNotContainsString('draftAction', $html);
    }

    public function test_compare_url_is_passed_into_the_alpine_config(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $expectedUrl = route('revisions.compare', ['entity' => 'act', 'id' => $act->id, 'field' => 'description']);

        $this->assertStringContainsString('compareUrl:', $html);
        $this->assertStringContainsString(str_replace('/', '\/', $expectedUrl), $html);
    }
}
