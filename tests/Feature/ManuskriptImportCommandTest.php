<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Drives `manuskript:import` end to end against the trimmed fixture tree in
 * `tests/Fixtures/manuskript/`: the command itself, the project + single act,
 * chapters and scenes, and character codex entries.
 */
class ManuskriptImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = base_path('tests/Fixtures/manuskript');
    }

    public function test_it_imports_the_project_and_a_single_act(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $project = Project::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Petals and Rust', $project->name);
        $this->assertSame('J. Alcott', $project->author);

        $act = Act::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('Act 1', $act->name);
        $this->assertSame(1, $act->position);
        $this->assertSame(1, Act::where('project_id', $project->id)->count());
    }

    public function test_name_option_overrides_the_infos_title(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
            '--name' => 'A Different Title',
        ])->assertSuccessful();

        $this->assertSame('A Different Title', Project::where('user_id', $user->id)->firstOrFail()->name);
    }

    public function test_act_option_overrides_the_default_act_name(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
            '--act' => 'Part One',
        ])->assertSuccessful();

        $project = Project::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Part One', Act::where('project_id', $project->id)->firstOrFail()->name);
    }

    public function test_user_option_resolves_by_id(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => (string) $user->id,
        ])->assertSuccessful();

        $this->assertSame($user->id, Project::firstOrFail()->user_id);
    }

    public function test_user_option_resolves_by_email(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->email,
        ])->assertSuccessful();

        $this->assertSame($user->id, Project::firstOrFail()->user_id);
    }

    public function test_user_option_defaults_to_the_sole_user_when_omitted(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
        ])->assertSuccessful();

        $this->assertSame($user->id, Project::firstOrFail()->user_id);
    }

    public function test_it_fails_when_the_user_is_ambiguous(): void
    {
        User::factory()->count(2)->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
        ])->assertFailed();

        $this->assertSame(0, Project::count());
    }

    public function test_it_fails_for_an_unknown_user(): void
    {
        User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => 'nobody@example.com',
        ])->assertFailed();

        $this->assertSame(0, Project::count());
    }

    public function test_it_fails_for_a_nonexistent_path(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath.'/does-not-exist',
            '--user' => $user->id,
        ])->assertFailed();

        $this->assertSame(0, Project::count());
    }

    public function test_it_fails_when_the_manuskript_marker_is_missing(): void
    {
        $user = User::factory()->create();

        $notAProject = sys_get_temp_dir().'/manuskript-import-test-'.uniqid();
        mkdir($notAProject);
        mkdir("{$notAProject}/outline");

        try {
            $this->artisan('manuskript:import', [
                'path' => $notAProject,
                '--user' => $user->id,
            ])->assertFailed();

            $this->assertSame(0, Project::count());
        } finally {
            rmdir("{$notAProject}/outline");
            rmdir($notAProject);
        }
    }

    public function test_it_imports_chapters_in_numeric_prefix_order_with_contiguous_positions(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $act = Act::where('project_id', Project::firstOrFail()->id)->firstOrFail();
        $chapters = Chapter::where('act_id', $act->id)->orderBy('position')->get();

        // "5-empty-chapter" sorts between the two zero-padded prefixes only
        // under a NUMERIC sort (0, 5, 10) — a string sort would put it last.
        $this->assertSame(['First Bloom', 'Interlude', 'second chapter'], $chapters->pluck('name')->all());
        $this->assertSame([1, 2, 3], $chapters->pluck('position')->all());
    }

    public function test_it_imports_scenes_in_numeric_prefix_order_with_contiguous_positions(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $firstChapter = Chapter::where('name', 'First Bloom')->firstOrFail();
        $scenes = Scene::where('chapter_id', $firstChapter->id)->orderBy('position')->get();

        $this->assertSame(['Opening', 'no title', 'Confrontation'], $scenes->pluck('name')->all());
        $this->assertSame([1, 2, 3], $scenes->pluck('position')->all());
    }

    public function test_scene_name_falls_back_to_the_filename_when_title_is_missing(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        // "10-no-title.md" has no title: header: the prefix and extension are
        // stripped and "-" is restored to a space.
        $this->assertTrue(Scene::where('name', 'no title')->exists());
    }

    public function test_scene_contents_is_the_body_verbatim_with_no_header_block(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $scene = Scene::where('name', 'Opening')->firstOrFail();

        $this->assertSame(
            "Renée stepped into the greenhouse before dawn, the glass still fogged with the night's chill.\n",
            $scene->contents,
        );
    }

    public function test_disallowed_html_in_a_scene_body_is_stripped_marked_and_counted(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Disallowed HTML removed from 1 scene content block(s).');

        $scene = Scene::where('name', 'Confrontation')->firstOrFail();

        $this->assertStringContainsString('[INVALID CONTENT REMOVED]', $scene->contents);
        $this->assertStringNotContainsString('<object', $scene->contents);
        $this->assertStringContainsString('Renée found the note pinned to the shed door.', $scene->contents);
        $this->assertStringContainsString('She read it twice before she understood what it meant.', $scene->contents);
    }

    public function test_empty_chapters_and_empty_scenes_are_imported_and_counted(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        // "5-empty-chapter" carries a title ("Interlude") but no scene files.
        $emptyChapter = Chapter::where('name', 'Interlude')->firstOrFail();
        $this->assertSame(0, Scene::where('chapter_id', $emptyChapter->id)->count());

        $emptyScene = Scene::where('name', 'no title')->firstOrFail();
        $this->assertSame('', $emptyScene->contents);
    }

    public function test_it_reports_the_loose_file_directly_under_outline(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('99-TODO.md');
    }

    public function test_it_reports_a_non_scene_file_inside_a_chapter_directory(): void
    {
        $user = User::factory()->create();

        // ".grazie.en.yaml" is a stray IDE file inside "00-first-chapter": not
        // a scene, not folder.txt, so it is reported as skipped, not imported.
        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('.grazie.en.yaml');
    }

    public function test_it_reports_total_chapter_and_scene_counts(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Chapters: 3, scenes: 4');
    }

    public function test_it_imports_characters_as_codex_entries(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('characters: 3');

        $project = Project::where('user_id', $user->id)->firstOrFail();
        $entries = CodexEntry::where('project_id', $project->id)->get();

        $this->assertCount(3, $entries);
        $this->assertTrue($entries->every(fn (CodexEntry $entry) => $entry->type === CodexEntryType::Character));
        $this->assertSame(
            ['Aline & Fils <the shopkeeper>', 'Renée Dupré', 'Tomas Ferreira'],
            $entries->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_filled_character_fields_become_headings_with_their_value(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $entry = CodexEntry::where('name', 'Renée Dupré')->firstOrFail();

        $this->assertStringContainsString('<h3>Age</h3><p>34</p>', $entry->description);
        $this->assertStringContainsString(
            '<h3>Motivation</h3><p>Escape the family orchard before the harvest ruins her plans.</p>',
            $entry->description,
        );
        $this->assertStringContainsString(
            '<h3>Full summary</h3><p>A gardener turned reluctant heir, torn between duty and ambition.</p>',
            $entry->description,
        );
    }

    public function test_id_color_importance_and_pov_never_become_headings(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $entry = CodexEntry::where('name', 'Renée Dupré')->firstOrFail();

        $this->assertStringNotContainsString('<h3>Id</h3>', $entry->description);
        $this->assertStringNotContainsString('<h3>Color</h3>', $entry->description);
        $this->assertStringNotContainsString('<h3>Importance</h3>', $entry->description);
        $this->assertStringNotContainsString('<h3>Pov</h3>', $entry->description);
    }

    public function test_multi_line_notes_becomes_a_paragraph_with_line_breaks(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $entry = CodexEntry::where('name', 'Renée Dupré')->firstOrFail();

        // HtmlSanitizer normalizes <br> to the self-closing <br /> form on write.
        $this->assertStringContainsString(
            '<h3>Notes</h3><p>Loves rain.<br />Hates goodbyes.<br />Keeps a journal in the potting shed.</p>',
            $entry->description,
        );
    }

    public function test_a_character_with_only_unfilled_fields_imports_with_an_empty_description(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $entry = CodexEntry::where('name', 'Tomas Ferreira')->firstOrFail();

        $this->assertTrue($entry->description === null || $entry->description === '');
    }

    public function test_character_description_survives_the_rich_text_sanitizer_unchanged(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        $entry = CodexEntry::where('name', 'Renée Dupré')->firstOrFail();
        $writtenDescription = $entry->description;

        // Re-save unchanged: SanitizesRichHtml would strip anything outside
        // the allow-list on write, proving the import produced clean HTML.
        $entry->description = $writtenDescription;
        $entry->save();

        $this->assertSame($writtenDescription, $entry->fresh()->description);
    }

    public function test_a_character_name_containing_html_special_characters_is_kept_raw(): void
    {
        $user = User::factory()->create();

        $this->artisan('manuskript:import', [
            'path' => $this->fixturePath,
            '--user' => $user->id,
        ])->assertSuccessful();

        // CodexEntry.name is plain text, escaped only at render time (Blade
        // {{ }}) — the import must not pre-escape or strip it.
        $entry = CodexEntry::where('type', CodexEntryType::Character)
            ->where('name', 'like', 'Aline%')
            ->firstOrFail();

        $this->assertSame('Aline & Fils <the shopkeeper>', $entry->name);
    }
}
