<?php

namespace App\Support;

use App\Enums\SearchDomain;
use App\Http\Requests\SearchRequest;
use App\Models\Book;
use App\Models\Project;

/**
 * Builds a {@see SearchScope} from a search request: the one place that turns
 * a book and a pair of chapter ids into a resolved, story-ordered chapter
 * range. Kept apart from SearchScope itself so that class stays a plain value
 * object under test, with no query to fake.
 */
final class SearchScopeFactory
{
    public static function fromRequest(SearchRequest $request, Project $project): SearchScope
    {
        $bookId = $request->filled('book') ? $request->integer('book') : null;
        $fromChapterId = $request->filled('from_chapter') ? $request->integer('from_chapter') : null;
        $toChapterId = $request->filled('to_chapter') ? $request->integer('to_chapter') : null;

        $book = $bookId !== null ? $project->books()->find($bookId) : null;

        return new SearchScope(
            bookId: $book?->id,
            fromChapterId: $fromChapterId,
            toChapterId: $toChapterId,
            chapterIds: $book !== null ? self::resolveChapterRange($book, $fromChapterId, $toChapterId) : [],
            domains: $request->enums('domains', SearchDomain::class),
        );
    }

    /**
     * Slice $book's story-ordered chapters from $fromChapterId to $toChapterId,
     * inclusive. A reversed pair is swapped — the order she clicked them in is
     * not information. Leaves the list empty when the slice is the whole book,
     * so the book filter alone does the work and no `whereIn` is built.
     *
     * @return array<int, int>
     */
    private static function resolveChapterRange(Book $book, ?int $fromChapterId, ?int $toChapterId): array
    {
        $ids = $book->chaptersInStoryOrder()->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $fromIndex = $fromChapterId !== null ? array_search($fromChapterId, $ids, true) : false;
        $toIndex = $toChapterId !== null ? array_search($toChapterId, $ids, true) : false;

        $start = $fromIndex !== false ? $fromIndex : 0;
        $end = $toIndex !== false ? $toIndex : count($ids) - 1;

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        if ($start === 0 && $end === count($ids) - 1) {
            return [];
        }

        return array_slice($ids, $start, $end - $start + 1);
    }
}
