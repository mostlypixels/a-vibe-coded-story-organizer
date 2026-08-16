# Revisions — deep dive

The short version lives in [`architecture.md` → Revisions](architecture.md#revisions-autosave--entity-history).
This page is the reference: the invariants, the pitfalls, and the reasoning the code
can't state on its own. Read the section you're about to change, not the whole file.

## Two altitudes

| | The unit | Where it lives |
|---|---|---|
| **Storage** | one immutable row **per field, per moment** | `revisions` table — append-only; only an explicit purge deletes |
| **Interface** | one **save point** per Save (or autosave burst), covering every field it touched | `revisions.save_id`, folded into `SavePoint`s by `RevisionHistory` |

Writers don't think *"I changed `Scene.notes` at 14:03"* — they think *"I saved"*. So every
screen is addressed by **entity + save point**; a single field is a `?field=` filter, not a
page of its own.

There is **no draft-vs-published split**. Autosave writes the live column, so exports,
search, share links and `SceneReferenceMatcher` always read what the writer sees.

## The registry — `App\Support\AutosavableFields::REGISTRY`

One array, keyed by URL slug (`project`, `act`, `chapter`, `plotline`, `event`, `scene`,
`codex`) → `[model class, [field => FieldKind]]`. Single source of truth for three concerns
that must not drift:

- **What autosaves**, and with which editor (`FieldKind::Plain` / `Rich` / `Markdown`).
- **Route resolution.** `routes/web.php` gates `{entity}` with `whereIn('entity',
  AutosavableFields::slugs())` — one generic `FieldAutosaveController` route, never one per
  model. `resolveField($slug, $field)` is the single home of the "unknown field 404s"
  contract; both `FieldAutosaveController` and `RevisionController` go through it.
- **Validation.** `validationRule($slug, $field)` is the only place a cap or content rule is
  expressed. The autosave endpoint and the Form Requests — Store *and* Update — both call it.

Coalescing windows (`config('revisions.windows')`) and per-field caps
(`config('revisions.caps')`) live in `config/revisions.php`, keyed `"entity.field"` — the same
slug this registry uses, `scene.contents` — with a `"default"` fallback. Nothing hard-codes
either elsewhere.

> [!WARNING]
> A key that names no registered field is not an error anyone sees: the lookup falls through to
> `default` and silently applies the wrong window or cap. `RevisionDataModelTest` walks the
> registry in both directions to catch it. The keys used to be the model's class basename
> (`Scene.contents`) while the registry used slugs, so the same fourteen fields had two names and
> a translation step between them — adding a field meant getting both right.

> [!WARNING]
> A `max:` literal in a Form Request for an autosaved field is a bug, not a style choice.
> The two paths drifted for twelve of the fourteen fields until `FormRequestCapAgreementTest`
> started walking the registry: autosave accepted 40,000 characters of `dedication`, then Save
> refused the text the server had already stored. The reverse also shipped — `Scene.contents`
> had no form cap at all, so a paste over 1,000,000 characters was saved once and rejected by
> every autosave after it.

## Writing rows — `App\Services\RevisionRecorder`

The only class that inserts or updates a `Revision`. Reached from:

- `FieldAutosaveController` — every `origin: automatic` save.
- The 7 entity controllers' `update()`, via the `RecordsManualRevisions` trait
  (`snapshotAutosaved()` before the save, `recordManualSave()` after) — the labeled
  `origin: manual` checkpoint. One trait, not copy-paste; the recorder supplies the
  "Saved &lt;date&gt;" label.
- The baseline-backfill migration, so a fresh install can't diverge from the live path.

**Coalescing.** Inside a field's window, a run of `automatic` saves overwrites the same open
row (plain `UPDATE`). Every other origin always inserts, so a Save or revert stays
individually visible even seconds after an autosave.

> [!WARNING]
> **A coalescing row keeps its original `save_id`** (and `created_at`) — same row, same save
> point. Consequence, accepted: if a Save touches three fields and one coalesces into an
> earlier open burst, that field lands in the **earlier** save point, so *Undo this save*
> leaves it alone. The alternative — rewriting a group's membership after the fact — makes
> save points mutable, which is worse. Also on `RevisionRecorder::record()`.

**Byte-identical no-op.** The recorder doesn't decide whether to write; callers compare
first.

- `FieldAutosaveController` skips the call when the incoming value equals the column (type
  something, undo it, leave no trace).
- `recordManualChanges()` compares each field's pre-edit snapshot to its post-save value, so
  Saving a form records only the fields actually touched.

**Baseline seeding (`ensureBaseline()`).** First time a field is touched, write an
`origin: baseline` row holding the *pre-edit* value, stamped with the entity's `updated_at`
(not `now()`) so compare-by-date reads "this value held from here onward". Skipped when the
field is empty.

**Origin** (`App\Enums\RevisionOrigin`): `automatic` / `manual` / `revert` / `baseline` —
how a row was created. Deliberately *not* the same taxonomy as purge categories. Labels are
in its docblock.

**Always set `project_id` explicitly** — never infer it from `revisionable_type`/`_id`.
`HasRevisions::revisionProject()` walks to the owning `Project` (itself, for `Project`),
mirroring `ProjectPolicy::update`. Deleting a Project cascades at the DB level without
firing Eloquent events, so a polymorphic lookup would silently break on orphaned rows.

**List queries never hydrate `value`.** History index, storage panel, purge previews select
explicit columns; `size_bytes` exists so `SUM()` never touches `value`. So does the whole-save
undo, which reads its group for the morph target, the origin and the `(created_at, id, field)`
ordering — the one `value` it needs belongs to a *predecessor* row, fetched by its own query in
`RevisionReverter`. Query-listener tests guard both. New queries against `revisions` follow the
same rule.

## Prune vs purge

> [!WARNING]
> Only `origin: automatic`, **unlabeled** rows are eligible for the unattended sweep — and
> never the newest row of any `(entity, field)` pair. `Revision::prunable()` expresses
> "keep the newest per field" as *"delete this row only if a strictly newer sibling exists"*,
> newest meaning `(created_at, id)` — the same ordering every other query in the feature
> walks — and with **no window function**, for portability across
> sqlite/mysql/mariadb/pgsql/sqlsrv (see `.specs/draft/multiple-database-engines`). This is
> the most safety-critical query in the feature. Any change needs a test proving a labeled
> row, a non-automatic row, and each field's newest row all survive regardless of age.

> [!CAUTION]
> It used to say `whereNotIn('id', SELECT MAX(id) … GROUP BY …)`, which is the same thing
> **only while insertion order matches timestamp order** within a triple. Baselines are
> back-dated to the entity's `updated_at`, so that does not always hold — and where it broke,
> `MAX(id)` protected the *older* row and left the newest one prunable, deleting the version
> the writer would have been shown. `RevisionRetentionAndPurgeTest::test_the_prune_keeps_the_newest_revision_even_when_it_was_inserted_first()`
> is the guard. Do not trade the correlated lookup back for a grouped one.

| | Prune | Purge |
|---|---|---|
| Path | unattended, scheduled `model:prune` | deliberate — `revisions:purge` + admin storage panel |
| Code | `Revision::prunable()` (instance method via `MassPrunable`; tests call `(new Revision())->prunable()`) | `App\Services\RevisionPurger` |
| Retention | `RevisionSetting::current()->retention_days` — admin-configurable, effective next sweep, no deploy | caller-specified |
| May delete labeled / non-automatic rows | **No** | **Yes** |

Purge exists as the release valve: without it an imported project's history or a two-year
`manual` trail is a one-way ratchet. Both entry points call the one service, so a dry-run
preview and the real deletion can't report different counts.

Its three **categories** are a cross-cutting slice, not a fourth origin: `automatic` /
`manual` map to origins, but `labeled` is `whereNotNull('label')` regardless of origin.

## Diffing — `App\Services\RevisionDiffer`

A router. Which strategy depends on who authored the markup:

| `FieldKind` | Strategy | Why |
|---|---|---|
| `Rich` (TipTap HTML) | **Visual** — `HtmlTokenizer` → `VisualHtmlDiffer` → `DiffHtmlRenderer`, in-house on `jfcherng/php-sequence-matcher` | The writer never types that HTML. She should see her paragraphs marked in place, not `</p><p>` churn. |
| `Markdown` (`Scene.contents`, front/back matter) and `Plain` (`Project.rights`) | **Source** — `SourceDiffer`, wrapping `jfcherng/php-diff` side-by-side, word detail | She types the Markdown. There the markup *is* the content. |

Both return a `RevisionDiffResult` (`html` + `changeCount`) whose `html` is safe to
`{!! !!}` — both producers escape the text. The visual pipeline:

- **`HtmlTokenizer`** — markup → blocks of text plus a formatting *signature*, so "same
  words, now bold" is detectable at all.
- **`VisualHtmlDiffer`** — matches blocks first, then words inside blocks that changed.
- **`DiffHtmlRenderer`** — rebuilds output from its own `EMITTED_TAGS` allow-list.

This retired the old **"Formatting changed only."** dead end (rich fields used to be
flattened to plain text, so bolding produced two identical strings). A formatting-only
change now renders the passage with an *x-badge* naming what changed.

> [!NOTE]
> **Why in-house rather than a package.** Every off-the-shelf PHP HTML-diff library was
> rejected on licence or maintenance: `caxy/php-htmldiff` (the maintained one, and the
> algorithmic reference) is **GPL-2.0**; so is `rashid2538/php-htmldiff`, additionally a PHP
> 5.3-era codebase with one release; `icap/html-diff` is an unmaintained DaisyDiff port with
> no declared licence. This app ships **as source**, so a GPL-2.0 dependency is a bigger
> decision than a diff view. `jfcherng/php-diff` is BSD-3 and installed, but is line/word
> oriented and not HTML-aware. The three classes build on its transitive dependency
> `jfcherng/php-sequence-matcher` (BSD-3, already in `vendor/`): no new dependency, no
> licence question. Full evaluation table: the feature's `expanded/diffing.md`.

> [!WARNING]
> **Never run diff output through `HtmlSanitizer`.** `RichTextFields::ALLOWED_TAGS`
> deliberately has no `ins`/`del` — so the editor's `<s>` ("no longer accurate") stays
> distinct from `<del>` ("removed between revisions") — and sanitizing would eat exactly the
> markers the diff adds. `DiffHtmlRenderer` is safe by *construction* instead. Equally,
> never route a Markdown or Plain field through the tokenizer: it would eat the `**`, `#`
> and `>` the writer changed.

### Diff styling

All of it lives in `<x-diff>` (`resources/views/components/diff.blade.php`) plus the
`.revision-diff` rules in `resources/css/app.css`. Both diff kinds render through it, so
they read as one feature — and none of it can bleed into `x-rich-text`, which renders the
author's own content and must never look like a diff. Plain CSS rather than Tailwind, for
the same reason as the `.tiptap` rules: it styles markup the app generates but no template
sees.

Every change is signalled three ways at once — background tint, `+`/`−` gutter glyph, and a
visually-hidden "inserted"/"removed" label. Colour alone isn't information, and `<ins>`/
`<del>` announcement is inconsistent across screen readers.

> [!WARNING]
> **Never use `text-decoration` as a change marker.** The writer can apply `<s>` and `<u>`
> herself (both in `ALLOWED_TAGS`), so a struck-out passage must keep meaning "she struck
> this out". The marker rules explicitly clear the browser default underline/strikethrough
> on `<ins>`/`<del>`.

> [!NOTE]
> The **source diff carries two channels, not three**: `jfcherng` writes that markup itself
> and offers no hook for a visually-hidden label, so it gets tint + glyph plus the semantic
> elements. Closing the gap means the source path producing its own markers instead of
> delegating — a bigger change than the styling layer should make alone.

## Summaries — `App\Services\RevisionSummarizer`

One line per history row. Each engine hands it a `ChangeExcerpt` (the first thing that
changed, as a run of words, plus the hunk count); it spends
`config('revisions.summary.max_length')` characters of *text* outward from that change, then
renders through `DiffHtmlRenderer`.

Three deliberate choices, easy to break:

- **Computed at write time**, stored on the row (`summary_html`, `change_count`) — a diff
  between two immutable revisions is a constant, so rendering a page of history diffs
  nothing.
- **Bounded by rendered length, not hunk count.** A find-and-replace on a character's name
  produces forty hunks; forty hunks in a list row is unreadable.
- **Budget spent outward from the change**, never from the top of the field down, so the
  thing the row exists to show can't be what gets cut. Cutting happens on words, before
  rendering — trimming a marked-up string could leave an `<ins>` open; trimming tokens
  can't.

`RevisionRecorder` is the only live-path caller. It resolves the **predecessor** (newest
revision for the same `(entity, field)` strictly older than the row being written) and
stores the summary on insert *and* on a coalescing update — a coalesced row's value is being
replaced, so its summary is stale, and a row is never its own predecessor. Baselines store
`null` / `0` and render as *Initial value*.

> [!WARNING]
> **A failed summary must never cost a save.** Both callers wrap the summarizer: if the diff
> layer throws, the row is written with a `null` summary and the failure is logged. A
> missing summary is cosmetic; a lost save is not.

> [!WARNING]
> **Stale summaries after a prune.** The row following a deleted one keeps a summary
> computed against a predecessor that no longer exists, so it under-reports. Accepted:
> recomputing during a mass prune turns a cheap `DELETE` into an O(n) diff job. The compare
> screen always diffs live, so only the list excerpt can be off.

## Save points

`revisions.save_id` ties one Save's rows together; `App\Services\RevisionHistory` folds them
into `SavePoint`s holding `SaveEntry`s in registry field order. Two queries — `GROUP BY
save_id` for the page, then that page's rows — folded in PHP. No window functions, no
`GROUP_CONCAT`: five database engines, and the way to be sure they all agree is to ask each
for very little.

Three details are easy to break:

- **Ordering is `(MAX(created_at), MAX(id))`.** An autosave burst and the Save closing it
  land in the same second; `created_at` alone orders them arbitrarily.
- **The page fetches one group beyond its limit.** Never rendered — it exists so the last
  row can name the save point before it, which its *compare with previous* link addresses.
- **`isCurrent` is resolved with no filters applied.** "Current" is a fact about the entity,
  not the list being viewed. Deriving it from a filtered page would crown whatever sat on
  top and tell the writer an old save is her current text.

> [!IMPORTANT]
> No history query ever selects `revisions.value`. `size_bytes`, `summary_html` and
> `change_count` exist so it never has to.

**A save point is a moment, not a set of values.** `RevisionSnapshot::asOf()` resolves, for
*every* registered field, the newest revision at or before that moment — so a save that
touched only `notes` still implies a state for `description` and `contents`.
`RevisionComparison::between()` diffs two snapshots and skips fields whose two sides resolve
to the same revision id, hydrating `value` only for the rest.

> [!NOTE]
> A field **neither** save touched can therefore appear as changed, when some save between
> them changed it. Correct, not a bug: the writer is comparing two states of the scene, not
> two lists of edits. The pair is never reordered — ordering `from` before `to` is the
> caller's job, because the caller is what accepted two ids from a query string.

## Revert and undo

**Additive, never destructive.** `RevisionController::revert` writes a new `origin: revert`
row holding the older value. No user action deletes history except an explicit purge.

The work lives in `App\Services\RevisionReverter`, so single-field revert and whole-save
undo run the same four steps and can't drift:

1. Check the base hash (`assertUnchanged()`, pre-flight, against the hydrated model).
2. Re-validate the old value against **today's** `AutosavableFields::validationRule()` —
   rules can have tightened since it was recorded.
3. Open the transaction, **re-check the base hash under a row lock**
   (`assertStillUnchanged()`), then assign and `save()`, so the model's mutators run.
4. Record the value the database actually ended up holding.

The controller is resolve → authorize → delegate → redirect.

> [!IMPORTANT]
> **The base hash is checked twice, and only the second one makes the revert safe.** A
> pre-flight check followed by a write is two steps with nothing holding the row still
> between them: two reverts arriving together both pass, and the second overwrites the
> first. So `restore()` re-reads the column inside its transaction with `lockForUpdate()`
> before writing. Do not "simplify" the duplicate away — the pre-flight one earns its place
> separately, by letting the whole-save undo refuse *every* field before opening a
> transaction, and by reporting a conflict as a conflict rather than as a validation error.
> `lockForUpdate()` compiles to nothing on SQLite, which serialises writers anyway.

> [!NOTE]
> **The two conflict surfaces answer differently, on purpose.** The base hash stops a revert
> from overwriting a value a second tab (or in-flight autosave) wrote after the page was
> drawn. On mismatch `RevisionReverter` throws `RevisionConflictException`; **browser** paths
> redirect back with an error alert — the writer did nothing wrong and needs a page they can
> act on, not a bare error screen. The **409** survives only on the JSON autosave endpoint,
> where a client reads it. Both revert outcomes are rendered once in `<x-revisions-layout>`,
> not per page, from `RevisionsLayout::ERROR_KEY` — a namespaced flash key, so an unrelated
> feature's `error` can never surface dressed as a revert conflict. The message **names the
> field** that moved (a compare page shows several) and does not tell the writer to reload:
> the redirect already re-rendered the page, so clicking again is all that is left.

**Undo this save** (`revisions.saves.revert` → `RevisionController::revertSave`) applies the
same machinery to a whole save point. `RevisionReverter::revertSave()` wraps it in one
`DB::transaction`, checks **every** field's base hash before writing **any**, and calls
`RevisionRecorder::startNewSave()` so the result is one new save point.

- **Only the fields that save touched** — never a whole-entity rollback to that moment,
  which would silently discard unrelated later edits.
- **All-or-nothing.** A half-applied undo is a state the writer never asked for and can't
  recognise; refusing outright is kinder.
- **The value restored is the one *before* the save** — the newest revision of that field
  strictly older than it, by `(created_at, id)`. When there is none, the save *created* that
  content and the undo empties it (every registered field is `nullable`).
- **Any save point can be undone, including the current one.** Undo runs backwards, so
  undoing the newest save is "undo what I just saved" — the most useful case, and what makes
  an undo undoable in turn. Exception: a **baseline** has nothing before it, so the button is
  hidden and the endpoint refuses it.
- `{save}` is constrained to the ULID alphabet in `routes/web.php`, so a malformed id 404s at
  the router. It is a lookup key, never a capability — authorization still walks to the
  owning `Project`.

## Getting there — three entry points, one destination

All three land on the *same* entity history page; they differ only in whether `?field=` is
pre-set.

| From | Control | Goes to |
|---|---|---|
| Any revisionable edit screen | **History** in the Actions card (`<x-entity-history-link>`) | that entity's whole history |
| Any autosaving field | small **History** icon beside its label (`<x-autosave-field>`) | same page, `?field=` set |
| **Tools** toolbar dropdown | **Revisions** | project-wide browser landing page |

`<x-entity-history-link>` takes the **model** and derives the slug via
`AutosavableFields::slugFor($model::class)`. Do not add a slug prop: every call site that
hand-writes `"act"`/`"codex"` is another chance to typo a 404 that only shows up when a
writer clicks it. `<x-edit-actions>` renders it when given a `historyModel`, so every
revisionable edit screen gets the link from its Actions card.

## Revisions browser (Tools ▸ Revisions)

`RevisionBrowserController`, route `projects.revisions.index`. Left sidebar is a tree —
entity type → entity → field — of everything in the project that has revisions.

- **Both levels are links to the same page**: entity name → unfiltered history; field leaf →
  `?field=` set, showing that field's count.
- Every URL is built in the service, never assembled in Blade.
- `App\Services\ProjectRevisionsBrowser` (following the `ProjectSearch` pattern) runs one
  grouped query over `revisions.project_id` for `(type, id, field)` triples with counts, then
  one small name query per present type — and **never selects `value`**.
- Only revised entities/fields appear.

Bounded three further ways so a heavily-revised project stays navigable: a **count badge**
per group heading; groups **default-collapse** (only the one holding the current entity
opens); a client-side Alpine **filter box** by entity name, auto-expanding matches. All
filter/collapse logic is plain Alpine *expressions*, never a component method, so children
read the ancestor `filter` state without the `this`-binding pitfall.

Landing page, entity history and compare all render inside `<x-revisions-layout>` (a
class-based component owning the tree build, so the three controllers stay thin), keeping
the sidebar in view while a reader drills into a diff and back. It's also where both revert
outcomes are flashed, once.

The sidebar's **active row follows the filter**: the field leaf gets `aria-current="page"`
when `?field=` names it, the entity name when there's no field filter. Assert on that
attribute in tests, never on swapped Tailwind classes (see `best-practices.md`).

The history page carries a **field filter** (a plain `<select>` of fields that actually have
history), a label search, and a "manual saves only" checkbox. All four controls plus the page
number live in the URL, so a filtered view is bookmarkable and Back means what it looks like.
The compare diff is a borderless **Old / New** side-by-side
(`resources/views/revisions/compare.blade.php` restyles `jfcherng/php-diff`'s `SideBySide`)
with only changed words tinted.

Each changed field is one card headed *Comparing changes to `<Entity>` field '`<Field>`'*,
holding **three panes of one shell** (`<x-revision-panel>`): *What changed* (the diff), then
*Older* and *Newer* — both whole values, side by side (`<x-revision-version>`), each with its
own **Revert to this** underneath.

- A diff says what moved; it does not say what you'd be looking at if you took one side.
  "Revert to this" is a choice between two *versions*, so the button lives under the version
  it restores — in the card header it pointed at neither column.
- The diff is a labelled pane like the other two, not loose text above them: three things are
  being shown, and one of them being unlabelled is what made the pair below look like the
  whole comparison.
- Each column renders its value the way the app renders that field elsewhere: rich HTML
  through `<x-rich-text>`, Markdown through `Str::markdown()` (like `Scene::renderedContents`),
  plain text escaped. What you compare is what you get back.
- The panes scroll (`max-h-96`) rather than growing: two full scene contents at full height
  would push the two buttons a novel's length apart.
- A side that is already the entity's current save point shows a **Current version** badge —
  reverting to the value the field already holds is a no-op that would still write a revision.

## Routes

| Route name | Verb + path | What it is |
|---|---|---|
| `projects.revisions.index` | `GET /projects/{project}/revisions` | browser landing page |
| `revisions.index` | `GET /revisions/{entity}/{id}` | **the** history page; `?field=` `?label=` `?manual=` `?page=` are filters |
| `revisions.compare` | `GET /revisions/{entity}/{id}/compare` | compare two save points (`?from=` / `?to=`) |
| `revisions.field` | `GET /revisions/{entity}/{id}/{field}` | **legacy redirect** → `revisions.index?field=` |
| `revisions.field-compare` | `GET /revisions/{entity}/{id}/{field}/compare` | **legacy redirect** → `revisions.compare`, translating old revision ids to save ids |
| `revisions.revert` | `POST /revisions/{revision}/revert` | revert one field to one older revision |
| `revisions.saves.revert` | `POST /revisions/saves/{save}/revert` | undo a whole save point |
| `autosave.update` | `PATCH /autosave/{entity}/{id}/{field}` | the one generic autosave endpoint (JSON) |

> [!NOTE]
> **Reading history authorizes `view`; changing it authorizes `update`.** History, compare
> and browser call `authorize('view', ...)`; both revert endpoints demand `update`. In this
> single-owner app they resolve to the same person today — the altitude is set so a future
> read-only collaborator could read history without rewriting it. All of them walk to the
> owning `Project`: the id in the URL (and the `{save}` ULID) is a lookup key, never a
> capability.
>
> The legacy redirects exist because the field-scoped *page* is gone — it's the same page
> with `?field=` set. An old bookmark still lands on a page about what it was about.

## Known gaps

- **Short fields and relations** (`name`, `chapter_id`, `status`, `event_id`,
  `mentioned_events`) still save only on manual form submit — they carry cross-field
  validation that doesn't survive field-level autosave. Closing that is
  `.specs/draft/data-loss-warnings`'s job, shipped independently by design.
- **`Ctrl-S` is claimed.** Autosave binds `Ctrl-S`/`Cmd-S` inside an autosaving field to
  flush the pending save and close the coalescing window. Whoever picks up
  `.specs/draft/keyboard-shortcuts` should treat it as spoken for.

> [!NOTE]
> **"Why is my history empty?"** The save-grouping migration
> (`2026_07_25_000000_add_save_grouping_to_revisions_table`) **deletes every pre-existing
> revision row** before adding `save_id` — rows written before save points existed have no
> group, and a null grouping key poisons every read path (see the migration's docblock).
> Not broken: `RevisionRecorder::ensureBaseline()` writes a fresh `baseline` row from the
> live value the next time each field is edited. Safe to do because the project is pre-V1
> and the only data is the Melusine demo seed.

## Where the rationale lives

- `.specs/shipped/2026-07/autosave-with-revisions/` — why no draft/published split, why no
  `laravel-auditing` / `laravel-versionable` / `revisionable` package, why no server-side
  collaborative locking (`handoff.md`, `resolution-log.md`).
- `.specs/shipped/2026-07/revision-history-rework/` — the move to entity + save point.
  `resolution-log.md` holds the diff-library evaluation; **`standing-issues.md` holds the
  costs accepted with eyes open** — still true of `master`, and not to be "fixed" without
  re-opening the decision behind them. Open both before changing any of this.
