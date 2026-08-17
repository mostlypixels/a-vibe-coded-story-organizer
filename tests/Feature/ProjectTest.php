<?php

namespace Tests\Feature;

use App\Enums\BookLanguage;
use App\Enums\RevisionOrigin;
use App\Enums\StoryOverviewMode;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\WordCountSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for the Project resource itself.
 *
 * The plotline and event controllers have their own dedicated test files
 * (PlotlineTest, EventTest). What stays here is project-scoped: the dashboard,
 * project CRUD/authorization, and the model invariants that project creation
 * seeds — the auto-created main plotline and the Start/End bookend events — plus
 * the Project::startEvent()/endEvent() bookend helpers.
 */
class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_the_authenticated_users_projects_alphabetically(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['name' => 'Zebra']);
        Project::factory()->for($user)->create(['name' => 'Apple']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeInOrder(['Apple', 'Zebra']);
    }

    public function test_the_dashboard_offers_edit_and_delete_on_every_project_row(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('projects.edit', $project).'"', $html);
        $this->assertStringContainsString('action="'.route('projects.destroy', $project).'"', $html);
        // The list and the grid are two renderings of the same list, so both carry the
        // actions — the writer's view preference must not decide what they can do.
        $this->assertSame(2, substr_count($html, 'action="'.route('projects.destroy', $project).'"'));
    }

    public function test_the_dashboard_delete_warns_with_the_same_cascade_sentence_as_the_edit_page(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        Act::factory()->for($book)->create();
        CodexEntry::factory()->for($project)->create();

        $expected = 'This project has 1 act and 1 codex entry, which will also be deleted.';

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee($expected);
        $this->actingAs($user)->get(route('projects.edit', $project))->assertOk()->assertSee($expected);
    }

    public function test_the_dashboard_counts_cost_the_same_whatever_the_project_count(): void
    {
        // The delete warning needs four relation counts per project. They must come from
        // the list query, not from one loadCount() per row.
        $user = User::factory()->create();
        Project::factory()->for($user)->create();

        // The first request of a session writes rows a later one only reads, so warm it
        // up before measuring — the comparison is between two steady-state requests.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $withOne = count(DB::getQueryLog());

        Project::factory()->for($user)->count(4)->create();

        // Flush after the factory writes, so only the request's own queries are counted.
        DB::flushQueryLog();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $withFive = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withOne, $withFive);
    }

    public function test_a_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'My Project',
            'description' => 'A test project',
        ]);

        $project = Project::first();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame($user->id, $project->user_id);
    }

    public function test_a_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('projects.show', $project))->assertForbidden();
    }

    // --- Publication metadata columns (epub export) ------------------------

    public function test_language_defaults_to_en_when_not_set_explicitly(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->for($user)->create(['name' => 'Untitled']);

        $this->assertSame(BookLanguage::English, $project->fresh()->language);
    }

    public function test_overview_render_mode_defaults_to_chapter_when_not_set_explicitly(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->for($user)->create(['name' => 'Untitled']);

        $this->assertSame(StoryOverviewMode::Chapter, $project->fresh()->overview_render_mode);
    }

    public function test_the_epub_metadata_attributes_are_mass_assignable(): void
    {
        $user = User::factory()->create();

        // fill() proves mass-assignability of the six new columns; the factory
        // supplies only the non-fillable user_id association.
        $project = Project::factory()->for($user)->create();
        $project->fill([
            'name' => 'My Novel',
            'description' => 'A test project',
            'language' => 'fr',
            'author' => 'Jane Author',
            'publisher' => 'Imaginary Press',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => 'covers/my-novel.jpg',
        ])->save();

        $project = $project->fresh();

        $this->assertSame(BookLanguage::French, $project->language);
        $this->assertSame('Jane Author', $project->author);
        $this->assertSame('Imaginary Press', $project->publisher);
        $this->assertSame('978-0-306-40615-7', $project->isbn);
        $this->assertSame('covers/my-novel.jpg', $project->cover_image);
    }

    // --- Project edit form: book metadata + cover upload -------------------

    public function test_owner_can_update_a_project_with_all_book_metadata_and_a_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'My Novel',
            'description' => 'A test project',
            'language' => 'fr',
            'author' => 'Jane Author',
            'publisher' => 'Imaginary Press',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect(route('projects.show', $project));

        $project = $project->fresh();
        $this->assertSame('My Novel', $project->name);
        $this->assertSame(BookLanguage::French, $project->language);
        $this->assertSame('Jane Author', $project->author);
        $this->assertSame('Imaginary Press', $project->publisher);
        $this->assertSame('978-0-306-40615-7', $project->isbn);
        $this->assertNotNull($project->cover_image);
        Storage::disk('public')->assertExists($project->cover_image);
    }

    public function test_saving_the_edit_form_records_a_labeled_manual_revision_for_a_changed_autosaved_field(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => 'Old description']);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'description' => 'New description',
            'language' => 'en',
        ]);

        $revision = $project->revisions()->where('field', 'description')->latest('created_at')->first();

        $this->assertNotNull($revision);
        $this->assertSame(RevisionOrigin::Manual, $revision->origin);
        $this->assertSame('New description', $revision->value);
        $this->assertNotNull($revision->label);
    }

    public function test_saving_the_edit_form_does_not_record_a_revision_for_an_untouched_autosaved_field(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['description' => 'Same description']);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'New name',
            'description' => 'Same description',
            'language' => 'en',
        ]);

        $this->assertSame(0, $project->revisions()->where('field', 'description')->count());
    }

    public function test_a_non_owner_cannot_update_a_project(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->put(route('projects.update', $project), [
            'name' => 'Hijacked',
            'language' => 'en',
        ])->assertForbidden();
    }

    public function test_owner_can_set_both_word_goals_and_they_persist(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => 'en',
            'daily_word_goal' => '500',
            'total_word_goal' => '80000',
        ]);

        $project = $project->fresh();
        $this->assertSame(500, $project->daily_word_goal);
        $this->assertSame(80000, $project->total_word_goal);
    }

    public function test_a_non_owner_cannot_set_word_goals(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)->put(route('projects.update', $project), [
            'name' => 'Hijacked',
            'language' => 'en',
            'daily_word_goal' => '500',
        ])->assertForbidden();

        $this->assertNull($project->fresh()->daily_word_goal);
    }

    public function test_empty_word_goal_input_clears_the_goal_to_null(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'daily_word_goal' => 500,
            'total_word_goal' => 80000,
        ]);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => 'en',
            'daily_word_goal' => '',
            'total_word_goal' => '',
        ]);

        $project = $project->fresh();
        $this->assertNull($project->daily_word_goal);
        $this->assertNull($project->total_word_goal);
    }

    public function test_a_negative_word_goal_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => 'en',
            'daily_word_goal' => '-5',
        ]);

        $response->assertSessionHasErrors('daily_word_goal');
    }

    public function test_a_non_integer_word_goal_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'language' => 'en',
            'total_word_goal' => 'lots',
        ]);

        $response->assertSessionHasErrors('total_word_goal');
    }

    public function test_the_edit_page_footer_form_resyncs_codex_references_for_the_project(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();
        $scene = Scene::factory()->for($chapter)->create(['contents' => 'Mélusine walked into the room.']);
        $entry = CodexEntry::factory()->for($project)->create(['name' => 'Mélusine']);

        // The pivot starts empty: nothing has scanned this pre-existing scene yet.
        $this->assertDatabaseMissing('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);

        $response = $this->actingAs($user)->post(route('projects.codex-references.sync', $project));

        $response->assertRedirect(route('projects.edit', $project));
        $response->assertSessionHas('status', 'codex-references-synced');
        $this->assertDatabaseHas('scene_codex_entry', [
            'scene_id' => $scene->id,
            'codex_entry_id' => $entry->id,
        ]);
    }

    public function test_a_non_owner_cannot_resync_codex_references(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->post(route('projects.codex-references.sync', $project))
            ->assertForbidden();
    }

    public function test_updating_a_project_with_an_invalid_isbn_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'My Novel',
            'language' => 'en',
            'isbn' => '978-0-306-40615-0', // bad checksum
        ]);

        $response->assertSessionHasErrors('isbn');
    }

    // --- Front/back-matter Markdown --------------------------------------

    public function test_updating_a_project_with_an_unsupported_language_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'My Novel',
            'language' => 'ja', // not a supported BookLanguage case
        ]);

        $response->assertSessionHasErrors('language');
    }

    public function test_updating_a_project_with_an_invalid_cover_fails_validation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Wrong mime type (a PDF, not an image).
        $wrongType = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'My Novel',
            'language' => 'en',
            'cover_image' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'),
        ]);
        $wrongType->assertSessionHasErrors('cover_image');

        // Oversized image (over the 5 MB / 5120 KB cover limit).
        $oversized = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'My Novel',
            'language' => 'en',
            'cover_image' => UploadedFile::fake()->image('huge.jpg')->size(6000),
        ]);
        $oversized->assertSessionHasErrors('cover_image');
    }

    public function test_replacing_the_cover_deletes_the_old_file_and_stores_the_new_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldPath = 'project-covers/old-cover.jpg';
        Storage::disk('public')->put($oldPath, 'old contents');
        $project = Project::factory()->for($user)->create(['cover_image' => $oldPath]);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'My Novel',
            'language' => 'en',
            'cover_image' => UploadedFile::fake()->image('new-cover.jpg'),
        ])->assertRedirect();

        $project = $project->fresh();
        $this->assertNotSame($oldPath, $project->cover_image);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($project->cover_image);
    }

    public function test_removing_the_cover_clears_the_column_and_deletes_the_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldPath = 'project-covers/old-cover.jpg';
        Storage::disk('public')->put($oldPath, 'old contents');
        $project = Project::factory()->for($user)->create(['cover_image' => $oldPath]);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'My Novel',
            'language' => 'en',
            'remove_cover_image' => '1',
        ])->assertRedirect();

        $this->assertNull($project->fresh()->cover_image);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_deleting_a_project_removes_its_cover_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $coverPath = 'project-covers/doomed-cover.jpg';
        Storage::disk('public')->put($coverPath, 'contents');
        $project = Project::factory()->for($user)->create(['cover_image' => $coverPath]);

        $this->actingAs($user)->delete(route('projects.destroy', $project))
            ->assertRedirect(route('dashboard'));

        Storage::disk('public')->assertMissing($coverPath);
    }

    // --- Project-creation invariants ---------------------------------------

    public function test_a_main_plotline_is_created_automatically_with_the_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->assertSame(1, $project->plotlines()->count());
        $this->assertTrue($project->plotlines()->first()->is_main);
        $this->assertSame('Main plotline', $project->plotlines()->first()->name);
        $this->assertNotNull($project->plotlines()->first()->color);
    }

    public function test_start_and_end_events_are_created_automatically_with_the_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $mainPlotline = $project->plotlines()->first();

        $this->assertSame(2, $project->events()->count());

        $start = $project->events()->where('title', 'Start')->first();
        $end = $project->events()->where('title', 'End')->first();

        $this->assertNotNull($start);
        $this->assertNotNull($end);
        $this->assertTrue($start->is_fixed);
        $this->assertTrue($end->is_fixed);
        $this->assertSame('0001', $start->event_datetime->format('Y'));
        $this->assertSame('3000', $end->event_datetime->format('Y'));
        $this->assertTrue($start->plotlines->contains($mainPlotline));
        $this->assertTrue($end->plotlines->contains($mainPlotline));
    }

    public function test_start_and_end_event_helpers_resolve_the_bookends(): void
    {
        $project = Project::factory()->create();

        $start = $project->startEvent();
        $end = $project->endEvent();

        $this->assertTrue($start->is_fixed);
        $this->assertTrue($end->is_fixed);
        $this->assertSame('0001', $start->event_datetime->format('Y'));
        $this->assertSame('3000', $end->event_datetime->format('Y'));
        $this->assertSame('Start', $start->title);
        $this->assertSame('End', $end->title);
    }

    public function test_the_bookend_helpers_break_datetime_ties_by_id(): void
    {
        $project = Project::factory()->create();

        // Replace the auto-created bookends with two fixed events sharing one datetime,
        // so only the id tie-break can distinguish them (delete at the model level —
        // the is_fixed guard lives in the controller, not the DB).
        $project->events()->delete();

        $sharedDatetime = '1500-06-15 00:00:00';

        $lower = Event::factory()->for($project)->create([
            'event_datetime' => $sharedDatetime,
            'is_fixed' => true,
        ]);
        $higher = Event::factory()->for($project)->create([
            'event_datetime' => $sharedDatetime,
            'is_fixed' => true,
        ]);

        $this->assertLessThan($higher->id, $lower->id);

        // Both share a datetime, so only the id tie-break distinguishes them: lowest
        // id wins startEvent(), highest id wins endEvent().
        $this->assertSame($lower->id, $project->startEvent()->id);
        $this->assertSame($higher->id, $project->endEvent()->id);
    }

    // --- Delete confirmation string -----------------------------------------

    public function test_the_edit_page_lists_only_non_zero_categories_in_the_delete_confirmation(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);

        Act::factory()->for($book)->create();
        Act::factory()->for($book)->create();
        CodexEntry::factory()->for($project)->character()->create();
        CodexEntry::factory()->for($project)->character()->create();
        CodexEntry::factory()->for($project)->character()->create();
        CodexEntry::factory()->for($project)->character()->create();
        CodexEntry::factory()->for($project)->character()->create();

        // No extra plotlines/events beyond the auto-created main plotline and
        // Start/End bookends, so those two categories must stay out of the sentence.
        $response = $this->actingAs($user)->get(route('projects.edit', $project));

        $response->assertSee('This project has 2 acts and 5 codex entries, which will also be deleted.', false);
        $response->assertDontSee('Are you sure you want to delete this project?');
    }

    public function test_the_page_level_draft_recovery_modal_is_not_mounted_on_a_page_with_autosave_fields(): void
    {
        // Regression guard: the draft recovery modal was removed. A future
        // layout edit must not re-mount it.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.edit', $project));

        $response->assertOk();
        // The old modal rendered the dialog's name inside its
        // open-modal.window listener comparison; assert that string is gone.
        $response->assertDontSee("== 'draft-recovery'", false);
        $response->assertDontSee('data-autosave-draft-banner', false);
    }

    public function test_a_brand_new_projects_edit_page_shows_the_unqualified_delete_confirmation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // A fresh project only has its auto-created main plotline and Start/End
        // bookend events (Project::booted()) — none of which count toward the
        // cascade sentence, so the original unqualified question must show instead.
        $response = $this->actingAs($user)->get(route('projects.edit', $project));

        $response->assertSee('Are you sure you want to delete this project?', false);
        $response->assertDontSee('0 acts');
        $response->assertDontSee('0 plotlines');
        $response->assertDontSee('0 events');
        $response->assertDontSee('0 codex entries');
    }

    public function test_the_edit_page_links_to_the_projects_revision_history(): void
    {
        // The Actions card carries the entity-level History link — the primary
        // way into the revisions browser. The closing quote prevents a match on
        // a per-field `?field=` icon link.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee(
                'href="'.route('revisions.index', ['entity' => 'project', 'id' => $project->id]).'"',
                false,
            );
    }

    // --- Total word count on the project header --------------------------

    public function test_the_project_page_shows_the_total_word_count(): void
    {
        $user = User::factory()->create();
        [$project, $book] = $this->projectWithBook($user);
        $act = Act::factory()->for($book)->create();
        $chapter = Chapter::factory()->for($act)->create();

        // A total far outside the range the plotline/event counts shown on the
        // same page could produce, so the assertion can only be satisfied by
        // summing every scene in the project.
        Scene::factory()->for($chapter)->create(['contents' => trim(str_repeat('word ', 725))]);
        Scene::factory()->for($chapter)->create(['contents' => trim(str_repeat('word ', 275))]); // total: 1,000

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('1,000 words');
    }

    public function test_a_project_with_no_scenes_shows_zero_words_on_its_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('0 words');
    }

    // --- The dashboard Progress card ---------------------------------------

    public function test_the_dashboard_progress_card_renders_the_full_chart(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('Progress'))
            ->assertSee('data-variant="full"', false)
            ->assertSee(route('projects.progress', $project), false);
    }

    public function test_the_dashboard_opens_with_the_last_edited_scenes_and_codex(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSeeInOrder([__('Recent scenes'), __('Recent codex entries')]);
    }

    public function test_a_project_with_no_goals_renders_the_card_without_goal_bars(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'daily_word_goal' => null,
            'total_word_goal' => null,
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee(__('Today'))
            // No bar and no "of ∞", but the plain total stays: the dashboard
            // states the project's length nowhere else.
            ->assertDontSee('words of')
            ->assertSee(__('Total'))
            ->assertSee('0 words');
    }

    public function test_a_project_with_both_goals_renders_both_progress_rows(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'daily_word_goal' => 500,
            'total_word_goal' => 10000,
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(__('Today'))
            ->assertSee(__('Total'));
    }

    public function test_the_progress_card_costs_exactly_two_extra_queries(): void
    {
        // The chart's series comes from WordCountHistory, which costs two
        // queries whatever the range length (see WordCountHistoryTest).
        // This asserts the dashboard card does not regress to one query per
        // day — projects/show is already the heaviest page in the app.
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['daily_word_goal' => null]);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('projects.show', $project));
        $snapshotQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'word_count_snapshots'));
        DB::disableQueryLog();

        $this->assertCount(2, $snapshotQueries);
    }

    public function test_the_streak_adds_one_query_and_only_with_a_daily_goal(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['daily_word_goal' => 500]);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('projects.show', $project));
        $snapshotQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'word_count_snapshots'));
        DB::disableQueryLog();

        $this->assertCount(3, $snapshotQueries);
    }

    public function test_the_dashboard_shows_the_daily_goal_streak(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $project = Project::factory()->for($user)->create(['daily_word_goal' => 500]);

        $today = CarbonImmutable::now('UTC')->startOfDay();

        foreach ([3, 2, 1] as $daysAgo) {
            WordCountSnapshot::factory()->for($project)->create([
                'recorded_on' => $today->subDays($daysAgo)->toDateString(),
                'word_count' => (4 - $daysAgo) * 500,
            ]);
        }

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('3 days in a row');
    }

    public function test_a_project_with_no_daily_goal_shows_no_streak(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['daily_word_goal' => null]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee(__('No streak yet'));
    }
}
