<?php

namespace Tests\Feature;

use App\Enums\SearchDomain;
use App\Models\Project;
use App\Models\User;
use App\Support\SearchScope;
use App\Support\SearchScopeSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchScopeSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_domain_reads_as_a_name_not_a_count(): void
    {
        $parts = SearchScopeSummary::parts(
            new SearchScope(domains: [SearchDomain::Scenes]),
            null,
            collect(),
        );

        $this->assertSame(['scenes'], $parts);
    }

    public function test_a_few_domains_are_named_one_by_one(): void
    {
        $parts = SearchScopeSummary::parts(
            new SearchScope(domains: [SearchDomain::Acts, SearchDomain::Chapters, SearchDomain::Scenes]),
            null,
            collect(),
        );

        $this->assertSame(['acts, chapters, scenes'], $parts);
    }

    public function test_many_domains_are_counted_and_the_count_is_plural(): void
    {
        $parts = SearchScopeSummary::parts(
            new SearchScope(domains: [
                SearchDomain::Acts,
                SearchDomain::Chapters,
                SearchDomain::Scenes,
                SearchDomain::Events,
            ]),
            null,
            collect(),
        );

        $this->assertSame(['4 domains'], $parts);
    }

    public function test_a_book_filter_names_the_book(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Melusine']);
        $book = $project->books()->first();

        $parts = SearchScopeSummary::parts(
            new SearchScope(bookId: $book->id),
            $book,
            collect(),
        );

        $this->assertSame([$book->displayName()], $parts);
    }

    public function test_an_unnarrowed_scope_names_nothing(): void
    {
        $this->assertSame([], SearchScopeSummary::parts(new SearchScope, null, collect()));
    }
}
