<?php

namespace App\Http\Controllers;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Models\Project;
use App\Models\Revision;
use App\Services\RevisionComparison;
use App\Services\RevisionHistory;
use App\Services\RevisionReverter;
use App\Support\AutosavableFields;
use App\Support\Crumb;
use App\Support\FieldComparison;
use App\Support\SavePoint;
use App\View\Components\RevisionsLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Shows revision history and comparisons, and handles field or save-point reverts.
 *
 * Revision routes identify an entity by registry slug and ID. These routes have
 * no project parameter, so this controller also builds their breadcrumb tail.
 */
class RevisionController extends Controller
{
    /** Shows one entity's filtered, bookmarkable save-point history. */
    public function index(Request $request, string $entity, int $id, RevisionHistory $history): View
    {
        $model = $this->resolveEntity($entity, $id);

        $filters = [
            'field' => $this->resolveFieldFilter($entity, $request),
            'label' => trim((string) $request->query('label', '')),
            'manualOnly' => $request->boolean('manual'),
        ];

        $savePoints = $history->forEntity($model, $filters, $request->integer('page', 1))
            // Preserve filters across pagination.
            ->withQueryString();

        $project = $model->revisionProject();
        $heading = $this->revisionsLeaf($entity, $model->revisionDisplayName(), $filters['field'], __('History'), __('history'));

        return view('revisions.index', [
            'project' => $project,
            'entity' => $entity,
            'id' => $id,
            'field' => $filters['field'],
            'label' => $filters['label'],
            'manualOnly' => $filters['manualOnly'],
            'entityName' => $model->revisionDisplayName(),
            'savePoints' => $savePoints,
            // A filtered page still needs hashes for every field in the save.
            'baseHashes' => $this->baseHashes($entity, $model),
            'fieldOptions' => $this->fieldOptions($entity, $model),
            'editUrl' => route(AutosavableFields::editRouteFor($entity), $model),
            'heading' => $heading,
            'breadcrumbTrail' => $this->revisionsTrail($project, $heading),
        ]);
    }

    /** Redirects the old field history URL to the field filter. */
    public function field(string $entity, int $id, string $field): RedirectResponse
    {
        $this->resolveEntity($entity, $id);

        AutosavableFields::resolveField($entity, $field);

        return redirect()->route('revisions.index', [
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
        ]);
    }

    /**
     * The fields of this entity that have any history, as `field => headline`.
     *
     * @return array<string, string>
     */
    private function fieldOptions(string $entity, Model $model): array
    {
        $fields = AutosavableFields::fieldsFor($entity);

        $withHistory = $model->revisions()->getQuery()->reorder()->distinct()->pluck('field')->all();

        $options = [];

        foreach (array_keys($fields) as $field) {
            if (in_array($field, $withHistory, true)) {
                $options[$field] = Str::headline($field);
            }
        }

        return $options;
    }

    /**
     * Compares all entity fields at two save points.
     * Missing points use the newest pair. Reversed points become chronological.
     */
    public function compare(
        Request $request,
        string $entity,
        int $id,
        RevisionHistory $history,
        RevisionComparison $comparison,
    ): View {
        $model = $this->resolveEntity($entity, $id);
        $field = $this->resolveFieldFilter($entity, $request);

        // Field filters do not limit the save-point pickers.
        $points = $history->savePoints($model);
        [$from, $to] = $this->resolvePair($points, $request);

        $comparisons = ($from !== null && $to !== null)
            ? $comparison->between($model, $from, $to, $field)
            : collect();

        $project = $model->revisionProject();
        $heading = $this->revisionsLeaf($entity, $model->revisionDisplayName(), $field, __('Compare'), __('compare'));

        return view('revisions.compare', [
            'project' => $project,
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'entityName' => $model->revisionDisplayName(),
            'points' => $points,
            'from' => $from,
            'to' => $to,
            'comparisons' => $comparisons,
            'unchangedFields' => $this->unchangedFields($entity, $comparisons, $from, $to),
            // Restore buttons use current hashes for conflict detection.
            'baseHashes' => $this->baseHashes($entity, $model),
            'savesApart' => $this->savesApart($points, $from, $to),
            'editUrl' => route(AutosavableFields::editRouteFor($entity), $model),
            'heading' => $heading,
            'breadcrumbTrail' => $this->revisionsTrail($project, $heading),
        ]);
    }

    /** Redirects the old revision-ID comparison URL to save-point IDs. */
    public function fieldCompare(Request $request, string $entity, int $id, string $field): RedirectResponse
    {
        $model = $this->resolveEntity($entity, $id);

        AutosavableFields::resolveField($entity, $field);

        return redirect()->route('revisions.compare', array_filter([
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'from' => $this->saveIdOf($model, $request->query('from')),
            'to' => $this->saveIdOf($model, $request->query('to')),
        ]));
    }

    /** Returns a revision's save ID, or null for a missing or stale ID. */
    private function saveIdOf(Model $model, mixed $revisionId): ?string
    {
        if (! is_numeric($revisionId)) {
            return null;
        }

        return $model->revisions()->whereKey($revisionId)->value('save_id');
    }

    /**
     * The two save points to compare, oldest first.
     *
     * @param  Collection<int, SavePoint>  $points  Newest first.
     * @return array{0: ?SavePoint, 1: ?SavePoint}
     */
    private function resolvePair(Collection $points, Request $request): array
    {
        if ($points->count() < 2) {
            return [null, null];
        }

        $fromId = $request->query('from');
        $toId = $request->query('to');

        if ($fromId === null || $toId === null) {
            return [$points->get(1), $points->get(0)];
        }

        $from = $this->pointOrFail($points, $fromId);
        $to = $this->pointOrFail($points, $toId);

        return $points->search($from, strict: true) < $points->search($to, strict: true)
            ? [$to, $from]
            : [$from, $to];
    }

    /**
     * @param  Collection<int, SavePoint>  $points
     */
    private function pointOrFail(Collection $points, mixed $saveId): SavePoint
    {
        $point = $points->firstWhere('saveId', $saveId);

        abort_if($point === null, 404);

        return $point;
    }

    /**
     * @param  Collection<int, FieldComparison>  $comparisons
     * @return list<string> Registered field labels without a change.
     */
    private function unchangedFields(string $entity, Collection $comparisons, ?SavePoint $from, ?SavePoint $to): array
    {
        if ($from === null || $to === null) {
            return [];
        }

        $changed = $comparisons->pluck('field')->all();
        $fields = AutosavableFields::fieldsFor($entity);

        return array_values(array_map(
            fn (string $field): string => Str::headline($field),
            array_diff(array_keys($fields), $changed),
        ));
    }

    /** @return array<string, string> Current hashes by registered field. */
    private function baseHashes(string $entity, Model $model): array
    {
        $fields = AutosavableFields::fieldsFor($entity);
        $hashes = [];

        foreach (array_keys($fields) as $field) {
            $hashes[$field] = hash('sha256', (string) ($model->getAttribute($field) ?? ''));
        }

        return $hashes;
    }

    /** @param Collection<int, SavePoint> $points */
    private function savesApart(Collection $points, ?SavePoint $from, ?SavePoint $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        return abs($points->search($from, strict: true) - $points->search($to, strict: true));
    }

    /** Reverts one field and returns conflicts to the page as an actionable alert. */
    public function revert(Request $request, Revision $revision, RevisionReverter $reverter): RedirectResponse
    {
        $entity = $revision->revisionable;

        $this->authorize('update', $entity->revisionProject());

        $validated = $request->validate([
            'base_hash' => ['required', 'string'],
        ]);

        try {
            $reverter->revertField($entity, $revision, $validated['base_hash'], $request->user());
        } catch (RevisionConflictException $exception) {
            return back()->with(RevisionsLayout::ERROR_KEY, $exception->getMessage());
        }

        return back()->with('status', 'reverted');
    }

    /**
     * Restores every field touched by one save to its preceding value.
     * The save ID is only a lookup key; authorization still uses the owning project.
     */
    public function revertSave(Request $request, string $save, RevisionReverter $reverter): RedirectResponse
    {
        // Do not select revision values for the save-point lookup.
        $group = Revision::query()
            ->where('save_id', $save)
            ->select(['id', 'save_id', 'field', 'created_at', 'origin', 'revisionable_type', 'revisionable_id'])
            ->get();

        abort_if($group->isEmpty(), 404);

        $entity = $group->first()->revisionable;

        abort_if($entity === null, 404);

        $this->authorize('update', $entity->revisionProject());

        $validated = $request->validate([
            'base_hashes' => ['required', 'array'],
            'base_hashes.*' => ['required', 'string'],
        ]);

        // A baseline has no preceding saved value to restore.
        if (SavePoint::dominantOrigin($group->pluck('origin')) === RevisionOrigin::Baseline) {
            return back()->with(RevisionsLayout::ERROR_KEY, __('That is the initial value — there is no earlier version to go back to.'));
        }

        try {
            $restored = $reverter->revertSave($entity, $group, $validated['base_hashes'], $request->user());
        } catch (RevisionConflictException $exception) {
            return back()->with(RevisionsLayout::ERROR_KEY, $exception->getMessage());
        }

        return redirect()
            ->route(AutosavableFields::editRouteFor(AutosavableFields::slugFor($entity::class)), $entity)
            ->with('status', 'reverted-save')
            ->with('restored_fields', array_map(Str::headline(...), $restored));
    }

    /** Resolves the entity and authorizes history access through its project. */
    private function resolveEntity(string $entity, int $id): Model
    {
        $model = AutosavableFields::modelFor($entity)::findOrFail($id);

        $this->authorize('view', $model->revisionProject());

        return $model;
    }

    /** Resolves a registered field filter or returns null for all fields. */
    private function resolveFieldFilter(string $entity, Request $request): ?string
    {
        $field = trim((string) $request->query('field', ''));

        if ($field === '') {
            return null;
        }

        AutosavableFields::resolveField($entity, $field);

        return $field;
    }

    /** @return list<Crumb> */
    private function revisionsTrail(Project $project, string $leaf): array
    {
        return [
            new Crumb(__('Dashboard'), route('projects.show', $project)),
            new Crumb(__('Tools'), route('projects.tools.home', $project)),
            new Crumb(__('Revisions'), route('projects.revisions.index', $project)),
            new Crumb($leaf, current: true),
        ];
    }

    /** Builds the entity and optional field label for a breadcrumb leaf. */
    private function revisionsLeaf(string $entity, string $entityName, ?string $field, string $whole, string $scoped): string
    {
        $subject = $field === null ? $whole : Str::headline($field).' '.$scoped;

        return sprintf('%s "%s" — %s', Str::headline($entity), $entityName, $subject);
    }
}
