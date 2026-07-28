<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\WordCountFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
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

    /**
     * Word-count spec, task 7: the live in-field counter renders on every
     * `x-autosave-field`, starting from the server-computed count so the
     * first paint is exact rather than an estimate the writer has to wait
     * out (ui.md / architecture.md's "The live counter (JS)").
     */
    public function test_the_live_word_count_renders_with_the_servers_initial_count(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['description' => 'One two three four']);

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $this->assertStringContainsString('data-word-count', $html);
        $this->assertStringContainsString('x-data="wordCount(', $html);
        $this->assertStringContainsString(WordCountFormat::text(4), $html);
    }

    /**
     * A number that changes on every keystroke must never be announced to a
     * screen reader (ui.md) — it stays available on demand, not as an event.
     */
    public function test_the_live_word_count_carries_aria_live_off(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create(['description' => 'Hello world']);

        $html = Blade::render(
            '<x-autosave-field entity="act" :model="$act" field="description" :label="__(\'Description\')" />',
            ['act' => $act],
        );

        $this->assertStringContainsString('aria-live="off"', $html);
    }

    /**
     * `WordCounter::count()` for `Scene.contents` should not be re-derived
     * here: `Scene::booted()`'s saving hook (task 4) already keeps
     * `word_count` current, and this component reuses that stored number
     * rather than re-rendering the Markdown a second time.
     *
     * The stored count is planted *wrong* on purpose. Asserting against a
     * correct one would pass whether the component reads the column or
     * recounts the contents — both return the same number — which is the same
     * hole WordCountTest's own rename test documents. Only a wrong stored
     * value reaching the page distinguishes the two.
     */
    public function test_the_live_word_count_for_scene_contents_reuses_the_stored_column(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'One two three']);

        // Written straight to the column so the saving hook never corrects it.
        DB::table('scenes')->where('id', $scene->id)->update(['word_count' => 999]);

        $html = Blade::render(
            '<x-autosave-field entity="scene" :model="$scene" field="contents" :label="__(\'Contents\')" />',
            ['scene' => $scene->fresh()],
        );

        $this->assertStringContainsString(WordCountFormat::text(999), $html);
        $this->assertStringContainsString('initialCount: 999', $html);
    }
}
