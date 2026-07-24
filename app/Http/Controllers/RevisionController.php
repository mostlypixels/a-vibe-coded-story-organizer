<?php

namespace App\Http\Controllers;

use App\Enums\FieldKind;
use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Services\RevisionDiffer;
use App\Services\RevisionRecorder;
use App\Support\AutosavableFields;
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
     * List every revision recorded for one (entity, id, field) triple,
     * newest first, with an optional label search.
     *
     * Never hydrates `value` (00-overview.md's "list queries never hydrate
     * value" invariant, backed by `size_bytes` existing precisely so this
     * page never needs to) — only the columns the table actually renders are
     * selected, and `user` is eager-loaded selecting just `id`/`name`.
     */
    public function index(Request $request, string $entity, int $id, string $field): View
    {
        [$model, $registeredFields] = $this->resolve($entity, $id, $field);

        $entityName = $model->revisionDisplayName();

        $search = trim((string) $request->query('label', ''));

        // The current stored value's hash, carried as a hidden field on every
        // revert form on this page — the same base-hash conflict check the
        // autosave PATCH endpoint uses (FieldAutosaveController), so a revert
        // against stale state 409s instead of silently overwriting newer work.
        $baseHash = hash('sha256', (string) ($model->getAttribute($field) ?? ''));

        // The full, unfiltered id order for this (entity, field) pair — cheap
        // (ids only) and used two ways below: to mark the current-value row
        // (see the docblock on that variable) and to resolve each row's
        // "compare with previous" link even when a label search is active.
        $orderedIds = $model->revisions()
            ->where('field', $field)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->values();

        // Every save that ever writes a revision stores the value it just
        // persisted (RevisionRecorder::record()/ensureBaseline()), and a
        // byte-identical automatic save skips writing entirely rather than
        // leaving a stale row — so the newest revision's value is always
        // exactly the entity's current column value. Marking it "current"
        // therefore never needs to hydrate `value` to compare it.
        $currentRevisionId = $orderedIds->first();

        $revisions = $model->revisions()
            ->where('field', $field)
            ->select(['id', 'created_at', 'user_id', 'label', 'origin'])
            ->with('user:id,name')
            ->when($search !== '', fn ($query) => $query->where('label', 'like', '%'.$search.'%'))
            ->get()
            ->map(function (Revision $revision) use ($orderedIds, $currentRevisionId, $entity, $id, $field) {
                $position = $orderedIds->search($revision->id);
                $previousId = $position === false ? null : $orderedIds->get($position + 1);

                return (object) [
                    'revision' => $revision,
                    'isCurrent' => $revision->id === $currentRevisionId,
                    'compareWithPreviousUrl' => $previousId === null ? null : route('revisions.compare', [
                        'entity' => $entity, 'id' => $id, 'field' => $field,
                        'from' => $previousId, 'to' => $revision->id,
                    ]),
                ];
            });

        return view('revisions.index', [
            // The owning Project drives the shared <x-revisions-layout> sidebar;
            // already resolved (and authorized against) in resolve() above.
            'project' => $model->revisionProject(),
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'entityName' => $entityName,
            'search' => $search,
            'rows' => $revisions,
            'canCompareLatestTwo' => $orderedIds->count() >= 2,
            'compareLatestTwoUrl' => $orderedIds->count() >= 2
                ? route('revisions.compare', [
                    'entity' => $entity, 'id' => $id, 'field' => $field,
                    'from' => $orderedIds->get(1), 'to' => $orderedIds->get(0),
                ])
                : null,
            'fieldSwitcher' => $this->fieldSwitcher($entity, $id, $field, $registeredFields),
            'editUrl' => route(self::EDIT_ROUTES[$entity], $model),
            'baseHash' => $baseHash,
        ]);
    }

    /**
     * Word-level diff between two revisions of the same (entity, field) pair
     * (expanded/architecture.md "Compare — diff service").
     *
     * `from`/`to` are revision ids (query string). When either is omitted,
     * defaults to the two most recent revisions — "what changed last" is the
     * common case a reader lands here to answer.
     */
    public function compare(Request $request, string $entity, int $id, string $field, RevisionDiffer $differ): View
    {
        [$model] = $this->resolve($entity, $id, $field);

        $entityName = $model->revisionDisplayName();

        $baseHash = hash('sha256', (string) ($model->getAttribute($field) ?? ''));

        $revisionsQuery = $model->revisions()->where('field', $field);

        $fromId = $request->query('from');
        $toId = $request->query('to');

        if ($fromId !== null && $toId !== null) {
            $from = (clone $revisionsQuery)->findOrFail($fromId);
            $to = (clone $revisionsQuery)->findOrFail($toId);
        } else {
            // No explicit pair requested: fall back to the two most recent
            // revisions (there may be fewer than two — handled by $to/$from
            // staying null, which the view renders as "not enough history").
            $latestTwo = (clone $revisionsQuery)
                ->select(['id', 'created_at', 'user_id', 'label', 'origin', 'value'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(2)
                ->get();

            $to = $latestTwo->first();
            $from = $latestTwo->get(1);
        }

        // Chronological order for the diff itself, regardless of which one the
        // caller labeled "from"/"to" in the query string.
        if ($from !== null && $to !== null && $from->created_at->gt($to->created_at)) {
            [$from, $to] = [$to, $from];
        }

        $kind = AutosavableFields::kindOf($entity, $field);
        $result = ($from !== null && $to !== null)
            ? $differ->diff($kind, $from->value, $to->value)
            : null;

        return view('revisions.compare', [
            // The owning Project drives the shared <x-revisions-layout> sidebar;
            // already resolved (and authorized against) in resolve() above.
            'project' => $model->revisionProject(),
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'entityName' => $entityName,
            'from' => $from,
            'to' => $to,
            'result' => $result,
            'baseHash' => $baseHash,
        ]);
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
     * Resolve the {entity}/{id}/{field} route segments into the model instance
     * and authorize the request, walking to the owning Project (CLAUDE.md's
     * authorization rule). The slug+field→class step and its unknown-field 404
     * are shared with FieldAutosaveController via AutosavableFields::resolveField().
     *
     * Reading revision history is a `view` capability, not `update`: both
     * history and compare (and the browser landing, RevisionBrowserController)
     * authorize `view`, while the mutating revert action below deliberately
     * still demands `update`. In this single-owner app the two abilities
     * resolve to the same user today, but the altitude is set on purpose so a
     * future view-only collaborator could read history without being able to
     * revert.
     *
     * @return array{0: Model, 1: array<string, FieldKind>}
     */
    private function resolve(string $entity, int $id, string $field): array
    {
        [$modelClass, $registeredFields] = AutosavableFields::resolveField($entity, $field);

        $model = $modelClass::findOrFail($id);

        $this->authorize('view', $model->revisionProject());

        return [$model, $registeredFields];
    }

    /**
     * Links to every other field registered for this entity (ui.md's "field
     * switcher"), so navigating between e.g. a Scene's description/notes/
     * contents history never needs a trip back through the edit page.
     *
     * @param  array<string, FieldKind>  $registeredFields
     * @return Collection<int, object{field: string, label: string, active: bool, url: string}>
     */
    private function fieldSwitcher(string $entity, int $id, string $currentField, array $registeredFields): Collection
    {
        return collect(array_keys($registeredFields))
            ->map(fn (string $otherField) => (object) [
                'field' => $otherField,
                'label' => Str::headline($otherField),
                'active' => $otherField === $currentField,
                'url' => route('revisions.index', ['entity' => $entity, 'id' => $id, 'field' => $otherField]),
            ]);
    }
}
