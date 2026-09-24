<?php

namespace App\Http\Controllers;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Http\Requests\RevertRevisionRequest;
use App\Http\Requests\RevertSaveRequest;
use App\Http\Requests\ShowRevisionsRequest;
use App\Models\Project;
use App\Models\Revision;
use App\Services\RevisionComparePage;
use App\Services\RevisionHistory;
use App\Services\RevisionReverter;
use App\Support\AutosavableFields;
use App\Support\Crumb;
use App\Support\SavePoint;
use App\View\Components\RevisionsLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(ShowRevisionsRequest $request, string $entity, int $id, RevisionHistory $history): View
    {
        $model = $request->revisionable();
        $project = $model->revisionProject();
        $this->authorize('view', $project);

        $filters = [
            'field' => $request->fieldFilter(),
            'label' => trim((string) $request->validated('label')),
            'manualOnly' => $request->boolean('manual'),
        ];

        $savePoints = $history->forEntity($model, $filters, $request->integer('page', 1))
            // Preserve filters across pagination.
            ->withQueryString();

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
            'baseHashes' => AutosavableFields::currentHashes($model),
            'fieldOptions' => $history->fieldOptions($model),
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

    /** Compares all entity fields at two save points. */
    public function compare(ShowRevisionsRequest $request, string $entity, int $id, RevisionComparePage $page): View
    {
        $model = $request->revisionable();
        $project = $model->revisionProject();
        $this->authorize('view', $project);

        $field = $request->fieldFilter();
        $heading = $this->revisionsLeaf($entity, $model->revisionDisplayName(), $field, __('Compare'), __('compare'));

        return view('revisions.compare', [
            ...$page->build($model, $field, $request->validated('from'), $request->validated('to')),
            'project' => $project,
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'entityName' => $model->revisionDisplayName(),
            // Restore buttons use current hashes for conflict detection.
            'baseHashes' => AutosavableFields::currentHashes($model),
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

    /** Reverts one field and returns conflicts to the page as an actionable alert. */
    public function revert(RevertRevisionRequest $request, Revision $revision, RevisionReverter $reverter): RedirectResponse
    {
        $entity = $this->revisionableOrFail($revision);

        try {
            $reverter->revertField($entity, $revision, $request->validated('base_hash'), $request->user());
        } catch (RevisionConflictException $exception) {
            return back()->with(RevisionsLayout::ERROR_KEY, $exception->getMessage());
        }

        return back()->with('status', 'reverted');
    }

    /** Restores every field touched by one save to its preceding value. */
    public function revertSave(RevertSaveRequest $request, string $save, RevisionReverter $reverter): RedirectResponse
    {
        $group = $request->saveGroup();
        $entity = $this->revisionableOrFail($group->first());

        // A baseline has no preceding saved value to restore.
        if (SavePoint::dominantOrigin($group->pluck('origin')) === RevisionOrigin::Baseline) {
            return back()->with(RevisionsLayout::ERROR_KEY, __('That is the initial value — there is no earlier version to go back to.'));
        }

        try {
            $restored = $reverter->revertSave($entity, $group, $request->validated('base_hashes'), $request->user());
        } catch (RevisionConflictException $exception) {
            return back()->with(RevisionsLayout::ERROR_KEY, $exception->getMessage());
        }

        return redirect()
            ->route(AutosavableFields::editRouteFor(AutosavableFields::slugFor($entity::class)), $entity)
            ->with('status', 'reverted-save')
            ->with('restored_fields', array_map(Str::headline(...), $restored));
    }

    /** A revision of a deleted entity is a 404, but only after the project check. */
    private function revisionableOrFail(Revision $revision): Model
    {
        $this->authorize('update', $revision->owningProject());

        return $revision->revisionable ?? abort(404);
    }

    /** Resolves the entity and authorizes history access through its project. */
    private function resolveEntity(string $entity, int $id): Model
    {
        $model = AutosavableFields::modelFor($entity)::findOrFail($id);

        $this->authorize('view', $model->revisionProject());

        return $model;
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
