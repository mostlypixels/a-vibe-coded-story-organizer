<?php

namespace App\Support;

use App\Enums\SearchDomain;

/**
 * The book, chapter range, and domain filters narrowing one search request.
 *
 * Runs no query itself — {@see SearchScopeFactory} resolves the chapter range
 * and hands over the finished id list. That split is what lets this class stay
 * a plain value object under test.
 *
 * `new SearchScope()` is the unfiltered scope: every existing caller and view
 * keeps behaving as it does today.
 */
final readonly class SearchScope
{
    /**
     * @param  array<int, int>  $chapterIds  the resolved range, in story order; empty
     *                                       means "every chapter in the book" (or no book)
     * @param  array<int, SearchDomain>  $domains  empty means every domain
     */
    public function __construct(
        public ?int $bookId = null,
        public ?int $fromChapterId = null,
        public ?int $toChapterId = null,
        public array $chapterIds = [],
        public array $domains = [],
    ) {}

    /**
     * Whether $domain should run at all: it must be selected, and not made
     * meaningless by the book filter.
     */
    public function includes(SearchDomain $domain): bool
    {
        return $this->selects($domain) && ! $this->hiddenByBook($domain);
    }

    /**
     * True only when a book is set and $domain has no book to filter by.
     * Kept apart from {@see includes()} because only this reason gets an
     * explanatory line in the view — an unchecked domain stays silent.
     */
    public function hiddenByBook(SearchDomain $domain): bool
    {
        return $this->bookId !== null && ! $domain->carriesBook();
    }

    /** True when any filter narrows the search below "every domain, whole project". */
    public function isNarrowed(): bool
    {
        return $this->bookId !== null || $this->domains !== [];
    }

    /**
     * The filter query string, for the "see all" link, the domain page's
     * links, and $paginator->appends() — the one builder every caller shares.
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->bookId !== null) {
            $query['book'] = $this->bookId;
        }

        if ($this->fromChapterId !== null) {
            $query['from_chapter'] = $this->fromChapterId;
        }

        if ($this->toChapterId !== null) {
            $query['to_chapter'] = $this->toChapterId;
        }

        if ($this->domains !== []) {
            $query['domains'] = array_map(fn (SearchDomain $domain) => $domain->value, $this->domains);
        }

        return $query;
    }

    private function selects(SearchDomain $domain): bool
    {
        return $this->domains === [] || in_array($domain, $this->domains, true);
    }
}
