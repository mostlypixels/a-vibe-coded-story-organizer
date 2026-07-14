<?php

namespace Tests\Feature;

use App\Enums\BookLanguage;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'rights' => '© 2026 Jane Author',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => 'covers/my-novel.jpg',
        ])->save();

        $project = $project->fresh();

        $this->assertSame(BookLanguage::French, $project->language);
        $this->assertSame('Jane Author', $project->author);
        $this->assertSame('Imaginary Press', $project->publisher);
        $this->assertSame('© 2026 Jane Author', $project->rights);
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
            'rights' => '© 2026 Jane Author',
            'isbn' => '978-0-306-40615-7',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect(route('projects.show', $project));

        $project = $project->fresh();
        $this->assertSame('My Novel', $project->name);
        $this->assertSame(BookLanguage::French, $project->language);
        $this->assertSame('Jane Author', $project->author);
        $this->assertSame('Imaginary Press', $project->publisher);
        $this->assertSame('© 2026 Jane Author', $project->rights);
        $this->assertSame('978-0-306-40615-7', $project->isbn);
        $this->assertNotNull($project->cover_image);
        Storage::disk('public')->assertExists($project->cover_image);
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

    public function test_the_edit_page_footer_form_resyncs_codex_references_for_the_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $act = Act::factory()->for($project)->create();
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
}
