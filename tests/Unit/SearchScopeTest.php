<?php

namespace Tests\Unit;

use App\Enums\SearchDomain;
use App\Support\SearchScope;
use PHPUnit\Framework\TestCase;

/**
 * SearchScope runs no query, so this is a real unit test: every chapter id
 * list here is handed in already resolved. See SearchScopeFactoryTest for the
 * lookup that produces one.
 */
class SearchScopeTest extends TestCase
{
    public function test_includes_is_true_for_every_domain_when_domains_is_empty(): void
    {
        $scope = new SearchScope;

        foreach (SearchDomain::cases() as $domain) {
            $this->assertTrue($scope->includes($domain));
        }
    }

    public function test_includes_is_false_for_an_unselected_domain(): void
    {
        $scope = new SearchScope(domains: [SearchDomain::Scenes]);

        $this->assertTrue($scope->includes(SearchDomain::Scenes));
        $this->assertFalse($scope->includes(SearchDomain::Chapters));
    }

    public function test_a_book_filter_hides_domains_that_carry_no_book(): void
    {
        $scope = new SearchScope(bookId: 1);

        $this->assertTrue($scope->includes(SearchDomain::Scenes));
        $this->assertFalse($scope->includes(SearchDomain::Plotlines));
        $this->assertFalse($scope->includes(SearchDomain::Events));
        $this->assertFalse($scope->includes(SearchDomain::Characters));
        $this->assertFalse($scope->includes(SearchDomain::Locations));
        $this->assertFalse($scope->includes(SearchDomain::Organizations));
    }

    public function test_hidden_by_book_is_true_for_plotlines_events_and_the_codex_only_when_a_book_is_set(): void
    {
        $unscoped = new SearchScope;
        $scoped = new SearchScope(bookId: 1);

        foreach ([SearchDomain::Plotlines, SearchDomain::Events, SearchDomain::Characters, SearchDomain::Locations, SearchDomain::Organizations] as $domain) {
            $this->assertFalse($unscoped->hiddenByBook($domain));
            $this->assertTrue($scoped->hiddenByBook($domain));
        }

        foreach ([SearchDomain::Acts, SearchDomain::Chapters, SearchDomain::Scenes] as $domain) {
            $this->assertFalse($scoped->hiddenByBook($domain));
        }
    }

    public function test_an_unchecked_domain_is_not_hidden_by_book(): void
    {
        // Unselected and hidden-by-book are different reasons a domain is
        // absent; only the second prints the explanatory line.
        $scope = new SearchScope(domains: [SearchDomain::Scenes]);

        $this->assertFalse($scope->hiddenByBook(SearchDomain::Plotlines));
        $this->assertFalse($scope->includes(SearchDomain::Plotlines));
    }

    public function test_is_narrowed_is_false_for_the_default_scope(): void
    {
        $this->assertFalse((new SearchScope)->isNarrowed());
    }

    public function test_is_narrowed_is_true_with_a_book_or_a_domain_filter(): void
    {
        $this->assertTrue((new SearchScope(bookId: 1))->isNarrowed());
        $this->assertTrue((new SearchScope(domains: [SearchDomain::Scenes]))->isNarrowed());
    }

    public function test_to_query_omits_empty_keys(): void
    {
        $this->assertSame([], (new SearchScope)->toQuery());
    }

    public function test_to_query_includes_only_the_set_filters(): void
    {
        $scope = new SearchScope(bookId: 3, fromChapterId: 5, toChapterId: 9, domains: [SearchDomain::Scenes, SearchDomain::Chapters]);

        $this->assertSame([
            'book' => 3,
            'from_chapter' => 5,
            'to_chapter' => 9,
            'domains' => ['scenes', 'chapters'],
        ], $scope->toQuery());
    }
}
