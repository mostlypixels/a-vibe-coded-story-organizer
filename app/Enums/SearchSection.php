<?php

namespace App\Enums;

use App\Support\SearchResults;

/**
 * The groups the search page renders its columns under. This is the one
 * definition of the grouping: {@see SearchDomain::section()} reads it to say
 * which section a domain belongs to, and {@see SearchResults} reads it to
 * say whether a section has anything to show.
 */
enum SearchSection: string
{
    case Timeline = 'timeline';
    case Story = 'story';
    case Codex = 'codex';
    case Notes = 'notes';

    /**
     * The section heading text.
     */
    public function label(): string
    {
        return match ($this) {
            self::Timeline => __('Timeline'),
            self::Story => __('Story'),
            self::Codex => __('Codex'),
            self::Notes => __('Notes'),
        };
    }

    /**
     * The domains this section groups, in display order.
     *
     * @return array<int, SearchDomain>
     */
    public function domains(): array
    {
        return match ($this) {
            self::Timeline => [SearchDomain::Plotlines, SearchDomain::Events],
            self::Story => [SearchDomain::Acts, SearchDomain::Chapters, SearchDomain::Scenes],
            self::Codex => [SearchDomain::Characters, SearchDomain::Locations, SearchDomain::Organizations],
            self::Notes => [SearchDomain::Notes],
        };
    }
}
