<?php

namespace Tests\Feature;

use App\Enums\CodexEntryType;
use App\Models\Book;
use App\Models\PublicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Services\EpubExporterTest;

/**
 * The Export-ebook config form's write path: PublicationSettingController@update
 * and the section_order move-up/move-down actions. These tests assert
 * persistence and validation only. EpubExportTest covers the export endpoint,
 * and {@see EpubExporterTest} the `section_order` output.
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
            'include_book_cover' => '1',
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
        [, $book] = $this->projectWithBook($user);

        $this->assertSame(0, PublicationSetting::count());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $book), $this->validPayload())
            ->assertRedirect(route('admin.data.export-ebook', ['book' => $book->id]));

        $this->assertSame(1, PublicationSetting::count());

        $setting = $book->fresh()->publicationSetting;
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
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $book), $this->validPayload());
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $book), $this->validPayload([
            'include_scene_titles' => '0',
        ]));

        $this->assertSame(1, PublicationSetting::count());
        $this->assertFalse($book->fresh()->publicationSetting->include_scene_titles);
    }

    public function test_saved_values_reload_into_the_config_form(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $book), $this->validPayload());

        $response = $this->actingAs($user)->get(route('admin.data.export-ebook', ['book' => $book->id]));

        $response->assertOk();
        $response->assertSee('selected', false);
    }

    public function test_two_books_in_one_project_hold_independent_configs(): void
    {
        $user = User::factory()->create();
        [$project, $firstBook] = $this->projectWithBook($user);
        $secondBook = Book::factory()->for($project)->create(['name' => 'Book Two']);

        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $firstBook), $this->validPayload([
            'include_scene_titles' => '1',
        ]));
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $secondBook), $this->validPayload([
            'include_scene_titles' => '0',
        ]));

        $this->assertSame(2, PublicationSetting::count());
        $this->assertTrue($firstBook->fresh()->publicationSetting->include_scene_titles);
        $this->assertFalse($secondBook->fresh()->publicationSetting->include_scene_titles);
    }

    // -------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------

    public function test_non_owner_update_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);

        $this->actingAs($other)
            ->patch(route('admin.data.publication-settings.update', $book), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, PublicationSetting::count());
    }

    public function test_guest_update_is_redirected_to_login(): void
    {
        [, $book] = $this->projectWithBook();

        $this->patch(route('admin.data.publication-settings.update', $book), $this->validPayload())
            ->assertRedirect(route('login'));
    }

    public function test_non_owner_cannot_move_a_section(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);

        $this->actingAs($other)
            ->patch(route('admin.data.publication-settings.section-order.move-down', ['book' => $book, 'section' => 'dedication']))
            ->assertForbidden();
    }

    public function test_non_owner_cannot_move_a_section_up(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $book] = $this->projectWithBook($owner);

        $this->actingAs($other)
            ->patch(route('admin.data.publication-settings.section-order.move-up', ['book' => $book, 'section' => 'dedication']))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------

    public function test_invalid_divider_type_fails_validation(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $book), $this->validPayload([
                'divider_type' => 'not-a-real-divider',
            ]))
            ->assertSessionHasErrors('divider_type');
    }

    public function test_section_order_with_a_duplicate_fails_validation(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $order = PublicationSetting::SECTION_KEYS;
        $order[count($order) - 1] = $order[0]; // duplicate 'title', dropping 'appendix'

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $book), $this->validPayload([
                'section_order' => $order,
            ]))
            ->assertSessionHasErrors('section_order');
    }

    public function test_section_order_with_an_unknown_key_fails_validation(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $order = PublicationSetting::SECTION_KEYS;
        $order[] = 'not-a-real-section';

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $book), $this->validPayload([
                'section_order' => $order,
            ]))
            ->assertSessionHasErrors('section_order');
    }

    public function test_section_order_with_title_not_first_fails_validation(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $order = PublicationSetting::SECTION_KEYS;
        [$order[0], $order[1]] = [$order[1], $order[0]]; // 'title' no longer first

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $book), $this->validPayload([
                'section_order' => $order,
            ]))
            ->assertSessionHasErrors('section_order');
    }

    public function test_unknown_appendix_entry_type_fails_validation(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.update', $book), $this->validPayload([
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
        [, $book] = $this->projectWithBook($user);
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $book), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-down', ['book' => $book, 'section' => 'dedication']))
            ->assertRedirect(route('admin.data.export-ebook', ['book' => $book->id]));

        $order = $book->fresh()->publicationSetting->section_order;
        $this->assertSame('title', $order[0]);
        $this->assertSame('acknowledgements', $order[1]);
        $this->assertSame('dedication', $order[2]);
    }

    public function test_title_cannot_be_moved_out_of_first_position(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $book), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-down', ['book' => $book, 'section' => 'title']));

        $order = $book->fresh()->publicationSetting->section_order;
        $this->assertSame('title', $order[0]);
    }

    public function test_the_first_movable_section_cannot_move_above_title(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $book), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-up', ['book' => $book, 'section' => 'dedication']));

        $order = $book->fresh()->publicationSetting->section_order;
        $this->assertSame('title', $order[0]);
        $this->assertSame('dedication', $order[1]);
    }

    public function test_move_section_up_swaps_it_with_its_neighbour(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $this->actingAs($user)->patch(route('admin.data.publication-settings.update', $book), $this->validPayload());

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-up', ['book' => $book, 'section' => 'acknowledgements']));

        $order = $book->fresh()->publicationSetting->section_order;
        $this->assertSame('acknowledgements', $order[1]);
        $this->assertSame('dedication', $order[2]);
    }

    public function test_moving_an_unknown_section_is_not_found(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->patch(route('admin.data.publication-settings.section-order.move-up', ['book' => $book, 'section' => 'not-a-real-section']))
            ->assertNotFound();
    }
}
