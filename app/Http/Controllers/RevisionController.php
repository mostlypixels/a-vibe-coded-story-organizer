<?php

namespace App\Http\Controllers;

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Services\RevisionComparison;
use App\Services\RevisionHistory;
use App\Services\RevisionRecorder;
use App\Support\AutosavableFields;
use App\Support\FieldComparison;
use App\Support\SavePoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * History and compare views for one revisionable (entity, field) pair
 * (expanded/ui.md "History page"/"Compare view", handoff.md §5.1/§5.3), plus
 * the revert action (task 11).
 *
 * `index`/`compare` resolve the {entity} slug through App\Support\
 * AutosavableFields exactly like FieldAutosaveController (task 6) — an
 * unregistered slug never reaches this class at all, the router 404s it
 * first (routes/web.php's `->whereIn('entity', AutosavableFields::slugs())`).
 * `revert` instead resolves straight from the {revision} route-model binding
 * (handoff.md §9.3's routes table) and derives the slug itself via
 * AutosavableFields::slugFor(), since a Revision's polymorphic
 * `revisionable_type` is always a real, already-registered model class.
 *
 * Any purge/retention UI (tasks 12-13) is deliberately out of scope here.
 */
class RevisionController extends Controller
{
    /**
     * The "back to editing" link for each registered slug. Not part of
     * App\Support\AutosavableFields — that registry is about which
     * model+field pairs autosave, not the app's own edit-route naming — but
     * small and stable enough to keep as a plain map here rather than
     * deriving it (e.g. `Str::plural($entity).'.edit'` breaks for `codex`,
     * which is already plural).
     *
     * @var array<string, string>
     */
    private const EDIT_ROUTES = [
        'project' => 'projects.edit',
        'act' => 'acts.edit',
        'chapter' => 'chapters.edit',
        'plotline' => 'plotlines.edit',
        'event' => 'events.edit',
        'scene' => 'scenes.edit',
        'codex' => 'codex.edit',
    ];

    /**
     * One entity's history, as the save points a writer actually made
     * (expanded/ui.md "History page").
     *
     * `?field=`, `?label=`, `?manual=` and `?page=` are the whole state — the
     * page is a pure GET, bookmarkable and shareable, with no session-held
     * filter to go stale behind the reader's back.
     *
     * No query logic lives here: {@see RevisionHistory} owns it, and never
     * hydrates `revisions.value` (the stored `summary_html` / `change_count`
     * exist precisely so a list page never has to diff or read a value).
     */
    public function index(Request $request, string $entity, int $id, RevisionHistory $history): View
    {
        $model = $this->resolveEntity($entity, $id);

        $filters = [
            'field' => $this->resolveFieldFilter($entity, $request),
            'label' => trim((string) $request->query('label', '')),
            'manualOnly' => $request->boolean('manual'),
        ];

        $savePoints = $history->forEntity($model, $filters, $request->integer('page', 1))
            // Every filter has to survive a page change, or paging silently
            // widens what the reader is looking at.
            ->withQueryString();

        return view('revisions.index', [
            // The owning Project drives the shared <x-revisions-layout> sidebar;
            // already resolved (and authorized against) in resolveEntity().
            'project' => $model->revisionProject(),
            'entity' => $entity,
            'id' => $id,
            'field' => $filters['field'],
            'label' => $filters['label'],
            'manualOnly' => $filters['manualOnly'],
            'entityName' => $model->revisionDisplayName(),
            'savePoints' => $savePoints,
            // Only fields that actually have history are offered: a filter that
            // can only ever return nothing is not a choice worth showing.
            'fieldOptions' => $this->fieldOptions($entity, $model),
            'editUrl' => route(self::EDIT_ROUTES[$entity], $model),
        ]);
    }

    /**
     * The pre-save-point history URL, kept as a redirect.
     *
     * The field-scoped page is gone as a *page*: it is the same page with
     * `?field=` set. One concept, one screen — and an old link still arrives
     * showing what it was about.
     */
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
        [, $fields] = AutosavableFields::REGISTRY[$entity];

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
     * Compare an entity at two save points, field by field
     * (expanded/ui.md "Compare page").
     *
     * Entity-level, not field-level: two save points are two *moments*, and the
     * page answers "what is different about this act between them" — including
     * a field neither save wrote directly but that changed in between. See
     * App\Support\EntitySnapshot for why that is correct rather than surprising.
     *
     * `from`/`to` are save ids. Either missing falls back to the two most
     * recent points ("what changed last" is what a reader lands here to ask),
     * and a reversed pair is put back in chronological order rather than
     * rejected — the direction of a diff is not the reader's to get wrong.
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

        // Both pickers offer every save point, unfiltered: narrowing the option
        // list by the field filter would hide the very save the reader is
        // trying to compare against.
        $points = $history->savePoints($model);
        [$from, $to] = $this->resolvePair($points, $request);

        $comparisons = ($from !== null && $to !== null)
            ? $comparison->between($model, $from, $to, $field)
            : collect();

        return view('revisions.compare', [
            // The owning Project drives the shared <x-revisions-layout> sidebar;
            // already resolved (and authorized against) in resolveEntity().
            'project' => $model->revisionProject(),
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'entityName' => $model->revisionDisplayName(),
            'points' => $points,
            'from' => $from,
            'to' => $to,
            'comparisons' => $comparisons,
            'unchangedFields' => $this->unchangedFields($entity, $comparisons, $from, $to),
            // One base hash per field, for the per-field restore buttons: the
            // same conflict check the autosave endpoint uses, so restoring
            // against a field someone else has since changed 409s rather than
            // silently overwriting their work.
            'baseHashes' => $this->baseHashes($entity, $model),
            'savesApart' => $this->savesApart($points, $from, $to),
            'editUrl' => route(self::EDIT_ROUTES[$entity], $model),
        ]);
    }

    /**
     * The pre-save-point compare URL, translated and redirected.
     *
     * Its `from`/`to` were *revision* ids; a revision knows the save point it
     * belongs to, so the equivalent entity-level comparison is one lookup away.
     * The field it was scoped to survives as the `?field=` filter, so a bookmark
     * still lands on a page about what the bookmark was about.
     */
    public function fieldCompare(Request $request, string $entity, int $id, string $field): RedirectResponse
    {
        $model = $this->resolveEntity($entity, $id);

        // Keeps the unknown-field 404 in one place, shared with the autosave
        // endpoint — a legacy URL naming a field that no longer exists is not
        // something to redirect helpfully.
        AutosavableFields::resolveField($entity, $field);

        return redirect()->route('revisions.compare', array_filter([
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'from' => $this->saveIdOf($model, $request->query('from')),
            'to' => $this->saveIdOf($model, $request->query('to')),
        ]));
    }

    /**
     * The save point a revision id belongs to, or null when the id is absent or
     * gone. A stale bookmark loses its pair, not its page.
     */
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
            // The two most recent points, oldest first.
            return [$points->get(1), $points->get(0)];
        }

        $from = $this->pointOrFail($points, $fromId);
        $to = $this->pointOrFail($points, $toId);

        // The list is newest-first, so the lower index is the newer point.
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
     * The registered fields the comparison found unchanged, by headline, for
     * the page's "N other fields unchanged" line.
     *
     * Derived by subtracting the changed fields from the registry rather than
     * asked of RevisionComparison, which deliberately returns only what differs.
     *
     * @param  Collection<int, FieldComparison>  $comparisons
     * @return list<string>
     */
    private function unchangedFields(string $entity, Collection $comparisons, ?SavePoint $from, ?SavePoint $to): array
    {
        if ($from === null || $to === null) {
            return [];
        }

        $changed = $comparisons->pluck('field')->all();
        [, $fields] = AutosavableFields::REGISTRY[$entity];

        return array_values(array_map(
            fn (string $field): string => Str::headline($field),
            array_diff(array_keys($fields), $changed),
        ));
    }

    /**
     * The current stored value's hash for every registered field.
     *
     * @return array<string, string>
     */
    private function baseHashes(string $entity, Model $model): array
    {
        [, $fields] = AutosavableFields::REGISTRY[$entity];
        $hashes = [];

        foreach (array_keys($fields) as $field) {
            $hashes[$field] = hash('sha256', (string) ($model->getAttribute($field) ?? ''));
        }

        return $hashes;
    }

    /**
     * How many save points lie between the two being compared — the "12 saves
     * apart" in the pair summary.
     *
     * @param  Collection<int, SavePoint>  $points
     */
    private function savesApart(Collection $points, ?SavePoint $from, ?SavePoint $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        return abs($points->search($from, strict: true) - $points->search($to, strict: true));
    }

    /**
     * Revert one field to an older revision's value (task 11,
     * expanded/architecture.md "Revert", handoff.md §5.2).
     *
     * Additive, never destructive: the reverted-away-from state stays exactly
     * where it is in history, and this always writes a *new* `origin: revert`
     * row rather than touching any existing revision — reverting twice in a
     * row ("undo a revert by reverting again", handoff.md §5.2) works for
     * free, since every revert is just another forward-moving write.
     *
     * Takes the same base-hash conflict check as FieldAutosaveController::
     * update() (task 6) — a revert against a field someone else already
     * changed since the page loaded must 409, not silently clobber their
     * work — and re-runs the same validation/sanitization a normal save
     * does, via AutosavableFields::validationRule() and the model's own
     * mutators (e.g. SanitizesRichHtml for a rich field), so an older
     * revision's value can never bypass rules tightened since it was
     * recorded.
     */
    public function revert(Request $request, Revision $revision, RevisionRecorder $recorder): RedirectResponse
    {
        $entity = $revision->revisionable;
        $field = $revision->field;
        $slug = AutosavableFields::slugFor($entity::class);

        $this->authorize('update', $entity->revisionProject());

        $validated = $request->validate([
            'base_hash' => ['required', 'string'],
        ]);

        $currentValue = (string) ($entity->getAttribute($field) ?? '');
        $storedHash = hash('sha256', $currentValue);

        if ($validated['base_hash'] !== $storedHash) {
            abort(409, __('This field was changed elsewhere.'));
        }

        Validator::make(
            ['value' => $revision->value],
            ['value' => AutosavableFields::validationRule($slug, $field)],
        )->validate();

        $entity->{$field} = $revision->value;
        $entity->save(); // Mutators run here, e.g. SanitizesRichHtml for rich fields.

        $storedValue = (string) ($entity->fresh()->getAttribute($field) ?? '');

        $recorder->record(
            $entity,
            $field,
            $storedValue,
            $request->user(),
            RevisionOrigin::Revert,
            __('Reverted to :date', ['date' => $revision->created_at->format('d F H:i')]),
        );

        return back()->with('status', 'reverted');
    }

    /**
     * The entity a history or compare page is about, authorized.
     *
     * The field-free half of {@see self::resolve()}: with history addressed per
     * entity, a field is a *filter* rather than part of the address, so
     * resolving one no longer needs a field to resolve at all.
     *
     * Authorization walks to the owning Project, as everywhere else — the id in
     * the URL is a lookup key, never a capability.
     *
     * Reading history is a `view` capability, not `update`: history, compare
     * and the browser landing all authorize `view`, while the mutating revert
     * action deliberately still demands `update`. In this single-owner app the
     * two resolve to the same user today, but the altitude is set on purpose so
     * a future view-only collaborator could read history without being able to
     * revert it.
     */
    private function resolveEntity(string $entity, int $id): Model
    {
        $model = AutosavableFields::modelFor($entity)::findOrFail($id);

        $this->authorize('view', $model->revisionProject());

        return $model;
    }

    /**
     * The `?field=` filter: null for "all fields", or a field registered for
     * this entity.
     *
     * An unregistered field 404s through {@see AutosavableFields::resolveField()}
     * rather than being quietly ignored — the same contract
     * FieldAutosaveController holds, kept in one place so the two can never
     * drift. A filter naming a field that does not exist is a broken link, and
     * silently showing everything would hide that.
     */
    private function resolveFieldFilter(string $entity, Request $request): ?string
    {
        $field = trim((string) $request->query('field', ''));

        if ($field === '') {
            return null;
        }

        AutosavableFields::resolveField($entity, $field);

        return $field;
    }
}
