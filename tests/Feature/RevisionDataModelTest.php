<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Project;
use App\Models\Revision;
use App\Support\AutosavableFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The revisions data model: the revisions table, the Revision model (and its
 * MassPrunable prunable() query), the RevisionOrigin enum, and
 * config/revisions.php. Model and config tests against factory-seeded rows only
 * — RevisionRecorderTest covers the write path.
 */
class RevisionDataModelTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Migration smoke test
    // ---------------------------------------------------------------------

    public function test_revisions_table_stores_and_reads_back_a_row(): void
    {
        $revision = Revision::factory()->create([
            'field' => 'contents',
            'value' => 'Once upon a time.',
            'origin' => RevisionOrigin::Manual,
            'label' => 'Before the big rewrite',
        ]);

        $fresh = Revision::find($revision->id);

        $this->assertSame('contents', $fresh->field);
        $this->assertSame('Once upon a time.', $fresh->value);
        $this->assertSame(RevisionOrigin::Manual, $fresh->origin);
        $this->assertSame('Before the big rewrite', $fresh->label);
        $this->assertInstanceOf(Carbon::class, $fresh->created_at);
    }

    // ---------------------------------------------------------------------
    // RevisionOrigin enum
    // ---------------------------------------------------------------------

    public function test_revision_origin_has_exactly_the_four_expected_cases(): void
    {
        $cases = array_map(fn (RevisionOrigin $case) => $case->value, RevisionOrigin::cases());

        $this->assertCount(4, RevisionOrigin::cases());
        $this->assertEqualsCanonicalizing(
            ['automatic', 'manual', 'revert', 'baseline'],
            $cases,
        );
    }

    // ---------------------------------------------------------------------
    // Revision::prunable() — the safety-critical query
    // ---------------------------------------------------------------------

    public function test_prunable_includes_an_old_automatic_unlabeled_row_with_a_newer_sibling(): void
    {
        config(['revisions.retention_days' => 90]);
        $project = Project::factory()->create();

        $old = Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now()->subDays(200),
        ]);

        Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now()->subDay(),
        ]);

        $this->assertPrunableIds([$old->id]);
    }

    public function test_prunable_excludes_the_only_newest_revision_for_a_field_even_if_old_and_automatic(): void
    {
        config(['revisions.retention_days' => 90]);
        $project = Project::factory()->create();

        Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now()->subDays(200),
        ]);

        $this->assertPrunableIds([]);
    }

    public function test_prunable_excludes_non_automatic_origins_regardless_of_age(): void
    {
        config(['revisions.retention_days' => 90]);
        $project = Project::factory()->create();

        // Each of these is the newest AND only row for its own distinct field,
        // so also give each a same-field older automatic sibling to prove the
        // exclusion is about origin, not "newest for the field".
        foreach ([RevisionOrigin::Manual, RevisionOrigin::Revert, RevisionOrigin::Baseline] as $index => $origin) {
            $field = "field_{$index}";

            Revision::factory()->create([
                'revisionable_type' => Project::class,
                'revisionable_id' => $project->id,
                'project_id' => $project->id,
                'field' => $field,
                'origin' => RevisionOrigin::Automatic,
                'label' => null,
                'created_at' => now()->subDays(300),
            ]);

            Revision::factory()->create([
                'revisionable_type' => Project::class,
                'revisionable_id' => $project->id,
                'project_id' => $project->id,
                'field' => $field,
                'origin' => $origin,
                'label' => null,
                'created_at' => now()->subDays(200),
            ]);
        }

        // Only the older "automatic" siblings should be prunable — one per
        // non-automatic origin above, and those rows themselves never are.
        $prunableIds = (new Revision)->prunable()->pluck('id')->sort()->values();
        $nonAutomaticIds = Revision::query()
            ->whereNot('origin', RevisionOrigin::Automatic)
            ->pluck('id')
            ->sort()
            ->values();

        $this->assertCount($nonAutomaticIds->count(), $prunableIds);
        $this->assertEmpty($prunableIds->intersect($nonAutomaticIds));
    }

    public function test_prunable_excludes_a_labeled_automatic_row_even_if_old(): void
    {
        config(['revisions.retention_days' => 90]);
        $project = Project::factory()->create();

        Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => 'Keep this one',
            'created_at' => now()->subDays(300),
        ]);

        // A newer sibling so the labeled row is not "the only/newest" either —
        // proving the exclusion is driven by the label, not by newest-ness.
        Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now(),
        ]);

        $this->assertPrunableIds([]);
    }

    public function test_prunable_excludes_an_automatic_unlabeled_row_still_within_the_retention_window(): void
    {
        config(['revisions.retention_days' => 90]);
        $project = Project::factory()->create();

        Revision::factory()->create([
            'revisionable_type' => Project::class,
            'revisionable_id' => $project->id,
            'project_id' => $project->id,
            'field' => 'description',
            'origin' => RevisionOrigin::Automatic,
            'label' => null,
            'created_at' => now()->subDays(10),
        ]);

        $this->assertPrunableIds([]);
    }

    private function assertPrunableIds(array $expectedIds): void
    {
        $prunableIds = (new Revision)->prunable()->pluck('id')->sort()->values()->all();
        sort($expectedIds);

        $this->assertSame($expectedIds, $prunableIds);
    }

    // ---------------------------------------------------------------------
    // config/revisions.php
    // ---------------------------------------------------------------------

    public function test_config_windows_returns_the_documented_defaults(): void
    {
        $windows = config('revisions.windows');

        $this->assertSame(60, $windows['scene.contents']);
        $this->assertSame(300, $windows['default']);
    }

    public function test_config_caps_returns_the_documented_defaults(): void
    {
        $caps = config('revisions.caps');

        $this->assertSame(1_000_000, $caps['scene.contents']);
        $this->assertSame(1_000, $caps['book.rights']);
        $this->assertSame(100_000, $caps['default']);
    }

    /**
     * Both arrays are keyed by the registry's own slug — `scene.contents`, the word
     * in the address bar — so there is one vocabulary for these fourteen fields
     * rather than two with a translation step between them.
     *
     * This is the test the old two-scheme arrangement could not have: a mistyped key
     * never raises, it falls through to `default` and silently applies the wrong
     * window or cap. So rather than assert the keys are spelled right, assert that
     * every key present is one the registry can actually reach.
     */
    public function test_every_per_field_config_key_names_a_registered_field(): void
    {
        $registered = [];

        foreach (AutosavableFields::REGISTRY as $slug => [$modelClass, $fields]) {
            foreach (array_keys($fields) as $field) {
                $registered[] = "{$slug}.{$field}";
            }
        }

        foreach (['windows', 'caps'] as $array) {
            $keys = array_keys(config("revisions.{$array}"));

            foreach (array_diff($keys, ['default']) as $key) {
                $this->assertContains(
                    $key,
                    $registered,
                    "config('revisions.{$array}') has a key [{$key}] that no registered field resolves to. "
                    .'It will never be read — the lookup falls through to `default` instead, silently.',
                );
            }
        }
    }

    /**
     * The other direction: every registered field resolves both of its settings
     * without falling through, for the fields that declare an override.
     */
    public function test_every_registered_field_resolves_its_window_and_cap(): void
    {
        foreach (AutosavableFields::REGISTRY as $slug => [$modelClass, $fields]) {
            foreach (array_keys($fields) as $field) {
                $this->assertGreaterThan(0, AutosavableFields::windowSeconds($slug, $field), "{$slug}.{$field} window");
                $this->assertGreaterThan(0, AutosavableFields::characterCap($slug, $field), "{$slug}.{$field} cap");
            }
        }

        // The overrides specifically, so a rename that quietly defaulted them all
        // would be caught rather than passing the loop above.
        $this->assertSame(60, AutosavableFields::windowSeconds('scene', 'contents'));
        $this->assertSame(1_000_000, AutosavableFields::characterCap('scene', 'contents'));
        $this->assertSame(1_000, AutosavableFields::characterCap('book', 'rights'));
        $this->assertSame(20_000, AutosavableFields::characterCap('book', 'dedication'));
    }

    public function test_config_retention_days_defaults_to_ninety(): void
    {
        $this->assertSame(90, config('revisions.retention_days'));
    }
}
