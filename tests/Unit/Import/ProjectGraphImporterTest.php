<?php

namespace Tests\Unit\Import;

use App\Enums\SceneStatus;
use App\Exceptions\ImportValidationException;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\Import\ProjectGraphImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unit-level tests for ProjectGraphImporter (import task 04): the phase
 * methods are called directly against a hand-built, already-extracted archive
 * directory — no zip, no HTTP, no Import row (those belong to tasks 02/05/06).
 *
 * The fixture covers every remapping/reconciliation rule from data-model.md:
 * a story tree whose position order deliberately disagrees with insertion
 * order, a non-main plotline and a non-fixed event alongside the anchors, a
 * scene referencing timeline events, and a codex entry with aliases, tags,
 * event-anchored attribute values, and media both with and without bytes.
 */
class ProjectGraphImporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A minimal valid 1x1 PNG for the cover media bytes.
     */
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * The extraction root the fixture archive is written under (the directory
     * that contains data/); removed in tearDown.
     */
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->fixtureRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-importer-test-'.uniqid();
        $this->writeFixture();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Full run — remapped rows everywhere a source id appeared
    // ------------------------------------------------------------------

    public function test_a_full_import_produces_the_expected_graph_with_remapped_ids(): void
    {
        $user = User::factory()->create();

        [$project, $idMaps] = $this->runFullImport($user);

        // Project (phase 1) — owned by the importing user, fresh id, content read.
        $this->assertSame($user->id, $project->user_id);
        $this->assertSame('Fixture project', $project->name);
        $this->assertStringContainsString('<strong>bold</strong>', (string) $project->description);
        $this->assertNotSame(900, $project->id, 'the archive project id must never be reused');

        // Timeline (phase 2) — 2 plotlines, 3 events, pivot remapped.
        $this->assertSame(2, $project->plotlines()->count());
        $this->assertSame(3, $project->events()->count());

        $sideQuest = $project->plotlines()->where('name', 'Side quest')->firstOrFail();
        $this->assertSame($sideQuest->id, $idMaps[ProjectGraphImporter::MAP_PLOTLINES][701]);

        $reveal = $project->events()->where('title', 'The Reveal')->firstOrFail();
        $this->assertFalse($reveal->is_fixed);
        $this->assertNotSame(802, $reveal->id);
        // plotline_ids [700, 701] resolved to the NEW plotline ids.
        $this->assertEqualsCanonicalizing(
            [$idMaps[ProjectGraphImporter::MAP_PLOTLINES][700], $sideQuest->id],
            $reveal->plotlines()->pluck('plotlines.id')->all(),
        );

        // Story (phase 3) — the whole tree, nested under the new project.
        $this->assertSame(1, $project->acts()->count());
        $act = $project->acts()->firstOrFail();
        $this->assertSame('Act One', $act->name);
        $this->assertSame(1, $act->chapters()->count());
        $this->assertSame(2, $act->chapters()->firstOrFail()->scenes()->count());

        $sceneB = Scene::query()->where('name', 'Scene B')->firstOrFail();
        $this->assertSame(SceneStatus::ToEdit, $sceneB->status);
        $this->assertStringContainsString('*prose*', (string) $sceneB->contents);

        // Codex (phase 4) — tag, attribute, entry, aliases, values, media.
        $this->assertSame(1, $project->tags()->count());
        $this->assertSame(1, $project->codexAttributes()->count());
        $this->assertSame(1, $project->codexEntries()->count());

        $entry = $project->codexEntries()->firstOrFail();
        $this->assertSame('Alice Harker', $entry->name);
        $this->assertEqualsCanonicalizing(['Ally', 'The Wanderer'], $entry->aliases()->pluck('alias')->all());
        $this->assertSame(
            [$idMaps[ProjectGraphImporter::MAP_TAGS][600]],
            $entry->tags()->pluck('tags.id')->all(),
        );
        $this->assertSame(2, $entry->attributeValues()->count());
        $this->assertSame(2, $entry->media()->count());
    }

    // ------------------------------------------------------------------
    // Anchor reconciliation — update in place, never duplicate
    // ------------------------------------------------------------------

    public function test_the_archive_anchors_update_the_auto_created_rows_in_place(): void
    {
        $user = User::factory()->create();

        $importer = $this->importer();
        $project = $importer->importProject($this->fixtureRoot, $user);

        // Capture the auto-created rows BEFORE the timeline phase runs.
        $autoCreatedMainId = $project->plotlines()->where('is_main', true)->firstOrFail()->id;
        $autoCreatedStartId = $project->startEvent()->id;
        $autoCreatedEndId = $project->endEvent()->id;

        $idMaps = [];
        $importer->importTimeline($this->fixtureRoot, $project, $idMaps);

        // Exactly one main plotline and two bookends remain — no duplicates.
        $this->assertSame(1, $project->plotlines()->where('is_main', true)->count());
        $this->assertSame(2, $project->events()->where('is_fixed', true)->count());

        // The SAME rows, updated with EVERY field the archive recorded.
        $main = $project->plotlines()->where('is_main', true)->firstOrFail();
        $this->assertSame($autoCreatedMainId, $main->id);
        $this->assertSame('Central Arc', $main->name);
        $this->assertSame('#ef4444', $main->color);
        $this->assertStringContainsString('The spine.', (string) $main->description);
        $this->assertSame($autoCreatedMainId, $idMaps[ProjectGraphImporter::MAP_PLOTLINES][700]);

        $start = $project->startEvent();
        $this->assertSame($autoCreatedStartId, $start->id);
        $this->assertSame('Dawn of Time', $start->title);
        $this->assertStringContainsString('It begins.', (string) $start->description);
        $this->assertSame($autoCreatedStartId, $idMaps[ProjectGraphImporter::MAP_EVENTS][800]);

        $end = $project->endEvent();
        $this->assertSame($autoCreatedEndId, $end->id);
        $this->assertSame('Heat Death', $end->title);
        $this->assertSame($autoCreatedEndId, $idMaps[ProjectGraphImporter::MAP_EVENTS][801]);
    }

    // ------------------------------------------------------------------
    // Position replay
    // ------------------------------------------------------------------

    public function test_position_order_survives_even_when_it_disagrees_with_insertion_order(): void
    {
        [$project] = $this->runFullImport(User::factory()->create());

        $chapter = $project->acts()->firstOrFail()->chapters()->firstOrFail();
        $scenes = $chapter->scenes()->orderBy('position')->get();

        // The fixture's directory order (300-scene-b before 301-scene-a) makes
        // Scene B insert FIRST — a hook-derived position would give it 1. The
        // archive says B is position 2, and that must win.
        $this->assertSame(['Scene A', 'Scene B'], $scenes->pluck('name')->all());
        $this->assertSame([1, 2], $scenes->pluck('position')->all());

        $sceneB = $scenes->firstWhere('name', 'Scene B');
        $sceneA = $scenes->firstWhere('name', 'Scene A');
        $this->assertLessThan($sceneA->id, $sceneB->id, 'Scene B must have been inserted first for this test to prove replay');
    }

    public function test_codex_media_position_is_replayed_from_the_archive(): void
    {
        [$project] = $this->runFullImport(User::factory()->create());

        $referenceFile = $project->codexEntries()->firstOrFail()
            ->media()->where('original_name', 'notes.pdf')->firstOrFail();

        // The archive says position 3; the creating() hook would derive 1.
        $this->assertSame(3, $referenceFile->position);
    }

    // ------------------------------------------------------------------
    // Reference remapping
    // ------------------------------------------------------------------

    public function test_a_scene_event_reference_resolves_to_the_new_event_id(): void
    {
        [$project, $idMaps] = $this->runFullImport(User::factory()->create());

        $sceneB = Scene::query()->where('name', 'Scene B')->firstOrFail();
        $reveal = $project->events()->where('title', 'The Reveal')->firstOrFail();

        $this->assertSame($reveal->id, $sceneB->event_id);
        $this->assertNotSame(802, $sceneB->event_id, 'the archive event id must never be written');

        // mentioned_event_ids [800] resolves to the reconciled Start bookend.
        $this->assertSame(
            [$project->startEvent()->id],
            $sceneB->mentionedEvents()->pluck('events.id')->all(),
        );
        $this->assertSame($idMaps[ProjectGraphImporter::MAP_EVENTS][800], $project->startEvent()->id);
    }

    public function test_attribute_value_start_events_resolve_including_the_start_bookend(): void
    {
        [$project] = $this->runFullImport(User::factory()->create());

        $entry = $project->codexEntries()->firstOrFail();
        $reveal = $project->events()->where('title', 'The Reveal')->firstOrFail();

        $baseline = $entry->attributeValues()->where('value', '29')->firstOrFail();
        $this->assertSame($project->startEvent()->id, $baseline->start_event_id);

        $later = $entry->attributeValues()->where('value', '30')->firstOrFail();
        $this->assertSame($reveal->id, $later->start_event_id);
    }

    public function test_an_unresolvable_event_reference_throws_and_rolls_back_the_phase(): void
    {
        $user = User::factory()->create();

        // Corrupt Scene B's event_id to a source id no event.json carries.
        $this->mutateFixtureJson(
            'data/acts/100-act-one/chapters/200-chapter-one/scenes/300-scene-b/scene.json',
            fn (array $scene): array => array_merge($scene, ['event_id' => 999999]),
        );

        $importer = $this->importer();
        $project = $importer->importProject($this->fixtureRoot, $user);
        $idMaps = [];
        $importer->importTimeline($this->fixtureRoot, $project, $idMaps);

        try {
            $importer->importStory($this->fixtureRoot, $project, $idMaps);
            $this->fail('An unresolvable event_id must throw, never be silently dropped.');
        } catch (ImportValidationException $exception) {
            $this->assertStringContainsString('event_id', $exception->getMessage());
        }

        // The story phase is one transaction: nothing from it may survive.
        $this->assertSame(0, $project->acts()->count());
        $this->assertSame(0, Scene::query()->count());
        // ...while the previously committed phases are untouched.
        $this->assertSame(3, $project->events()->count());
    }

    // ------------------------------------------------------------------
    // Media
    // ------------------------------------------------------------------

    public function test_media_bytes_are_copied_to_a_newly_generated_storage_path(): void
    {
        [$project] = $this->runFullImport(User::factory()->create());

        $cover = $project->codexEntries()->firstOrFail()
            ->media()->where('collection', 'cover')->firstOrFail();

        $this->assertTrue($cover->hasFile());
        $this->assertNotNull($cover->path);
        // A fresh storage path — never the archive's own relative path.
        $this->assertNotSame('cover/portrait.png', $cover->path);
        $this->assertStringStartsWith('codex-media/', $cover->path);
        Storage::disk('public')->assertExists($cover->path);
        $this->assertSame(
            base64_decode(self::TINY_PNG_BASE64),
            Storage::disk('public')->get($cover->path),
        );

        // original_name is re-derived via basename() — the fixture declares a
        // path-traversing original_name on purpose.
        $this->assertSame('portrait.png', $cover->original_name);
    }

    public function test_a_declared_media_row_without_bytes_creates_a_metadata_only_row(): void
    {
        [$project] = $this->runFullImport(User::factory()->create());

        $referenceFile = $project->codexEntries()->firstOrFail()
            ->media()->where('original_name', 'notes.pdf')->firstOrFail();

        $this->assertFalse($referenceFile->hasFile());
        $this->assertNull($referenceFile->path);
        $this->assertNull($referenceFile->url());
        // The metadata the archive DID carry is preserved in full.
        $this->assertSame('application/pdf', $referenceFile->mime_type);
        $this->assertSame(1234, $referenceFile->size);
    }

    // ------------------------------------------------------------------
    // Naming collisions
    // ------------------------------------------------------------------

    public function test_importing_the_same_archive_twice_suffixes_the_second_name(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $first = $this->importer()->importProject($this->fixtureRoot, $user);
        $second = $this->importer()->importProject($this->fixtureRoot, $user);

        $this->assertSame('Fixture project', $first->name);
        $this->assertSame(
            'Fixture project (imported '.now()->format('Y-m-d H:i').')',
            $second->name,
        );
    }

    public function test_the_name_collision_check_is_case_insensitive(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'FIXTURE PROJECT']);

        $imported = $this->importer()->importProject($this->fixtureRoot, $user);

        $this->assertSame(
            'Fixture project (imported '.now()->format('Y-m-d H:i').')',
            $imported->name,
        );
    }

    public function test_another_users_project_name_never_collides(): void
    {
        $otherUser = User::factory()->create();
        Project::factory()->for($otherUser)->create(['name' => 'Fixture project']);

        $imported = $this->importer()->importProject($this->fixtureRoot, User::factory()->create());

        $this->assertSame('Fixture project', $imported->name);
    }

    // ------------------------------------------------------------------
    // Content sanitization is applied inline as files are read
    // ------------------------------------------------------------------

    public function test_a_disallowed_html_fragment_rejects_the_import(): void
    {
        file_put_contents(
            $this->fixtureRoot.'/data/project/description.html',
            '<p onclick="alert(1)">Hi</p>',
        );

        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage('HTML content that is not allowed');

        $this->importer()->importProject($this->fixtureRoot, User::factory()->create());
    }

    // ------------------------------------------------------------------
    // Fixture + helpers
    // ------------------------------------------------------------------

    private function importer(): ProjectGraphImporter
    {
        return app(ProjectGraphImporter::class);
    }

    /**
     * Run all four phases in order, exactly as the orchestrator (task 05) will.
     *
     * @return array{0: Project, 1: array<string, array<int, int>>}
     */
    private function runFullImport(User $user): array
    {
        $importer = $this->importer();

        $project = $importer->importProject($this->fixtureRoot, $user);
        $idMaps = [];
        $importer->importTimeline($this->fixtureRoot, $project, $idMaps);
        $importer->importStory($this->fixtureRoot, $project, $idMaps);
        $importer->importCodex($this->fixtureRoot, $project, $idMaps);

        return [$project, $idMaps];
    }

    /**
     * Decode a fixture descriptor, transform it, and write it back.
     */
    private function mutateFixtureJson(string $relativePath, callable $mutate): void
    {
        $absolute = $this->fixtureRoot.'/'.$relativePath;
        $decoded = json_decode((string) file_get_contents($absolute), true);
        file_put_contents($absolute, json_encode($mutate($decoded)));
    }

    /**
     * Write the hand-built extracted archive: one project, a story tree whose
     * scene positions disagree with directory (= insertion) order, the two
     * anchor rows plus one regular plotline/event, and a codex entry carrying
     * aliases, a tag, two event-anchored attribute values, and two media rows
     * (cover bytes present; reference file metadata-only).
     */
    private function writeFixture(): void
    {
        $this->writeFixtureFile('data/project/project.json', json_encode([
            'id' => 900,
            'name' => 'Fixture project',
            'description_file' => 'description.html',
        ]));
        $this->writeFixtureFile('data/project/description.html', '<p>A <strong>bold</strong> project.</p>');

        // --- Timeline: main plotline + side quest, two bookends + a regular event.
        $this->writeFixtureFile('data/timeline/plotlines/700-central-arc/plotline.json', json_encode([
            'id' => 700, 'name' => 'Central Arc', 'color' => '#ef4444', 'is_main' => true,
            'project_id' => 900, 'description_file' => 'description.html',
        ]));
        $this->writeFixtureFile('data/timeline/plotlines/700-central-arc/description.html', '<p>The spine.</p>');
        $this->writeFixtureFile('data/timeline/plotlines/701-side-quest/plotline.json', json_encode([
            'id' => 701, 'name' => 'Side quest', 'color' => '#3b82f6', 'is_main' => false, 'project_id' => 900,
        ]));

        $this->writeFixtureFile('data/timeline/events/800-dawn-of-time/event.json', json_encode([
            'id' => 800, 'title' => 'Dawn of Time', 'event_datetime' => '0001-01-01T00:00:00+00:00',
            'is_fixed' => true, 'project_id' => 900, 'plotline_ids' => [700],
            'description_file' => 'description.html',
        ]));
        $this->writeFixtureFile('data/timeline/events/800-dawn-of-time/description.html', '<p>It begins.</p>');
        $this->writeFixtureFile('data/timeline/events/801-heat-death/event.json', json_encode([
            'id' => 801, 'title' => 'Heat Death', 'event_datetime' => '3000-01-01T00:00:00+00:00',
            'is_fixed' => true, 'project_id' => 900, 'plotline_ids' => [700],
        ]));
        $this->writeFixtureFile('data/timeline/events/802-the-reveal/event.json', json_encode([
            'id' => 802, 'title' => 'The Reveal', 'event_datetime' => '2026-05-01T09:30:00+00:00',
            'is_fixed' => false, 'project_id' => 900, 'plotline_ids' => [700, 701],
        ]));

        // --- Story: one act, one chapter, two scenes whose positions disagree
        // with directory order (300-scene-b globs before 301-scene-a).
        $this->writeFixtureFile('data/acts/100-act-one/act.json', json_encode([
            'id' => 100, 'name' => 'Act One', 'position' => 1, 'project_id' => 900,
            'description_file' => 'description.html',
        ]));
        $this->writeFixtureFile('data/acts/100-act-one/description.html', '<p>Opening act.</p>');
        $this->writeFixtureFile('data/acts/100-act-one/chapters/200-chapter-one/chapter.json', json_encode([
            'id' => 200, 'name' => 'Chapter One', 'position' => 1, 'act_id' => 100,
        ]));

        $sceneDir = 'data/acts/100-act-one/chapters/200-chapter-one/scenes';
        $this->writeFixtureFile("{$sceneDir}/300-scene-b/scene.json", json_encode([
            'id' => 300, 'name' => 'Scene B', 'position' => 2, 'status' => 'to_edit',
            'chapter_id' => 200, 'event_id' => 802, 'mentioned_event_ids' => [800],
            'contents_file' => 'contents.md', 'description_file' => 'description.html',
            'notes_file' => 'notes.html',
        ]));
        $this->writeFixtureFile("{$sceneDir}/300-scene-b/contents.md", 'Some *prose* here.');
        $this->writeFixtureFile("{$sceneDir}/300-scene-b/description.html", '<p>The confrontation.</p>');
        $this->writeFixtureFile("{$sceneDir}/300-scene-b/notes.html", '<p>Tighten the ending.</p>');
        $this->writeFixtureFile("{$sceneDir}/301-scene-a/scene.json", json_encode([
            'id' => 301, 'name' => 'Scene A', 'position' => 1, 'status' => 'draft',
            'chapter_id' => 200, 'event_id' => null, 'mentioned_event_ids' => [],
        ]));

        // --- Codex: tags, one attribute, one entry with everything attached.
        $this->writeFixtureFile('data/tags.json', json_encode([
            ['id' => 600, 'name' => 'protagonist'],
        ]));
        $this->writeFixtureFile('data/codex/attributes.json', json_encode([
            ['id' => 500, 'name' => 'Age', 'applies_to' => ['character'], 'position' => 1],
        ]));

        $pngBytes = base64_decode(self::TINY_PNG_BASE64);
        $entryDir = 'data/codex/character/400-alice-harker';
        $this->writeFixtureFile("{$entryDir}/entry.json", json_encode([
            'id' => 400,
            'name' => 'Alice Harker',
            'type' => 'character',
            'project_id' => 900,
            'aliases' => ['Ally', 'The Wanderer'],
            'tag_ids' => [600],
            'attribute_values' => [
                ['id' => 1, 'attribute_id' => 500, 'start_event_id' => 800, 'value' => '29'],
                ['id' => 2, 'attribute_id' => 500, 'start_event_id' => 802, 'value' => '30'],
            ],
            'media' => [
                [
                    'id' => 71, 'collection' => 'cover', 'position' => 1,
                    // A traversal-shaped original_name on purpose: import must
                    // re-derive it via basename(), never trust the JSON.
                    'original_name' => '../evil/portrait.png',
                    'mime_type' => 'image/png', 'size' => strlen($pngBytes),
                    'file' => 'cover/portrait.png',
                ],
                [
                    // Metadata-only: declared, but no bytes in the archive —
                    // and a position (3) the creating() hook would never derive.
                    'id' => 72, 'collection' => 'reference_file', 'position' => 3,
                    'original_name' => 'notes.pdf', 'mime_type' => 'application/pdf',
                    'size' => 1234, 'file' => 'reference-files/01-notes.pdf',
                ],
            ],
            'description_file' => 'description.html',
        ]));
        $this->writeFixtureFile("{$entryDir}/description.html", '<p>The protagonist.</p>');
        $this->writeFixtureFile("{$entryDir}/cover/portrait.png", $pngBytes);
    }

    /**
     * Write one fixture file, creating its directory on the way.
     */
    private function writeFixtureFile(string $relativePath, string $contents): void
    {
        $absolute = $this->fixtureRoot.'/'.$relativePath;

        $directory = dirname($absolute);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolute, $contents);
    }

    /**
     * Recursively remove the fixture directory.
     */
    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
