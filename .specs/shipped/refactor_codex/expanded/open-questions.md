# Open questions & decisions to confirm

## Q1 — Finding 4: freeze bookend datetimes, or add a stable Start marker?

The source spec offers two resolutions:

- **A. Forbid `event_datetime` edits on `is_fixed` events** (recommended in
  `timeline-integrity.md`): one `prohibited` rule in `UpdateEventRequest` + hiding the input
  in the event edit form. No schema change. Narrows the documented bookend contract from
  "editable but not deletable" to "datetime frozen, title/description/plotlines editable" —
  CLAUDE.md and `documentation/` must be updated.
- **B. Resolve Start/End by a stable marker** (an `is_start` boolean column, or
  lowest-id-fixed-event): keeps datetimes editable but adds a migration (or a subtle id-based
  rule) and still leaves the *semantic* weirdness of a "Start" event dated after other events.

**Recommendation: A.** The bookends exist purely as timeline sentinels; no user story needs
their dates to move. Confirm the writer never legitimately re-dates "Start"/"End" (e.g. to
narrow the visible timeline range) before implementing.

## Q2 — Finding 5: is `''` the right "empty" semantics, or should empty mean "no value"?

The fix makes `value` `present` (empty string storable) to match `ensureBaseline('')`. An
alternative worldview: an empty baseline means *unvalued*, and `valueAt` should return null
until the first real value. That would instead remove the auto-`''`-baseline behavior — a much
deeper change that contradicts the gap-free invariant as specced (`.specs/codex/attribute-timeline.md`).

**Recommendation: keep `''` as a first-class value** ("recorded as blank"), per the original
spec. Just confirming intent because finding 1's fix (`ensureBaseline('')` inside `upsertAt`)
bakes it in further. If blanks should *display* differently (e.g. "—" instead of empty) that's
a small view tweak, separate from storage semantics.

## Q3 — Finding 5: shared session errors/old() across the timeline editor's many small forms

The card renders one form per period plus an add-period form. With the default error bag,
a failed submit shows the error (and `old('value')`) against **every** form in the card.
Options:

- **Accept it** (source spec: "acceptable here") — zero extra machinery. A writer sees the
  message and knows which form they just submitted.
- Key errors per attribute (named error bags via `protected $errorBag` set dynamically, or a
  hidden `attribute_id` echoed into the error key) — precise but adds FormRequest complexity
  for a card with a handful of rows.

**Recommendation: accept shared errors now**; revisit only if the codex grows attributes into
the dozens. Decide before implementing the `old()` fold-in, since it has the same blast radius.

## Q4 — Finding 9: responsive-nav active state on the first codex link only

Today `request()->routeIs('projects.codex.*') || routeIs('codex.*')` marks only the
**Characters** responsive link active, whatever codex page you're on. When the links become a
loop, preserving this via `$loop->first` keeps behavior identical but perpetuates a quirk;
matching the active type (`routeIs(...) && $type === $codexType->routeKey()`-style) would be
more correct but is a small behavior change.

**Recommendation: fix the quirk while in there** (highlight the actual current type; needs the
current `{type}` route param compared to the loop's case) and note it under `Changed` in the
CHANGELOG. Cheap, and the loop makes it natural.

## Q5 — Scope: which lower-priority notes ride along?

The source spec lists five "no action required now" notes. Proposed disposition:

| Note | Proposal |
|---|---|
| `CodexAsOfResolver` cost per page view | **Defer** (explicit non-goal; revisit at hundreds of entries). |
| `applies_to` narrowing strands values silently | **Defer the behavior; consider the one-line form hint** on the attribute edit form ("Un-ticking a type hides its existing values but does not delete them") — worth folding into this pass if the attribute form is touched anyway. Otherwise defer whole. |
| `RuntimeException` control flow in `removeAt` | **Fold in cheaply**: replace the controller's try/catch (`CodexAttributeValueController@destroy:50-55`) with `abort_if($isBaselineWithSiblings, 403)` semantics matching the `is_main`/`is_fixed` convention — the Blade already hides the Remove button, so the guard is unreachable through the UI. Requires moving/duplicating the guard check or letting the service expose `canRemoveAt()`. Small; decide yes/no. |
| Orphaned tags accumulate | **Defer**; optionally add `whereHas('entries')` to the index filter dropdown query while in `CodexEntryController@index` — one-line, zero risk. |
| Timeline forms lose input (`old()`) | **Included** in `ui-fixes.md` finding 5 fold-in (subject to Q3). |

## Q6 — Does `User` deletion need its own purge hook, or delete projects through Eloquent?

`media-lifecycle.md` recommends the `User` `deleting` hook call
`$user->projects->each->delete()` so the `Project` hook is the single purge trigger. This
makes account deletion slower (per-project Eloquent deletes, each firing purges) but keeps one
mechanism. The alternative (both hooks call `purgeProject` directly) is faster but two
callers. At this app's scale either works — confirm the DRY-favoring choice, and confirm the
`User` model currently defines a `projects()` relation (CLAUDE.md implies it; verify).
