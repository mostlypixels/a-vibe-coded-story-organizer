<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The grouped result set of a project search, shaped for the results view.
 *
 * The view renders three sections, each a stack of full-width result tables
 * (one per entity type, like the entity list pages):
 *   - Timeline: Plotlines, Events
 *   - Story:    Acts, Chapters, Scenes
 *   - Codex:    Characters, Locations, Organizations (one table per CodexEntryType)
 *
 * Tables with no matches are hidden, and a section whose tables are all empty
 * is skipped entirely — the has*Matches() helpers below drive that so the view
 * doesn't have to know which tables belong to which section.
 *
 * Each property is a `Collection<int, SearchResultRow>` already in the entity's
 * natural order (see ProjectSearch). Named properties per entity type keep the
 * view dead simple and make the "one table per codex type" decision explicit
 * rather than hiding it behind a generic keyed bag.
 */
class SearchResults
{
    /**
     * @param  Collection<int, SearchResultRow>  $plotlines
     * @param  Collection<int, SearchResultRow>  $events
     * @param  Collection<int, SearchResultRow>  $acts
     * @param  Collection<int, SearchResultRow>  $chapters
     * @param  Collection<int, SearchResultRow>  $scenes
     * @param  Collection<int, SearchResultRow>  $characters
     * @param  Collection<int, SearchResultRow>  $locations
     * @param  Collection<int, SearchResultRow>  $organizations
     */
    public function __construct(
        public readonly Collection $plotlines,
        public readonly Collection $events,
        public readonly Collection $acts,
        public readonly Collection $chapters,
        public readonly Collection $scenes,
        public readonly Collection $characters,
        public readonly Collection $locations,
        public readonly Collection $organizations,
    ) {}

    /**
     * Every entity type's rows flattened into one collection — handy for totals
     * and the "no results" empty state.
     *
     * @return Collection<int, SearchResultRow>
     */
    public function all(): Collection
    {
        return collect([
            $this->plotlines,
            $this->events,
            $this->acts,
            $this->chapters,
            $this->scenes,
            $this->characters,
            $this->locations,
            $this->organizations,
        ])->flatten();
    }

    /**
     * Total number of matched entities (one row each) across all entity types.
     */
    public function count(): int
    {
        return $this->all()->count();
    }

    /**
     * True when the search matched nothing anywhere — drives the empty state.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * True when anything in the Timeline section (Plotlines, Events) matched —
     * the view skips the whole section otherwise.
     */
    public function hasTimelineMatches(): bool
    {
        return $this->plotlines->isNotEmpty() || $this->events->isNotEmpty();
    }

    /**
     * True when anything in the Story section (Acts, Chapters, Scenes) matched.
     */
    public function hasStoryMatches(): bool
    {
        return $this->acts->isNotEmpty()
            || $this->chapters->isNotEmpty()
            || $this->scenes->isNotEmpty();
    }

    /**
     * True when anything in the Codex section (one table per entry type) matched.
     */
    public function hasCodexMatches(): bool
    {
        return $this->characters->isNotEmpty()
            || $this->locations->isNotEmpty()
            || $this->organizations->isNotEmpty();
    }
}
