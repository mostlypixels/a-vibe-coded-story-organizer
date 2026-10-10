<?php

namespace Tests\Feature;

use App\Enums\Genre;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Models\User;
use App\Services\StarterNoteCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->project = Project::factory()->for($this->owner)->create();
    }

    private function category(string $name, ?NoteCategory $parent = null, ?Project $project = null): NoteCategory
    {
        return NoteCategory::factory()->create([
            'project_id' => ($project ?? $this->project)->id,
            'parent_id' => $parent?->id,
            'name' => $name,
        ]);
    }

    // --- Store ---------------------------------------------------------------

    public function test_owner_can_create_a_top_level_category(): void
    {
        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $this->project), ['name' => 'Maps'])
            ->assertRedirect(route('projects.notes.index', $this->project));

        $category = NoteCategory::firstOrFail();
        $this->assertSame('Maps', $category->name);
        $this->assertNull($category->parent_id);
        $this->assertSame($this->project->id, $category->project_id);
    }

    public function test_owner_can_create_a_nested_category(): void
    {
        $parent = $this->category('Research');

        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $this->project), ['name' => 'Tides', 'parent_id' => $parent->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($parent->id, NoteCategory::where('name', 'Tides')->firstOrFail()->parent_id);
    }

    public function test_name_is_required_and_capped_at_255(): void
    {
        $this->actingAs($this->owner)->post(route('projects.note-categories.store', $this->project), ['name' => ''])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->owner)->post(route('projects.note-categories.store', $this->project), ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, NoteCategory::count());
    }

    public function test_a_sibling_name_clash_is_rejected_at_the_root(): void
    {
        $this->category('Research');

        $this->actingAs($this->owner)->post(route('projects.note-categories.store', $this->project), ['name' => 'Research'])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, NoteCategory::count());
    }

    public function test_a_sibling_name_clash_is_rejected_under_a_parent(): void
    {
        $parent = $this->category('Research');
        $this->category('Tides', $parent);

        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $this->project), ['name' => 'Tides', 'parent_id' => $parent->id])
            ->assertSessionHasErrors('name');
        $this->assertSame(2, NoteCategory::count());
    }

    public function test_the_same_name_is_allowed_under_another_parent_and_in_another_project(): void
    {
        $research = $this->category('Research');
        $planning = $this->category('Planning');
        $this->category('Tides', $research);
        $elsewhere = Project::factory()->for($this->owner)->create();
        $this->category('Planning', null, $elsewhere);

        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $this->project), ['name' => 'Tides', 'parent_id' => $planning->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $elsewhere), ['name' => 'Research'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_parent_from_another_project_is_rejected(): void
    {
        $elsewhere = Project::factory()->for($this->owner)->create();
        $foreign = $this->category('Foreign', null, $elsewhere);

        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $this->project), ['name' => 'Child', 'parent_id' => $foreign->id])
            ->assertSessionHasErrors('parent_id');
        $this->assertSame(0, $this->project->noteCategories()->count());
    }

    public function test_a_category_four_levels_deep_is_rejected(): void
    {
        $one = $this->category('One');
        $two = $this->category('Two', $one);
        $three = $this->category('Three', $two);

        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $this->project), ['name' => 'Four', 'parent_id' => $three->id])
            ->assertSessionHasErrors('parent_id');
        $this->actingAs($this->owner)
            ->post(route('projects.note-categories.store', $this->project), ['name' => 'Three b', 'parent_id' => $two->id])
            ->assertSessionHasNoErrors();
    }

    // --- Update --------------------------------------------------------------

    public function test_owner_can_rename_a_category_and_keep_its_parent(): void
    {
        $parent = $this->category('Research');
        $child = $this->category('Tides', $parent);

        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $child), ['name' => 'Currents'])
            ->assertRedirect(route('projects.notes.index', $this->project));

        $child->refresh();
        $this->assertSame('Currents', $child->name);
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_a_category_keeps_its_own_name_on_save(): void
    {
        $category = $this->category('Research');

        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $category), ['name' => 'Research', 'parent_id' => null])
            ->assertSessionHasNoErrors();
    }

    public function test_owner_can_move_a_category_and_to_the_root(): void
    {
        $planning = $this->category('Planning');
        $tides = $this->category('Tides');

        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $tides), ['name' => 'Tides', 'parent_id' => $planning->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($planning->id, $tides->fresh()->parent_id);

        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $tides), ['name' => 'Tides', 'parent_id' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($tides->fresh()->parent_id);
    }

    public function test_moving_under_itself_or_a_descendant_is_rejected(): void
    {
        $one = $this->category('One');
        $two = $this->category('Two', $one);

        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $one), ['name' => 'One', 'parent_id' => $one->id])
            ->assertSessionHasErrors('parent_id');
        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $one), ['name' => 'One', 'parent_id' => $two->id])
            ->assertSessionHasErrors('parent_id');
        $this->assertNull($one->fresh()->parent_id);
    }

    public function test_moving_a_branch_past_the_depth_limit_is_rejected(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C');
        $d = $this->category('D', $c);

        // C holds D, so C under B would put D at level 4.
        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $c), ['name' => 'C', 'parent_id' => $b->id])
            ->assertSessionHasErrors('parent_id');
        // D alone fits under B.
        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $d), ['name' => 'D', 'parent_id' => $b->id])
            ->assertSessionHasNoErrors();
    }

    public function test_a_rename_that_clashes_with_a_sibling_is_rejected(): void
    {
        $this->category('Research');
        $planning = $this->category('Planning');

        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $planning), ['name' => 'Research'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_move_into_a_group_with_the_same_name_is_rejected(): void
    {
        $research = $this->category('Research');
        $this->category('Tides', $research);
        $tides = $this->category('Tides');

        $this->actingAs($this->owner)
            ->patch(route('note-categories.update', $tides), ['name' => 'Tides', 'parent_id' => $research->id])
            ->assertSessionHasErrors('name');
    }

    // --- Destroy -------------------------------------------------------------

    public function test_delete_moves_notes_and_sub_categories_to_the_parent(): void
    {
        $top = $this->category('Top');
        $middle = $this->category('Middle', $top);
        $leaf = $this->category('Leaf', $middle);
        $note = Note::factory()->for($this->project)->create(['note_category_id' => $middle->id, 'updated_at' => now()->subYear()]);
        $stamp = $note->fresh()->updated_at;

        $this->actingAs($this->owner)
            ->delete(route('note-categories.destroy', $middle))
            ->assertRedirect(route('projects.notes.index', $this->project));

        $this->assertModelMissing($middle);
        $this->assertSame($top->id, $note->fresh()->note_category_id);
        $this->assertSame($top->id, $leaf->fresh()->parent_id);
        $this->assertTrue($stamp->equalTo($note->fresh()->updated_at), 'A category move must not touch updated_at.');
    }

    public function test_delete_at_the_root_moves_contents_to_the_root(): void
    {
        $top = $this->category('Top');
        $child = $this->category('Child', $top);
        $note = Note::factory()->for($this->project)->create(['note_category_id' => $top->id]);

        $this->actingAs($this->owner)->delete(route('note-categories.destroy', $top))->assertRedirect();

        $this->assertModelMissing($top);
        $this->assertNull($child->fresh()->parent_id);
        $this->assertNull($note->fresh()->note_category_id);
        $this->assertModelExists($note);
    }

    // --- Authorization ---------------------------------------------------------

    public function test_a_non_owner_gets_403_on_every_action(): void
    {
        $category = $this->category('Private');
        $as = $this->actingAs($this->other);

        $as->post(route('projects.note-categories.store', $this->project), ['name' => 'Hack'])->assertForbidden();
        $as->patch(route('note-categories.update', $category), ['name' => 'Hacked'])->assertForbidden();
        $as->delete(route('note-categories.destroy', $category))->assertForbidden();

        $this->assertSame(1, NoteCategory::count());
        $this->assertSame('Private', $category->fresh()->name);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->post(route('projects.note-categories.store', $this->project), ['name' => 'X'])->assertRedirect(route('login'));
    }

    // --- Index sidebar and filter ------------------------------------------------

    public function test_the_index_shows_the_tree_with_the_current_item_marked(): void
    {
        $research = $this->category('Research');
        $this->category('Tides', $research);

        $html = $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'category' => $research->id]))
            ->assertOk()
            ->assertSee('Research')
            ->assertSee('Tides')
            ->assertSee(__('Uncategorized'))
            ->getContent();

        $this->assertMatchesRegularExpression('/<a[^>]*category='.$research->id.'"[^>]*aria-current="page"/', $html);
    }

    public function test_the_category_filter_shows_only_direct_notes(): void
    {
        $research = $this->category('Research');
        $tides = $this->category('Tides', $research);
        Note::factory()->for($this->project)->create(['title' => 'In research', 'note_category_id' => $research->id]);
        Note::factory()->for($this->project)->create(['title' => 'In tides', 'note_category_id' => $tides->id]);
        Note::factory()->for($this->project)->create(['title' => 'Loose note']);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'category' => $research->id]))
            ->assertSee('In research')
            ->assertDontSee('In tides')
            ->assertDontSee('Loose note');
    }

    public function test_category_none_shows_only_uncategorized_notes(): void
    {
        $research = $this->category('Research');
        Note::factory()->for($this->project)->create(['title' => 'In research', 'note_category_id' => $research->id]);
        Note::factory()->for($this->project)->create(['title' => 'Loose note']);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.index', ['project' => $this->project, 'category' => 'none']))
            ->assertSee('Loose note')
            ->assertDontSee('In research');
    }

    // --- Note forms ---------------------------------------------------------------

    public function test_a_note_can_be_stored_and_moved_into_a_category(): void
    {
        $research = $this->category('Research');

        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), [
            'title' => 'Tides', 'note_category_id' => $research->id,
        ])->assertSessionHasNoErrors();
        $note = Note::firstOrFail();
        $this->assertSame($research->id, $note->note_category_id);

        $this->actingAs($this->owner)->put(route('notes.update', $note), [
            'title' => 'Tides', 'body' => $note->body, 'note_category_id' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($note->fresh()->note_category_id);
    }

    public function test_a_note_category_from_another_project_is_rejected(): void
    {
        $elsewhere = Project::factory()->for($this->owner)->create();
        $foreign = $this->category('Foreign', null, $elsewhere);
        $note = Note::factory()->for($this->project)->create();

        $this->actingAs($this->owner)->post(route('projects.notes.store', $this->project), [
            'title' => 'Bad', 'note_category_id' => $foreign->id,
        ])->assertSessionHasErrors('note_category_id');
        $this->actingAs($this->owner)->put(route('notes.update', $note), [
            'title' => 'Bad', 'body' => $note->body, 'note_category_id' => $foreign->id,
        ])->assertSessionHasErrors('note_category_id');

        $this->assertSame(1, Note::count());
        $this->assertNull($note->fresh()->note_category_id);
    }

    public function test_the_create_and_edit_forms_offer_the_indented_categories(): void
    {
        $research = $this->category('Research');
        $this->category('Tides', $research);
        $note = Note::factory()->for($this->project)->create(['note_category_id' => $research->id]);

        $this->actingAs($this->owner)
            ->get(route('projects.notes.create', ['project' => $this->project, 'category' => $research->id]))
            ->assertOk()
            ->assertSee('name="note_category_id"', false)
            ->assertSeeInOrder(['value="'.$research->id.'"', 'selected', "\u{00A0}\u{00A0}Tides"], false);

        $this->actingAs($this->owner)->get(route('notes.edit', $note))
            ->assertOk()
            ->assertSee('value="'.$research->id.'"', false);
    }

    public function test_the_show_page_names_the_category_path(): void
    {
        $research = $this->category('Research');
        $tides = $this->category('Tides', $research);
        $note = Note::factory()->for($this->project)->create(['note_category_id' => $tides->id]);

        $this->actingAs($this->owner)->get(route('notes.show', $note))
            ->assertOk()
            ->assertSee('Research › Tides');
    }

    // --- Starter categories ----------------------------------------------------------

    public function test_creating_a_project_adds_the_six_starter_categories(): void
    {
        $this->actingAs($this->owner)->post(route('projects.store'), ['name' => 'Fresh project'])->assertRedirect();

        $project = Project::where('name', 'Fresh project')->firstOrFail();
        $this->assertEqualsCanonicalizing(StarterNoteCategories::NAMES, $project->noteCategories()->pluck('name')->all());
        $this->assertSame(0, $project->noteCategories()->whereNotNull('parent_id')->count());
    }

    public function test_onboarding_adds_the_six_starter_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), ['name' => 'First', 'genre' => Genre::Blank->value])->assertRedirect();

        $project = Project::where('user_id', $user->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(StarterNoteCategories::NAMES, $project->noteCategories()->pluck('name')->all());
    }

    public function test_the_demo_install_adds_starters_to_demo_projects_once(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:install-demo', ['--user' => (string) $user->id])->assertSuccessful();

        $demo = $user->projects()->get();
        $this->assertCount(3, $demo);
        foreach ($demo as $project) {
            $this->assertEqualsCanonicalizing(StarterNoteCategories::NAMES, $project->noteCategories()->whereNull('parent_id')->pluck('name')->all());
        }

        // A deleted starter stays deleted on a second install.
        $demo->first()->noteCategories()->where('name', 'Ideas')->delete();
        $this->artisan('app:install-demo', ['--user' => (string) $user->id])->assertSuccessful();
        $this->assertSame(5, $demo->first()->noteCategories()->whereNull('parent_id')->count());
    }

    public function test_a_model_created_project_has_no_categories(): void
    {
        $project = Project::factory()->for($this->owner)->create();

        $this->assertSame(0, $project->noteCategories()->count());
    }
}
