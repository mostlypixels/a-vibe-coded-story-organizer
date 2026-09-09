<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Support\AccentFolder;
use App\Support\RichText;
use App\Support\RichTextFields;
use App\Support\SearchResultRow;
use App\Support\SearchResults;
use App\Support\SearchScope;
use App\Support\SearchSnippet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Searches stored text across one project's story, timeline, and codex entities.
 *
 * Matching runs in PHP for portable accent folding. It is case-insensitive and
 * accent-insensitive. AND terms can match different fields on the same entity.
 * Rich HTML becomes plain text before matching. Every query remains project-scoped.
 */
class ProjectSearch
{
    /** Searchable columns and labels in preview priority order. */
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
     * @param  SearchScope  $scope  the book, chapter range and domain filters; the
     *                              default scope filters nothing
     */
    public function search(Project $project, string $query, SearchMode $mode, SearchScope $scope = new SearchScope): SearchResults
    {
        $terms = $this->terms($query, $mode);

        if ($terms === []) {
            return $this->emptyResults();
        }

        // Search codex entries once for every included type and split the rows after.
        $books = $this->booksById($project);
        $codexTypes = $this->includedCodexTypes($scope);
        $codexRows = $codexTypes === []
            ? collect()
            : $this->searchCodex($project, $terms, $mode, $codexTypes);

        return new SearchResults(
            plotlines: $this->rowsFor(SearchDomain::Plotlines, $project, $terms, $mode, $books, $scope),
            events: $this->rowsFor(SearchDomain::Events, $project, $terms, $mode, $books, $scope),
            acts: $this->rowsFor(SearchDomain::Acts, $project, $terms, $mode, $books, $scope),
            chapters: $this->rowsFor(SearchDomain::Chapters, $project, $terms, $mode, $books, $scope),
            scenes: $this->rowsFor(SearchDomain::Scenes, $project, $terms, $mode, $books, $scope),
            characters: $this->codexRowsOfType($codexRows, CodexEntryType::Character),
            locations: $this->codexRowsOfType($codexRows, CodexEntryType::Location),
            organizations: $this->codexRowsOfType($codexRows, CodexEntryType::Organization),
        );
    }

    /** @return Collection<int, SearchResultRow> Callers paginate this in memory. */
    public function searchDomain(Project $project, SearchDomain $domain, string $query, SearchMode $mode, SearchScope $scope = new SearchScope): Collection
    {
        $terms = $this->terms($query, $mode);

        if ($terms === []) {
            return collect();
        }

        $codexType = $this->codexType($domain);

        if ($codexType !== null) {
            return $this->searchCodex($project, $terms, $mode, [$codexType]);
        }

        $books = $domain->carriesBook() ? $this->booksById($project) : collect();

        return $this->searchEntityFor($domain, $project, $terms, $mode, $books, $scope);
    }

    /**
     * A domain's rows, or an empty collection when the scope excludes it. An
     * excluded domain must run no query at all — that is the saving.
     *
     * @param  array<int, string>  $terms
     * @param  Collection<int, Book>  $books
     * @return Collection<int, SearchResultRow>
     */
    private function rowsFor(SearchDomain $domain, Project $project, array $terms, SearchMode $mode, Collection $books, SearchScope $scope): Collection
    {
        if (! $scope->includes($domain)) {
            return collect();
        }

        return $this->searchEntityFor($domain, $project, $terms, $mode, $books, $scope);
    }

    /**
     * The codex query for exactly the requested types. One query serves all
     * three codex domains, so the types it must hydrate come in as a list.
     *
     * @param  array<int, string>  $terms
     * @param  array<int, CodexEntryType>  $types
     * @return Collection<int, SearchResultRow>
     */
    private function searchCodex(Project $project, array $terms, SearchMode $mode, array $types): Collection
    {
        $query = CodexEntry::query()
            ->where('project_id', $project->id)
            ->whereIn('type', array_map(fn (CodexEntryType $type) => $type->value, $types))
            ->orderBy('name');

        return $this->searchEntity($query, self::CODEX_ENTRY_FIELDS, $terms, $mode, null);
    }

    /**
     * The codex types the scope asks for, so no type nobody wants is hydrated.
     *
     * @return array<int, CodexEntryType>
     */
    private function includedCodexTypes(SearchScope $scope): array
    {
        $types = [];

        foreach ([SearchDomain::Characters, SearchDomain::Locations, SearchDomain::Organizations] as $domain) {
            if ($scope->includes($domain)) {
                $types[] = $this->codexType($domain);
            }
        }

        return $types;
    }

    /** The codex type a domain reads, or null when the domain is not a codex one. */
    private function codexType(SearchDomain $domain): ?CodexEntryType
    {
        return match ($domain) {
            SearchDomain::Characters => CodexEntryType::Character,
            SearchDomain::Locations => CodexEntryType::Location,
            SearchDomain::Organizations => CodexEntryType::Organization,
            default => null,
        };
    }

    /**
     * Run one domain's base query through {@see searchEntity}. Codex domains
     * do not come through here: one query serves all three (see searchCodex()).
     *
     * @param  array<int, string>  $terms
     * @param  Collection<int, Book>  $books  the project's books, keyed by id (see booksById()) — passed
     *                                        through even for domains that never carry one; only a
     *                                        domain whose {@see SearchDomain::carriesBook()} is true reads it
     * @return Collection<int, SearchResultRow>
     */
    private function searchEntityFor(SearchDomain $domain, Project $project, array $terms, SearchMode $mode, Collection $books, SearchScope $scope): Collection
    {
        [$query, $fields] = $this->queryFor($domain, $project, $scope);

        return $this->searchEntity($query, $fields, $terms, $mode, $domain->carriesBook() ? $books : null);
    }

    /** @return array{0: Builder, 1: array<string, string>} */
    private function queryFor(SearchDomain $domain, Project $project, SearchScope $scope): array
    {
        return match ($domain) {
            SearchDomain::Plotlines => [
                Plotline::query()->where('project_id', $project->id)->orderBy('name'),
                self::PLOTLINE_FIELDS,
            ],
            SearchDomain::Events => [
                Event::query()->where('project_id', $project->id)
                    ->orderBy('event_datetime')->orderBy('id'),
                self::EVENT_FIELDS,
            ],
            SearchDomain::Acts => [
                $this->scopedActQuery($project, $scope),
                self::ACT_FIELDS,
            ],
            SearchDomain::Chapters => [
                $this->scopeBookAndRange(
                    $project->chapterQuery()
                        ->join('acts', 'acts.id', '=', 'chapters.act_id')
                        ->select('chapters.*', 'acts.book_id as book_id')
                        ->orderBy('chapters.position')->orderBy('chapters.id'),
                    $scope,
                ),
                self::CHAPTER_FIELDS,
            ],
            SearchDomain::Scenes => [
                $this->scopeBookAndRange(
                    $project->sceneQuery()
                        ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
                        ->join('acts', 'acts.id', '=', 'chapters.act_id')
                        ->select('scenes.*', 'acts.book_id as book_id')
                        ->orderBy('scenes.position')->orderBy('scenes.id'),
                    $scope,
                ),
                self::SCENE_FIELDS,
            ],
            // The three codex domains share one query, built by searchCodex().
            SearchDomain::Characters, SearchDomain::Locations, SearchDomain::Organizations => throw new InvalidArgumentException(
                'Codex domains build their query in searchCodex(), not queryFor().'
            ),
        };
    }

    /**
     * Acts filter on their own book_id, and on the acts owning the chapters in
     * the range — a subquery, so the range still costs no extra round trip.
     */
    private function scopedActQuery(Project $project, SearchScope $scope): Builder
    {
        $query = Act::query()
            ->whereHas('book', fn (Builder $query) => $query->where('project_id', $project->id))
            ->orderBy('position')->orderBy('id');

        if ($scope->bookId !== null) {
            $query->where('acts.book_id', $scope->bookId);
        }

        if ($scope->chapterIds !== []) {
            $query->whereIn('acts.id', Chapter::query()
                ->whereIn('id', $scope->chapterIds)
                ->select('act_id'));
        }

        return $query;
    }

    /**
     * Narrow a chapter or scene query to the scope's book and chapter range.
     * Both queries already join `acts`, so the book column is in reach.
     */
    private function scopeBookAndRange(Builder $query, SearchScope $scope): Builder
    {
        if ($scope->bookId !== null) {
            $query->where('acts.book_id', $scope->bookId);
        }

        if ($scope->chapterIds !== []) {
            $query->whereIn('chapters.id', $scope->chapterIds);
        }

        return $query;
    }

    /** @return Collection<int, Book> Books prepared for display-name lookup. */
    private function booksById(Project $project): Collection
    {
        return $project->books()->get()
            ->each(fn (Book $book) => $book->setRelation('project', $project))
            ->keyBy('id');
    }

    /**
     * Strips and folds each field once. Plain text remains available for snippets.
     *
     * @param  array<string, string>  $fields
     * @param  array<int, string>  $terms
     * @param  Collection<int, Book>|null  $books
     * @return Collection<int, SearchResultRow>
     */
    private function searchEntity(Builder $query, array $fields, array $terms, SearchMode $mode, ?Collection $books): Collection
    {
        $columns = array_keys($fields);

        $foldedTerms = $this->foldedTerms($terms);

        $rows = collect();

        // The base query already enforces the project boundary.
        foreach ($query->get() as $entity) {
            $plainValues = $this->plainFieldValues($entity, $columns);
            $foldedValues = array_map(AccentFolder::fold(...), $plainValues);

            if (! $this->entityMatches($foldedValues, $foldedTerms, $mode)) {
                continue;
            }

            $book = $books?->get($entity->getAttribute('book_id'));
            $row = $this->rowFor($entity, $fields, $plainValues, $foldedValues, $terms, $foldedTerms, $book);

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

    /** @return array<int, string> Exact phrase stays whole; other modes split words. */
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
     * @param  array<string, string>  $foldedValues
     * @param  array<int, string>  $foldedTerms
     */
    private function entityMatches(array $foldedValues, array $foldedTerms, SearchMode $mode): bool
    {
        $matchedTermCount = 0;

        foreach ($foldedTerms as $term) {
            foreach ($foldedValues as $value) {
                if ($value !== '' && str_contains($value, $term)) {
                    if ($mode !== SearchMode::AllTerms) {
                        return true;
                    }

                    $matchedTermCount++;
                    break; // this term is satisfied; move on to the next term
                }
            }
        }

        return $mode === SearchMode::AllTerms
            && $foldedTerms !== []
            && $matchedTermCount === count($foldedTerms);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, string> Plain text by database column.
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
     * Uses the first matching field for the snippet and lists every matching field.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $plainValues
     * @param  array<string, string>  $foldedValues
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $foldedTerms
     */
    private function rowFor(
        Model $entity,
        array $fields,
        array $plainValues,
        array $foldedValues,
        array $terms,
        array $foldedTerms,
        ?Book $book,
    ): ?SearchResultRow {
        $matchedLabels = [];
        $snippet = null;

        foreach ($fields as $column => $label) {
            if ($plainValues[$column] === '' || ! $this->containsAnyTerm($foldedValues[$column], $foldedTerms)) {
                continue;
            }

            $matchedLabels[] = $label;
            // Highlight original text so accents and case remain visible.
            $snippet ??= SearchSnippet::highlight($plainValues[$column], $terms);
        }

        if ($matchedLabels === []) {
            return null;
        }

        return new SearchResultRow(
            entity: $entity,
            fieldLabels: $matchedLabels,
            snippet: $snippet,
            book: $book,
        );
    }

    /** @param array<int, string> $foldedTerms */
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
     * @param  Collection<int, SearchResultRow>  $rows
     * @return Collection<int, SearchResultRow>
     */
    private function codexRowsOfType(Collection $rows, CodexEntryType $type): Collection
    {
        return $rows
            ->filter(fn (SearchResultRow $row) => $row->entity->getAttribute('type') === $type)
            ->values();
    }

    /** Returns an empty result set with every domain collection present. */
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
