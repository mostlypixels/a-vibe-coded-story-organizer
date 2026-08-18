<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Models\Act;
use App\Models\Book;
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
     */
    public function search(Project $project, string $query, SearchMode $mode): SearchResults
    {
        $terms = $this->terms($query, $mode);

        if ($terms === []) {
            return $this->emptyResults();
        }

        // Search codex entries once and split their result rows by type.
        $books = $this->booksById($project);
        $codexRows = $this->searchEntityFor(SearchDomain::Characters, $project, $terms, $mode, $books);

        return new SearchResults(
            plotlines: $this->searchEntityFor(SearchDomain::Plotlines, $project, $terms, $mode, $books),
            events: $this->searchEntityFor(SearchDomain::Events, $project, $terms, $mode, $books),
            acts: $this->searchEntityFor(SearchDomain::Acts, $project, $terms, $mode, $books),
            chapters: $this->searchEntityFor(SearchDomain::Chapters, $project, $terms, $mode, $books),
            scenes: $this->searchEntityFor(SearchDomain::Scenes, $project, $terms, $mode, $books),
            characters: $this->codexRowsOfType($codexRows, CodexEntryType::Character),
            locations: $this->codexRowsOfType($codexRows, CodexEntryType::Location),
            organizations: $this->codexRowsOfType($codexRows, CodexEntryType::Organization),
        );
    }

    /** @return Collection<int, SearchResultRow> Callers paginate this in memory. */
    public function searchDomain(Project $project, SearchDomain $domain, string $query, SearchMode $mode): Collection
    {
        $terms = $this->terms($query, $mode);

        if ($terms === []) {
            return collect();
        }

        $books = $domain->carriesBook() ? $this->booksById($project) : collect();
        $rows = $this->searchEntityFor($domain, $project, $terms, $mode, $books);

        return match ($domain) {
            SearchDomain::Characters => $this->codexRowsOfType($rows, CodexEntryType::Character),
            SearchDomain::Locations => $this->codexRowsOfType($rows, CodexEntryType::Location),
            SearchDomain::Organizations => $this->codexRowsOfType($rows, CodexEntryType::Organization),
            default => $rows,
        };
    }

    /**
     * Run one domain's base query through {@see searchEntity}. Characters,
     * Locations, and Organizations all read the same CodexEntry query and fields
     * — the type split happens after, in {@see codexRowsOfType}.
     *
     * @param  array<int, string>  $terms
     * @param  Collection<int, Book>  $books  the project's books, keyed by id (see booksById()) — passed
     *                                        through even for domains that never carry one; only a
     *                                        domain whose {@see SearchDomain::carriesBook()} is true reads it
     */
    private function searchEntityFor(SearchDomain $domain, Project $project, array $terms, SearchMode $mode, Collection $books): Collection
    {
        [$query, $fields] = $this->queryFor($domain, $project);

        return $this->searchEntity($query, $fields, $terms, $mode, $domain->carriesBook() ? $books : null);
    }

    /** @return array{0: Builder, 1: array<string, string>} */
    private function queryFor(SearchDomain $domain, Project $project): array
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
                Act::query()->whereHas('book', fn (Builder $query) => $query->where('project_id', $project->id))
                    ->orderBy('position')->orderBy('id'),
                self::ACT_FIELDS,
            ],
            SearchDomain::Chapters => [
                $project->chapterQuery()
                    ->join('acts', 'acts.id', '=', 'chapters.act_id')
                    ->select('chapters.*', 'acts.book_id as book_id')
                    ->orderBy('chapters.position')->orderBy('chapters.id'),
                self::CHAPTER_FIELDS,
            ],
            SearchDomain::Scenes => [
                $project->sceneQuery()
                    ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
                    ->join('acts', 'acts.id', '=', 'chapters.act_id')
                    ->select('scenes.*', 'acts.book_id as book_id')
                    ->orderBy('scenes.position')->orderBy('scenes.id'),
                self::SCENE_FIELDS,
            ],
            SearchDomain::Characters, SearchDomain::Locations, SearchDomain::Organizations => [
                CodexEntry::query()->where('project_id', $project->id)->orderBy('name'),
                self::CODEX_ENTRY_FIELDS,
            ],
        };
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
