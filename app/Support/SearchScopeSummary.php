<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Support\Collection;

/**
 * Names the filters a search is narrowed by, for the line above the results.
 *
 * Without that line a filtered empty result reads as a broken search. The parts
 * are built here, not in the view: the chapter range needs story numbering, and
 * a Blade template is the wrong place to derive it.
 */
final class SearchScopeSummary
{
    /**
     * How many domains the summary names one by one before it starts counting.
     * More than this and the line is longer than the results it introduces.
     */
    private const NAMED_DOMAIN_LIMIT = 3;

    /**
     * @param  Collection<int, Chapter>  $chapters  the book's chapters in story order, empty when no book is resolved
     * @return array<int, string>
     */
    public static function parts(SearchScope $scope, ?Book $book, Collection $chapters): array
    {
        $parts = [];

        if ($scope->bookId !== null && $book !== null) {
            $parts[] = $book->displayName();
        }

        if ($chapters->isNotEmpty() && ($scope->fromChapterId !== null || $scope->toChapterId !== null)) {
            $numbering = StoryNumbering::fromChapters($chapters);

            $from = $scope->fromChapterId !== null ? $numbering->chapter($scope->fromChapterId) : 1;
            $to = $scope->toChapterId !== null ? $numbering->chapter($scope->toChapterId) : $chapters->count();

            $parts[] = __('chapters :from–:to', ['from' => $from, 'to' => $to]);
        }

        if ($scope->domains !== []) {
            $parts[] = count($scope->domains) <= self::NAMED_DOMAIN_LIMIT
                ? implode(', ', array_map(fn ($domain) => lcfirst($domain->label()), $scope->domains))
                : trans_choice('{1} :count domain|[2,*] :count domains', count($scope->domains), ['count' => count($scope->domains)]);
        }

        return $parts;
    }
}
