<?php

namespace App\Enums;

use App\Models\CodexEntry;
use App\Support\SearchResultRow;
use App\Support\SearchResults;
use Illuminate\Support\Collection;

/**
 * The eight search-result columns rendered on the search page (and, per column,
 * a dedicated "see all" page). Characters, Locations, and Organizations all come
 * from the one {@see CodexEntry} search split by type, but they stay
 * separate domains — the split by type is exactly what makes them separate
 * columns.
 *
 * This enum is the single definition of a column's route, display field, and
 * result set, so the search page and the domain page read one source instead
 * of each carrying its own literal pairs.
 */
enum SearchDomain: string
{
    case Plotlines = 'plotlines';
    case Events = 'events';
    case Acts = 'acts';
    case Chapters = 'chapters';
    case Scenes = 'scenes';
    case Characters = 'characters';
    case Locations = 'locations';
    case Organizations = 'organizations';

    /**
     * The column heading text.
     */
    public function label(): string
    {
        return match ($this) {
            self::Plotlines => 'Plotlines',
            self::Events => 'Events',
            self::Acts => 'Acts',
            self::Chapters => 'Chapters',
            self::Scenes => 'Scenes',
            self::Characters => 'Characters',
            self::Locations => 'Locations',
            self::Organizations => 'Organizations',
        };
    }

    /**
     * The named route a row's entity links to for editing.
     */
    public function editRoute(): string
    {
        return match ($this) {
            self::Plotlines => 'plotlines.edit',
            self::Events => 'events.edit',
            self::Acts => 'acts.edit',
            self::Chapters => 'chapters.edit',
            self::Scenes => 'scenes.edit',
            self::Characters, self::Locations, self::Organizations => 'codex.edit',
        };
    }

    /**
     * The entity attribute holding the display name.
     */
    public function nameField(): string
    {
        return $this === self::Events ? 'title' : 'name';
    }

    /**
     * Pull this domain's rows off a result set.
     *
     * @return Collection<int, SearchResultRow>
     */
    public function rowsFrom(SearchResults $results): Collection
    {
        return match ($this) {
            self::Plotlines => $results->plotlines,
            self::Events => $results->events,
            self::Acts => $results->acts,
            self::Chapters => $results->chapters,
            self::Scenes => $results->scenes,
            self::Characters => $results->characters,
            self::Locations => $results->locations,
            self::Organizations => $results->organizations,
        };
    }

    /**
     * Every domain's route key, for the {domain} route constraint.
     *
     * @return array<int, string>
     */
    public static function routeKeys(): array
    {
        return array_column(self::cases(), 'value');
    }
}
