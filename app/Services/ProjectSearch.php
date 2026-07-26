<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Enums\SearchMode;
use App\Models\Act;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Support\AccentFolder;
use App\Support\RichText;
use App\Support\RichTextFields;
use App\Support\SearchResultRow;
use App\Support\SearchResults;
use App\Support\SearchSnippet;
use Illuminate\Database\Eloquent\Builder;
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
 *   - Matching runs in PHP, not SQL. Each entity's project-scoped rows are fetched
 *     with one query, then matched against the folded terms here. Accent folding
 *     (see {@see AccentFolder}) must be byte-for-byte identical to the label /
 *     snippet logic and portable across every driver, and a folding SQL expression
 *     is neither — a per-column nested-REPLACE chain overflows SQLite's parser on
 *     some builds. At this app's per-project scale a full scan is already the cost
 *     of a leading-wildcard LIKE, so materialising the rows is no more expensive.
 *   - Matching is case- AND accent-insensitive: `Melusine` matches `Mélusine`.
 *   - AND mode is cross-field per entity: each term must appear in *some* field,
 *     not necessarily the same one.
 *   - User-supplied `%`/`_` need no escaping — matching is a literal `str_contains`
 *     on folded text, so they never act as wildcards.
 *   - Search runs against stored values (rich-HTML fields stripped to plain text
 *     first) — Scene.contents (Markdown source) and Scene.notes, never rendered output.
 *   - Cross-project isolation: every query is scoped to $project, directly by
 *     project_id or via the act / chapter.act parent chain.
 */
class ProjectSearch
{
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

        // One query per entity type. The three codex columns are sliced out of a
        // single CodexEntry search below rather than searched three times.
        $codexRows = $this->searchEntity(
            CodexEntry::query()->where('project_id', $project->id)->orderBy('name'),
            self::CODEX_ENTRY_FIELDS,
            $terms,
            $mode,
        );

        return new SearchResults(
            plotlines: $this->searchEntity(
                Plotline::query()->where('project_id', $project->id)->orderBy('name'),
                self::PLOTLINE_FIELDS,
                $terms,
                $mode,
            ),
            events: $this->searchEntity(
                Event::query()->where('project_id', $project->id)
                    ->orderBy('event_datetime')->orderBy('id'),
                self::EVENT_FIELDS,
                $terms,
                $mode,
            ),
            acts: $this->searchEntity(
                Act::query()->where('project_id', $project->id)
                    ->orderBy('position')->orderBy('id'),
                self::ACT_FIELDS,
                $terms,
                $mode,
            ),
            chapters: $this->searchEntity(
                $project->chapterQuery()->orderBy('position')->orderBy('id'),
                self::CHAPTER_FIELDS,
                $terms,
                $mode,
            ),
            scenes: $this->searchEntity(
                $project->sceneQuery()->orderBy('position')->orderBy('id'),
                self::SCENE_FIELDS,
                $terms,
                $mode,
            ),
            characters: $this->codexRowsOfType($codexRows, CodexEntryType::Character),
            locations: $this->codexRowsOfType($codexRows, CodexEntryType::Location),
            organizations: $this->codexRowsOfType($codexRows, CodexEntryType::Organization),
        );
    }

    /**
     * One entity type's search: fetch its project-scoped rows, keep the ones that
     * match, and turn each into a result row.
     *
     * **Each entity's text is stripped and folded exactly once**, here, and the two
     * derived maps are then passed down. Membership ("is this entity in the result
     * set?") and labelling ("which of its fields matched?") are still separate
     * questions answered by separate methods, but they used to be separate *passes*:
     * the gate folded every field, then the row builder stripped them all again and
     * folded each one a third time. For a scene that is up to a megabyte through
     * `RichText::toPlainText()` and `AccentFolder::fold()` twice over, per matching
     * scene, per search. Matching in PHP is the deliberate design decision (see the
     * class docblock); doing it repeatedly never was.
     *
     * Both maps are needed and neither is derivable from the other cheaply: matching
     * compares folded text, while the snippet must be built from the *unfolded*
     * plain text, or the preview would show the reader accent-stripped, lowercased
     * prose instead of what they wrote.
     *
     * @param  Builder  $query  the project-scoped base query for one entity
     * @param  array<string, string>  $fields  db_column => label
     * @param  array<int, string>  $terms
     * @return Collection<int, SearchResultRow>
     */
    private function searchEntity(Builder $query, array $fields, array $terms, SearchMode $mode): Collection
    {
        $columns = array_keys($fields);

        // Folded once per search, not once per entity per field.
        $foldedTerms = $this->foldedTerms($terms);

        $rows = collect();

        // Fetching every project row for the entity is the design decision above;
        // the base query is already project-scoped, so this can never cross the
        // project boundary.
        foreach ($query->get() as $entity) {
            $plainValues = $this->plainFieldValues($entity, $columns);
            $foldedValues = array_map(AccentFolder::fold(...), $plainValues);

            if (! $this->entityMatches($foldedValues, $foldedTerms, $mode)) {
                continue;
            }

            $row = $this->rowFor($entity, $fields, $plainValues, $foldedValues, $terms, $foldedTerms);

            if ($row !== null) {
                $rows->push($row);
            }
        }

        return $rows;
    }

    /**
     * The non-empty search terms, accent-folded ready for comparison.
     *
     * @param  array<int, string>  $terms
     * @return array<int, string>
     */
    private function foldedTerms(array $terms): array
    {
        $folded = [];

        foreach ($terms as $term) {
            if ($term !== '') {
                $folded[] = AccentFolder::fold($term);
            }
        }

        return $folded;
    }

    /**
     * Split the query into the terms the entity gate and the per-field match check
     * both use. Exact-phrase mode is a single, unsplit literal; AND/OR split on
     * whitespace.
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
     * Whether one entity satisfies the query under the given mode, matching on its
     * already-folded field values.
     *
     *   - AllTerms (AND): every term must appear in *some* field (not necessarily
     *     the same one). An empty term list never matches.
     *   - AnyTerm / ExactPhrase (OR / single literal): any term in any field.
     *
     * @param  array<string, string>  $foldedValues  db_column => folded plain text
     * @param  array<int, string>  $foldedTerms
     */
    private function entityMatches(array $foldedValues, array $foldedTerms, SearchMode $mode): bool
    {
        $matchedTermCount = 0;

        foreach ($foldedTerms as $term) {
            foreach ($foldedValues as $value) {
                if ($value !== '' && str_contains($value, $term)) {
                    // OR / exact-phrase: a single hit is enough.
                    if ($mode !== SearchMode::AllTerms) {
                        return true;
                    }

                    $matchedTermCount++;
                    break; // this term is satisfied; move on to the next term
                }
            }
        }

        // AllTerms: every usable term must have matched. OR / exact only reach here
        // when no term matched at all.
        return $mode === SearchMode::AllTerms
            && $foldedTerms !== []
            && $matchedTermCount === count($foldedTerms);
    }

    /**
     * Extract each searchable field's plain-text value, used both for matching and
     * for the preview. Rich-HTML fields (e.g. Scene.notes) are stripped to the
     * reader's text so a term is matched against what the reader sees, never the
     * raw tags — matching and the snippet therefore always agree.
     *
     * @param  array<int, string>  $columns
     * @return array<string, string> db_column => plain text
     */
    private function plainFieldValues(Model $entity, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            $value = (string) ($entity->getAttribute($column) ?? '');

            if ($value !== '' && RichTextFields::isRich($entity::class, $column)) {
                $value = RichText::toPlainText($value);
            }

            $values[$column] = $value;
        }

        return $values;
    }

    /**
     * Collapse one matched entity into ONE result row listing every field the
     * terms appeared in. The text preview (snippet) is built from the first
     * matching field, in the declared field order — the row's matched-fields
     * list tells the reader where else the terms appeared.
     *
     * Returns null when no field matched. In practice a gated-in entity always
     * yields at least one label, because {@see entityMatches} compares the same
     * folded values against the same folded terms — the null is the honest
     * type, not a case anyone should rely on.
     *
     * @param  array<string, string>  $fields  db_column => label
     * @param  array<string, string>  $plainValues  db_column => plain text
     * @param  array<string, string>  $foldedValues  db_column => folded plain text
     * @param  array<int, string>  $terms  raw terms, for highlighting
     * @param  array<int, string>  $foldedTerms
     */
    private function rowFor(
        Model $entity,
        array $fields,
        array $plainValues,
        array $foldedValues,
        array $terms,
        array $foldedTerms,
    ): ?SearchResultRow {
        $matchedLabels = [];
        $snippet = null;

        foreach ($fields as $column => $label) {
            if ($plainValues[$column] === '' || ! $this->containsAnyTerm($foldedValues[$column], $foldedTerms)) {
                continue;
            }

            $matchedLabels[] = $label;
            // First matching field wins the preview slot. Highlighting runs on the
            // *unfolded* text: the reader must see their own accents and casing.
            $snippet ??= SearchSnippet::highlight($plainValues[$column], $terms);
        }

        if ($matchedLabels === []) {
            return null;
        }

        return new SearchResultRow(
            entity: $entity,
            fieldLabels: $matchedLabels,
            snippet: $snippet,
        );
    }

    /**
     * Whether a folded field value contains at least one folded term. This is what
     * makes a field a "matching field" listed in its entity's result row.
     *
     * `AccentFolder::fold` already lowercases, so a plain `str_contains` on two
     * folded strings is the correct case- and accent-insensitive check.
     *
     * @param  array<int, string>  $foldedTerms
     */
    private function containsAnyTerm(string $foldedValue, array $foldedTerms): bool
    {
        foreach ($foldedTerms as $term) {
            if (str_contains($foldedValue, $term)) {
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
