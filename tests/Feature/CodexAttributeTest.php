<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodexAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_the_index_with_applies_to_badges(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        CodexAttribute::factory()->for($project)
            ->appliesTo(CodexEntryType::Character)
            ->create(['name' => 'Hair color']);

        $response = $this->actingAs($user)->get(route('projects.codex-attributes.index', $project));

        $response->assertOk();
        $response->assertSee('Hair color');
        $response->assertSee('Character');
    }

    public function test_owner_can_render_the_create_and_edit_forms(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $attribute = CodexAttribute::factory()->for($project)->create(['name' => 'Terrain']);

        $this->actingAs($user)->get(route('projects.codex-attributes.create', $project))
            ->assertOk()
            ->assertSee('New Attribute');

        $this->actingAs($user)->get(route('codex-attributes.edit', $attribute))
            ->assertOk()
            ->assertSee('Terrain');
    }

    public function test_owner_can_create_an_attribute_scoped_to_selected_types(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('projects.codex-attributes.store', $project), [
            'name' => 'Hair color',
            'applies_to' => ['character'],
        ]);

        $response->assertRedirect(route('projects.codex-attributes.index', $project));

        $attribute = CodexAttribute::where('name', 'Hair color')->firstOrFail();
        $this->assertSame($project->id, $attribute->project_id);

        // The attribute applies to characters only — not to locations or organizations.
        $this->assertTrue($attribute->appliesTo(CodexEntryType::Character));
        $this->assertFalse($attribute->appliesTo(CodexEntryType::Location));
        $this->assertFalse($attribute->appliesTo(CodexEntryType::Organization));
    }

    public function test_position_is_auto_assigned_per_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.codex-attributes.store', $project), [
            'name' => 'First',
            'applies_to' => ['character'],
        ]);
        $this->actingAs($user)->post(route('projects.codex-attributes.store', $project), [
            'name' => 'Second',
            'applies_to' => ['character'],
        ]);

        $this->assertSame(1, CodexAttribute::where('name', 'First')->value('position'));
        $this->assertSame(2, CodexAttribute::where('name', 'Second')->value('position'));
    }

    public function test_owner_can_update_an_attribute(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $attribute = CodexAttribute::factory()->for($project)
            ->appliesTo(CodexEntryType::Character)
            ->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->put(route('codex-attributes.update', $attribute), [
            'name' => 'New Name',
            'applies_to' => ['location', 'organization'],
        ]);

        $response->assertRedirect(route('projects.codex-attributes.index', $project));

        $attribute->refresh();
        $this->assertSame('New Name', $attribute->name);
        $this->assertFalse($attribute->appliesTo(CodexEntryType::Character));
        $this->assertTrue($attribute->appliesTo(CodexEntryType::Location));
        $this->assertTrue($attribute->appliesTo(CodexEntryType::Organization));
    }

    public function test_store_requires_a_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.codex-attributes.store', $project), [
            'name' => '',
            'applies_to' => ['character'],
        ])->assertSessionHasErrors('name');
    }

    public function test_store_rejects_empty_applies_to(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.codex-attributes.store', $project), [
            'name' => 'Orphan',
        ])->assertSessionHasErrors('applies_to');
    }

    public function test_store_rejects_a_non_enum_applies_to_value(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->post(route('projects.codex-attributes.store', $project), [
            'name' => 'Bad Type',
            'applies_to' => ['dragon'],
        ])->assertSessionHasErrors('applies_to.0');
    }

    public function test_destroy_cascades_attribute_values(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $entry = CodexEntry::factory()->for($project)->character()->create();
        $attribute = CodexAttribute::factory()->for($project)->create();
        $value = CodexAttributeValue::factory()
            ->for($entry, 'entry')
            ->create(['codex_attribute_id' => $attribute->id]);

        $this->actingAs($user)->delete(route('codex-attributes.destroy', $attribute))
            ->assertRedirect(route('projects.codex-attributes.index', $project));

        $this->assertNull($attribute->fresh());
        $this->assertNull(CodexAttributeValue::find($value->id));
    }

    public function test_non_owner_is_forbidden_from_every_action(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $attribute = CodexAttribute::factory()->for($project)->create();

        $this->actingAs($other)->get(route('projects.codex-attributes.index', $project))->assertForbidden();
        $this->actingAs($other)->get(route('projects.codex-attributes.create', $project))->assertForbidden();
        $this->actingAs($other)->post(route('projects.codex-attributes.store', $project), [
            'name' => 'Sneaky',
            'applies_to' => ['character'],
        ])->assertForbidden();
        $this->actingAs($other)->get(route('codex-attributes.edit', $attribute))->assertForbidden();
        $this->actingAs($other)->put(route('codex-attributes.update', $attribute), [
            'name' => 'Hijacked',
            'applies_to' => ['character'],
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('codex-attributes.destroy', $attribute))->assertForbidden();
    }
}
