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
use App\Support\AccentFolder;
use App\Support\RichText;
use App\Support\RichTextFields;
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
     * Fetch a project-scoped entity's rows and keep those that match the query
     * under the given mode.
     *
     * The rows are matched in PHP (see the class docblock for why not SQL): one
     * query fetches every project row for the entity, then {@see entityMatches}
     * decides membership. The base query is already scoped to the project, so
     * fetching "all" rows can never cross the project boundary.
     *
     * @param  Builder  $query  the project-scoped base query for one entity
     * @param  array<int, string>  $terms
     * @param  array<string, string>  $fields  db_column => label
     * @return EloquentCollection<int, Model>
     */
    private function matches(Builder $query, array $terms, SearchMode $mode, array $fields): EloquentCollection
    {
        $columns = array_keys($fields);

        return $query->get()
            ->filter(fn (Model $entity) => $this->entityMatches($entity, $columns, $terms, $mode))
            ->values();
    }

    /**
     * Whether one entity satisfies the query under the given mode, matching on the
     * accent-folded plain text of its searchable fields.
     *
     *   - AllTerms (AND): every term must appear in *some* field (not necessarily
     *     the same one). An empty term list never matches.
     *   - AnyTerm / ExactPhrase (OR / single literal): any term in any field.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $terms
     */
    private function entityMatches(Model $entity, array $columns, array $terms, SearchMode $mode): bool
    {
        $foldedValues = array_map(
            static fn (string $value) => AccentFolder::fold($value),
            $this->plainFieldValues($entity, $columns),
        );

        $usableTermCount = 0;
        $matchedTermCount = 0;

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            $usableTermCount++;
            $foldedTerm = AccentFolder::fold($term);

            foreach ($foldedValues as $value) {
                if ($value !== '' && str_contains($value, $foldedTerm)) {
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
            && $usableTermCount > 0
            && $matchedTermCount === $usableTermCount;
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
     * Collapse each matched entity into ONE result row that lists every field
     * the terms appeared in. The text preview (snippet) is built from the first
     * matching field, in the declared field order — the row's matched-fields
     * list tells the reader where else the terms appeared.
     *
     * The field-match check runs in PHP against the already-fetched, plain-text
     * field value (see {@see fieldContainsAnyTerm}). Empty fields are skipped.
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
            $values = $this->plainFieldValues($entity, array_keys($fields));
            $matchedLabels = [];
            $snippet = null;

            foreach ($fields as $column => $label) {
                $value = $values[$column];

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
     * Whether a field's plain-text value contains at least one of the terms
     * (case- and accent-insensitive). This is what makes a field a "matching
     * field" listed in its entity's result row; it folds accents exactly like
     * {@see entityMatches} so a gated-in entity always yields at least one label.
     *
     * @param  array<int, string>  $terms
     */
    private function fieldContainsAnyTerm(string $value, array $terms): bool
    {
        // Fold once; AccentFolder::fold already lowercases, so a plain
        // str_contains is the correct case- and accent-insensitive check.
        $foldedValue = AccentFolder::fold($value);

        foreach ($terms as $term) {
            if ($term !== '' && str_contains($foldedValue, AccentFolder::fold($term))) {
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
