<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Project;
use App\Models\PublicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Export-ebook config form's write path (task 04):
 * PublicationSettingController@update persisting App\Models\PublicationSetting,
 * and the section_order move-up/move-down actions. The EPUB exporter does not
 * consume these settings yet (tasks 08+), so these tests assert persistence
 * and validation, not epub output.
 */
class PublicationSettingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A full, valid payload for PublicationSettingController@update — every
     * boolean toggle explicitly present (mirrors real checkbox submission),
     * every enum a valid case, and section_order the untouched default order.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'include_project_cover' => '1',
            'include_chapter_covers' => '0',
            'include_scene_titles' => '1',
            'include_act_descriptions' => '0',
            'include_chapter_descriptions' => '0',
            'include_scene_descriptions' => '0',
            'include_dedication' => '1',
            'include_acknowledgements' => '0',
            'include_preface' => '0',
            'include_postface' => '0',
            'include_author' => '1',
            'include_publisher' => '1',
            'include_rights' => '0',
            'include_isbn' => '0',
            'chapter_title_format' => 'number_title',
            'table_of_contents_depth' => 'acts',
            'divider_type' => 'decorative',
            'section_order' => PublicationSetting::SECTION_KEYS,
            'include_codex_appendix' => '1',
            'appendix_entry_types' => [CodexEntryType::Character->value, CodexEntryType::Location->value],
            'appendix_include_images' => '1',
        ], $overrides);
    }

    // -------------------------------------------------------------------
    // Persistence: create then update the same singleton row
    // -------------------------------------------------------------------

    public function test_owner_save_creates_the_publication_setting_row(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->assertSame(0, PublicationSetting::count());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $project), $this->validPayload())
            ->assertRedirect(route('admin.data.export-ebook', ['project' => $project->id]));

        $this->assertSame(1, PublicationSetting::count());

        $setting = $project->fresh()->publicationSetting;
        $this->assertNotNull($setting);
        $this->assertTrue($setting->include_scene_titles);
        $this->assertFalse($setting->include_act_descriptions);
        $this->assertSame('number_title', $setting->chapter_title_format->value);
        $this->assertSame('acts', $setting->table_of_contents_depth->value);
        $this->assertSame('decorative', $setting->divider_type->value);
        $this->assertTrue($setting->include_codex_appendix);
        $this->assertSame(
            [CodexEntryType::Character->value, CodexEntryType::Location->value],
            $setting->appendix_entry_types
        );
    }

    public function test_second_save_updates_the_same_row_no_duplicate(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $project), $this->validPayload());
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $project), $this->validPayload([
            'include_scene_titles' => '0',
        ]));

        $this->assertSame(1, PublicationSetting::count());
        $this->assertFalse($project->fresh()->publicationSetting->include_scene_titles);
    }

    public function test_saved_values_reload_into_the_config_form(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $project), $this->validPayload());

        $response = $this->actingAs($user)->get(route('admin.data.export-ebook', ['project' => $project->id]));

        $response->assertOk();
        $response->assertSee('selected', false);
    }

    // -------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------

    public function test_non_owner_update_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->patch(route('admin.data.publication-settings.update', $project), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, PublicationSetting::count());
    }

    public function test_guest_update_is_redirected_to_login(): void
    {
        $project = Project::factory()->create();

        $this->patch(route('admin.data.publication-settings.update', $project), $this->validPayload())
            ->assertRedirect(route('login'));
    }

    public function test_non_owner_cannot_move_a_section(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->patch(route('admin.data.publication-settings.section-order.move-down', ['project' => $project, 'section' => 'dedication']))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------

    public function test_invalid_divider_type_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $project), $this->validPayload([
                'divider_type' => 'not-a-real-divider',
            ]))
            ->assertSessionHasErrors('divider_type');
    }

    public function test_section_order_with_a_duplicate_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $order = PublicationSetting::SECTION_KEYS;
        $order[count($order) - 1] = $order[0]; // duplicate 'title', dropping 'appendix'

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $project), $this->validPayload([
                'section_order' => $order,
            ]))
            ->assertSessionHasErrors('section_order');
    }

    public function test_section_order_with_an_unknown_key_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $order = PublicationSetting::SECTION_KEYS;
        $order[] = 'not-a-real-section';

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $project), $this->validPayload([
                'section_order' => $order,
            ]))
            ->assertSessionHasErrors('section_order');
    }

    public function test_section_order_with_title_not_first_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $order = PublicationSetting::SECTION_KEYS;
        [$order[0], $order[1]] = [$order[1], $order[0]]; // 'title' no longer first

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $project), $this->validPayload([
                'section_order' => $order,
            ]))
            ->assertSessionHasErrors('section_order');
    }

    public function test_unknown_appendix_entry_type_fails_validation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $project), $this->validPayload([
                'appendix_entry_types' => ['not-a-real-type'],
            ]))
            ->assertSessionHasErrors('appendix_entry_types.0');
    }

    // -------------------------------------------------------------------
    // Section reordering (title pinned first; move-up/move-down actions)
    // -------------------------------------------------------------------

    public function test_move_section_down_swaps_it_with_its_neighbour(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $project), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-down', ['project' => $project, 'section' => 'dedication']))
            ->assertRedirect(route('admin.data.export-ebook', ['project' => $project->id]));

        $order = $project->fresh()->publicationSetting->section_order;
        $this->assertSame('title', $order[0]);
        $this->assertSame('acknowledgements', $order[1]);
        $this->assertSame('dedication', $order[2]);
    }

    public function test_title_cannot_be_moved_out_of_first_position(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $project), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-down', ['project' => $project, 'section' => 'title']));

        $order = $project->fresh()->publicationSetting->section_order;
        $this->assertSame('title', $order[0]);
    }

    public function test_the_first_movable_section_cannot_move_above_title(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $project), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-up', ['project' => $project, 'section' => 'dedication']));

        $order = $project->fresh()->publicationSetting->section_order;
        $this->assertSame('title', $order[0]);
        $this->assertSame('dedication', $order[1]);
    }

    public function test_move_section_up_swaps_it_with_its_neighbour(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $project), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-up', ['project' => $project, 'section' => 'acknowledgements']));

        $order = $project->fresh()->publicationSetting->section_order;
        $this->assertSame('acknowledgements', $order[1]);
        $this->assertSame('dedication', $order[2]);
    }

    public function test_moving_an_unknown_section_is_not_found(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-up', ['project' => $project, 'section' => 'not-a-real-section']))
            ->assertNotFound();
    }
}
