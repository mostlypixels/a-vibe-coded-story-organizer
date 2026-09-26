<?php

namespace App\Support;

use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * The cascade-count sentence shown before a project is deleted, e.g. "This project has
 * 2 acts and 5 codex entries, which will also be deleted." A project with nothing beyond
 * its un-deletable main plotline and bookend events falls back to the plain question:
 * there is nothing to enumerate that the writer does not already expect to lose.
 *
 * The project edit page and the dashboard list both delete a project, so both must warn
 * with the same sentence. This class owns the sentence *and* the counts it needs — a
 * caller that forgets {@see withCounts()} or {@see loadCounts()} gets a message that
 * silently omits every category.
 *
 * > [!WARNING]
 * > Load the counts with one query for the whole list ({@see withCounts()}), never
 * > per row ({@see loadCounts()} inside a loop).
 */
class ProjectDeleteWarning
{
    /**
     * Adds every count {@see for()} reads to a project list query.
     *
     * Chapters and scenes have no relation on Project for `withCount()`, so they are
     * correlated subqueries. They stay in the same single query.
     *
     * @template TQuery of Builder<Project>|HasMany<Project, *>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function withCounts(Builder|HasMany $query): Builder|HasMany
    {
        return $query->withCount(self::countRelations())->addSelect([
            'chapters_count' => self::projectChapters()->selectRaw('count(*)'),
            'scenes_count' => self::projectScenes()->selectRaw('count(*)'),
            'scene_words_sum' => self::projectScenes()->selectRaw('coalesce(sum(scenes.word_count), 0)'),
        ]);
    }

    /** The same counts as {@see withCounts()}, for one loaded project. */
    public static function loadCounts(Project $project): void
    {
        $project->loadCount(self::countRelations());
        $project->setAttribute('chapters_count', $project->chapterQuery()->count());
        $project->setAttribute('scenes_count', $project->sceneQuery()->count());
        $project->setAttribute('scene_words_sum', (int) $project->sceneQuery()->sum('word_count'));
    }

    public static function for(Project $project): string
    {
        $categories = [
            // Unlike the categories below, a one-book count is never adjusted
            // down by one — it is hidden outright. A project always holds a
            // book (Project::booted()), so a one-book project must read as
            // having nothing unexpected to lose; a three-book project loses
            // three books, not two, once there is more than one.
            [$project->books_count > 1 ? $project->books_count : 0, '{1} :count book|[2,*] :count books'],
            [$project->acts_count, '{1} :count act|[2,*] :count acts'],
            [$project->chapters_count, '{1} :count chapter|[2,*] :count chapters'],
            [$project->scenes_count, '{1} :count scene (:words)|[2,*] :count scenes (:words)'],
            [$project->plotlines_count, '{1} :count plotline|[2,*] :count plotlines'],
            [$project->events_count, '{1} :count event|[2,*] :count events'],
            [$project->codex_entries_count, '{1} :count codex entry|[2,*] :count codex entries'],
        ];

        $words = WordCountFormat::text((int) $project->scene_words_sum);
        $nonZero = [];

        foreach ($categories as [$count, $choicePattern]) {
            if ($count > 0) {
                $nonZero[] = trans_choice($choicePattern, $count, ['count' => $count, 'words' => $words]);
            }
        }

        if ($nonZero === []) {
            return __('Are you sure you want to delete this project?');
        }

        return __('This project has :categories, which will also be deleted.', [
            'categories' => Arr::join($nonZero, ', ', ' '.__('and').' '),
        ]);
    }

    /**
     * The relation counts, for `withCount()` / `loadCount()`.
     *
     * The main plotline and the Start/End bookend events are auto-created invariants of
     * every project (see Project::booted()), so they are excluded: a brand-new project
     * must read as having nothing to lose, not "1 plotline and 2 events".
     *
     * @return array<int|string, mixed>
     */
    private static function countRelations(): array
    {
        return [
            'books',
            'acts',
            'plotlines' => fn ($query) => $query->where('is_main', false),
            'events' => fn ($query) => $query->where('is_fixed', false),
            'codexEntries',
        ];
    }

    /**
     * The chapters of the outer query's project row. The correlated twin of
     * Project::chapterQuery(), with the same nested `whereIn` walk.
     *
     * @return Builder<Chapter>
     */
    private static function projectChapters(): Builder
    {
        return Chapter::query()->whereIn('chapters.act_id', self::projectActIds());
    }

    /**
     * The scenes of the outer query's project row. The correlated twin of
     * Project::sceneQuery().
     *
     * @return Builder<Scene>
     */
    private static function projectScenes(): Builder
    {
        return Scene::query()->whereIn('scenes.chapter_id', Chapter::query()->select('chapters.id')
            ->whereIn('chapters.act_id', self::projectActIds()));
    }

    /** @return Builder<Act> */
    private static function projectActIds(): Builder
    {
        return Act::query()->select('acts.id')
            ->whereIn('acts.book_id', Book::query()->select('books.id')->whereColumn('books.project_id', 'projects.id'));
    }
}
