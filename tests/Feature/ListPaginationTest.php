<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The `x-pagination-bar` and every index it was dropped into: tags, codex
 * attributes, events, plotlines, codex entries, scenes, chapters, acts and books.
 */
class ListPaginationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Three words, so every scene weighs the same and a total is easy to read.
     * `word_count` is derived from `contents` by a saving() hook, so the text
     * is what a test can set — the column is not writable.
     */
    private const THREE_WORDS = 'one two three';

    /** @return array<string, array{string, class-string, string}> */
    public static function indexes(): array
    {
        return [
            'tags' => ['projects.tags.index', Tag::class, 'tags'],
            'codex attributes' => ['projects.codex-attributes.index', CodexAttribute::class, 'attributes'],
            'events' => ['projects.events.index', Event::class, 'events'],
            'plotlines' => ['projects.plotlines.index', Plotline::class, 'plotlines'],
        ];
    }

    #[DataProvider('indexes')]
    public function test_the_index_hands_a_paginator_to_the_view(string $route, string $factory, string $viewKey): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->clearExisting($factory, $project);
        $factory::factory()->for($project)->count(3)->create();

        $response = $this->actingAs($user)->get($this->indexUrl($route, $project));

        $response->assertOk();
        $response->assertViewHas($viewKey, fn ($paginator) => $paginator instanceof LengthAwarePaginator);
        $this->assertSame(3, $response->viewData($viewKey)->total());
    }

    #[DataProvider('indexes')]
    public function test_the_bar_renders_with_no_rows(string $route, string $factory, string $viewKey): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->clearExisting($factory, $project);

        $response = $this->actingAs($user)->get($this->indexUrl($route, $project));

        $response->assertOk();
        $response->assertSee('Showing 0-0 of 0');
    }

    #[DataProvider('indexes')]
    public function test_a_second_page_holds_the_remainder(string $route, string $factory, string $viewKey): void
    {
        config(['pagination.default' => 2]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->clearExisting($factory, $project);
        $factory::factory()->for($project)->count(3)->create();

        $page1 = $this->actingAs($user)->get($this->indexUrl($route, $project));
        $page1->assertOk();
        $this->assertCount(2, $page1->viewData($viewKey));

        $page2 = $this->actingAs($user)->get($this->indexUrl($route, $project, ['page' => 2]));
        $page2->assertOk();
        $this->assertCount(1, $page2->viewData($viewKey));
    }

    #[DataProvider('indexes')]
    public function test_page_links_keep_sort_direction_and_search(string $route, string $factory, string $viewKey): void
    {
        config(['pagination.default' => 2]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->clearExisting($factory, $project);
        $factory::factory()->for($project)->count(3)->create();

        $response = $this->actingAs($user)->get($this->indexUrl($route, $project, [
            'page' => 2,
            'sort' => 'name',
            'direction' => 'desc',
            'search' => 'foo',
        ]));

        $response->assertOk();
        $response->assertSee('sort=name', false);
        $response->assertSee('direction=desc', false);
        $response->assertSee('search=foo', false);
    }

    /**
     * `Project::booted()` seeds a main plotline and two fixed Start/End
     * events on creation. Clearing them keeps row counts exact for the
     * pagination assertions above.
     *
     * @param  class-string  $factory
     */
    private function clearExisting(string $factory, Project $project): void
    {
        $factory::query()->where('project_id', $project->id)->delete();
    }

    /** @param  array<string, mixed>  $query */
    private function indexUrl(string $route, Project $project, array $query = []): string
    {
        return route($route, ['project' => $project, ...$query]);
    }

    #[DataProvider('indexes')]
    public function test_a_non_owner_is_forbidden_on_page_two(string $route, string $factory, string $viewKey): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $factory::factory()->for($project)->count(3)->create();

        $this->actingAs($stranger)
            ->get($this->indexUrl($route, $project, ['page' => 2]))
            ->assertForbidden();
    }

    public function test_setting_the_size_on_one_list_applies_to_another(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Tag::factory()->for($project)->count(3)->create();
        Plotline::factory()->for($project)->count(3)->create();

        $this->actingAs($user)
            ->from(route('projects.tags.index', $project))
            ->patch(route('preferences.page-size.update'), ['page_size' => 250]);

        $response = $this->actingAs($user)->get(route('projects.plotlines.index', $project));

        $response->assertOk();
        $this->assertSame(250, $response->viewData('plotlines')->perPage());
    }

    private function codexIndexUrl(Project $project, array $query = []): string
    {
        return route('projects.codex.index', ['project' => $project, 'type' => CodexEntryType::Character->routeKey(), ...$query]);
    }

    public function test_codex_entry_index_hands_a_paginator_to_the_view(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CodexEntry::factory()->for($project)->character()->count(3)->create();

        $response = $this->actingAs($user)->get($this->codexIndexUrl($project));

        $response->assertOk();
        $response->assertViewHas('entries', fn ($paginator) => $paginator instanceof LengthAwarePaginator);
        $this->assertSame(3, $response->viewData('entries')->total());
    }

    public function test_codex_entry_index_second_page_holds_the_remainder(): void
    {
        config(['pagination.default' => 2]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CodexEntry::factory()->for($project)->character()->count(3)->create();

        $page1 = $this->actingAs($user)->get($this->codexIndexUrl($project));
        $page1->assertOk();
        $this->assertCount(2, $page1->viewData('entries'));

        $page2 = $this->actingAs($user)->get($this->codexIndexUrl($project, ['page' => 2]));
        $page2->assertOk();
        $this->assertCount(1, $page2->viewData('entries'));
    }

    public function test_codex_entry_page_links_keep_the_tag_filter_and_search(): void
    {
        config(['pagination.default' => 2]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->for($project)->create();
        CodexEntry::factory()->for($project)->character()->count(3)->create()
            ->each(fn (CodexEntry $entry) => $entry->tags()->attach($tag));

        $response = $this->actingAs($user)->get($this->codexIndexUrl($project, [
            'page' => 2,
            'tag' => $tag->id,
            'search' => 'foo',
        ]));

        $response->assertOk();
        $response->assertSee('tag='.$tag->id, false);
        $response->assertSee('search=foo', false);
    }

    public function test_codex_entry_duplicate_names_are_computed_per_rendered_row(): void
    {
        config(['pagination.default' => 2]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        CodexEntry::factory()->for($project)->character()->count(3)->create();

        $response = $this->actingAs($user)->get($this->codexIndexUrl($project));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('duplicateNames'));
    }

    public function test_a_non_owner_is_forbidden_on_page_two_of_codex_entries(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        CodexEntry::factory()->for($project)->character()->count(3)->create();

        $this->actingAs($stranger)
            ->get($this->codexIndexUrl($project, ['page' => 2]))
            ->assertForbidden();
    }

    // --- The story lists: scenes, chapters, acts, books -------------------

    public function test_the_scene_index_hands_a_paginator_to_the_view(): void
    {
        [$user, $book] = $this->bookWithScenes(3);

        $response = $this->actingAs($user)->get(route('books.scenes.index', $book));

        $response->assertOk();
        $response->assertViewHas('scenes', fn ($paginator) => $paginator instanceof LengthAwarePaginator);
        $this->assertSame(3, $response->viewData('scenes')->total());
    }

    public function test_the_chapter_act_and_book_indexes_hand_a_paginator_to_the_view(): void
    {
        [$user, $book] = $this->bookWithScenes(1);

        $chapters = $this->actingAs($user)->get(route('books.chapters.index', $book));
        $chapters->assertOk();
        $chapters->assertViewHas('chapters', fn ($paginator) => $paginator instanceof LengthAwarePaginator);

        $acts = $this->actingAs($user)->get(route('books.acts.index', $book));
        $acts->assertOk();
        $acts->assertViewHas('acts', fn ($paginator) => $paginator instanceof LengthAwarePaginator);

        $books = $this->actingAs($user)->get(route('projects.books.index', $book->project));
        $books->assertOk();
        $books->assertViewHas('books', fn ($paginator) => $paginator instanceof LengthAwarePaginator);
    }

    public function test_a_hundred_and_twenty_scenes_split_across_two_pages_at_the_default_size(): void
    {
        [$user, $book] = $this->bookWithScenes(120);

        $page1 = $this->actingAs($user)->get(route('books.scenes.index', $book));
        $page1->assertOk();
        $this->assertCount(100, $page1->viewData('scenes'));

        $page2 = $this->actingAs($user)->get(route('books.scenes.index', ['book' => $book, 'page' => 2]));
        $page2->assertOk();
        $this->assertCount(20, $page2->viewData('scenes'));
    }

    /** Story numbers come from the whole book, so page 2 starts at 101, not at 1. */
    public function test_the_first_row_of_the_second_scene_page_is_numbered_from_the_book(): void
    {
        [$user, $book] = $this->bookWithScenes(120);

        $response = $this->actingAs($user)->get(route('books.scenes.index', ['book' => $book, 'page' => 2]));

        $response->assertOk();
        $scenes = $response->viewData('scenes');
        $this->assertSame(101, $response->viewData('numbering')->scene($scenes->first()));
    }

    public function test_the_act_move_buttons_only_stop_at_the_first_and_last_page(): void
    {
        config(['pagination.default' => 2]);

        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $acts = Act::factory()->for($book)->count(5)
            ->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
            ->create();

        $page1 = $this->actingAs($user)->get(route('books.acts.index', $book));
        $page1->assertOk();
        $this->assertTrue($this->moveButtonIsDisabled($page1->getContent(), route('acts.move-up', $acts[0])));

        $page2 = $this->actingAs($user)->get(route('books.acts.index', ['book' => $book, 'page' => 2]));
        $page2->assertOk();
        $this->assertFalse($this->moveButtonIsDisabled($page2->getContent(), route('acts.move-up', $acts[2])));
        $this->assertFalse($this->moveButtonIsDisabled($page2->getContent(), route('acts.move-down', $acts[3])));

        $page3 = $this->actingAs($user)->get(route('books.acts.index', ['book' => $book, 'page' => 3]));
        $page3->assertOk();
        $this->assertTrue($this->moveButtonIsDisabled($page3->getContent(), route('acts.move-down', $acts[4])));
    }

    /**
     * The scene list shows the move buttons only under a chapter filter and the
     * position sort, so its page-2 check carries both.
     */
    public function test_the_scene_move_up_button_is_enabled_on_the_first_row_of_a_later_page(): void
    {
        config(['pagination.default' => 2]);

        [$user, $book] = $this->bookWithScenes(4);
        $chapter = $book->chapterQuery()->first();
        $scenes = $book->sceneQuery()->orderBy('position')->get();

        $response = $this->actingAs($user)->get(route('books.scenes.index', [
            'book' => $book,
            'chapter' => $chapter->id,
            'page' => 2,
        ]));

        $response->assertOk();
        $this->assertFalse($this->moveButtonIsDisabled($response->getContent(), route('scenes.move-up', $scenes[2])));
    }

    public function test_scene_page_links_keep_the_chapter_filter_and_the_sort(): void
    {
        config(['pagination.default' => 2]);

        [$user, $book] = $this->bookWithScenes(3);
        $chapter = $book->chapterQuery()->first();

        $response = $this->actingAs($user)->get(route('books.scenes.index', [
            'book' => $book,
            'page' => 2,
            'chapter' => $chapter->id,
            'sort' => 'name',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $response->assertSee('chapter='.$chapter->id, false);
        $response->assertSee('sort=name', false);
        $response->assertSee('direction=desc', false);
    }

    public function test_a_non_owner_is_forbidden_on_page_two_of_the_story_lists(): void
    {
        $stranger = User::factory()->create();
        [, $book] = $this->bookWithScenes(3);

        foreach (['books.scenes.index', 'books.chapters.index', 'books.acts.index'] as $route) {
            $this->actingAs($stranger)
                ->get(route($route, ['book' => $book, 'page' => 2]))
                ->assertForbidden();
        }

        $this->actingAs($stranger)
            ->get(route('projects.books.index', ['project' => $book->project, 'page' => 2]))
            ->assertForbidden();
    }

    public function test_the_scene_footer_splits_the_page_total_from_the_full_total(): void
    {
        [$user, $book] = $this->bookWithScenes(120);

        $page1 = $this->actingAs($user)->get(route('books.scenes.index', $book));
        $page1->assertOk();
        $page1->assertSee('Page total');
        $page1->assertSee('Full total');
        $this->assertSame(300, $page1->viewData('scenes')->sum('word_count'));
        $this->assertSame(360, $page1->viewData('fullWordCount'));

        $page2 = $this->actingAs($user)->get(route('books.scenes.index', ['book' => $book, 'page' => 2]));
        $page2->assertOk();
        $this->assertSame(60, $page2->viewData('scenes')->sum('word_count'));
        $this->assertSame(360, $page2->viewData('fullWordCount'));
    }

    public function test_the_chapter_footer_splits_the_page_total_from_the_full_total(): void
    {
        config(['pagination.default' => 2]);

        [$user, $book] = $this->bookWithChapters(3, scenesEach: 2);

        $page1 = $this->actingAs($user)->get(route('books.chapters.index', $book));
        $page1->assertOk();
        $page1->assertSee('Page total');
        $page1->assertSee('Full total');
        $this->assertSame(4, $page1->viewData('chapters')->sum('scenes_count'));
        $this->assertSame(12, $page1->viewData('chapters')->sum('word_count'));
        $this->assertSame(6, $page1->viewData('fullSceneCount'));
        $this->assertSame(18, $page1->viewData('fullWordCount'));

        $page2 = $this->actingAs($user)->get(route('books.chapters.index', ['book' => $book, 'page' => 2]));
        $page2->assertOk();
        $this->assertSame(2, $page2->viewData('chapters')->sum('scenes_count'));
        $this->assertSame(6, $page2->viewData('fullSceneCount'));
        $this->assertSame(18, $page2->viewData('fullWordCount'));
    }

    public function test_the_act_footer_splits_the_page_total_from_the_full_total(): void
    {
        config(['pagination.default' => 2]);

        [$user, $book] = $this->bookWithActs(3, scenesEach: 2);

        $page1 = $this->actingAs($user)->get(route('books.acts.index', $book));
        $page1->assertOk();
        $page1->assertSee('Page total');
        $page1->assertSee('Full total');
        $this->assertSame(2, $page1->viewData('acts')->sum('chapters_count'));
        $this->assertSame(12, $page1->viewData('acts')->sum('word_count'));
        $this->assertSame(3, $page1->viewData('fullChapterCount'));
        $this->assertSame(18, $page1->viewData('fullWordCount'));

        $page2 = $this->actingAs($user)->get(route('books.acts.index', ['book' => $book, 'page' => 2]));
        $page2->assertOk();
        $this->assertSame(1, $page2->viewData('acts')->sum('chapters_count'));
        $this->assertSame(3, $page2->viewData('fullChapterCount'));
        $this->assertSame(18, $page2->viewData('fullWordCount'));
    }

    /**
     * The full total is the whole filtered list, not the whole book: a scenes
     * list narrowed to one chapter reports that chapter's words.
     */
    public function test_the_full_total_covers_the_filter_and_not_the_book(): void
    {
        config(['pagination.default' => 2]);

        [$user, $book] = $this->bookWithChapters(2, scenesEach: 5);
        $chapter = $book->chapterQuery()->orderBy('id')->first();

        $response = $this->actingAs($user)->get(route('books.scenes.index', [
            'book' => $book,
            'chapter' => $chapter->id,
        ]));

        $response->assertOk();
        $this->assertSame(6, $response->viewData('scenes')->sum('word_count'));
        $this->assertSame(15, $response->viewData('fullWordCount'));
    }

    public function test_an_empty_story_list_renders_neither_footer_row(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        foreach (['books.scenes.index', 'books.chapters.index', 'books.acts.index'] as $route) {
            $response = $this->actingAs($user)->get(route($route, $book));

            $response->assertOk();
            $response->assertDontSee('Page total');
            $response->assertDontSee('Full total');
        }
    }

    /**
     * One act, one chapter, $count scenes at ascending positions.
     *
     * @return array{User, Book}
     */
    /** Sorting reshuffles every row, so the old page number must not survive the click. */
    public function test_a_sort_header_link_drops_the_page_number(): void
    {
        [$user, $book] = $this->bookWithScenes(120);

        $response = $this->actingAs($user)->get(
            route('books.scenes.index', ['book' => $book, 'page' => 2, 'chapter' => $book->acts->first()->chapters->first()->id])
        );

        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/<a href="[^"]*sort=name[^"]*"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a href="[^"]*sort=name[^"]*page=2[^"]*"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a href="[^"]*page=2[^"]*sort=name[^"]*"/', $html);
    }

    /**
     * The bar's own row range fills in only where Laravel's paginator view stays
     * silent, so a multi-page list never prints the range twice.
     */
    public function test_the_row_range_is_printed_once(): void
    {
        [$user, $book] = $this->bookWithScenes(120);

        $onePage = $this->actingAs($user)->get(route('books.acts.index', $book));
        $onePage->assertOk();
        $onePage->assertSee('Showing 1-1 of 1');

        $twoPages = $this->actingAs($user)->get(route('books.scenes.index', $book));
        $twoPages->assertOk();
        $twoPages->assertDontSee('Showing 1-100 of 120');
        $twoPages->assertSee('120');
    }

    private function bookWithScenes(int $count): array
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['position' => 1]);
        $chapter = Chapter::factory()->for($act)->create(['position' => 1]);

        $this->fillWithScenes($chapter, $count);

        return [$user, $book];
    }

    /**
     * One act, $count chapters, $scenesEach scenes of three words in each.
     *
     * @return array{User, Book}
     */
    private function bookWithChapters(int $count, int $scenesEach): array
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['position' => 1]);

        for ($i = 1; $i <= $count; $i++) {
            $this->fillWithScenes(
                Chapter::factory()->for($act)->create(['position' => $i]),
                $scenesEach
            );
        }

        return [$user, $book];
    }

    /**
     * $count acts, one chapter each, $scenesEach scenes of three words in it.
     *
     * @return array{User, Book}
     */
    private function bookWithActs(int $count, int $scenesEach): array
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        for ($i = 1; $i <= $count; $i++) {
            $act = Act::factory()->for($book)->create(['position' => $i]);

            $this->fillWithScenes(
                Chapter::factory()->for($act)->create(['position' => 1]),
                $scenesEach
            );
        }

        return [$user, $book];
    }

    private function fillWithScenes(Chapter $chapter, int $count): void
    {
        Scene::factory()
            ->for($chapter)
            ->count($count)
            ->sequence(fn ($sequence) => [
                'position' => $sequence->index + 1,
                'contents' => self::THREE_WORDS,
            ])
            ->create();
    }

    /**
     * Whether the move button whose form posts to $action is disabled. The
     * form action is the only per-row identifier in the markup, so the search
     * is scoped to one `<form>` block. The `disabled="disabled"` attribute is
     * the hook, never the `disabled:` Tailwind classes the button always
     * carries.
     */
    private function moveButtonIsDisabled(string $html, string $action): bool
    {
        $pattern = '/<form[^>]*action="'.preg_quote(e($action), '/').'".*?<\/form>/s';

        $this->assertSame(1, preg_match($pattern, $html, $matches), 'No move form found for '.$action);

        return str_contains($matches[0], 'disabled="disabled"');
    }
}
