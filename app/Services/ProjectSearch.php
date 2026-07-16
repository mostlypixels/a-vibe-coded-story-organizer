<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Enums\SearchMode;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Support\SearchResultRow;
use App\Support\SearchResults;
use App\Support\SearchSnippet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Full-text-ish search across a single project's six searchable entities.
 *
 * This is the one place the search queries are built. It runs exactly one query
 * per entity type (Act, Chapter, Scene, Event, Plotline, CodexEntry) — six
 * queries total, regardless of how many rows match — then, in PHP, turns each
 * matched entity into one {@see SearchResultRow} listing every matching field
 * (a Scene that matches in both `contents` and `notes` yields one row whose
 * matched fields are "Contents, Notes").
 *
 * Design decisions (all binding, see .specs/.../advanced_search/plan/00-overview.md):
 *   - Portable `LIKE '%term%'` — no driver-specific FULLTEXT/FTS, so it works on
 *     all four supported drivers (sqlite/mysql/pgsql/sqlsrv).
 *   - AND mode is cross-field per entity: each term must appear in *some* field,
 *     not necessarily the same one.
 *   - User-supplied `%`/`_` are escaped so they match literally, never as SQL
 *     wildcards (see {@see escapeLikeWildcards}).
 *   - Search runs against RAW stored values — Scene.contents (Markdown source)
 *     and Scene.notes (rich-HTML source), never the rendered output.
 *   - Cross-project isolation: every query is scoped to $project, directly by
 *     project_id or via the act / chapter.act parent chain.
 */
class ProjectSearch
{
    /**
     * The escape character used in every generated `LIKE ... ESCAPE` clause.
     *
     * A backslash is chosen per the plan's binding decision. The bound pattern
     * has its `%`, `_` and `\` prefixed with this character (see
     * {@see escapeLikeWildcards}) so a literal percent/underscore typed by the
     * user matches literally instead of acting as a wildcard.
     */
    private const ESCAPE_CHARACTER = '\\';

    /**
     * Searchable fields per entity, as `db_column => Human Label`. The label is
     * what the results view lists in the "Matched in" column, and the order here
     * is the order labels appear in — and which field the text preview is built
     * from (the first one that matched).
     */
    private const ACT_FIELDS = ['name' => 'Name', 'description' => 'Description'];

    private const CHAPTER_FIELDS = ['name' => 'Name', 'description' => 'Description'];

    private const SCENE_FIELDS = [
        'name' => 'Name',
        'description' => 'Description',
        'contents' => 'Contents',
        'notes' => 'Notes',
    ];

    private const EVENT_FIELDS = ['title' => 'Title', 'description' => 'Description'];

    private const PLOTLINE_FIELDS = ['name' => 'Name', 'description' => 'Description'];

    private const CODEX_ENTRY_FIELDS = ['name' => 'Name', 'description' => 'Description'];

    /**
     * Run the search and return the grouped result set.
     *
     * @param  Project  $project  the already-authorized project to search within
     * @param  string  $query  the raw query string from the search box
     * @param  SearchMode  $mode  how the terms combine (AND / OR / exact phrase)
     */
    public function search(Project $project, string $query, SearchMode $mode): SearchResults
    {
        $terms = $this->terms($query, $mode);

        // Nothing usable to search for: return an empty (but well-formed) result set.
        if ($terms === []) {
            return $this->emptyResults();
        }

        $codexRows = $this->rowsFor(
            $this->matches(
                CodexEntry::query()->where('project_id', $project->id)->orderBy('name'),
                $terms,
                $mode,
                self::CODEX_ENTRY_FIELDS,
            ),
            self::CODEX_ENTRY_FIELDS,
            $terms,
        );

        return new SearchResults(
            plotlines: $this->rowsFor(
                $this->matches(
                    Plotline::query()->where('project_id', $project->id)->orderBy('name'),
                    $terms,
                    $mode,
                    self::PLOTLINE_FIELDS,
                ),
                self::PLOTLINE_FIELDS,
                $terms,
            ),
            events: $this->rowsFor(
                $this->matches(
                    Event::query()->where('project_id', $project->id)
                        ->orderBy('event_datetime')->orderBy('id'),
                    $terms,
                    $mode,
                    self::EVENT_FIELDS,
                ),
                self::EVENT_FIELDS,
                $terms,
            ),
            acts: $this->rowsFor(
                $this->matches(
                    Act::query()->where('project_id', $project->id)
                        ->orderBy('position')->orderBy('id'),
                    $terms,
                    $mode,
                    self::ACT_FIELDS,
                ),
                self::ACT_FIELDS,
                $terms,
            ),
            chapters: $this->rowsFor(
                $this->matches(
                    Chapter::query()
                        ->whereHas('act', fn (Builder $q) => $q->where('project_id', $project->id))
                        ->orderBy('position')->orderBy('id'),
                    $terms,
                    $mode,
                    self::CHAPTER_FIELDS,
                ),
                self::CHAPTER_FIELDS,
                $terms,
            ),
            scenes: $this->rowsFor(
                $this->matches(
                    Scene::query()
                        ->whereHas('chapter.act', fn (Builder $q) => $q->where('project_id', $project->id))
                        ->orderBy('position')->orderBy('id'),
                    $terms,
                    $mode,
                    self::SCENE_FIELDS,
                ),
                self::SCENE_FIELDS,
                $terms,
            ),
            characters: $this->codexRowsOfType($codexRows, CodexEntryType::Character),
            locations: $this->codexRowsOfType($codexRows, CodexEntryType::Location),
            organizations: $this->codexRowsOfType($codexRows, CodexEntryType::Organization),
        );
    }

    /**
     * Split the query into the terms the WHERE clause and the per-field match
     * check both use. Exact-phrase mode is a single, unsplit literal; AND/OR
     * split on whitespace.
     *
     * @return array<int, string>
     */
    private function terms(string $query, SearchMode $mode): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        if ($mode === SearchMode::ExactPhrase) {
            return [$query];
        }

        return preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Apply the mode-specific WHERE constraints to a project-scoped query and
     * fetch the matching rows.
     *
     * The mode constraints live inside a single wrapping `where(fn …)` group so
     * they can never leak past the project scope (an OR would otherwise break out
     * of the `project_id` filter).
     *
     * @param  Builder  $query  the project-scoped base query for one entity
     * @param  array<int, string>  $terms
     * @param  array<string, string>  $fields  db_column => label
     * @return EloquentCollection<int, Model>
     */
    private function matches(Builder $query, array $terms, SearchMode $mode, array $fields): EloquentCollection
    {
        $columns = array_keys($fields);

        return $query
            ->where(function (Builder $group) use ($terms, $mode, $columns) {
                if ($mode === SearchMode::AllTerms) {
                    // AND across terms, OR across fields: one nested group per term,
                    // each satisfied if the term appears in ANY field.
                    foreach ($terms as $term) {
                        $group->where(fn (Builder $termGroup) => $this->orLikeAnyColumn($termGroup, $columns, $term));
                    }

                    return;
                }

                // OR / exact-phrase: any (term × field) pairing satisfies the match.
                foreach ($terms as $term) {
                    $this->orLikeAnyColumn($group, $columns, $term);
                }
            })
            ->get();
    }

    /**
     * OR a `column LIKE %term%` predicate across every given column.
     *
     * @param  array<int, string>  $columns
     */
    private function orLikeAnyColumn(Builder $query, array $columns, string $term): void
    {
        $pattern = '%'.$this->escapeLikeWildcards($term).'%';

        foreach ($columns as $column) {
            // whereRaw is used (not the `like` operator) so we can attach the
            // ESCAPE clause the wildcard-escaping relies on. Column names come
            // from our own constants, never user input, so embedding them raw is
            // safe; the pattern itself is always a bound parameter.
            $query->orWhereRaw(
                $column." like ? escape '".self::ESCAPE_CHARACTER."'",
                [$pattern],
            );
        }
    }

    /**
     * Escape the LIKE wildcards `%` and `_` (and the escape character itself) so
     * a literal percent/underscore in a user's term matches literally instead of
     * behaving as a SQL wildcard. Pairs with the `ESCAPE` clause in
     * {@see orLikeAnyColumn}.
     */
    private function escapeLikeWildcards(string $term): string
    {
        return addcslashes($term, '%_'.self::ESCAPE_CHARACTER);
    }

    /**
     * Collapse each matched entity into ONE result row that lists every field
     * the terms appeared in. The text preview (snippet) is built from the first
     * matching field, in the declared field order — the row's matched-fields
     * list tells the reader where else the terms appeared.
     *
     * The field-match check runs in PHP against the already-fetched row (no extra
     * query) and is deliberately case-insensitive (mb_stripos) to mirror the
     * case-insensitive LIKE. Empty/whitespace fields are skipped.
     *
     * @param  EloquentCollection<int, Model>  $entities
     * @param  array<string, string>  $fields  db_column => label
     * @param  array<int, string>  $terms
     * @return Collection<int, SearchResultRow>
     */
    private function rowsFor(EloquentCollection $entities, array $fields, array $terms): Collection
    {
        $rows = collect();

        foreach ($entities as $entity) {
            $matchedLabels = [];
            $snippet = null;

            foreach ($fields as $column => $label) {
                $value = (string) ($entity->getAttribute($column) ?? '');

                if ($value === '' || ! $this->fieldContainsAnyTerm($value, $terms)) {
                    continue;
                }

                $matchedLabels[] = $label;
                // First matching field wins the preview slot.
                $snippet ??= SearchSnippet::highlight($value, $terms);
            }

            if ($matchedLabels !== []) {
                $rows->push(new SearchResultRow(
                    entity: $entity,
                    fieldLabels: $matchedLabels,
                    snippet: $snippet,
                ));
            }
        }

        return $rows;
    }

    /**
     * Whether a field's raw value contains at least one of the terms
     * (case-insensitive). This is what makes a field a "matching field" listed
     * in its entity's result row.
     *
     * @param  array<int, string>  $terms
     */
    private function fieldContainsAnyTerm(string $value, array $terms): bool
    {
        foreach ($terms as $term) {
            if ($term !== '' && mb_stripos($value, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only the codex result rows whose entity is of the given type — used to
     * split the single CodexEntry query into the three per-type columns.
     *
     * @param  Collection<int, SearchResultRow>  $rows
     * @return Collection<int, SearchResultRow>
     */
    private function codexRowsOfType(Collection $rows, CodexEntryType $type): Collection
    {
        return $rows
            ->filter(fn (SearchResultRow $row) => $row->entity->getAttribute('type') === $type)
            ->values();
    }

    /**
     * A fully-formed, empty result set (every column an empty collection).
     */
    private function emptyResults(): SearchResults
    {
        return new SearchResults(
            plotlines: collect(),
            events: collect(),
            acts: collect(),
            chapters: collect(),
            scenes: collect(),
            characters: collect(),
            locations: collect(),
            organizations: collect(),
        );
    }
}
