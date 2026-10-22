<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Enums\EditorCardState;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\EditorCard;
use App\Models\Event;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\WordCountSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The Write page: its entry route, its scene page and its layout. */
class EditorTest extends TestCase
{
    use RefreshDatabase;

    private function sceneIn(Book $book, array $attributes = []): Scene
    {
        $act = Act::factory()->for($book)->create();

        return Scene::factory()->for(Chapter::factory()->for($act))->create($attributes);
    }

    /** @return array{User, Project, Scene} */
    private function ownedScene(): array
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        return [$user, $project, $this->sceneIn($book)];
    }

    public function test_home_redirects_to_the_newest_scene_across_books(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create();

        $this->sceneIn($firstBook, ['updated_at' => now()->subDays(2)]);
        $newest = $this->sceneIn($secondBook, ['updated_at' => now()->subDay()]);
        $this->sceneIn($firstBook, ['updated_at' => now()->subDays(3)]);

        $this->actingAs($user)
            ->get(route('projects.write', $project))
            ->assertRedirect(route('scenes.write', $newest));
    }

    public function test_home_shows_the_empty_state_without_scenes(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->get(route('projects.write', $project))
            ->assertOk()
            ->assertSee('data-editor-empty', false)
            ->assertSee('href="'.e(route('books.scenes.create', $book)).'"', false);
    }

    public function test_show_renders_the_prose_editor_and_its_header(): void
    {
        [$user, , $scene] = $this->ownedScene();

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertSee('data-autosave-field="scene:'.$scene->id.':contents"', false)
            ->assertSee('<h1 id="editor-scene-title"', false)
            ->assertSee(e($scene->name), false)
            ->assertSee('href="'.e(route('scenes.edit', $scene)).'"', false)
            ->assertSee('autofocus: true', false)
            ->assertSee('sticky -top-px', false)
            // The prose is the only autosave field on the page.
            ->assertDontSee(':description"', false);
    }

    public function test_the_prose_column_follows_the_page_width_setting(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $user->update(['page_width' => 50]);

        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk()->getContent();

        $this->assertStringContainsString(':root{--page-width:50%}', $html);
        $this->assertMatchesRegularExpression('/<section[^>]*data-editor-centre[^>]*>\s*<div class="mx-auto md:max-w-\(--page-width\)/', $html);
    }

    public function test_show_uses_the_stored_layout(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $user->update(['editor_layout' => [
            'left' => ['width' => 300, 'collapsed' => true],
            'right' => ['width' => 420, 'collapsed' => false],
            'sections' => ['codex' => false, 'notes' => true, 'recent' => true],
        ]]);

        $html = $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertSee('--editor-left: 2.5rem; --editor-right: 420px;', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-editor-panel="left"\s+data-collapsed="true"/', $html);
        $this->assertMatchesRegularExpression('/data-editor-panel="right"\s+data-collapsed="false"/', $html);
    }

    public function test_the_resize_handles_are_separators_with_the_stored_widths(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $user->update(['editor_layout' => [
            'left' => ['width' => 300, 'collapsed' => false],
            'right' => ['width' => 420, 'collapsed' => true],
            'sections' => ['codex' => false, 'notes' => true, 'recent' => true],
        ]]);

        $html = $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertSee('x-data="editorLayout(', false)
            ->assertSee(str_replace('/', '\/', route('preferences.editor-layout')), false)
            ->getContent();

        foreach (['left' => 300, 'right' => 420] as $side => $width) {
            $this->assertMatchesRegularExpression(
                '/role="separator"\s+aria-orientation="vertical"\s+tabindex="0"\s+'
                .'aria-label="Resize '.$side.' panel"\s+aria-valuemin="200"\s+aria-valuemax="640"\s+aria-valuenow="'.$width.'"/',
                $html,
            );
        }

        // The collapse buttons and the sections start in the stored state.
        $this->assertMatchesRegularExpression('/aria-expanded="true"\s+aria-controls="editor-panel-left"/', $html);
        $this->assertMatchesRegularExpression('/aria-expanded="false"\s+aria-controls="editor-panel-right"/', $html);
        $this->assertMatchesRegularExpression('/<details\s+class="[^"]*"\s+open\s[^>]*data-editor-section-details="codex"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<details\s+class="[^"]*"\s+open[^>]*data-editor-section-details="notes"/s', $html);
        $this->assertMatchesRegularExpression('/<details\s+class="[^"]*"\s+x-on:toggle[^>]*data-editor-section-details="notes"/s', $html);
    }

    public function test_show_uses_the_defaults_for_a_null_layout(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $this->assertNull($user->editor_layout);

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertSee('--editor-left: 280px; --editor-right: 360px;', false);
    }

    public function test_the_breadcrumb_ends_in_write_and_the_scene(): void
    {
        [$user, $project, $scene] = $this->ownedScene();

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertSeeInOrder([
                route('projects.show', $project),
                route('projects.write', $project),
                e($scene->name),
            ], false);
    }

    /**
     * Three scenes across a chapter and an act boundary. The second act is
     * created first, so story order differs from id order.
     *
     * @return array{User, Book, list<Scene>}
     */
    private function storyAcrossBoundaries(): array
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $secondAct = Act::factory()->for($book)->create();
        $firstAct = Act::factory()->for($book)->create();
        $secondAct->update(['position' => 2]);
        $firstAct->update(['position' => 1]);

        $firstChapter = Chapter::factory()->for($firstAct)->create();
        $secondChapter = Chapter::factory()->for($firstAct)->create();
        $thirdChapter = Chapter::factory()->for($secondAct)->create();

        return [$user, $book, [
            Scene::factory()->for($firstChapter)->create(),
            Scene::factory()->for($secondChapter)->create(),
            Scene::factory()->for($thirdChapter)->create(),
        ]];
    }

    public function test_previous_and_next_cross_chapter_and_act_boundaries(): void
    {
        [$user, , [$first, $middle, $last]] = $this->storyAcrossBoundaries();

        $this->actingAs($user)
            ->get(route('scenes.write', $middle))
            ->assertOk()
            ->assertViewHas('previous', fn (?Scene $scene) => $scene?->is($first))
            ->assertViewHas('next', fn (?Scene $scene) => $scene?->is($last))
            ->assertSee('href="'.e(route('scenes.write', $first)).'"', false)
            ->assertSee('href="'.e(route('scenes.write', $last)).'"', false)
            ->assertSee(e($first->name), false)
            ->assertSee(e($last->name), false);

        $this->actingAs($user)
            ->get(route('scenes.write', $last))
            ->assertViewHas('previous', fn (?Scene $scene) => $scene?->is($middle));
    }

    public function test_previous_and_next_follow_scene_position_inside_a_chapter(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $later = Scene::factory()->for($chapter)->create();
        $earlier = Scene::factory()->for($chapter)->create();
        $later->update(['position' => 2]);
        $earlier->update(['position' => 1]);

        $this->actingAs($user)
            ->get(route('scenes.write', $earlier))
            ->assertViewHas('previous', null)
            ->assertViewHas('next', fn (?Scene $scene) => $scene?->is($later));
    }

    public function test_the_pager_links_are_hidden_at_the_book_start_and_end(): void
    {
        [$user, , [$first, , $last]] = $this->storyAcrossBoundaries();

        $this->actingAs($user)
            ->get(route('scenes.write', $first))
            ->assertViewHas('previous', null)
            ->assertDontSee('data-editor-previous', false)
            ->assertSee('data-editor-next', false);

        $this->actingAs($user)
            ->get(route('scenes.write', $last))
            ->assertViewHas('next', null)
            ->assertSee('data-editor-previous', false)
            ->assertDontSee('data-editor-next', false);

        [$alone, , $scene] = $this->ownedScene();

        $this->actingAs($alone)
            ->get(route('scenes.write', $scene))
            ->assertDontSee('data-editor-pager', false);
    }

    public function test_scenes_of_another_book_never_appear_in_the_pager(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create();

        $this->sceneIn($firstBook);
        $only = $this->sceneIn($secondBook);
        $this->sceneIn($firstBook);

        $this->actingAs($user)
            ->get(route('scenes.write', $only))
            ->assertViewHas('previous', null)
            ->assertViewHas('next', null)
            ->assertDontSee('data-editor-pager', false);
    }

    public function test_today_equals_the_dashboard_figure(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        [$project, $book] = $this->projectWithBook($user);
        $project->update(['daily_word_goal' => 500]);

        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => CarbonImmutable::now('UTC')->subDay()->toDateString(),
            'word_count' => 1000,
        ]);
        $scene = $this->sceneIn($book);
        $scene->update(['contents' => '<p>'.str_repeat('word ', 1240).'</p>']);

        $dashboard = $this->actingAs($user)->get(route('projects.show', $project))->viewData('writtenToday');
        $this->assertNotSame(0, $dashboard);

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertViewHas('writtenToday', $dashboard)
            ->assertSee('data-editor-today="'.$dashboard.'"', false)
            ->assertSee('sceneCount: '.$scene->fresh()->word_count, false);
    }

    public function test_the_today_goal_bar_shows_only_with_a_daily_goal(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $project->update(['daily_word_goal' => null]);

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertSee('data-editor-today=', false)
            ->assertDontSee('data-editor-today-goal', false);

        $project->update(['daily_word_goal' => 500]);

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertSee('data-editor-today-goal', false)
            ->assertSee('aria-valuemax="500"', false);
    }

    public function test_the_prose_editor_keeps_its_position_per_scene(): void
    {
        [$user, , $scene] = $this->ownedScene();

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertSee("positionKey: 'editor-position:{$scene->id}'", false)
            ->assertSee('data-position-scroll', false);
    }

    public function test_the_left_panel_lists_at_most_15_recent_scenes_across_books_newest_first(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create();

        $scenes = [];
        foreach (range(1, 16) as $i) {
            $scenes[$i] = $this->sceneIn($i % 2 ? $firstBook : $secondBook, [
                'name' => "Recent scene {$i}",
                'updated_at' => now()->subMinutes(100 - $i),
            ]);
        }

        $html = $this->actingAs($user)->get(route('scenes.write', $scenes[5]))->assertOk()->getContent();
        $recent = substr($html, strpos($html, 'data-editor-recent'));
        $recent = substr($recent, 0, strpos($recent, 'data-editor-outline'));

        // Scene 1 is the oldest of 16 rows: it falls off the list.
        $this->assertStringNotContainsString('Recent scene 1<', $recent);
        $this->assertSame(15, substr_count($recent, '<time'));
        $this->assertLessThan(strpos($recent, 'Recent scene 15<'), strpos($recent, 'Recent scene 16<'));
        $this->assertLessThan(strpos($recent, 'Recent scene 2<'), strpos($recent, 'Recent scene 3<'));
        $this->assertStringContainsString('href="'.route('scenes.write', $scenes[16]).'"', $recent);
        $this->assertStringNotContainsString(route('scenes.edit', $scenes[16]), $recent);
    }

    public function test_the_recent_section_is_closed_with_a_null_layout(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $this->assertNull($user->editor_layout);

        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<details\s+class="[^"]*"\s+x-on:toggle[^>]*data-editor-section-details="recent"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<details\s+class="[^"]*"\s+open[^>]*data-editor-section-details="recent"/s', $html);
    }

    public function test_the_recent_section_is_open_when_the_stored_layout_says_so(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $user->update(['editor_layout' => [
            'left' => ['width' => 300, 'collapsed' => false],
            'right' => ['width' => 420, 'collapsed' => false],
            'sections' => ['codex' => false, 'notes' => false, 'recent' => false],
        ]]);

        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<details\s+class="[^"]*"\s+open\s[^>]*data-editor-section-details="recent"/s', $html);
    }

    public function test_the_current_scene_row_is_marked(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $other = $this->sceneIn($scene->book(), ['name' => 'Other scene']);

        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->getContent();
        $recent = substr($html, strpos($html, 'data-editor-recent'));
        $recent = substr($recent, 0, strpos($recent, 'data-editor-outline'));

        $this->assertSame(1, substr_count($recent, 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '#<a\s+href="'.preg_quote(route('scenes.write', $scene), '#').'"\s+aria-current="page"#',
            $recent,
        );
        $this->assertStringContainsString('href="'.route('scenes.write', $other).'"', $recent);
    }

    public function test_the_left_panel_hands_the_scene_search_its_urls(): void
    {
        [$user, $project, $scene] = $this->ownedScene();

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertSee('editorSceneSearch', false)
            ->assertSee("candidatesUrl: '".str_replace('/', '\/', route('projects.link-targets', $project))."'", false)
            ->assertSee('__SCENE__', false)
            ->assertSee('role="combobox"', false);
    }

    /**
     * The rendered cards of one right-panel section, in page order, as `id => state`.
     * The empty card template has no id, so it does not match.
     *
     * @return array<int, string>
     */
    private function cardsIn(string $html, string $type): array
    {
        preg_match_all('/data-card-type="'.$type.'"\s+data-card-id="(\d+)"\s+data-state="(\w+)"/', $html, $matches);

        return array_combine(array_map('intval', $matches[1]), $matches[2]);
    }

    private function card(User $user, Scene $scene, CodexEntry|Note $target, EditorCardState $state): EditorCard
    {
        return EditorCard::saveState($user, $scene, $target, $state);
    }

    public function test_saved_cards_render_newest_change_first_and_closed_rows_are_hidden(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        [$old, $new, $closed] = CodexEntry::factory()->for($project)->character()->count(3)->create();

        $this->card($user, $scene, $old, EditorCardState::Collapsed);
        $this->travel(1)->minutes();
        $this->card($user, $scene, $closed, EditorCardState::Closed);
        $this->travel(1)->minutes();
        $this->card($user, $scene, $new, EditorCardState::Expanded);

        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk()->getContent();

        $this->assertSame([$new->id => 'expanded', $old->id => 'collapsed'], $this->cardsIn($html, 'codex'));
        $this->assertStringContainsString(e($new->name), $html);
        $this->assertStringNotContainsString('data-card-id="'.$closed->id.'"', $html);
    }

    public function test_linked_notes_without_rows_render_collapsed_after_saved_cards(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $saved = Note::factory()->for($project)->create(['title' => 'Saved']);
        $linked = Note::factory()->for($project)->create(['title' => 'Linked']);
        $closedLinked = Note::factory()->for($project)->create(['title' => 'Closed linked']);
        $unlinked = Note::factory()->for($project)->create(['title' => 'Unlinked']);
        $linked->linkTo($scene);
        $closedLinked->linkTo($scene);

        $this->card($user, $scene, $saved, EditorCardState::Expanded);
        $this->card($user, $scene, $closedLinked, EditorCardState::Closed);

        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk()->getContent();

        $this->assertSame([$saved->id => 'expanded', $linked->id => 'collapsed'], $this->cardsIn($html, 'note'));
        $this->assertStringNotContainsString('data-card-id="'.$unlinked->id.'"', $html);
    }

    public function test_cards_of_another_user_do_not_show(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $entry = CodexEntry::factory()->for($project)->character()->create();
        $note = Note::factory()->for($project)->create();
        $other = User::factory()->create();

        $this->card($other, $scene, $entry, EditorCardState::Expanded);
        $this->card($other, $scene, $note, EditorCardState::Expanded);

        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk()->getContent();

        $this->assertSame([], $this->cardsIn($html, 'codex'));
        $this->assertSame([], $this->cardsIn($html, 'note'));
    }

    public function test_the_card_sections_get_their_urls(): void
    {
        [$user, , $scene] = $this->ownedScene();

        $this->actingAs($user)
            ->get(route('scenes.write', $scene))
            ->assertOk()
            ->assertSee('x-data="editorCards(', false)
            ->assertSee("'".str_replace('/', '\/', route('codex.card', ['codexEntry' => '__ID__', 'scene' => $scene->id]))."'", false)
            ->assertSee("'".str_replace('/', '\/', route('notes.card', ['note' => '__ID__']))."'", false)
            ->assertSee("'".str_replace('/', '\/', route('scenes.editor-cards.update', $scene))."'", false);
    }

    public function test_the_card_query_count_does_not_grow_with_the_cards(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $event = Event::factory()->for($project)->create();
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create();
        $category = NoteCategory::factory()->for($project)->create();

        $queriesFor = function (int $count) use ($user, $project, $book, $event, $attribute, $category): int {
            $scene = $this->sceneIn($book, ['event_id' => $event->id]);

            foreach (range(1, $count) as $i) {
                $entry = CodexEntry::factory()->for($project)->character()->create();
                CodexAttributeValue::factory()->startingAt($event)->create(['codex_entry_id' => $entry->id, 'codex_attribute_id' => $attribute->id]);
                $this->card($user, $scene, $entry, EditorCardState::Expanded);
                $this->card($user, $scene, Note::factory()->for($project)->for($category, 'category')->create(), EditorCardState::Expanded);
                Note::factory()->for($project)->for($category, 'category')->create()->linkTo($scene);
            }

            // The first request of a test writes one-time rows, such as the active project.
            $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('scenes.write', $scene))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->assertSame($queriesFor(1), $queriesFor(10));
    }

    public function test_the_section_query_count_does_not_grow_with_the_cards(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $event = Event::factory()->for($project)->create();
        $attribute = CodexAttribute::factory()->for($project)->appliesTo(CodexEntryType::Character)->create();
        $category = NoteCategory::factory()->for($project)->create();

        $queriesFor = function (int $count, string $type) use ($user, $project, $book, $event, $attribute, $category): int {
            $scene = $this->sceneIn($book, ['event_id' => $event->id]);

            foreach (range(1, $count) as $i) {
                $entry = CodexEntry::factory()->for($project)->character()->create();
                CodexAttributeValue::factory()->startingAt($event)->create(['codex_entry_id' => $entry->id, 'codex_attribute_id' => $attribute->id]);
                $this->card($user, $scene, $entry, EditorCardState::Expanded);
                $this->card($user, $scene, Note::factory()->for($project)->for($category, 'category')->create(), EditorCardState::Expanded);
                Note::factory()->for($project)->for($category, 'category')->create()->linkTo($scene);
            }

            $url = route('scenes.editor-cards.index', [$scene, $type]);
            $this->actingAs($user)->get($url)->assertOk();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        foreach (['codex', 'note'] as $type) {
            $this->assertSame($queriesFor(1, $type), $queriesFor(10, $type), $type);
        }
    }

    public function test_the_breadcrumb_context_and_the_new_scene_dialog_list_the_chapters_in_story_order(): void
    {
        [$user, $book, $scenes] = $this->storyAcrossBoundaries();
        $chapters = $scenes[2]->chapter->act->book->chaptersInStoryOrder();

        $response = $this->actingAs($user)->get(route('scenes.write', $scenes[2]))->assertOk();

        $response->assertSee('Chapter 3', false);
        $response->assertSeeInOrder($chapters->map(
            fn (Chapter $chapter) => '<option value="'.$chapter->id.'">'.e($chapter->act->name).' &mdash; '.e($chapter->name).'</option>'
        )->all(), false);
    }

    /** The outline nav of a Write page, without the rest of the panel. */
    private function outlineOf(User $user, Scene $scene): string
    {
        $html = $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk()->getContent();
        $start = strpos($html, 'data-editor-outline');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</nav>', $start) - $start);
    }

    public function test_the_outline_lists_every_scene_in_story_order_across_acts(): void
    {
        [$user, , $scenes] = $this->storyAcrossBoundaries();

        $outline = $this->outlineOf($user, $scenes[1]);

        $this->assertSeeInOrderIn($outline, array_map(
            fn (Scene $scene) => 'href="'.route('scenes.write', $scene).'"',
            $scenes,
        ));
    }

    public function test_the_outline_uses_the_story_numbering_labels(): void
    {
        [$user, , $scenes] = $this->storyAcrossBoundaries();

        $outline = $this->outlineOf($user, $scenes[0]);

        $this->assertSeeInOrderIn($outline, ['Act 1', 'Chapter 1', 'Chapter 2', 'Act 2', 'Chapter 3']);
    }

    public function test_the_outline_hides_the_act_heading_for_one_act(): void
    {
        [$user, , $scene] = $this->ownedScene();

        $outline = $this->outlineOf($user, $scene);

        $this->assertStringNotContainsString('Act 1', $outline);
        $this->assertStringContainsString('Chapter 1', $outline);
    }

    public function test_only_the_current_chapter_is_open_in_the_outline(): void
    {
        [$user, , $scenes] = $this->storyAcrossBoundaries();

        $outline = $this->outlineOf($user, $scenes[1]);

        $chapters = array_slice(explode('<details', $outline), 1);

        $this->assertCount(3, $chapters);
        $this->assertSame([false, true, false], array_map(
            fn (string $chapter) => (bool) preg_match('/^\s+class="[^"]*"\s+open\s*>/', $chapter),
            $chapters,
        ));
    }

    public function test_the_current_scene_is_the_only_outline_row_with_aria_current(): void
    {
        [$user, , $scenes] = $this->storyAcrossBoundaries();

        $outline = $this->outlineOf($user, $scenes[1]);

        $this->assertSame(1, substr_count($outline, 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '#<a\s+href="'.preg_quote(route('scenes.write', $scenes[1]), '#').'"[^>]*aria-current="page"#',
            $outline,
        );
    }

    public function test_the_outline_leaves_out_scenes_of_other_books(): void
    {
        [$user, $project, $scene] = $this->ownedScene();
        $otherBook = Book::factory()->for($project)->create();
        $foreign = $this->sceneIn($otherBook, ['name' => 'Foreign scene']);

        $outline = $this->outlineOf($user, $scene);

        $this->assertStringNotContainsString(route('scenes.write', $foreign), $outline);
        $this->assertStringNotContainsString('Foreign scene', $outline);
    }

    public function test_an_empty_chapter_shows_a_muted_line(): void
    {
        [$user, , $scene] = $this->ownedScene();
        Chapter::factory()->for($scene->chapter->act)->create();

        $outline = $this->outlineOf($user, $scene);

        $this->assertSame(1, substr_count($outline, 'No scenes yet.'));
    }

    public function test_the_outline_shows_no_word_counts(): void
    {
        [$user, , $scene] = $this->ownedScene();
        $scene->forceFill(['word_count' => 120])->save();
        Scene::factory()->for($scene->chapter)->create()->forceFill(['word_count' => 30])->save();

        $outline = $this->outlineOf($user, $scene);

        $this->assertStringNotContainsString('150 words', $outline);
    }

    /** @param  list<string>  $needles */
    private function assertSeeInOrderIn(string $haystack, array $needles): void
    {
        $offset = 0;

        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle, $offset);
            $this->assertNotFalse($position, "Expected '{$needle}' after offset {$offset} in the outline.");
            $offset = $position + strlen($needle);
        }
    }

    public function test_the_outline_query_count_does_not_grow_with_the_scenes(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();

        $queriesFor = function (int $count) use ($user, $chapter): int {
            Scene::factory()->count($count)->for($chapter)->create();
            $scene = $chapter->scenes()->first();

            // The first request of a test writes one-time rows, such as the active project.
            $this->actingAs($user)->get(route('scenes.write', $scene))->assertOk();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('scenes.write', $scene))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $three = $queriesFor(3);

        $this->assertSame($three, $queriesFor(27));
    }

    public function test_a_non_owner_gets_403_on_both_routes(): void
    {
        [, $project, $scene] = $this->ownedScene();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('projects.write', $project))->assertForbidden();
        $this->actingAs($intruder)->get(route('scenes.write', $scene))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        [, $project, $scene] = $this->ownedScene();

        $this->get(route('projects.write', $project))->assertRedirect(route('login'));
        $this->get(route('scenes.write', $scene))->assertRedirect(route('login'));
    }
}
