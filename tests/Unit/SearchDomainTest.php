<?php

namespace Tests\Unit;

use App\Enums\SearchDomain;
use App\Support\SearchResults;
use PHPUnit\Framework\TestCase;

class SearchDomainTest extends TestCase
{
    /**
     * One SearchResults whose eight properties each hold a distinct one-element
     * collection, so rowsFrom() can be proven to read the right property.
     */
    private function results(): SearchResults
    {
        return new SearchResults(
            plotlines: collect(['plotlines']),
            events: collect(['events']),
            acts: collect(['acts']),
            chapters: collect(['chapters']),
            scenes: collect(['scenes']),
            characters: collect(['characters']),
            locations: collect(['locations']),
            organizations: collect(['organizations']),
        );
    }

    public function test_rows_from_returns_the_matching_property(): void
    {
        $results = $this->results();

        $this->assertSame(['plotlines'], SearchDomain::Plotlines->rowsFrom($results)->all());
        $this->assertSame(['events'], SearchDomain::Events->rowsFrom($results)->all());
        $this->assertSame(['acts'], SearchDomain::Acts->rowsFrom($results)->all());
        $this->assertSame(['chapters'], SearchDomain::Chapters->rowsFrom($results)->all());
        $this->assertSame(['scenes'], SearchDomain::Scenes->rowsFrom($results)->all());
        $this->assertSame(['characters'], SearchDomain::Characters->rowsFrom($results)->all());
        $this->assertSame(['locations'], SearchDomain::Locations->rowsFrom($results)->all());
        $this->assertSame(['organizations'], SearchDomain::Organizations->rowsFrom($results)->all());
    }

    /**
     * Regression guard: these must equal the literal edit-route strings
     * `search.index` passed to x-search.result-table before the enum refactor.
     */
    public function test_edit_route_matches_the_pre_refactor_literal_per_domain(): void
    {
        $this->assertSame('plotlines.edit', SearchDomain::Plotlines->editRoute());
        $this->assertSame('events.edit', SearchDomain::Events->editRoute());
        $this->assertSame('acts.edit', SearchDomain::Acts->editRoute());
        $this->assertSame('chapters.edit', SearchDomain::Chapters->editRoute());
        $this->assertSame('scenes.edit', SearchDomain::Scenes->editRoute());
        $this->assertSame('codex.edit', SearchDomain::Characters->editRoute());
        $this->assertSame('codex.edit', SearchDomain::Locations->editRoute());
        $this->assertSame('codex.edit', SearchDomain::Organizations->editRoute());
    }

    /**
     * Only the three Codex domains have a read page; every other domain falls
     * through to its edit route.
     */
    public function test_view_route_is_codex_show_for_codex_domains_and_falls_through_elsewhere(): void
    {
        $this->assertSame('codex.show', SearchDomain::Characters->viewRoute());
        $this->assertSame('codex.show', SearchDomain::Locations->viewRoute());
        $this->assertSame('codex.show', SearchDomain::Organizations->viewRoute());

        $this->assertSame('plotlines.edit', SearchDomain::Plotlines->viewRoute());
        $this->assertSame('events.edit', SearchDomain::Events->viewRoute());
        $this->assertSame('acts.edit', SearchDomain::Acts->viewRoute());
        $this->assertSame('chapters.edit', SearchDomain::Chapters->viewRoute());
        $this->assertSame('scenes.edit', SearchDomain::Scenes->viewRoute());
    }

    /**
     * Regression guard: only Events used `name-field="title"` before the
     * refactor — every other domain relied on the "name" default.
     */
    public function test_name_field_matches_the_pre_refactor_literal_per_domain(): void
    {
        $this->assertSame('title', SearchDomain::Events->nameField());

        foreach (SearchDomain::cases() as $domain) {
            if ($domain === SearchDomain::Events) {
                continue;
            }

            $this->assertSame('name', $domain->nameField());
        }
    }

    /**
     * Only Acts, Chapters, and Scenes hang off a book — the codex and timeline
     * domains stay project-wide.
     */
    public function test_carries_book_is_true_for_acts_chapters_and_scenes_only(): void
    {
        $this->assertTrue(SearchDomain::Acts->carriesBook());
        $this->assertTrue(SearchDomain::Chapters->carriesBook());
        $this->assertTrue(SearchDomain::Scenes->carriesBook());

        $this->assertFalse(SearchDomain::Plotlines->carriesBook());
        $this->assertFalse(SearchDomain::Events->carriesBook());
        $this->assertFalse(SearchDomain::Characters->carriesBook());
        $this->assertFalse(SearchDomain::Locations->carriesBook());
        $this->assertFalse(SearchDomain::Organizations->carriesBook());
    }

    public function test_route_keys_returns_every_domains_value(): void
    {
        $this->assertSame(
            ['plotlines', 'events', 'acts', 'chapters', 'scenes', 'characters', 'locations', 'organizations'],
            SearchDomain::routeKeys(),
        );
    }
}
