<?php

namespace App\Http\Controllers;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\DuplicateEntityRequest;
use App\Http\Requests\StoreCodexEntryRequest;
use App\Http\Requests\UpdateCodexEntryRequest;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Models\Scene;
use App\Services\AttributeTimeline;
use App\Services\CodexEntryDuplicator;
use App\Services\CodexMediaService;
use App\Services\SceneReferenceMatcher;
use App\Support\DuplicateName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CodexEntryController extends Controller
{
    use RecordsManualRevisions;
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
            ->get();

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
            'attributes' => $this->applicableAttributes($project, $entryType),
            'projectTags' => $project->tags()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCodexEntryRequest $request, Project $project, string $type, CodexMediaService $media, SceneReferenceMatcher $matcher): RedirectResponse
    {
        $entryType = CodexEntryType::fromRouteKey($type);
        $validated = $request->validated();

        // Keep disk operations outside the database transaction.
        [$entry, $pathsToDelete] = DB::transaction(function () use ($request, $project, $entryType, $validated, $media, $matcher) {
            $entry = $project->codexEntries()->create([
                'type' => $entryType,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncAliases($entry, $validated['aliases'] ?? []);
            $entry->tags()->sync($this->resolveTags($project, $validated['tags'] ?? []));
            $this->seedAttributeBaselines($entry, $entryType, $validated['attribute_baselines'] ?? []);

            // Create references atomically with the new name and aliases.
            $matcher->syncProject($project);

            return [$entry, $this->queueMediaRemovals($entry, $request, $media)];
        });

        $media->deleteFiles($pathsToDelete);
        $this->storeMediaUploads($entry, $request, $media);

        return redirect()->route('projects.codex.index', [$project, $entryType->routeKey()]);
    }

    public function edit(CodexEntry $codexEntry): View
    {
        $this->authorize('update', $codexEntry->project);

        $codexEntry->load('aliases', 'tags', 'media', 'attributeValues.startEvent');

        $project = $codexEntry->project;
        $startEvent = $project->startEvent();

        return view('codex.edit', [
            'project' => $project,
            'type' => $codexEntry->type,
            'entry' => $codexEntry,
            'sheets' => $this->timelineSheets($codexEntry, $startEvent),
            'startEvent' => $startEvent,
            'events' => $project->events()->orderBy('event_datetime')->orderBy('id')->get(),
            'projectTags' => $project->tags()->orderBy('name')->get(),
            'referencingScenes' => $this->referencingScenesInTimelineOrder($codexEntry),
            'duplicateSuggestion' => DuplicateName::suggest(
                $codexEntry->name,
                $project->codexEntries()->where('type', $codexEntry->type->value)->pluck('name')
            ),
        ]);
    }

    /**
     * Orders assigned scenes by event and unassigned scenes by manuscript position.
     *
     * @return Collection<int, Scene>
     */
    private function referencingScenesInTimelineOrder(CodexEntry $codexEntry): Collection
    {
        return $codexEntry->referencingScenes()
            ->with('chapter.act', 'event')
            ->get()
            ->sortBy(fn (Scene $scene) => [
                $scene->event === null ? 1 : 0,
                $scene->event?->event_datetime?->timestamp ?? 0,
                $scene->event?->id ?? 0,
                $scene->chapter->act->position,
                $scene->chapter->position,
                $scene->position,
            ])
            ->values();
    }

    public function update(UpdateCodexEntryRequest $request, CodexEntry $codexEntry, CodexMediaService $media, SceneReferenceMatcher $matcher): RedirectResponse
    {
        $project = $codexEntry->project;
        $validated = $request->validated();
        $data = ['name' => $validated['name'], 'description' => $validated['description'] ?? null];

        // Keep disk operations outside the database transaction.
        $pathsToDelete = DB::transaction(function () use ($request, $project, $codexEntry, $validated, $data, $media, $matcher) {
            // Skip the project-wide rescan when matching terms do not change.
            $termsBefore = $this->referenceTerms($codexEntry->name, $codexEntry->aliases()->pluck('alias')->all());

            $beforeAutosavedFields = $this->snapshotAutosaved($codexEntry, $data);

            $codexEntry->update($data);

            $this->syncAliases($codexEntry, $validated['aliases'] ?? []);
            $codexEntry->tags()->sync($this->resolveTags($project, $validated['tags'] ?? []));

            // Save aliases and recomputed references atomically.
            $termsAfter = $this->referenceTerms($codexEntry->name, $codexEntry->aliases()->pluck('alias')->all());

            if ($termsBefore !== $termsAfter) {
                $matcher->syncProject($project);
            }

            $this->recordManualSave($codexEntry, $beforeAutosavedFields);

            return $this->queueMediaRemovals($codexEntry, $request, $media);
        });

        $media->deleteFiles($pathsToDelete);
        $this->storeMediaUploads($codexEntry, $request, $media);

        return $this->redirectAfterSave(
            $request,
            ['codex.edit', $codexEntry],
            ['projects.codex.index', [$project, $codexEntry->type->routeKey()]],
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

    /** @param array<int, string|null> $aliases */
    private function syncAliases(CodexEntry $entry, array $aliases): void
    {
        $entry->aliases()->delete();

        $rows = collect($aliases)
            ->map(fn ($alias) => trim((string) $alias))
            ->filter()
            ->unique()
            ->map(fn ($alias) => ['alias' => $alias])
            ->values()
            ->all();

        if ($rows !== []) {
            $entry->aliases()->createMany($rows);
        }
    }

    /**
     * Builds a sorted, case-sensitive set from the name and aliases.
     * A case change can change reference matches and must trigger a rescan.
     *
     * @param  array<int, string>  $aliases
     * @return array<int, string>
     */
    private function referenceTerms(string $name, array $aliases): array
    {
        return collect($aliases)
            ->push($name)
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string|null>  $names
     * @return array<int, int> Project-scoped tag IDs.
     */
    private function resolveTags(Project $project, array $names): array
    {
        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn ($name) => $project->tags()->firstOrCreate(['name' => $name])->id)
            ->values()
            ->all();
    }

    /** @return array<int, string> Paths to delete after the transaction commits. */
    private function queueMediaRemovals(CodexEntry $entry, FormRequest $request, CodexMediaService $media): array
    {
        $idsToRemove = (array) $request->validated('remove_media', []);

        return $media->queueRemovals($entry, $idsToRemove, $request->hasFile('cover'));
    }

    /**
     * Writes uploads after the database commit.
     * A failed row insert removes its file, so partial failure leaves no orphan.
     */
    private function storeMediaUploads(CodexEntry $entry, FormRequest $request, CodexMediaService $media): void
    {
        if ($request->hasFile('cover')) {
            $media->storeCover($entry, $request->file('cover'));
        }

        if ($request->hasFile('reference_images')) {
            $media->storeMany($entry, CodexMediaCollection::ReferenceImage, $request->file('reference_images'));
        }

        if ($request->hasFile('reference_files')) {
            $media->storeMany($entry, CodexMediaCollection::ReferenceFile, $request->file('reference_files'));
        }
    }

    /** @return Collection<int, CodexAttribute> Filtered in PHP for database portability. */
    private function applicableAttributes(Project $project, CodexEntryType $type): Collection
    {
        return $project->codexAttributes()
            ->orderBy('position')
            ->get()
            ->filter(fn (CodexAttribute $attribute) => $attribute->appliesTo($type))
            ->values();
    }

    /** @param array<int|string, string|null> $baselines */
    private function seedAttributeBaselines(CodexEntry $entry, CodexEntryType $type, array $baselines): void
    {
        foreach ($this->applicableAttributes($entry->project, $type) as $attribute) {
            $value = (string) ($baselines[$attribute->id] ?? '');

            (new AttributeTimeline($entry, $attribute))->ensureBaseline($value);
        }
    }

    /** @return Collection<int, array{attribute: CodexAttribute, baseline: ?CodexAttributeValue, periods: Collection}> */
    private function timelineSheets(CodexEntry $entry, Event $startEvent): Collection
    {
        return $this->applicableAttributes($entry->project, $entry->type)
            ->map(function (CodexAttribute $attribute) use ($entry, $startEvent) {
                $periods = (new AttributeTimeline($entry, $attribute))->periods();

                return [
                    'attribute' => $attribute,
                    'baseline' => $periods->firstWhere('start_event_id', $startEvent->id),
                    'periods' => $periods->reject(fn ($period) => $period->start_event_id === $startEvent->id)->values(),
                ];
            });
    }
}
