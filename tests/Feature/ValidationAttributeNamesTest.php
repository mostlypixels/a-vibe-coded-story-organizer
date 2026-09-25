<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Validation messages name the label the writer sees, not the input name. */
class ValidationAttributeNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chapter_without_an_act_names_the_act(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->post(route('books.chapters.store', $book), ['name' => 'One'])
            ->assertSessionHasErrors(['act_id' => 'The act field is required.']);
    }

    public function test_a_scene_without_a_name_names_the_title(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);
        $chapter = Chapter::factory()->for(Act::factory()->for($book))->create();
        $scene = Scene::factory()->for($chapter)->create();

        $this->actingAs($user)
            ->put(route('scenes.update', $scene), ['name' => '', 'chapter_id' => $chapter->id])
            ->assertSessionHasErrors(['name' => 'The title field is required.']);
    }

    public function test_an_event_without_a_date_names_the_date_and_time(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->post(route('projects.events.store', $project), ['title' => 'Ball'])
            ->assertSessionHasErrors(['event_datetime' => 'The date and time field is required.']);
    }

    public function test_a_one_off_challenge_without_an_end_date_names_both_in_plain_words(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);

        $this->actingAs($user)
            ->post(route('projects.challenges.store', $project), [
                'name' => 'Sprint',
                'recurrence' => 'none',
                'starts_on' => '',
                'target_words' => 1000,
            ])
            ->assertSessionHasErrors([
                'starts_on' => 'The start date field is required.',
                'ends_on' => 'The end date field is required when recurrence is one-off.',
            ]);
    }

    public function test_a_list_item_names_the_singular_item(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);
        $entry = CodexEntry::factory()->for($project)->character()->create();

        $this->actingAs($user)
            ->put(route('codex.update', $entry), ['name' => $entry->name, 'aliases' => [str_repeat('a', 256)]])
            ->assertSessionHasErrors(['aliases.0' => 'The alias field must not be greater than 255 characters.']);
    }
}
