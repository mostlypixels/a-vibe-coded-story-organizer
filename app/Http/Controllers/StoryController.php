<?php

namespace App\Http\Controllers;

use App\Enums\StoryOverviewMode;
use App\Http\Requests\UpdateStoryOverviewModeRequest;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Services\RecentlyEdited;
use App\Support\StoryNumbering;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StoryController extends Controller
{
    /**
     * The Story section landing page: the acts, chapters and scenes touched
     * most recently, each with a link to its full index. Same shape as the
     * Timeline and Codex home actions.
     */
    public function home(Project $project, RecentlyEdited $recentlyEdited): View
    {
        $this->authorize('view', $project);

        return view('story.home', [
            'project' => $project,
            'recentActs' => $recentlyEdited->acts($project),
            'recentChapters' => $recentlyEdited->chapters($project),
            'recentScenes' => $recentlyEdited->scenes($project),
        ]);
    }

    /**
     * The Story overview. Two render modes on the project's
     * `overview_render_mode`: `Book` renders the whole tree on one page (slow on
     * a long story, opt-in); `Chapter` (default) paginates one chapter per page
     * and stays fast — only that chapter's scene bodies load, while numbering,
     * the table of contents, and word totals stay whole-book through
     * contents-free queries.
     */
    public function index(Project $project, Request $request): View
    {
        $this->authorize('view', $project);

        if ($project->overview_render_mode === StoryOverviewMode::Book) {
            return $this->book($project);
        }

        return $this->chapter($project, $request->query('chapter'));
    }

    /**
     * Persists the owner's choice of `overview_render_mode` and redirects back
     * to the overview, preserving the current `?chapter=` if the request came
     * from a chapter-mode page.
     */
    public function updateMode(UpdateStoryOverviewModeRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()->route('projects.story.overview', array_filter([
            'project' => $project,
            'chapter' => $request->query('chapter'),
        ]));
    }

    /**
     * Whole-story render: the full act -> chapter -> scene tree on one page.
     */
    private function book(Project $project): View
    {
        $acts = $project->acts()
            ->with('chapters.scenes.event')
            ->orderBy('position')
            ->get()
            ->each(function ($act) {
                $act->chapters = $act->chapters->sortBy('position')->each(function ($chapter) {
                    $chapter->scenes = $chapter->scenes->sortBy('position');
                });
            });

        // Scenes are already eager-loaded above, so this sums an in-memory
        // collection — no query fires. Per-act/chapter totals are the same
        // ->sum() over the same loaded relations, computed where they're
        // used in the view. There is no `wordCount()` accessor: an explicit sum
        // keeps it visible that this is free only because the data is already
        // in memory.
        $wordCount = $acts->sum(
            fn ($act) => $act->chapters->sum(
                fn ($chapter) => $chapter->scenes->sum('word_count'),
            ),
        );

        return view('story.index', [
            'project' => $project,
            'acts' => $acts,
            'wordCount' => $wordCount,
            // The tree is already fully eager-loaded above, so fromActs()
            // derives the numbering with zero extra queries.
            'numbering' => StoryNumbering::fromActs($acts),
        ]);
    }

    /**
     * One-chapter render. Loads only the target chapter's scene bodies; every
     * other figure on the page — numbering, the table of contents, per-act and
     * book word totals — comes from contents-free queries, so the request cost
     * does not grow with the rest of the story.
     */
    private function chapter(Project $project, ?string $chapterId): View
    {
        // The table of contents is whole-book: every act with its chapters, but
        // names and positions only — no scenes, no contents.
        $tocActs = $project->acts()
            ->select(['id', 'name', 'position'])
            ->with(['chapters' => fn (HasMany $query) => $query
                ->select(['id', 'name', 'position', 'act_id'])
                ->orderBy('position'),
            ])
            ->orderBy('position')
            ->get();

        $currentChapter = $this->resolveChapter($project, $chapterId, $tocActs);

        if ($currentChapter !== null) {
            // The sole scene-contents fetch on the page.
            $currentChapter->setRelation(
                'scenes',
                $currentChapter->scenes()->with('event')->orderBy('position')->get(),
            );
        }

        // Per-act word totals from one grouped aggregate joined chapter -> act,
        // filtered to the project — no scene body loads. An act with no scenes
        // never appears in the map, so `?? 0` keeps it null-safe below.
        $actWordCounts = Scene::query()
            ->join('chapters', 'scenes.chapter_id', '=', 'chapters.id')
            ->join('acts', 'chapters.act_id', '=', 'acts.id')
            ->where('acts.project_id', $project->id)
            ->groupBy('chapters.act_id')
            ->selectRaw('chapters.act_id as act_id, sum(scenes.word_count) as total')
            ->pluck('total', 'act_id');

        [$previousChapter, $nextChapter] = $this->neighbourChapters($currentChapter, $tocActs);

        return view('story.chapter', [
            'project' => $project,
            'tocActs' => $tocActs,
            'currentChapter' => $currentChapter,
            'previousChapter' => $previousChapter,
            'nextChapter' => $nextChapter,
            'numbering' => StoryNumbering::forProject($project),
            'wordCount' => (int) $actWordCounts->sum(),
            'actWordCount' => $currentChapter === null
                ? 0
                : (int) ($actWordCounts[$currentChapter->act_id] ?? 0),
        ]);
    }

    /**
     * The current chapter's project-wide neighbours (prev, next), from the
     * same contents-free `$tocActs` tree the TOC renders — no extra query.
     * The pager walks the whole book, not just the current act, so the last
     * chapter of an act neighbours the first chapter of the next one.
     *
     * @return array{0: ?Chapter, 1: ?Chapter}
     */
    private function neighbourChapters(?Chapter $currentChapter, Collection $tocActs): array
    {
        if ($currentChapter === null) {
            return [null, null];
        }

        $orderedChapters = $tocActs->flatMap(fn ($act) => $act->chapters)->values();

        $currentIndex = $orderedChapters->search(
            fn ($chapter) => $chapter->id === $currentChapter->id,
        );

        return [
            $currentIndex > 0 ? $orderedChapters->get($currentIndex - 1) : null,
            $orderedChapters->get($currentIndex + 1),
        ];
    }

    /**
     * The chapter to render: the `chapter` id when given and owned by $project,
     * otherwise the first chapter by project-wide order (act position, then
     * chapter position), or null when the project has no chapters.
     *
     * A `chapter` id is never trusted: an id for a chapter in another project
     * (or an unknown id) is a 403, walked through `chapter->act->project`.
     */
    private function resolveChapter(Project $project, ?string $chapterId, Collection $tocActs): ?Chapter
    {
        if ($chapterId !== null) {
            $chapter = Chapter::with('act')->find($chapterId);

            abort_if($chapter === null || $chapter->act->project_id !== $project->id, 403);

            return $chapter;
        }

        $firstChapterId = $tocActs
            ->flatMap(fn ($act) => $act->chapters)
            ->first()?->id;

        return $firstChapterId === null
            ? null
            : Chapter::with('act')->find($firstChapterId);
    }
}
