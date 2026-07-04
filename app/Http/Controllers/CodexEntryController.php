<?php

namespace App\Http\Controllers;

use App\Enums\CodexEntryType;
use App\Enums\CodexMediaCollection;
use App\Http\Requests\StoreCodexEntryRequest;
use App\Http\Requests\UpdateCodexEntryRequest;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Project;
use App\Services\AttributeTimeline;
use App\Services\CodexMediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CodexEntryController extends Controller
{
    public function index(Request $request, Project $project, string $type): View
    {
        $this->authorize('view', $project);

        $entryType = CodexEntryType::fromRouteKey($type);

        // Only name is sortable on this index (entries are not position-ordered).
        $sort = 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $entries = CodexEntry::query()
            ->where('project_id', $project->id)
            ->where('type', $entryType->value)
            ->with(['tags', 'aliases', 'cover'])
            // Search matches the entry name OR any of its aliases (LIKE, portable on SQLite).
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

        return view('codex.index', [
            'project' => $project,
            'type' => $entryType,
            'entries' => $entries,
            'tags' => $project->tags()->orderBy('name')->get(),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Project $project, string $type): View
    {
        $this->authorize('update', $project);

        $entryType = CodexEntryType::fromRouteKey($type);

        return view('codex.create', [
            'project' => $project,
            'type' => $entryType,
            // On create only the Start-baseline value is captured per applicable attribute;
            // the full period editor appears on edit (periods need an entry id).
            'attributes' => $this->applicableAttributes($project, $entryType),
        ]);
    }

    public function store(StoreCodexEntryRequest $request, Project $project, string $type, CodexMediaService $media): RedirectResponse
    {
        $entryType = CodexEntryType::fromRouteKey($type);
        $validated = $request->validated();

        DB::transaction(function () use ($request, $project, $entryType, $validated, $media) {
            $entry = $project->codexEntries()->create([
                'type' => $entryType,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncAliases($entry, $validated['aliases'] ?? []);
            $entry->tags()->sync($this->resolveTags($project, $validated['tags'] ?? []));
            $this->seedAttributeBaselines($entry, $entryType, $validated['attribute_baselines'] ?? []);

            // Files written last so a failure in the steps above rolls back without
            // leaving orphan files on disk.
            $this->applyMediaChanges($entry, $request, $media);
        });

        return redirect()->route('projects.codex.index', [$project, $entryType->routeKey()]);
    }

    public function edit(CodexEntry $codexEntry): View
    {
        $this->authorize('update', $codexEntry->project);

        $codexEntry->load('aliases', 'tags', 'media', 'attributeValues.startEvent');

        $project = $codexEntry->project;
        $startEvent = $this->startEvent($project);

        return view('codex.edit', [
            'project' => $project,
            'type' => $codexEntry->type,
            'entry' => $codexEntry,
            // Pre-compute each attribute's periods in the controller — no timeline math in Blade.
            'sheets' => $this->timelineSheets($codexEntry, $startEvent),
            'startEvent' => $startEvent,
            // Anchor choices for the "Add period" row.
            'events' => $project->events()->orderBy('event_datetime')->orderBy('id')->get(),
        ]);
    }

    public function update(UpdateCodexEntryRequest $request, CodexEntry $codexEntry, CodexMediaService $media): RedirectResponse
    {
        $project = $codexEntry->project;
        $validated = $request->validated();

        DB::transaction(function () use ($request, $project, $codexEntry, $validated, $media) {
            $codexEntry->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncAliases($codexEntry, $validated['aliases'] ?? []);
            $codexEntry->tags()->sync($this->resolveTags($project, $validated['tags'] ?? []));

            // Files written last (see store): removals then uploads.
            $this->applyMediaChanges($codexEntry, $request, $media);
        });

        return redirect()->route('projects.codex.index', [$project, $codexEntry->type->routeKey()]);
    }

    public function destroy(CodexEntry $codexEntry): RedirectResponse
    {
        $this->authorize('update', $codexEntry->project);

        $project = $codexEntry->project;
        $type = $codexEntry->type;

        // FK cascadeOnDelete removes aliases, tag pivots, attribute values and media rows.
        // The entry's `deleting` hook purges the media files off disk first (the cascade
        // drops rows but never files) — see CodexEntry::booted() / CodexMediaService::purge.
        $codexEntry->delete();

        return redirect()->route('projects.codex.index', [$project, $type->routeKey()]);
    }

    /**
     * Replace the entry's aliases with the submitted list (empty/duplicate entries dropped).
     *
     * @param  array<int, string|null>  $aliases
     */
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
     * Resolve submitted tag names into project-scoped tag ids, creating any that don't exist yet.
     *
     * Analogous to SceneController::resolveHappensDuringEvent: firstOrCreate is scoped to the
     * project, so a name that already exists in another project produces a fresh, isolated tag
     * here rather than leaking the other project's row across the boundary.
     *
     * @param  array<int, string|null>  $names
     * @return array<int, int>
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

    /**
     * Apply the form's media changes: process removals first (frees the cover slot and
     * releases positions), then store the new cover and reference uploads. The service
     * owns disk paths, naming and the single-cover rule.
     */
    private function applyMediaChanges(CodexEntry $entry, FormRequest $request, CodexMediaService $media): void
    {
        // remove_media[] only exists on the update request; the ids were already
        // validated to belong to this entry, so this is just the deletion pass.
        $idsToRemove = (array) $request->validated('remove_media', []);

        if ($idsToRemove !== []) {
            $entry->media()->whereIn('id', $idsToRemove)->get()
                ->each(fn ($mediaRow) => $media->remove($mediaRow));
        }

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

    /**
     * The project's attribute definitions that apply to the given entry type, in display order.
     *
     * applies_to is a JSON column, so we filter in PHP (portable on SQLite) rather than with a
     * DB JSON query — the attribute count per project is small.
     *
     * @return Collection<int, CodexAttribute>
     */
    private function applicableAttributes(Project $project, CodexEntryType $type): Collection
    {
        return $project->codexAttributes()
            ->orderBy('position')
            ->get()
            ->filter(fn (CodexAttribute $attribute) => $attribute->appliesTo($type))
            ->values();
    }

    /**
     * Seed a Start-anchored baseline for every applicable attribute, using the submitted value
     * (or an empty string when none was provided). This keeps the gap-free invariant: an entry
     * ends up with a baseline for each attribute it should carry.
     *
     * @param  array<int|string, string|null>  $baselines
     */
    private function seedAttributeBaselines(CodexEntry $entry, CodexEntryType $type, array $baselines): void
    {
        foreach ($this->applicableAttributes($entry->project, $type) as $attribute) {
            $value = (string) ($baselines[$attribute->id] ?? '');

            (new AttributeTimeline($entry, $attribute))->ensureBaseline($value);
        }
    }

    /**
     * Build the timeline editor data for the edit view: one entry per applicable attribute with
     * its Start baseline (if any) split out from the later periods, so the Blade stays dumb.
     *
     * @return Collection<int, array{attribute: CodexAttribute, baseline: ?CodexAttributeValue, periods: Collection}>
     */
    private function timelineSheets(CodexEntry $entry, Event $startEvent): Collection
    {
        return $this->applicableAttributes($entry->project, $entry->type)
            ->map(function (CodexAttribute $attribute) use ($entry, $startEvent) {
                $periods = (new AttributeTimeline($entry, $attribute))->periods();

                return [
                    'attribute' => $attribute,
                    // The locked, non-removable Start row (null when the entry has no baseline yet).
                    'baseline' => $periods->firstWhere('start_event_id', $startEvent->id),
                    // Every later period, each removable.
                    'periods' => $periods->reject(fn ($period) => $period->start_event_id === $startEvent->id)->values(),
                ];
            });
    }

    /**
     * The project's Start event (earliest fixed event, year 0000) — the locked baseline anchor.
     */
    private function startEvent(Project $project): Event
    {
        return $project->events()
            ->where('is_fixed', true)
            ->orderBy('event_datetime')
            ->orderBy('id')
            ->firstOrFail();
    }
}
