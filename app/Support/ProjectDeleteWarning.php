<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Arr;

/**
 * The cascade-count sentence shown before a project is deleted, e.g. "This project has
 * 2 acts and 5 codex entries, which will also be deleted." A project with nothing beyond
 * its un-deletable main plotline and bookend events falls back to the plain question:
 * there is nothing to enumerate that the writer does not already expect to lose.
 *
 * The project edit page and the dashboard list both delete a project, so both must warn
 * with the same sentence. This class owns the sentence *and* the counts it needs — a
 * caller that forgets {@see countRelations()} gets a message that silently omits every
 * category.
 *
 * > [!WARNING]
 * > Load the counts with one query for the whole list (`withCount()` on the query), never
 * > per row (`loadCount()` inside a loop).
 */
class ProjectDeleteWarning
{
    /**
     * The relation counts {@see for()} reads, for `withCount()` / `loadCount()`.
     *
     * The main plotline and the Start/End bookend events are auto-created invariants of
     * every project (see Project::booted()), so they are excluded: a brand-new project
     * must read as having nothing to lose, not "1 plotline and 2 events".
     *
     * @return array<int|string, mixed>
     */
    public static function countRelations(): array
    {
        return [
            'acts',
            'plotlines' => fn ($query) => $query->where('is_main', false),
            'events' => fn ($query) => $query->where('is_fixed', false),
            'codexEntries',
        ];
    }

    public static function for(Project $project): string
    {
        $categories = [
            [$project->acts_count, '{1} :count act|[2,*] :count acts'],
            [$project->plotlines_count, '{1} :count plotline|[2,*] :count plotlines'],
            [$project->events_count, '{1} :count event|[2,*] :count events'],
            [$project->codex_entries_count, '{1} :count codex entry|[2,*] :count codex entries'],
        ];

        $nonZero = [];

        foreach ($categories as [$count, $choicePattern]) {
            if ($count > 0) {
                $nonZero[] = trans_choice($choicePattern, $count, ['count' => $count]);
            }
        }

        if ($nonZero === []) {
            return __('Are you sure you want to delete this project?');
        }

        return __('This project has :categories, which will also be deleted.', [
            'categories' => Arr::join($nonZero, ', ', ' '.__('and').' '),
        ]);
    }
}
