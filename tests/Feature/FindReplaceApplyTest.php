<?php

namespace Tests\Feature;

use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Scene;
use App\Models\User;
use App\Support\FieldHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The apply step (route + FindReplaceController::store + FindReplaceApplyRequest +
 * SceneFindReplace::apply()). The preview is covered by FindReplacePreviewTest.
 */
class FindReplaceApplyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Book $book;

    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        [$this->project, $this->book] = $this->projectWithBook($this->user);
        $this->chapter = Chapter::factory()->for(Act::factory()->for($this->book)->create())->create();
    }

    private function scene(string $contents, ?Chapter $chapter = null): Scene
    {
        return Scene::factory()->for($chapter ?? $this->chapter)->create(['contents' => $contents]);
    }

    /**
     * @param  array<int, list<int>>  $indexes  scene ID => match indexes
     * @return array<int, array{hash: string, indexes: list<int>}>
     */
    private function selection(array $indexes): array
    {
        $selection = [];

        foreach ($indexes as $sceneId => $chosen) {
            $selection[$sceneId] = ['hash' => FieldHash::of(Scene::query()->find($sceneId)->contents), 'indexes' => $chosen];
        }

        return $selection;
    }

    /** @param  array<string, mixed>  $overrides */
    private function apply(array $selection, array $overrides = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->user)->post(
            route('projects.replace.store', $this->project),
            $overrides + [
                'find' => 'Lane',
                'replace' => 'Dale',
                'match_case' => 1,
                'whole_word' => 1,
                'selection' => json_encode($selection),
            ],
        );
    }

    public function test_only_the_selected_indexes_change(): void
    {
        $scene = $this->scene('Lane met Lane, and then Lane left.');

        $this->apply($this->selection([$scene->id => [0, 2]]))->assertRedirect();

        $this->assertSame('Dale met Lane, and then Dale left.', $scene->fresh()->contents);
    }

    public function test_the_redirect_rebuilds_the_preview_query_and_reports_the_counts(): void
    {
        $first = $this->scene('Lane Lane');
        $second = $this->scene('Lane');

        $response = $this->apply(
            $this->selection([$first->id => [0, 1], $second->id => [0]]),
            ['book' => $this->book->id],
        );

        $response->assertRedirect(route('projects.replace.index', [
            'project' => $this->project,
            'find' => 'Lane',
            'replace' => 'Dale',
            'match_case' => 1,
            'whole_word' => 1,
            'book' => $this->book->id,
        ]));
        $response->assertSessionHas('replace_result', ['replaced' => 3, 'scenes' => 2, 'skipped' => []]);
    }

    public function test_the_page_after_apply_shows_the_matches_that_are_left_and_the_result(): void
    {
        $scene = $this->scene('Lane and Lane');

        $this->followingRedirects()
            ->apply($this->selection([$scene->id => [0]]))
            ->assertSee('data-replace-result', false)
            ->assertSee('Replaced 1 match in 1 scene.')
            ->assertSee('data-match-count="1"', false);
    }

    public function test_each_changed_scene_gets_one_labelled_manual_revision(): void
    {
        $changed = $this->scene('Lane met Lane');
        $untouched = $this->scene('Lane');

        $this->apply($this->selection([$changed->id => [0, 1], $untouched->id => []]));

        $revisions = Revision::query()
            ->where('origin', RevisionOrigin::Manual)
            ->where('field', 'contents')
            ->get();

        $this->assertCount(1, $revisions);
        $this->assertSame($changed->id, $revisions->first()->revisionable_id);
        $this->assertSame('Replace "Lane" with "Dale"', $revisions->first()->label);
        $this->assertSame('Dale met Dale', $revisions->first()->value);
        $this->assertSame(0, Revision::query()->where('revisionable_id', $untouched->id)->count());
    }

    public function test_a_long_find_text_is_cut_in_the_label(): void
    {
        $find = str_repeat('a', 120);
        $scene = $this->scene($find);

        $this->apply($this->selection([$scene->id => [0]]), ['find' => $find, 'replace' => 'b']);

        $label = Revision::query()->where('origin', RevisionOrigin::Manual)->value('label');

        $this->assertLessThan(255, strlen($label));
        $this->assertStringContainsString('...', $label);
    }

    public function test_word_count_and_codex_references_follow_the_new_text(): void
    {
        $entry = CodexEntry::factory()->for($this->project)->create(['name' => 'Melchior']);
        $scene = $this->scene('Lane arrives.');
        $this->assertSame(2, $scene->word_count);

        $this->apply($this->selection([$scene->id => [0]]), ['replace' => 'Melchior of the north']);

        $scene->refresh();
        $this->assertSame(5, $scene->word_count);
        $this->assertTrue($scene->codexReferences()->whereKey($entry->id)->exists());
    }

    public function test_a_stale_hash_skips_that_scene_and_the_others_still_apply(): void
    {
        $stale = $this->scene('Lane one');
        $fresh = $this->scene('Lane two');
        $selection = $this->selection([$stale->id => [0], $fresh->id => [0]]);

        // Another tab saves between the preview and the apply.
        $stale->update(['contents' => 'Lane one, edited']);

        $response = $this->apply($selection);

        $response->assertSessionHas('replace_result', [
            'replaced' => 1,
            'scenes' => 1,
            'skipped' => [['id' => $stale->id, 'name' => $stale->name]],
        ]);
        $this->assertSame('Lane one, edited', $stale->fresh()->contents);
        $this->assertSame('Dale two', $fresh->fresh()->contents);
        $this->assertSame(0, Revision::query()->where('origin', RevisionOrigin::Manual)->where('revisionable_id', $stale->id)->count());
    }

    public function test_a_scene_of_another_project_is_ignored(): void
    {
        [, $otherBook] = $this->projectWithBook();
        $foreign = $this->scene('Lane', Chapter::factory()->for(Act::factory()->for($otherBook)->create())->create());

        $response = $this->apply($this->selection([$foreign->id => [0]]));

        $response->assertSessionHas('replace_result', ['replaced' => 0, 'scenes' => 0, 'skipped' => []]);
        $this->assertSame('Lane', $foreign->fresh()->contents);
    }

    public function test_an_empty_replace_deletes_the_match(): void
    {
        $scene = $this->scene('Lane left.');

        $this->apply($this->selection([$scene->id => [0]]), ['replace' => '']);

        $this->assertSame(' left.', $scene->fresh()->contents);
    }

    public function test_multibyte_text_before_a_match_does_not_shift_the_replace(): void
    {
        $scene = $this->scene('Hélène et Lane, puis Lane.');

        $this->apply($this->selection([$scene->id => [1]]));

        $this->assertSame('Hélène et Lane, puis Dale.', $scene->fresh()->contents);
    }

    public function test_a_replace_equal_to_the_match_makes_no_revision(): void
    {
        $scene = $this->scene('Lane');

        $response = $this->apply($this->selection([$scene->id => [0]]), ['replace' => 'Lane']);

        $response->assertSessionHas('replace_result', ['replaced' => 0, 'scenes' => 0, 'skipped' => []]);
        $this->assertSame(0, Revision::query()->where('origin', RevisionOrigin::Manual)->count());
    }

    public function test_match_case_off_can_change_only_the_case(): void
    {
        $scene = $this->scene('Lane and lane');

        $this->apply($this->selection([$scene->id => [0, 1]]), ['replace' => 'lane', 'match_case' => 0]);

        $this->assertSame('lane and lane', $scene->fresh()->contents);
    }

    public function test_an_unknown_index_is_ignored(): void
    {
        $scene = $this->scene('Lane');

        $response = $this->apply($this->selection([$scene->id => [0, 7]]));

        $response->assertSessionHas('replace_result', ['replaced' => 1, 'scenes' => 1, 'skipped' => []]);
    }

    public function test_a_per_scene_revert_restores_the_old_text(): void
    {
        $scene = $this->scene('Lane met Lane');
        $this->apply($this->selection([$scene->id => [0, 1]]));

        $saveId = Revision::query()->where('origin', RevisionOrigin::Manual)->value('save_id');

        $this->actingAs($this->user)->post(route('revisions.saves.revert', $saveId), [
            'base_hashes' => ['contents' => FieldHash::of($scene->fresh()->contents)],
        ])->assertRedirect();

        $this->assertSame('Lane met Lane', $scene->fresh()->contents);
    }

    public function test_a_non_owner_gets_403_and_nothing_changes(): void
    {
        $scene = $this->scene('Lane');

        $this->apply($this->selection([$scene->id => [0]]), [], User::factory()->create())->assertForbidden();

        $this->assertSame('Lane', $scene->fresh()->contents);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->post(route('projects.replace.store', $this->project), ['find' => 'x'])
            ->assertRedirect(route('login'));
    }

    public function test_a_missing_or_blank_find_is_a_validation_error(): void
    {
        $scene = $this->scene('Lane');
        $selection = $this->selection([$scene->id => [0]]);

        $this->apply($selection, ['find' => ''])->assertSessionHasErrors('find');
        $this->apply($selection, ['find' => null])->assertSessionHasErrors('find');
        $this->assertSame('Lane', $scene->fresh()->contents);
    }

    public function test_a_find_of_spaces_is_allowed(): void
    {
        $scene = $this->scene('one  two');

        $this->apply($this->selection([$scene->id => [0]]), ['find' => '  ', 'replace' => ' ', 'whole_word' => 0])
            ->assertSessionHasNoErrors();

        $this->assertSame('one two', $scene->fresh()->contents);
    }

    public function test_a_find_that_is_too_long_is_rejected(): void
    {
        $this->apply([], ['find' => str_repeat('a', 501)])->assertSessionHasErrors('find');
    }

    /** @return array<string, array{string}> */
    public static function badSelections(): array
    {
        return [
            'not json' => ['{nope'],
            'a scalar' => ['5'],
            'entry is not an object' => ['{"1": 5}'],
            'missing hash' => ['{"1": {"indexes": [0]}}'],
            'hash is not a string' => ['{"1": {"hash": 5, "indexes": [0]}}'],
            'indexes missing' => ['{"1": {"hash": "x"}}'],
            'index is a string' => ['{"1": {"hash": "x", "indexes": ["0"]}}'],
            'index is negative' => ['{"1": {"hash": "x", "indexes": [-1]}}'],
            'indexes is an object' => ['{"1": {"hash": "x", "indexes": {"a": 0}}}'],
            'scene ID is not a number' => ['{"abc": {"hash": "x", "indexes": [0]}}'],
        ];
    }

    #[DataProvider('badSelections')]
    public function test_a_badly_shaped_selection_is_a_validation_error(string $json): void
    {
        $scene = $this->scene('Lane');

        $this->actingAs($this->user)
            ->post(route('projects.replace.store', $this->project), [
                'find' => 'Lane', 'replace' => 'Dale', 'selection' => $json,
            ])
            ->assertSessionHasErrors('selection');

        $this->assertSame('Lane', $scene->fresh()->contents);
    }

    public function test_a_missing_selection_is_a_validation_error(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.replace.store', $this->project), ['find' => 'Lane'])
            ->assertSessionHasErrors('selection');
    }

    public function test_more_matches_than_the_cap_are_refused(): void
    {
        $scene = $this->scene('Lane');
        $selection = [$scene->id => ['hash' => FieldHash::of('Lane'), 'indexes' => range(0, 2000)]];

        $this->apply($selection)->assertSessionHasErrors('selection');
        $this->assertSame('Lane', $scene->fresh()->contents);
    }

    public function test_exactly_the_cap_is_allowed(): void
    {
        $scene = $this->scene('Lane');
        $selection = [$scene->id => ['hash' => FieldHash::of('Lane'), 'indexes' => range(0, 1999)]];

        $this->apply($selection)->assertSessionHasNoErrors();
        $this->assertSame('Dale', $scene->fresh()->contents);
    }

    public function test_an_act_together_with_a_chapter_range_is_a_validation_error(): void
    {
        $this->apply([], [
            'book' => $this->book->id,
            'act' => $this->chapter->act_id,
            'from_chapter' => $this->chapter->id,
        ])->assertSessionHasErrors('act');
    }

    public function test_the_preview_page_posts_every_match_as_selected(): void
    {
        $first = $this->scene('Lane and Lane');
        $second = $this->scene('Lane');

        $response = $this->actingAs($this->user)
            ->get(route('projects.replace.index', ['project' => $this->project, 'find' => 'Lane', 'replace' => 'Dale']));

        $response->assertSee('data-apply-form', false);
        $selection = $response->viewData('preview')->selectAll();

        $this->assertSame([0, 1], $selection[$first->id]['indexes']);
        $this->assertSame(FieldHash::of('Lane'), $selection[$second->id]['hash']);
    }

    public function test_the_preview_page_renders_the_selection_bar_and_the_data_for_alpine(): void
    {
        $first = $this->scene('Lane and Lane');
        $second = $this->scene('Lane');

        $response = $this->actingAs($this->user)
            ->get(route('projects.replace.index', ['project' => $this->project, 'find' => 'Lane', 'replace' => 'Dale']))
            ->assertOk()
            ->assertSee('data-selection-bar', false)
            ->assertSee('data-selection-counter', false)
            ->assertSee('data-confirm-panel', false)
            ->assertSee('data-replace-scene', false)
            ->assertSee('data-scene-checkbox', false)
            ->assertSee(__('Select all'))
            ->assertSee(__('Select none'))
            ->assertSee(__('Unfold all'))
            ->assertSee(__('Fold all'));

        $this->assertMatchesRegularExpression('/x-data="findReplace\(JSON\.parse\(/', $response->getContent());
        // @js() escapes the JSON for the attribute, so look for the hashes, not the raw object.
        $response->assertSee(FieldHash::of($first->contents), false);
        $response->assertSee(FieldHash::of($second->contents), false);
    }

    public function test_a_selection_of_one_scene_changes_only_that_scene(): void
    {
        $chosen = $this->scene('Lane and Lane');
        $other = $this->scene('Lane again');

        $this->apply($this->selection([$chosen->id => [0, 1]]))->assertRedirect();

        $this->assertSame('Dale and Dale', $chosen->fresh()->contents);
        $this->assertSame('Lane again', $other->fresh()->contents);
        $this->assertSame(0, Revision::query()->where('revisionable_id', $other->id)->count());
    }

    public function test_skipped_scenes_are_listed_with_links_after_the_redirect(): void
    {
        $stale = $this->scene('Lane');
        $selection = $this->selection([$stale->id => [0]]);
        $stale->update(['contents' => 'Lane!']);

        $this->followingRedirects()
            ->apply($selection)
            ->assertSee('data-skipped-scenes', false)
            ->assertSee(route('scenes.show', $stale->id), false);
    }
}
