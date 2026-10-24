<?php

namespace App\Support;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * The lists that the narrow panel needs to show a {@see SearchScope}.
 *
 * Numbering restarts in each book, so the chapter and act pickers need exactly one
 * book: the book the scope names, or the only book of the project. With several
 * books and none chosen, no chapter or act query runs.
 */
final readonly class ScopePicker
{
    /**
     * @param  Collection<int, Book>  $books  ready for {@see Book::displayName()}
     * @param  Collection<int, Chapter>  $chapters  in story order
     * @param  Collection<int, Act>  $acts  in story order
     */
    public function __construct(
        public Collection $books,
        public ?Book $book,
        public Collection $chapters,
        public Collection $acts,
        public ?Act $act,
    ) {}

    public static function for(Project $project, SearchScope $scope): self
    {
        $books = $project->books()->get()
            ->each(fn (Book $book) => $book->setRelation('project', $project));

        $book = $scope->bookId !== null
            ? $books->firstWhere('id', $scope->bookId)
            : ($books->count() === 1 ? $books->first() : null);

        $acts = $book?->acts()->orderBy('position')->orderBy('id')->get() ?? collect();

        return new self(
            books: $books,
            book: $book,
            chapters: $book?->chaptersInStoryOrder() ?? collect(),
            acts: $acts,
            act: $scope->actId !== null ? $acts->firstWhere('id', $scope->actId) : null,
        );
    }

    /** The "Filtered to ..." line, or null when the scope narrows nothing. */
    public function summary(SearchScope $scope): ?string
    {
        return $scope->isNarrowed()
            ? implode(', ', SearchScopeSummary::parts($scope, $this->book, $this->chapters, $this->act))
            : null;
    }
}
