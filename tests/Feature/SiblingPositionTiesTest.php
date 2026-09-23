<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Move up / move down when siblings share a position or have gaps (#173).
 * `position` has no unique index, so ties can occur.
 */
class SiblingPositionTiesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function reorderableTypes(): array
    {
        return [
            'books' => ['books'],
            'acts' => ['acts'],
            'chapters' => ['chapters'],
            'scenes' => ['scenes'],
        ];
    }

    #[DataProvider('reorderableTypes')]
    public function test_move_down_passes_a_sibling_with_the_same_position(string $type): void
    {
        $user = User::factory()->create();
        [$first, $second] = $this->siblings($type, $user, [1, 1]);

        $this->actingAs($user)->patch(route("{$type}.move-down", $first))->assertRedirect();

        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
    }

    #[DataProvider('reorderableTypes')]
    public function test_move_up_passes_a_sibling_with_the_same_position(string $type): void
    {
        $user = User::factory()->create();
        [$first, $second] = $this->siblings($type, $user, [1, 1]);

        $this->actingAs($user)->patch(route("{$type}.move-up", $second))->assertRedirect();

        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
    }

    #[DataProvider('reorderableTypes')]
    public function test_a_move_renumbers_tied_and_gapped_siblings_to_one_to_n(string $type): void
    {
        $user = User::factory()->create();
        [$a, $b, $c, $d] = $this->siblings($type, $user, [2, 2, 7, 7]);

        $this->actingAs($user)->patch(route("{$type}.move-up", $d))->assertRedirect();

        $this->assertSame(
            [1, 2, 4, 3],
            [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position, $d->fresh()->position],
        );
    }

    #[DataProvider('reorderableTypes')]
    public function test_a_non_owner_cannot_move_a_tied_sibling(string $type): void
    {
        $owner = User::factory()->create();
        [$first, $second] = $this->siblings($type, $owner, [1, 1]);

        $this->actingAs(User::factory()->create())
            ->patch(route("{$type}.move-down", $first))
            ->assertForbidden();

        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }

    public function test_the_scene_move_json_reports_the_new_position_after_a_tie(): void
    {
        $user = User::factory()->create();
        [$first] = $this->siblings('scenes', $user, [1, 1]);

        $this->actingAs($user)
            ->patchJson(route('scenes.move-down', $first))
            ->assertOk()
            ->assertJson(['position' => 2]);
    }

    public function test_the_acts_index_breaks_position_ties_by_id(): void
    {
        $user = User::factory()->create();
        [$first, $second] = $this->siblings('acts', $user, [1, 1]);
        $first->update(['name' => 'Act Alpha']);
        $second->update(['name' => 'Act Beta']);

        $this->actingAs($user)
            ->get(route('books.acts.index', ['book' => $first->book, 'sort' => 'position', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Act Beta', 'Act Alpha']);
    }

    /**
     * Create siblings of one type in one parent, in id order, with the given positions.
     *
     * @param  list<int>  $positions
     * @return list<Model>
     */
    private function siblings(string $type, User $owner, array $positions): array
    {
        $project = Project::factory()->for($owner)->create();
        $book = $project->books()->first();

        $make = match ($type) {
            'books' => fn (int $position) => Book::factory()->for($project)->create(['position' => $position]),
            'acts' => fn (int $position) => Act::factory()->for($book)->create(['position' => $position]),
            'chapters' => (function () use ($book) {
                $act = Act::factory()->for($book)->create();

                return fn (int $position) => Chapter::factory()->for($act)->create(['position' => $position]);
            })(),
            'scenes' => (function () use ($book) {
                $chapter = Chapter::factory()->for(Act::factory()->for($book)->create())->create();

                return fn (int $position) => Scene::factory()->for($chapter)->create(['position' => $position]);
            })(),
        };

        if ($type === 'books') {
            // The project's own first book would join the sibling set.
            $book->delete();
        }

        return array_map($make, $positions);
    }
}
