<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Support\RecentItem;
use App\Support\StoryNumbering;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The entities of one project, newest write first, as view-ready
 * {@see RecentItem} rows.
 *
 * Two callers share this: the section landing pages (Story, Timeline, Codex)
 * show a short list per entity, and the project dashboard shows the single
 * latest of each. Ordering is by `updated_at` — ANY write, not only a prose
 * change — because the lists answer "where was I last working?".
 *
 * The manuscript methods take a `Project` **or** a `Book`: the Story landing
 * page is book-scoped, while the project dashboard stays project-wide. Both
 * models answer `acts()`, `chapterQuery()` and `sceneQuery()`, so the scope is
 * the only thing that changes. Plotlines, events and the codex are shared by
 * every book, so they take a project only.
 *
 * > [!WARNING]
 * > Entities with no `updated_at` cannot appear here: Tag and CodexAlias carry
 * > no timestamps, and a Revision is immutable with only a `created_at`.
 */
class RecentlyEdited
{
    /** @return Collection<int, RecentItem> */
    public function acts(Project|Book $scope, int $limit = RecentItem::LIMIT): Collection
    {
        return $scope->acts()
            ->latest('acts.updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Act $act) => new RecentItem(
                label: $act->name,
                url: route('acts.edit', $act),
                updatedAt: $act->updated_at,
            ));
    }

    /** @return Collection<int, RecentItem> */
    public function chapters(Project|Book $scope, int $limit = RecentItem::LIMIT): Collection
    {
        return $scope->chapterQuery()
            ->with('act')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Chapter $chapter) => new RecentItem(
                label: $chapter->name,
                url: route('chapters.edit', $chapter),
                updatedAt: $chapter->updated_at,
                context: $chapter->act->name,
                imageUrl: $chapter->cover_image
                    ? Storage::disk('public')->url($chapter->cover_image)
                    : null,
            ));
    }

    /** @return Collection<int, RecentItem> */
    public function scenes(Project|Book $scope, int $limit = RecentItem::LIMIT): Collection
    {
        // The project dashboard mixes scenes from every book, so it names the
        // book too. A book-scoped list already sits in one book, so it leaves
        // the book out.
        $withBook = $scope instanceof Project;

        $scenes = $scope->sceneQuery()
            ->with('chapter.act.book')
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        // Act and chapter numbers are book-wide and gap-free (see
        // StoryNumbering), not the raw `position` — so the breadcrumb agrees
        // with the chapter page. One numbering table per book the list touches.
        $numbering = [];

        return $scenes->map(function (Scene $scene) use ($withBook, &$numbering) {
            $chapter = $scene->chapter;
            $book = $chapter->act->book;
            $numbers = $numbering[$book->id] ??= StoryNumbering::forBook($book);

            $segments = [
                __('Act :number', ['number' => $numbers->act($chapter->act)]),
                __('Chapter :number', ['number' => $numbers->chapter($chapter)]).': '.$chapter->name,
            ];

            if ($withBook) {
                array_unshift($segments, __('Book :number', ['number' => $book->position]));
            }

            return new RecentItem(
                label: $scene->name,
                url: route('scenes.edit', $scene),
                updatedAt: $scene->updated_at,
                contextSegments: $segments,
            );
        });
    }

    /** @return Collection<int, RecentItem> */
    public function plotlines(Project $project, int $limit = RecentItem::LIMIT): Collection
    {
        return $project->plotlines()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Plotline $plotline) => new RecentItem(
                label: $plotline->name,
                url: route('plotlines.edit', $plotline),
                updatedAt: $plotline->updated_at,
            ));
    }

    /** @return Collection<int, RecentItem> */
    public function events(Project $project, int $limit = RecentItem::LIMIT): Collection
    {
        return $project->events()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Event $event) => new RecentItem(
                label: $event->title,
                url: route('events.edit', $event),
                updatedAt: $event->updated_at,
                context: $event->event_datetime->format('M j, Y'),
            ));
    }

    /**
     * Codex entries of one type. Both callers ask per type — the landing page
     * shows a list each, the dashboard a tile each — so the limit always
     * applies within a type, one query per type.
     *
     * @return Collection<int, RecentItem>
     */
    public function codexEntries(Project $project, CodexEntryType $type, int $limit = RecentItem::LIMIT): Collection
    {
        return $project->codexEntries()
            ->where('type', $type)
            ->with('cover')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (CodexEntry $entry) => new RecentItem(
                label: $entry->name,
                url: route('codex.edit', $entry),
                updatedAt: $entry->updated_at,
                imageUrl: $entry->cover?->url(),
            ));
    }

    /**
     * Codex entries of every type in one list, newest write first.
     *
     * The dashboard asks this way: a writer wants the last things they touched,
     * not one row per type. The type goes in `context` because the row has no
     * other way to say what it is — a codex name does not tell you if it is a
     * character or a location.
     *
     * @return Collection<int, RecentItem>
     */
    public function allCodexEntries(Project $project, int $limit = RecentItem::LIMIT): Collection
    {
        return $project->codexEntries()
            ->with('cover')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (CodexEntry $entry) => new RecentItem(
                label: $entry->name,
                url: route('codex.edit', $entry),
                updatedAt: $entry->updated_at,
                context: __($entry->type->label()),
                imageUrl: $entry->cover?->url(),
            ));
    }
}
