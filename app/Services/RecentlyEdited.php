<?php

namespace App\Services;

use App\Enums\CodexEntryType;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Support\RecentItem;
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
 * > [!WARNING]
 * > Entities with no `updated_at` cannot appear here: Tag and CodexAlias carry
 * > no timestamps, and a Revision is immutable with only a `created_at`.
 */
class RecentlyEdited
{
    /** @return Collection<int, RecentItem> */
    public function acts(Project $project, int $limit = RecentItem::LIMIT): Collection
    {
        return $project->acts()
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Act $act) => new RecentItem(
                label: $act->name,
                url: route('acts.edit', $act),
                updatedAt: $act->updated_at,
            ));
    }

    /** @return Collection<int, RecentItem> */
    public function chapters(Project $project, int $limit = RecentItem::LIMIT): Collection
    {
        return $project->chapterQuery()
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
    public function scenes(Project $project, int $limit = RecentItem::LIMIT): Collection
    {
        return $project->sceneQuery()
            ->with('chapter.act')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Scene $scene) => new RecentItem(
                label: $scene->name,
                url: route('scenes.edit', $scene),
                updatedAt: $scene->updated_at,
                context: $scene->chapter->act->name.' — '.$scene->chapter->name,
            ));
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
}
