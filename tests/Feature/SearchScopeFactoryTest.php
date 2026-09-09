<?php

namespace Tests\Feature;

use App\Enums\SearchDomain;
use App\Http\Requests\SearchRequest;
use App\Models\Act;
use App\Models\Chapter;
use App\Support\SearchScopeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The chapter-range lookup {@see SearchScopeTest} cannot cover: it needs real
 * chapters, in story order, across an act boundary.
 */
class SearchScopeFactoryTest extends TestCase
{
    use RefreshDatabase;

    private function requestWith(array $query): SearchRequest
    {
        return SearchRequest::create('/', 'GET', $query);
    }

    public function test_a_range_crossing_an_act_boundary_yields_every_chapter_between_in_story_order(): void
    {
        [$project, $book] = $this->projectWithBook();
        $actOne = Act::factory()->for($book)->create();
        $actTwo = Act::factory()->for($book)->create();

        $chapters = [
            Chapter::factory()->for($actOne)->create(),
            Chapter::factory()->for($actOne)->create(),
            Chapter::factory()->for($actTwo)->create(),
            Chapter::factory()->for($actTwo)->create(),
        ];

        $scope = SearchScopeFactory::fromRequest(
            $this->requestWith(['book' => $book->id, 'from_chapter' => $chapters[1]->id, 'to_chapter' => $chapters[2]->id]),
            $project,
        );

        $this->assertSame($book->id, $scope->bookId);
        $this->assertSame([$chapters[1]->id, $chapters[2]->id], $scope->chapterIds);
    }

    public function test_a_reversed_range_is_swapped(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();

        $chapters = [
            Chapter::factory()->for($act)->create(),
            Chapter::factory()->for($act)->create(),
            Chapter::factory()->for($act)->create(),
            Chapter::factory()->for($act)->create(),
        ];

        $scope = SearchScopeFactory::fromRequest(
            // Picked last-to-first: the click order is not information.
            $this->requestWith(['book' => $book->id, 'from_chapter' => $chapters[2]->id, 'to_chapter' => $chapters[0]->id]),
            $project,
        );

        $this->assertSame([$chapters[0]->id, $chapters[1]->id, $chapters[2]->id], $scope->chapterIds);
    }

    public function test_a_range_covering_the_whole_book_leaves_chapter_ids_empty(): void
    {
        [$project, $book] = $this->projectWithBook();
        $act = Act::factory()->for($book)->create();

        $chapters = [
            Chapter::factory()->for($act)->create(),
            Chapter::factory()->for($act)->create(),
        ];

        $scope = SearchScopeFactory::fromRequest(
            $this->requestWith(['book' => $book->id, 'from_chapter' => $chapters[0]->id, 'to_chapter' => $chapters[1]->id]),
            $project,
        );

        $this->assertSame([], $scope->chapterIds);
    }

    public function test_a_book_with_no_range_leaves_chapter_ids_empty(): void
    {
        [$project, $book] = $this->projectWithBook();
        Chapter::factory()->for(Act::factory()->for($book))->create();

        $scope = SearchScopeFactory::fromRequest(
            $this->requestWith(['book' => $book->id]),
            $project,
        );

        $this->assertSame($book->id, $scope->bookId);
        $this->assertSame([], $scope->chapterIds);
    }

    public function test_no_book_leaves_the_scope_unscoped(): void
    {
        [$project] = $this->projectWithBook();

        $scope = SearchScopeFactory::fromRequest($this->requestWith([]), $project);

        $this->assertNull($scope->bookId);
        $this->assertSame([], $scope->chapterIds);
    }

    public function test_domains_are_mapped_to_search_domain_cases(): void
    {
        [$project] = $this->projectWithBook();

        $scope = SearchScopeFactory::fromRequest(
            $this->requestWith(['domains' => ['scenes', 'chapters']]),
            $project,
        );

        $this->assertSame([SearchDomain::Scenes, SearchDomain::Chapters], $scope->domains);
    }

    public function test_a_book_from_another_project_resolves_no_book(): void
    {
        [$project] = $this->projectWithBook();
        [, $otherBook] = $this->projectWithBook();

        $scope = SearchScopeFactory::fromRequest(
            $this->requestWith(['book' => $otherBook->id]),
            $project,
        );

        $this->assertNull($scope->bookId);
    }
}
