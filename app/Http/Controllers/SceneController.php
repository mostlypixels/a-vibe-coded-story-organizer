<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ReordersSiblings;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\DuplicateEntityRequest;
use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\UpdateSceneRequest;
use App\Models\Book;
use App\Models\Project;
use App\Models\Scene;
use App\Services\CodexAsOfResolver;
use App\Services\Concerns\CreatesInlineEvents;
use App\Services\SceneDuplicator;
use App\Services\SceneReferenceMatcher;
use App\Support\DuplicateName;
use App\Support\EventWindow;
use App\Support\StoryNumbering;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SceneController extends Controller
{
    use CreatesInlineEvents;
    use RecordsManualRevisions;
    use RedirectsAfterSave;
    use ReordersSiblings;
    use ResolvesIndexSorting;

    public function index(Request $request, Book $book): View
    {
        $this->authorize('view', $book->project);

        [$sort, $direction] = $this->resolveSorting($request, ['name', 'position'], 'position');

        $scenes = $book->sceneQuery()
            // Only the scene columns: the two joins below are there to sort by, not to
            // select from, and without this the joined `chapters`/`acts` columns would
            // overwrite same-named scene attributes on the hydrated models. Safe here
            // because this query has no withCount/withSum whose aliases a select() would
            // reset (the chapters index does — see ChapterController::index).
            ->select('scenes.*')
            // Joined so the `#` column can sort by story order: act order, then chapter
            // within the act, then scene within the chapter. Grouping by `chapter_id`
            // instead — as this did — only matches story order until something is
            // reordered. `chapters` and `acts` both carry `name` and `position`, so
            // every column below is table-qualified to stay unambiguous.
            ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
            ->join('acts', 'acts.id', '=', 'chapters.act_id')
            ->with('chapter.act', 'event')
            ->when($request->filled('search'), fn ($query) => $query->where('scenes.name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('chapter'), fn ($query) => $query->where('scenes.chapter_id', $request->query('chapter')))
            ->when(
                $sort === 'position',
                // $direction is applied to every key, so descending reads as the story
                // backwards rather than acts ascending with scenes reversed inside them.
                // The id tie-breaks are part of the contract: `position` has no unique
                // constraint, so two siblings can share one and must still order stably.
                fn ($query) => $query
                    ->orderBy('acts.position', $direction)
                    ->orderBy('acts.id', $direction)
                    ->orderBy('chapters.position', $direction)
                    ->orderBy('chapters.id', $direction)
                    ->orderBy('scenes.position', $direction)
                    ->orderBy('scenes.id', $direction),
                // $sort is allow-listed by resolveSorting(), so it is safe to qualify.
                fn ($query) => $query->orderBy('scenes.'.$sort, $direction)
            )
            ->get();

        // One project-wide name list backs every row's suggestion, so this stays a
        // single query instead of one per row. Project-wide, not book-wide: a
        // duplicate name is confusing across the whole series, not only this volume.
        // Two rows can propose the same name — harmless, since a collision is accepted
        // and the page reloads after each duplicate (see DuplicateName).
        $names = $book->project->sceneQuery()->pluck('name');
        $duplicateNames = $scenes->mapWithKeys(
            fn (Scene $scene) => [$scene->id => DuplicateName::suggest($scene->name, $names)]
        );

        return view('scenes.index', [
            'book' => $book,
            'chapters' => $this->chaptersFor($book),
            'scenes' => $scenes,
            'sort' => $sort,
            'direction' => $direction,
            'duplicateNames' => $duplicateNames,
            // Built from the whole book, never the filtered/paginated $scenes
            // above — a scenes list filtered to one chapter must still start
            // counting from that chapter's true book-wide number.
            'numbering' => StoryNumbering::forBook($book),
        ]);
    }

    public function show(Scene $scene): View
    {
        $book = $scene->chapter->act->book;

        $this->authorize('view', $book->project);

        $scene->load('chapter.act', 'event', 'mentionedEvents');

        return view('scenes.show', [
            'scene' => $scene,
            'numbering' => StoryNumbering::forBook($book),
            // Same query the edit page runs, ordered for a stable read (type, name).
            'referencedEntries' => $scene->codexReferences()->with('cover')->orderBy('type')->orderBy('name')->get(),
            'duplicateSuggestion' => DuplicateName::suggest($scene->name, $book->project->sceneQuery()->pluck('name')),
        ]);
    }

    public function create(Book $book): View
    {
        $project = $book->project;

        $this->authorize('update', $project);

        // The inline "New event" field always makes a regular event.
        [$windowMin, $windowMax] = EventWindow::forRegularEvent($project);

        return view('scenes.create', [
            'book' => $book,
            'chapters' => $this->chaptersFor($book),
            // The timeline is shared by every book in the project, so the event
            // list and its bounds stay project-wide.
            'events' => $this->eventsFor($project),
            'windowMin' => $windowMin,
            'windowMax' => $windowMax,
        ]);
    }

    public function store(StoreSceneRequest $request, Book $book, SceneReferenceMatcher $matcher): RedirectResponse
    {
        $validated = $request->validated();
        $chapter = $book->chapterQuery()->findOrFail($validated['chapter_id']);

        $scene = $chapter->scenes()->create(
            $this->sceneAttributes($validated) + ['event_id' => $this->resolveInlineEvent(
                $book->project,
                $validated['new_event_title'] ?? null,
                $validated['new_event_datetime'] ?? null,
                $validated['event_id'] ?? null,
            )]
        );

        $scene->mentionedEvents()->sync($validated['mentioned_events'] ?? []);

        // Recompute which codex entries this scene's contents reference. A save always
        // resyncs the single scene (no "did contents change" skip — contents changing is
        // the point), mirroring the mentionedEvents()->sync() call above.
        $matcher->syncScene($scene);

        return redirect()->route('books.scenes.index', $book);
    }

    public function edit(Scene $scene, CodexAsOfResolver $codexAsOf): View
    {
        $book = $scene->chapter->act->book;
        $project = $book->project;

        $this->authorize('update', $project);

        $scene->load('event', 'mentionedEvents');

        // This scene's rank among its chapter's siblings, for the "2 of 5" half of
        // the position hint — a gap-free rank, not the raw (possibly gappy)
        // `position` column. Same (position, id) tie-break as StoryNumbering, one
        // level deep.
        $siblingIds = $scene->chapter->scenes()->orderBy('position')->orderBy('id')->pluck('id');

        [$windowMin, $windowMax] = EventWindow::forRegularEvent($project);

        return view('scenes.edit', [
            'scene' => $scene,
            'project' => $project,
            'book' => $book,
            'chapters' => $this->chaptersFor($book),
            'events' => $this->eventsFor($project),
            'windowMin' => $windowMin,
            'windowMax' => $windowMax,
            'numbering' => StoryNumbering::forBook($book),
            'positionInChapter' => $siblingIds->search($scene->id) + 1,
            'totalInChapter' => $siblingIds->count(),
            // Codex values resolved as of the scene's "happens during" event (null when the
            // scene is unassigned → the panel shows the undetermined state). Pre-computed here
            // so no timeline math or N+1 resolution happens in Blade.
            'codexAsOfGroups' => $codexAsOf->resolve($project, $scene->event),
            // Codex entries whose name/alias whole-word-matches this scene's contents, as of
            // the last save. A read-only view of the scene_codex_entry pivot maintained by
            // SceneReferenceMatcher — the sidebar renders this flat list ordered by (type, name).
            'referencedEntries' => $scene->codexReferences()->with('cover')->orderBy('type')->orderBy('name')->get(),
            'duplicateSuggestion' => DuplicateName::suggest($scene->name, $project->sceneQuery()->pluck('name')),
        ]);
    }

    public function update(UpdateSceneRequest $request, Scene $scene, SceneReferenceMatcher $matcher): RedirectResponse
    {
        $book = $scene->chapter->act->book;
        $project = $book->project;
        $validated = $request->validated();
        $chapter = $book->chapterQuery()->findOrFail($validated['chapter_id']);
        $sceneAttributes = $this->sceneAttributes($validated);

        $beforeAutosavedFields = $this->snapshotAutosaved($scene, $sceneAttributes);

        $scene->update(
            $sceneAttributes
            + ['chapter_id' => $chapter->id, 'event_id' => $this->resolveInlineEvent(
                $project,
                $validated['new_event_title'] ?? null,
                $validated['new_event_datetime'] ?? null,
                $validated['event_id'] ?? null,
            )]
        );

        $scene->mentionedEvents()->sync($validated['mentioned_events'] ?? []);

        // Recompute references against the scene's now-saved contents (see store()).
        $matcher->syncScene($scene);

        $this->recordManualSave($scene, $beforeAutosavedFields);

        return $this->redirectAfterSave($request, ['scenes.edit', $scene], ['books.scenes.index', $book]);
    }

    public function duplicate(DuplicateEntityRequest $request, Scene $scene, SceneDuplicator $duplicator): RedirectResponse
    {
        $copy = $duplicator->duplicate($scene, $request->validated('name'));

        return redirect()->route('scenes.edit', $copy)->with('status', 'duplicated');
    }

    public function destroy(Scene $scene): RedirectResponse
    {
        $book = $scene->chapter->act->book;

        $this->authorize('update', $book->project);

        $scene->delete();

        return redirect()->route('books.scenes.index', $book);
    }

    public function moveUp(Request $request, Scene $scene): RedirectResponse|JsonResponse
    {
        $this->reorderSibling($scene, $scene->chapter->act->book->project, up: true);

        return $this->reorderResponse($request, $scene);
    }

    public function moveDown(Request $request, Scene $scene): RedirectResponse|JsonResponse
    {
        $this->reorderSibling($scene, $scene->chapter->act->book->project, up: false);

        return $this->reorderResponse($request, $scene);
    }

    /**
     * Scenes are the one reorderable entity the Story overview moves over AJAX, so
     * their move actions answer JSON with the new position; every other caller is a
     * plain form post that redirects back (see the ReordersSiblings docblock).
     */
    private function reorderResponse(Request $request, Scene $scene): RedirectResponse|JsonResponse
    {
        return $request->wantsJson()
            ? response()->json(['position' => $scene->position])
            : redirect()->back();
    }

    private function chaptersFor(Book $book): Collection
    {
        return $book->chapterQuery()
            ->with('act')
            ->orderBy('name')
            ->get();
    }

    private function eventsFor(Project $project): Collection
    {
        return $project->events()->orderBy('event_datetime')->get();
    }

    /**
     * Scene column values, stripped of the relationship/form-only keys handled separately.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function sceneAttributes(array $validated): array
    {
        return collect($validated)
            ->except(['chapter_id', 'event_id', 'new_event_title', 'new_event_datetime', 'mentioned_events'])
            ->all();
    }
}
