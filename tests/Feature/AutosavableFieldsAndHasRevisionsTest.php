<?php

namespace Tests\Feature;

use App\Enums\FieldKind;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Rules\SanitizeHtml;
use App\Rules\ValidMarkdown;
use App\Support\AutosavableFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Task 03 — the declarative wiring layer: the AutosavableFields registry and the
 * HasRevisions trait each of the 7 registered models now implements. No write
 * path yet (RevisionRecorder is task 04) — these tests only cover lookups and
 * the revisionProject() authorization boundary.
 */
class AutosavableFieldsAndHasRevisionsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // AutosavableFields::slugs()
    // ---------------------------------------------------------------------

    public function test_slugs_returns_exactly_the_seven_expected_slugs(): void
    {
        $this->assertEqualsCanonicalizing(
            ['project', 'act', 'chapter', 'plotline', 'event', 'scene', 'codex'],
            AutosavableFields::slugs(),
        );
    }

    // ---------------------------------------------------------------------
    // AutosavableFields::modelFor() / kindOf() — the 14-field table
    // ---------------------------------------------------------------------

    public static function registeredFieldProvider(): array
    {
        return [
            'project.description' => ['project', 'description', Project::class, FieldKind::Rich],
            'project.dedication' => ['project', 'dedication', Project::class, FieldKind::Markdown],
            'project.acknowledgements' => ['project', 'acknowledgements', Project::class, FieldKind::Markdown],
            'project.preface' => ['project', 'preface', Project::class, FieldKind::Markdown],
            'project.postface' => ['project', 'postface', Project::class, FieldKind::Markdown],
            'project.rights' => ['project', 'rights', Project::class, FieldKind::Plain],
            'act.description' => ['act', 'description', Act::class, FieldKind::Rich],
            'chapter.description' => ['chapter', 'description', Chapter::class, FieldKind::Rich],
            'plotline.description' => ['plotline', 'description', Plotline::class, FieldKind::Rich],
            'event.description' => ['event', 'description', Event::class, FieldKind::Rich],
            'scene.description' => ['scene', 'description', Scene::class, FieldKind::Rich],
            'scene.notes' => ['scene', 'notes', Scene::class, FieldKind::Rich],
            'scene.contents' => ['scene', 'contents', Scene::class, FieldKind::Markdown],
            'codex.description' => ['codex', 'description', CodexEntry::class, FieldKind::Rich],
        ];
    }

    #[DataProvider('registeredFieldProvider')]
    public function test_model_for_and_kind_of_return_the_documented_model_and_kind(
        string $slug,
        string $field,
        string $expectedModel,
        FieldKind $expectedKind,
    ): void {
        $this->assertSame($expectedModel, AutosavableFields::modelFor($slug));
        $this->assertSame($expectedKind, AutosavableFields::kindOf($slug, $field));
    }

    // ---------------------------------------------------------------------
    // AutosavableFields::resolveField() — the shared "unknown field 404s"
    // contract (FieldAutosaveController + RevisionController both go through it)
    // ---------------------------------------------------------------------

    public function test_resolve_field_returns_the_model_class_and_field_map_for_a_registered_pair(): void
    {
        [$modelClass, $fields] = AutosavableFields::resolveField('scene', 'notes');

        $this->assertSame(Scene::class, $modelClass);
        $this->assertSame(
            ['description' => FieldKind::Rich, 'notes' => FieldKind::Rich, 'contents' => FieldKind::Markdown],
            $fields,
        );
    }

    public function test_resolve_field_404s_for_a_field_not_registered_on_the_slug(): void
    {
        // `title` is a real Event column but is not an autosavable field on
        // any slug — resolving it must 404, never leak through as a valid pair.
        $this->expectException(NotFoundHttpException::class);

        AutosavableFields::resolveField('act', 'title');
    }

    // ---------------------------------------------------------------------
    // revisionProject() — the authorization boundary, one per model
    // ---------------------------------------------------------------------

    public function test_project_revision_project_is_itself(): void
    {
        $project = Project::factory()->create();

        $this->assertTrue($project->revisionProject()->is($project));
    }

    public function test_act_revision_project_is_its_project(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();

        $this->assertTrue($act->revisionProject()->is($project));
    }

    public function test_chapter_revision_project_is_its_act_project(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();

        $this->assertTrue($chapter->revisionProject()->is($project));
    }

    public function test_scene_revision_project_is_its_chapter_act_project(): void
    {
        $project = Project::factory()->create();
        $act = Act::factory()->for($project)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->assertTrue($scene->revisionProject()->is($project));
    }

    public function test_plotline_revision_project_is_its_project(): void
    {
        $project = Project::factory()->create();
        $plotline = Plotline::factory()->for($project)->create();

        $this->assertTrue($plotline->revisionProject()->is($project));
    }

    public function test_event_revision_project_is_its_project(): void
    {
        $project = Project::factory()->create();
        $event = Event::factory()->for($project)->create();

        $this->assertTrue($event->revisionProject()->is($project));
    }

    public function test_codex_entry_revision_project_is_its_project(): void
    {
        $project = Project::factory()->create();
        $codexEntry = CodexEntry::factory()->for($project)->create();

        $this->assertTrue($codexEntry->revisionProject()->is($project));
    }

    // ---------------------------------------------------------------------
    // windowSeconds() / characterCap() — config lookups
    // ---------------------------------------------------------------------

    public function test_window_seconds_reads_the_specific_field_override(): void
    {
        $this->assertSame(60, AutosavableFields::windowSeconds('scene', 'contents'));
    }

    public function test_window_seconds_falls_back_to_default(): void
    {
        $this->assertSame(300, AutosavableFields::windowSeconds('act', 'description'));
    }

    public function test_character_cap_reads_the_specific_field_override(): void
    {
        $this->assertSame(1_000, AutosavableFields::characterCap('project', 'rights'));
        $this->assertSame(1_000_000, AutosavableFields::characterCap('scene', 'contents'));
    }

    public function test_character_cap_falls_back_to_default(): void
    {
        $this->assertSame(100_000, AutosavableFields::characterCap('act', 'description'));
    }

    // ---------------------------------------------------------------------
    // validationRule() — one rule source for the autosave endpoint and forms
    // ---------------------------------------------------------------------

    public function test_validation_rule_for_a_rich_field_includes_sanitize_html(): void
    {
        $rule = AutosavableFields::validationRule('act', 'description');

        $this->assertContains('nullable', $rule);
        $this->assertContains('string', $rule);
        $this->assertContains('max:100000', $rule);
        $this->assertTrue(
            collect($rule)->contains(fn ($item) => $item instanceof SanitizeHtml),
        );
    }

    public function test_validation_rule_for_a_markdown_field_includes_valid_markdown(): void
    {
        $rule = AutosavableFields::validationRule('scene', 'contents');

        $this->assertContains('max:1000000', $rule);
        $this->assertTrue(
            collect($rule)->contains(fn ($item) => $item instanceof ValidMarkdown),
        );
    }

    public function test_validation_rule_for_a_plain_field_is_a_bare_string_rule_with_cap(): void
    {
        $rule = AutosavableFields::validationRule('project', 'rights');

        $this->assertSame(['nullable', 'string', 'max:1000'], $rule);
    }
}
