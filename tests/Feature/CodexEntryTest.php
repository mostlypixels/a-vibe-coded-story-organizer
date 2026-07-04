<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\CodexAlias;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodexEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_a_type_index_and_it_is_isolated_by_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        CodexEntry::factory()->for($project)->location()->create(['name' => 'Lusignan Castle']);

        $characters = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters']));
        $characters->assertOk();
        $characters->assertSee('Melusine');
        $characters->assertDontSee('Lusignan Castle');

        $locations = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'locations']));
        $locations->assertSee('Lusignan Castle');
        $locations->assertDontSee('Melusine');
    }

    public function test_index_search_matches_name_and_alias(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $melusine = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $melusine->aliases()->create(['alias' => 'The Serpent Lady']);

        CodexEntry::factory()->for($project)->character()->create(['name' => 'Raymondin']);

        $byName = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters', 'search' => 'Melusine']));
        $byName->assertSee('Melusine');
        $byName->assertDontSee('Raymondin');

        $byAlias = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters', 'search' => 'Serpent']));
        $byAlias->assertSee('Melusine');
        $byAlias->assertDontSee('Raymondin');
    }

    public function test_index_can_be_filtered_by_tag(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $tag = Tag::factory()->for($project)->create(['name' => 'Fae']);

        $tagged = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $tagged->tags()->attach($tag);

        CodexEntry::factory()->for($project)->character()->create(['name' => 'Raymondin']);

        $response = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters', 'tag' => $tag->id]));
        $response->assertSee('Melusine');
        $response->assertDontSee('Raymondin');
    }

    public function test_owner_can_render_the_create_and_edit_forms(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $entry->aliases()->create(['alias' => 'The Serpent Lady']);

        $this->actingAs($user)->get(route('projects.codex.create', [$project, 'characters']))
            ->assertOk()
            ->assertSee('New Character');

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertSee('Melusine');
    }

    public function test_create_and_edit_forms_receive_project_tags_ordered_by_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Tag::factory()->for($project)->create(['name' => 'Nobility']);
        Tag::factory()->for($project)->create(['name' => 'Fae']);
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);

        $this->actingAs($user)->get(route('projects.codex.create', [$project, 'characters']))
            ->assertOk()
            ->assertViewHas('projectTags', fn ($projectTags) => $projectTags->pluck('name')->all() === ['Fae', 'Nobility']);

        $this->actingAs($user)->get(route('codex.edit', $entry))
            ->assertOk()
            ->assertViewHas('projectTags', fn ($projectTags) => $projectTags->pluck('name')->all() === ['Fae', 'Nobility']);
    }

    public function test_index_tag_filter_omits_tags_with_no_entries(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $attached = Tag::factory()->for($project)->create(['name' => 'Fae']);
        $orphan = Tag::factory()->for($project)->create(['name' => 'Unused']);

        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Melusine']);
        $entry->tags()->attach($attached);

        $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters']))
            ->assertOk()
            ->assertViewHas('tags', function ($tags) use ($attached, $orphan) {
                return $tags->contains($attached) && ! $tags->contains($orphan);
            });
    }

    public function test_owner_can_create_an_entry_with_aliases_and_tags(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $existingTag = Tag::factory()->for($project)->create(['name' => 'Fae']);

        $response = $this->actingAs($user)->post(route('projects.codex.store', [$project, 'characters']), [
            'name' => 'Melusine',
            'description' => 'A **serpent** from the waist down.',
            'aliases' => ['The Serpent Lady', 'Mère Lusignan'],
            'tags' => ['Fae', 'Nobility'],
        ]);

        $response->assertRedirect(route('projects.codex.index', [$project, 'characters']));

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();
        $this->assertSame($project->id, $entry->project_id);
        $this->assertSame('character', $entry->type->value);
        $this->assertSame('A **serpent** from the waist down.', $entry->description);
        $this->assertEqualsCanonicalizing(
            ['The Serpent Lady', 'Mère Lusignan'],
            $entry->aliases()->pluck('alias')->all()
        );

        // Existing tag reused (not duplicated), new tag firstOrCreate'd within the project.
        $this->assertSame(2, $project->tags()->count());
        $this->assertTrue($entry->tags->contains($existingTag));
        $this->assertEqualsCanonicalizing(['Fae', 'Nobility'], $entry->tags->pluck('name')->all());
    }

    public function test_store_creates_project_scoped_tags_without_reusing_another_projects_tag(): void
    {
        $user = User::factory()->create();
        $projectA = Project::factory()->for($user)->create();
        $projectB = Project::factory()->for($user)->create();
        $foreignTag = Tag::factory()->for($projectB)->create(['name' => 'Fae']);

        $this->actingAs($user)->post(route('projects.codex.store', [$projectA, 'characters']), [
            'name' => 'Melusine',
            'tags' => ['Fae'],
        ])->assertRedirect();

        $entry = CodexEntry::where('name', 'Melusine')->firstOrFail();
        $ownTag = $entry->tags->firstOrFail();

        // A fresh, project-scoped tag was created rather than attaching project B's row.
        $this->assertSame($projectA->id, $ownTag->project_id);
        $this->assertNotSame($foreignTag->id, $ownTag->id);
        $this->assertSame(1, $projectA->tags()->count());
    }

    public function test_owner_can_update_an_entry(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create(['name' => 'Old Name']);
        $entry->aliases()->create(['alias' => 'Stale Alias']);

        $response = $this->actingAs($user)->put(route('codex.update', $entry), [
            'name' => 'New Name',
            'description' => 'Updated.',
            'aliases' => ['Fresh Alias'],
            'tags' => ['Reworked'],
        ]);

        $response->assertRedirect(route('projects.codex.index', [$project, 'characters']));

        $entry->refresh();
        $this->assertSame('New Name', $entry->name);
        $this->assertSame(['Fresh Alias'], $entry->aliases()->pluck('alias')->all());
        $this->assertSame(['Reworked'], $entry->tags->pluck('name')->all());
    }

    public function test_store_requires_a_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.codex.store', [$project, 'characters']), [
            'name' => '',
        ])->assertSessionHasErrors('name');
    }

    public function test_unknown_type_segment_returns_404(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        // Build a valid URL then swap in an unknown type — the route `whereIn`
        // constraint should 404 before the controller runs.
        $url = str_replace(
            '/characters',
            '/dragons',
            route('projects.codex.index', [$project, 'characters'])
        );

        $this->actingAs($user)->get($url)->assertNotFound();
    }

    public function test_navigation_lists_every_codex_type(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('projects.codex.index', [$project, 'characters']));
        $response->assertOk();

        foreach (CodexEntryType::cases() as $codexType) {
            $response->assertSee($codexType->pluralLabel());
        }
    }

    public function test_destroy_cascades_aliases_tags_and_attribute_values(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->character()->create();
        $alias = $entry->aliases()->create(['alias' => 'Doomed']);
        $tag = Tag::factory()->for($project)->create();
        $entry->tags()->attach($tag);
        $attribute = CodexAttribute::factory()->for($project)->create();
        $value = CodexAttributeValue::factory()->for($entry, 'entry')->create(['codex_attribute_id' => $attribute->id]);

        $this->actingAs($user)->delete(route('codex.destroy', $entry))
            ->assertRedirect(route('projects.codex.index', [$project, 'characters']));

        $this->assertNull($entry->fresh());
        $this->assertNull(CodexAlias::find($alias->id));
        $this->assertNull(CodexAttributeValue::find($value->id));
        $this->assertDatabaseMissing('codex_entry_tag', ['codex_entry_id' => $entry->id, 'tag_id' => $tag->id]);
    }

    public function test_non_owner_is_forbidden_from_every_action(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->actingAs($other)->get(route('projects.codex.index', [$project, 'characters']))->assertForbidden();
        $this->actingAs($other)->get(route('projects.codex.create', [$project, 'characters']))->assertForbidden();
        $this->actingAs($other)->post(route('projects.codex.store', [$project, 'characters']), [
            'name' => 'Sneaky',
        ])->assertForbidden();
        $this->actingAs($other)->get(route('codex.edit', $entry))->assertForbidden();
        $this->actingAs($other)->put(route('codex.update', $entry), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($other)->delete(route('codex.destroy', $entry))->assertForbidden();
    }
}
