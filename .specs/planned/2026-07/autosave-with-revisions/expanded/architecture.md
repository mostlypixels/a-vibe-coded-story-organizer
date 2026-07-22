---
title: Autosave With Revisions — Architecture
---

# Architecture

Grounded in `handoff.md` §2–§6, §9.1, §9.3, §9.5, §9.6, §9.8–§9.13, and this session's
reading of `app/Support/RichTextFields.php`, `app/Models/Concerns/SanitizesRichHtml.php`,
`app/Models/Concerns/HasSiblingPosition.php`, `app/Services/SceneReferenceMatcher.php`,
`app/Policies/ProjectPolicy.php`, `app/Models/ImportSetting.php` +
`ImportSettingController`, and `routes/web.php`.

## `App\Support\AutosavableFields`

Sits beside `RichTextFields` (same directory, same "single source of truth" pattern —
see `PlotlineColors`/`CodexMediaRules` precedent) and *references* it for the rich
subset rather than absorbing it, per `handoff.md` §3.1: `RichTextFields` stays scoped to
the rich-HTML feature.

```php
namespace App\Support;

enum FieldKind: string
{
    case Rich = 'rich';       // routed through SanitizesRichHtml, RichTextFields
    case Markdown = 'markdown'; // ValidMarkdown, no sanitizer
    case Plain = 'plain';     // raw <textarea>, e.g. Project.rights
}

class AutosavableFields
{
    /**
     * type slug => [model class, [field => FieldKind, ...]]
     * Slug vocabulary mirrors the app's own URL segments (handoff.md §9.3).
     */
    public const REGISTRY = [
        'project' => [Project::class, [
            'description' => FieldKind::Rich,
            'dedication' => FieldKind::Markdown,
            'acknowledgements' => FieldKind::Markdown,
            'preface' => FieldKind::Markdown,
            'postface' => FieldKind::Markdown,
            'rights' => FieldKind::Plain,
        ]],
        'act' => [Act::class, ['description' => FieldKind::Rich]],
        'chapter' => [Chapter::class, ['description' => FieldKind::Rich]],
        'plotline' => [Plotline::class, ['description' => FieldKind::Rich]],
        'event' => [Event::class, ['description' => FieldKind::Rich]],
        'scene' => [Scene::class, [
            'description' => FieldKind::Rich,
            'notes' => FieldKind::Rich,
            'contents' => FieldKind::Markdown,
        ]],
        'codex' => [CodexEntry::class, ['description' => FieldKind::Rich]],
    ];

    public static function slugs(): array { /* array_keys(REGISTRY) */ }
    public static function modelFor(string $slug): string { /* … */ }
    public static function kindOf(string $slug, string $field): FieldKind { /* … */ }
    public static function windowSeconds(string $slug, string $field): int { /* config('revisions.windows') lookup, Model.field then default */ }
    public static function characterCap(string $slug, string $field): int { /* config('revisions.caps') lookup */ }
    public static function validationRule(string $slug, string $field): array { /* ValidMarkdown / SanitizeHtml / max: per kind+cap */ }
}
```

Note the 14-field table in `handoff.md` §7 groups fields per model+kind; the registry
above is the same data reshaped by slug for URL resolution. `RichTextFields::FIELDS`
gains no new entries — it already lists every rich field this registry needs (`Project`,
`Act`, `Chapter`, `Plotline`, `Event`, `Scene.description`/`Scene.notes`, `CodexEntry`).

## `App\Models\Concerns\HasRevisions`

Alongside `HasSiblingPosition`/`SanitizesRichHtml` in `app/Models/Concerns/`. Resolves
the owning project per the table in `data-model.md`:

```php
trait HasRevisions
{
    abstract public function revisionProject(): Project;

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('created_at');
    }
}
```

Each registered model implements `revisionProject()` per its path (`Act`/`Event`/
`CodexEntry`/`Plotline` return `$this->project`; `Chapter` returns `$this->act->project`;
`Scene` returns `$this->chapter->act->project`; `Project` returns `$this`).

## `App\Services\RevisionRecorder`

The one place that writes to `revisions`, called by the controller (below) and by the
backfill migration (`data-model.md`) — the "identical code path" `handoff.md` §9.2
requires.

```php
class RevisionRecorder
{
    public function record(Model $entity, string $field, string $value, User $user, RevisionOrigin $origin, ?string $label = null): Revision
    {
        $this->ensureBaseline($entity, $field);

        $window = AutosavableFields::windowSeconds(...);
        $open = $origin === RevisionOrigin::Automatic
            ? $entity->revisions()->where('field', $field)
                ->where('origin', RevisionOrigin::Automatic)
                ->where('created_at', '>=', now()->subSeconds($window))
                ->latest('created_at')->first()
            : null;

        if ($open !== null) {
            $open->update(['value' => $value, 'size_bytes' => strlen($value)]);
            return $open;
        }

        return $entity->revisions()->create([
            'field' => $field, 'value' => $value, 'size_bytes' => strlen($value),
            'project_id' => $entity->revisionProject()->id, 'user_id' => $user->id,
            'label' => $label, 'origin' => $origin, 'created_at' => now(),
        ]);
    }

    public function ensureBaseline(Model $entity, string $field): void
    {
        if ($entity->revisions()->where('field', $field)->exists()) {
            return;
        }
        $current = $entity->getAttribute($field);
        if ($current === null || $current === '') {
            return;
        }
        $entity->revisions()->create([
            'field' => $field, 'value' => $current, 'size_bytes' => strlen($current),
            'project_id' => $entity->revisionProject()->id,
            'user_id' => $entity->revisionProject()->user_id,
            'origin' => RevisionOrigin::Baseline,
            'created_at' => $entity->updated_at,
        ]);
    }
}
```

The byte-identical no-op check (§2.2: "typing something and undoing it leaves no
trace") happens one level up, in the controller, before calling `record()` — comparing
the incoming value against the entity's *current* column value, not against the last
revision (coalescing already handles the in-window case).

## `App\Http\Controllers\FieldAutosaveController`

One controller, one action, per `handoff.md` §3.1/§9.3:

```php
class FieldAutosaveController extends Controller
{
    public function update(string $entity, int $id, string $field, Request $request, RevisionRecorder $recorder): JsonResponse
    {
        [$modelClass, $fields] = AutosavableFields::REGISTRY[$entity]; // 404'd at router if unknown, see routes below
        abort_unless(array_key_exists($field, $fields), 404);

        $model = $modelClass::findOrFail($id);
        $this->authorize('update', $model->revisionProject());

        $validated = $request->validate(AutosavableFields::validationRule($entity, $field));

        $storedHash = hash('sha256', $model->getAttribute($field) ?? '');
        if ($request->string('base_hash') !== $storedHash) {
            return response()->json(['message' => __('Changed elsewhere')], 409);
        }

        $model->{$field} = $validated['value'];
        $isFormSubmitOrigin = $request->boolean('manual');
        $model->save(); // mutators run: SanitizesRichHtml for rich fields

        $storedValue = $model->fresh()->getAttribute($field); // stored != sent, handoff.md §11.2/§11.3

        if ($storedValue !== $recorder->lastValueFor($model, $field)) {
            $recorder->record($model, $field, $storedValue, $request->user(),
                $isFormSubmitOrigin ? RevisionOrigin::Manual : RevisionOrigin::Automatic);
        }

        if ($request->boolean('run_matcher') && $model instanceof Scene) {
            app(SceneReferenceMatcher::class)->syncScene($model);
        }

        return response()->json([
            'value' => $storedValue,
            'hash' => hash('sha256', $storedValue),
            'revision_id' => optional($recorder->lastRevisionFor($model, $field))->id,
            'saved_at' => now()->toIso8601String(),
        ]);
    }
}
```

Key points tying back to `handoff.md`:

* **§9.13 — the server is the sole hash authority.** The response always returns a hash
  of what was actually persisted (post-sanitization/post-normalization), never an echo
  of what the client sent. The client adopts this hash for its next `base_hash`, and
  never writes the returned `value` back into the live editor DOM (would yank the
  caret).
* **§3.3 — 409 on mismatch**, not last-write-wins.
* **§2.4 — coarse triggers vs. debounce** are distinguished by two request-body flags
  the client sets (`run_matcher`, and implicitly whether this is a Ctrl-S/blur/submit
  vs. a debounce tick) — the debounce request omits `run_matcher`; blur/Ctrl-S/submit
  set it. `manual=true` is set only by the real form submit action, distinct from Ctrl-S
  (§2.4's "Ctrl-S is a flush + window close, not a permanent checkpoint").
* Validation rules come from `AutosavableFields::validationRule()`, never duplicated —
  §9.8's "enforced identically by the autosave endpoint and the existing Form Requests".

## Routes

```php
Route::middleware(['auth', 'throttle:120,1'])->group(function () {
    Route::whereIn('entity', AutosavableFields::slugs())->group(function () {
        Route::patch('/autosave/{entity}/{id}/{field}', [FieldAutosaveController::class, 'update'])
            ->name('autosave.update');
        Route::get('/revisions/{entity}/{id}/{field}', [RevisionController::class, 'index'])
            ->name('revisions.index');
        Route::get('/revisions/{entity}/{id}/{field}/compare', [RevisionController::class, 'compare'])
            ->name('revisions.compare');
    });
    Route::post('/revisions/{revision}/revert', [RevisionController::class, 'revert'])
        ->name('revisions.revert');
});
```

`->whereIn('entity', AutosavableFields::slugs())` makes an unknown slug 404 at the
router, matching how `routes/web.php` already gates `{type}` with
`CodexEntryType::routeKeys()` (line 177, confirmed above) — same pattern, same file. A
test (`RouteRegistryTest` or similar) asserts every registry slug round-trips to a real
model, per §9.3.

`throttle:120,1` is the rate limit from §9.8: a 2-second debounce across 3 fields is
~90 req/min for a fast typist.

## Conflicts, 419/401, 429 — client-side state machine

The client-side decision logic (which HTTP status maps to which UI state, retry timing,
draft triage) is a plain JS module per `handoff.md` §9.12 — `resources/js/autosave/
store.js`, no DOM, testable with vitest. An Alpine store (`Alpine.store('autosave', ...)`)
wraps it as the thin adapter, subscribed to by both the per-field indicator (in
`x-autosave-field`, see `ui.md`) and the global lower-right badge.

Status-code mapping (§9.6, §9.8):

| Status | State | Notes |
|---|---|---|
| 200 | `saved` → fades to `idle` | adopts returned hash |
| 401 / 419 | `session-expired` | never `error`; opens `/login` in a new tab, replays queue on `focus`/`visibilitychange` |
| 403 (after a 401/419 replay as a *different* user) | its own state, not folded into `error` | §9.6's flagged gap — see `open-questions.md` |
| 409 | `conflict` | Reload / Keep mine / Compare |
| 422 | `error` (inline field errors) | cap/validation failure |
| 429 | `retrying`, honoring `Retry-After` | never `error` |
| network failure | `retrying` with backoff | |

## `SceneReferenceMatcher` and word-count — coarse-trigger seam

`SceneReferenceMatcher::syncScene()` (read above — loads every codex entry/alias, builds
a regex, `sync()`s the pivot) only runs when the controller's `run_matcher` flag is set
(blur/Ctrl-S/submit), never on a bare debounce tick — §2.5.

A `SceneContentsChanged` domain event fires from `FieldAutosaveController` alongside
that same coarse-trigger condition, for `Scene.contents` specifically. `.specs/draft/
word-count`'s rollup listener subscribes to it; autosave itself has no knowledge of word
counts (§9.10). This is a **published seam**, not an implementation — the listener does
not ship as part of this spec.

## Revert — `RevisionController::revert`

```php
public function revert(Revision $revision, RevisionRecorder $recorder): RedirectResponse
{
    $entity = $revision->revisionable;
    $this->authorize('update', $entity->revisionProject());
    // same 409 base-hash check as the PATCH endpoint, via a hidden form field
    $entity->{$revision->field} = $revision->value;
    $entity->save();
    $recorder->record($entity, $revision->field, $entity->fresh()->getAttribute($revision->field),
        request()->user(), RevisionOrigin::Revert, "Reverted to {$revision->created_at->format('d F H:i')}");
    return back();
}
```

Non-destructive per §5.2: a new row, never a delete/truncate of anything after the
reverted point.

## Compare — diff service

`App\Services\RevisionDiffer` wraps `jfcherng/php-diff` (composer dependency to add —
not currently in `composer.json`, confirmed by this session's `grep`; see
`open-questions.md` for the Laravel 13/PHP 8.5 compatibility check `handoff.md` §6
already flags as unverified). Rich fields diff `RichText::toPlainText()` output
(`app/Support/RichText.php`, read above — already exists and does exactly this
reduction); Markdown/plain fields diff the raw stored text (§5.3). When the two
revisions' plain-text projections are equal but the raw values differ, the compare view
renders **"formatting changed only"** instead of an empty diff.

## Retention: `Prunable` + `RevisionPurger`

* `Revision` uses `MassPrunable` (data-model.md). `routes/console.php` gets
  `Schedule::command('model:prune', ['--model' => [Revision::class]])->daily()` —
  the existing Laravel scheduler file, no new custom command (§4.1).
* `App\Services\RevisionPurger` is the one place the purge rules live (§4.3), called by
  both:
  * `App\Console\Commands\PurgeRevisions` (`revisions:purge --project= --category=
    --before= --dry-run`)
  * The "Revision storage" panel's controller action (project settings)
* `RevisionSetting`'s controller (mirroring `ImportSettingController`, read above)
  computes the pre-confirm delete count using the **real `prunable()` query object**
  from `Revision`, not a hand-rolled estimate (§9.11) — `Revision::query()->prunable-
  ish-clause-with-the-new-retention-value->count()`.

## Import/export

* Zip export: `ExportRequest` gains an `include_revisions` boolean toggle, mirroring the
  existing `include_images` toggle read in `ExportController::store()` above. Layout:
  one file per field, `.../scene-N/revisions/contents.json`, an array of that field's
  whole history (§8) — not one file per revision.
* Import: `created_at` preserved, `user_id` remapped to the importing user, every
  imported revision forced to `origin: import` (exempt from pruning) — implemented in
  whatever service currently walks the import manifest (see `.specs/shipped/2026-07/
  import` for the existing importer to extend, not re-architect).
