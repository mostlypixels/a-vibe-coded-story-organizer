<?php

namespace Tests\Feature;

use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_tags_with_entry_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $tag = Tag::factory()->for($project)->create(['name' => 'Protagonist']);
        $entry = CodexEntry::factory()->for($project)->create();
        $tag->entries()->attach($entry);

        $this->actingAs($user)->get(route('projects.tags.index', $project))
            ->assertOk()
            ->assertSee('Protagonist')
            ->assertSee('1');
    }

    public function test_owner_can_create_a_tag(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.tags.store', $project), ['name' => 'Magic'])
            ->assertRedirect(route('projects.tags.index', $project));

        $this->assertDatabaseHas('tags', ['project_id' => $project->id, 'name' => 'Magic']);
    }

    public function test_a_duplicate_tag_name_in_the_project_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Tag::factory()->for($project)->create(['name' => 'Magic']);

        $this->actingAs($user)->post(route('projects.tags.store', $project), ['name' => 'Magic'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $project->tags()->where('name', 'Magic')->count());
    }

    public function test_owner_can_rename_a_tag(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->for($project)->create(['name' => 'Villian']);

        $this->actingAs($user)->put(route('tags.update', $tag), ['name' => 'Villain'])
            ->assertRedirect(route('projects.tags.index', $project));

        $this->assertSame('Villain', $tag->fresh()->name);
    }

    public function test_a_rename_may_keep_the_same_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->for($project)->create(['name' => 'Magic']);

        $this->actingAs($user)->put(route('tags.update', $tag), ['name' => 'Magic'])
            ->assertSessionHasNoErrors();
    }

    public function test_deleting_a_tag_detaches_it_from_entries(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->for($project)->create();
        $entry = CodexEntry::factory()->for($project)->create();
        $tag->entries()->attach($entry);

        $this->actingAs($user)->delete(route('tags.destroy', $tag))
            ->assertRedirect(route('projects.tags.index', $project));

        $this->assertModelMissing($tag);
        $this->assertDatabaseMissing('codex_entry_tag', ['tag_id' => $tag->id]);
        $this->assertModelExists($entry);
    }

    public function test_non_owner_is_forbidden_from_every_action(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $tag = Tag::factory()->for($project)->create();

        $this->actingAs($other)->get(route('projects.tags.index', $project))->assertForbidden();
        $this->actingAs($other)->post(route('projects.tags.store', $project), ['name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($other)->put(route('tags.update', $tag), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($other)->delete(route('tags.destroy', $tag))->assertForbidden();
    }
}
