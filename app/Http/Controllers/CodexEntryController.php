<?php

namespace App\Http\Controllers;

use App\Enums\CodexEntryType;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\DuplicateEntityRequest;
use App\Http\Requests\StoreCodexEntryRequest;
use App\Http\Requests\UpdateCodexEntryRequest;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Services\CodexAttributeSheets;
use App\Services\CodexEntryDuplicator;
use App\Services\CodexEntrySaver;
use App\Services\ReferencingScenes;
use App\Support\CodexMediaUploads;
use App\Support\DuplicateName;
use App\Support\EventWindow;
use App\Support\PageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;

/**
 * Every save delegates to {@see CodexEntrySaver}. An entry is spread over
 * aliases, tags, attribute values, media, and the scene-reference pivot. The
 * order those tables are written in is a domain rule, not request handling.
 */
class CodexEntryController extends Controller
{
    use RedirectsAfterSave;
    use ResolvesIndexSorting;

    public function index(Request $request, Project $project, string $type): View
    {
        $this->authorize('view', $project);

        $entryType = CodexEntryType::fromRouteKey($type);

        [$sort, $direction] = $this->resolveSorting($request, ['name'], 'name');

        $entries = CodexEntry::query()
            ->where('project_id', $project->id)
            ->where('type', $entryType->value)
            ->with(['tags', 'aliases', 'cover'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->query('search');

                $query->where(function ($nameOrAlias) use ($search) {
                    $nameOrAlias->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('aliases', fn ($aliases) => $aliases->where('alias', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('tag'), fn ($query) => $query->whereHas(
                'tags',
                fn ($tags) => $tags->where('tags.id', $request->query('tag'))
            ))
            ->orderBy($sort, $direction)
            ->paginate(PageSize::resolve($request->user()?->page_size))
            ->withQueryString();

        // Share one type-scoped name list with all rows.
        $names = $project->codexEntries()->where('type', $entryType->value)->pluck('name');
        $duplicateNames = $entries->mapWithKeys(
            fn (CodexEntry $entry) => [$entry->id => DuplicateName::suggest($entry->name, $names)]
        );

        return view('codex.index', [
            'project' => $project,
            'type' => $entryType,
            'entries' => $entries,
            'tags' => $project->tags()->whereHas('entries')->orderBy('name')->get(),
            'sort' => $sort,
            'direction' => $direction,
            'duplicateNames' => $duplicateNames,
        ]);
    }

    public function create(Project $project, string $type): View
    {
        $this->authorize('update', $project);

        $entryType = CodexEntryType::fromRouteKey($type);

        return view('codex.create', [
            'project' => $project,
            'type' => $entryType,
            'attributes' => $project->codexAttributesFor($entryType),
            'projectTags' => $project->tags()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCodexEntryRequest $request, Project $project, string $type, CodexEntrySaver $saver): RedirectResponse
    {
        $entryType = CodexEntryType::fromRouteKey($type);

        $saver->create($project, $entryType, $request->validated(), CodexMediaUploads::fromRequest($request));

        return redirect()->route('projects.codex.index', [$project, $entryType->routeKey()]);
    }

    public function show(CodexEntry $codexEntry, CodexAttributeSheets $sheets, ReferencingScenes $referencingScenes): View
    {
        $this->authorize('view', $codexEntry->project);

        $codexEntry->load('aliases', 'tags', 'media', 'attributeValues.startEvent', 'inceptionEvent', 'terminationEvent');

        $project = $codexEntry->project;

        return view('codex.show', [
            'project' => $project,
            'entry' => $codexEntry,
            'sheets' => $sheets->setOnly($codexEntry, $project->startEvent()),
            'referencingScenes' => $referencingScenes->forEntry($codexEntry),
            'showBook' => $project->books()->count() > 1,
        ]);
    }

    /**
     * The capped "Referenced in scenes" card's "see all" destination.
     *
     * {@see ReferencingScenes::forEntry()} sorts in PHP (by event, then by
     * manuscript position), so a SQL LIMIT/OFFSET would page over the wrong
     * set. Slice the already-sorted collection by hand instead, the same way
     * {@see SearchController::domain()} pages a PHP-matched result set.
     */
    public function scenes(Request $request, CodexEntry $codexEntry, ReferencingScenes $referencingScenes): View
    {
        $this->authorize('view', $codexEntry->project);

        $scenes = $referencingScenes->forEntry($codexEntry);

        $page = max(1, $request->integer('page', 1));
        $perPage = PageSize::resolve($request->user()?->page_size);

        // A PHP-side slice, never a SQL LIMIT/OFFSET: forEntry() sorts the loaded
        // collection, so the database does not know this order and would page the
        // wrong set. The scene direction orders in SQL and does use ->paginate();
        // the two are meant to differ.
        $paginator = new LengthAwarePaginator(
            $scenes->forPage($page, $perPage)->values(),
            $scenes->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return view('references.scenes', [
            'entry' => $codexEntry,
            'paginator' => $paginator,
            'showBook' => $codexEntry->project->books()->count() > 1,
        ]);
    }

    public function edit(CodexEntry $codexEntry, CodexAttributeSheets $sheets, ReferencingScenes $referencingScenes): View
    {
        $this->authorize('update', $codexEntry->project);

        $codexEntry->load('aliases', 'tags', 'media', 'attributeValues.startEvent', 'inceptionEvent', 'terminationEvent');

        $project = $codexEntry->project;
        $startEvent = $project->startEvent();

        // The inline inception/termination fields always make a regular event.
        [$windowMin, $windowMax] = EventWindow::forRegularEvent($project);

        return view('codex.edit', [
            'project' => $project,
            'type' => $codexEntry->type,
            'entry' => $codexEntry,
            'sheets' => $sheets->attached($codexEntry, $startEvent),
            'unattachedAttributes' => $sheets->unattachedFor($codexEntry),
            'startEvent' => $startEvent,
            'events' => $project->events()->orderBy('event_datetime')->orderBy('id')->get(),
            // Inception/termination pickers offer regular events only — Start/End are
            // fixed project bookends, not events an entity's lifespan can attach to.
            'regularEvents' => $project->events()->where('is_fixed', false)->orderBy('event_datetime')->orderBy('id')->get(),
            'windowMin' => $windowMin,
            'windowMax' => $windowMax,
            'projectTags' => $project->tags()->orderBy('name')->get(),
            'referencingScenes' => $referencingScenes->forEntry($codexEntry),
            'showBook' => $project->books()->count() > 1,
            'duplicateSuggestion' => DuplicateName::suggest(
                $codexEntry->name,
                $project->codexEntries()->where('type', $codexEntry->type->value)->pluck('name')
            ),
        ]);
    }

    public function update(UpdateCodexEntryRequest $request, CodexEntry $codexEntry, CodexEntrySaver $saver): RedirectResponse
    {
        $saver->update(
            $codexEntry,
            $request->validated(),
            CodexMediaUploads::fromRequest($request),
            $request->user(),
        );

        return $this->redirectAfterSave(
            $request,
            ['codex.edit', $codexEntry],
            ['codex.show', $codexEntry],
        );
    }

    public function destroy(CodexEntry $codexEntry): RedirectResponse
    {
        $this->authorize('update', $codexEntry->project);

        $project = $codexEntry->project;
        $type = $codexEntry->type;

        // The model hook removes files before database cascades remove media rows.
        $codexEntry->delete();

        return redirect()->route('projects.codex.index', [$project, $type->routeKey()]);
    }

    public function duplicate(DuplicateEntityRequest $request, CodexEntry $codexEntry, CodexEntryDuplicator $duplicator): RedirectResponse
    {
        $copy = $duplicator->duplicate($codexEntry, $request->validated('name'));

        return redirect()->route('codex.edit', $copy)->with('status', 'duplicated');
    }
}
