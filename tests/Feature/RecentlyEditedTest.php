<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\CodexMedia;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Support\RecentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Everything App\Services\RecentlyEdited feeds: the lists on the Story,
 * Timeline and Codex landing pages, and the project dashboard's tiles.
 *
 * Ordering is by `updated_at` — ANY write, not only a prose change — so these
 * surfaces answer "where was I last working?". Authorization and the guest
 * redirect for the section routes stay in SectionStubTest.
 */
class RecentlyEditedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A project with a full act -> chapter chain, so scene-level assertions
     * have somewhere to hang.
     */
    private function chapterFor(User $user, string $actName = 'First act'): Chapter
    {
        [, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create(['name' => $actName]);

        return Chapter::factory()->for($act)->create(['name' => 'First chapter']);
    }

    // --- Story ------------------------------------------------------------

    public function test_the_story_home_lists_the_most_recently_edited_scenes_first(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);

        Scene::factory()->for($chapter)->create(['name' => 'Older scene'])
            ->forceFill(['updated_at' => now()->subDays(2)])->save();
        Scene::factory()->for($chapter)->create(['name' => 'Newer scene'])
            ->forceFill(['updated_at' => now()])->save();

        $response = $this->actingAs($user)
            ->get(route('books.story.home', $chapter->act->book));

        $response->assertOk();
        $response->assertSeeInOrder(['Newer scene', 'Older scene']);
    }

    public function test_the_story_home_shows_each_scenes_act_and_chapter_as_context(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user, 'Act of departure');
        Scene::factory()->for($chapter)->create(['name' => 'A scene']);

        $this->actingAs($user)
            ->get(route('books.story.home', $chapter->act->book))
            ->assertOk()
            ->assertSee('Act of departure — First chapter');
    }

    public function test_the_story_home_caps_each_list_at_the_recent_limit(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        // One more act than the list shows, oldest last so it is the one dropped.
        foreach (range(1, RecentItem::LIMIT + 1) as $index) {
            Act::factory()->for($book)->create(['name' => "Act {$index}"])
                ->forceFill(['updated_at' => now()->subMinutes($index)])->save();
        }

        $response = $this->actingAs($user)->get(route('books.story.home', $book));

        $response->assertOk();
        $response->assertSee('Act 1');
        $response->assertDontSee('Act '.(RecentItem::LIMIT + 1));
    }

    public function test_the_story_home_links_to_the_full_indexes(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->get(route('books.story.home', $book))
            ->assertOk()
            ->assertSee(route('books.acts.index', $book))
            ->assertSee(route('books.chapters.index', $book))
            ->assertSee(route('books.scenes.index', $book));
    }

    public function test_the_story_home_shows_an_empty_state_for_a_project_with_no_content(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->get(route('books.story.home', $book))
            ->assertOk()
            ->assertSee(__('No :items yet.', ['items' => __('scenes')]));
    }

    public function test_the_story_home_never_leaks_another_projects_scenes(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        Scene::factory()->for($chapter)->create(['name' => 'Mine']);

        $otherChapter = $this->chapterFor($user);
        Scene::factory()->for($otherChapter)->create(['name' => 'Someone elses project']);

        $this->actingAs($user)
            ->get(route('books.story.home', $chapter->act->book))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Someone elses project');
    }

    public function test_the_story_home_lists_only_the_book_in_the_url(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create();

        Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($firstBook)))
            ->create(['name' => 'Volume one scene']);
        Scene::factory()->for(Chapter::factory()->for(Act::factory()->for($secondBook)))
            ->create(['name' => 'Volume two scene']);

        $this->actingAs($user)
            ->get(route('books.story.home', $firstBook))
            ->assertOk()
            ->assertSee('Volume one scene')
            ->assertDontSee('Volume two scene');
    }

    // --- Dashboard lists --------------------------------------------------

    public function test_the_dashboard_lists_the_last_edited_scenes_with_their_breadcrumb(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user, 'Act of departure');
        $project = $chapter->act->book->project;

        Scene::factory()->for($chapter)->create(['name' => 'Older scene'])
            ->forceFill(['updated_at' => now()->subDay()])->save();
        Scene::factory()->for($chapter)->create(['name' => 'Newest scene'])
            ->forceFill(['updated_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee(__('Recent scenes'));
        $response->assertSeeInOrder(['Newest scene', 'Older scene']);
        $response->assertSee('Act of departure');
        $response->assertSee('First chapter');
    }

    public function test_the_dashboard_codex_list_mixes_the_types_and_names_each_one(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        CodexEntry::factory()->for($project)->create([
            'name' => 'Melusine',
            'type' => CodexEntryType::Character,
        ]);
        CodexEntry::factory()->for($project)->create([
            'name' => 'The weir',
            'type' => CodexEntryType::Location,
        ]);

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee(__('Recent codex entries'));
        $response->assertSee('Melusine');
        $response->assertSee('The weir');

        // The type is the only thing that tells a character from a location.
        $response->assertSee(__(CodexEntryType::Character->label()));
        $response->assertSee(__(CodexEntryType::Location->label()));
    }

    public function test_the_dashboard_lists_stop_at_three_rows(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $project = $chapter->act->book->project;

        foreach (range(1, 4) as $index) {
            Scene::factory()->for($chapter)->create(['name' => "Scene {$index}"])
                ->forceFill(['updated_at' => now()->subMinutes(5 - $index)])->save();
            CodexEntry::factory()->for($project)->create(['name' => "Entry {$index}"])
                ->forceFill(['updated_at' => now()->subMinutes(5 - $index)])->save();
        }

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('Scene 4');
        $response->assertDontSee('Scene 1');
        $response->assertSee('Entry 4');
        $response->assertDontSee('Entry 1');
    }

    public function test_the_dashboard_lists_say_so_when_a_project_is_empty(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('No :items yet.', ['items' => __('scenes')]))
            ->assertSee(route('books.story.home', $book))
            ->assertSee(route('projects.codex.home', $project));
    }

    // --- Timeline ---------------------------------------------------------

    public function test_the_timeline_home_lists_recent_plotlines_and_events(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Plotline::factory()->for($project)->create(['name' => 'A subplot']);
        Event::factory()->for($project)->create(['title' => 'The coronation']);

        $this->actingAs($user)
            ->get(route('projects.timeline.home', $project))
            ->assertOk()
            ->assertSee('A subplot')
            ->assertSee('The coronation')
            ->assertSee(route('projects.plotlines.index', $project))
            ->assertSee(route('projects.events.index', $project));
    }

    // --- Codex ------------------------------------------------------------

    public function test_the_codex_home_lists_recent_entries_under_their_own_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        CodexEntry::factory()->for($project)->create([
            'type' => CodexEntryType::Character,
            'name' => 'Melusine',
        ]);

        $response = $this->actingAs($user)->get(route('projects.codex.home', $project));

        $response->assertOk();
        $response->assertSee('Melusine');
        $response->assertSee(route('projects.codex.index', [$project, CodexEntryType::Character->routeKey()]));

        // A type with no entries still gets its card, showing the empty state.
        $response->assertSee(__('No :items yet.', ['items' => CodexEntryType::Location->pluralNoun()]));
    }

    public function test_the_codex_home_shows_an_entrys_cover_thumbnail(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->create([
            'type' => CodexEntryType::Character,
            'name' => 'Melusine',
        ]);
        CodexMedia::factory()->for($entry, 'entry')->create([
            'collection' => CodexMediaCollection::Cover,
            'path' => 'codex/melusine.jpg',
        ]);

        $this->actingAs($user)
            ->get(route('projects.codex.home', $project))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('codex/melusine.jpg'));
    }

    public function test_the_codex_home_still_shows_a_cover_box_when_no_image_is_saved(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        CodexEntry::factory()->for($project)->create([
            'type' => CodexEntryType::Character,
            'name' => 'Melusine',
        ]);

        // The placeholder keeps every row of a cover-bearing entity aligned,
        // so the box is part of the layout, not a decoration on filled rows.
        $this->actingAs($user)
            ->get(route('projects.codex.home', $project))
            ->assertOk()
            ->assertSee('rounded-sm border border-border bg-surface', false);
    }

    public function test_the_story_home_shows_a_chapters_cover_thumbnail(): void
    {
        $user = User::factory()->create();
        $chapter = $this->chapterFor($user);
        $chapter->update(['cover_image' => 'chapters/first.jpg']);

        $this->actingAs($user)
            ->get(route('books.story.home', $chapter->act->book))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('chapters/first.jpg'));
    }

    public function test_the_codex_home_does_not_list_attribute_definitions(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        CodexAttribute::factory()->for($project)->create(['name' => 'Eye colour']);

        $this->actingAs($user)
            ->get(route('projects.codex.home', $project))
            ->assertOk()
            ->assertDontSee('Eye colour');
    }
}
