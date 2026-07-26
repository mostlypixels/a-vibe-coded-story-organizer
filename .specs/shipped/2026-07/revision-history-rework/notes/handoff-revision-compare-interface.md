---
title: Handoff — revision compare interface rework
context: follow-up to .specs/planned/2026-07/autosave-with-revisions (shipped code)
status: notes, not a spec
date: 2026-07-24
companion: revision-compare-decisions.md, revision-ui-lexicon.md
---

# Handoff — revision compare interface rework

Session output, 2026-07-24. Revisions + history view are shipped. Compare
interface is not good enough. This file = what's there, what's wrong, what to do.

Full decision list → `revision-compare-decisions.md`.
Terminology → `revision-ui-lexicon.md`.

---

## 1. What exists now

Everything below was verified against the codebase on 2026-07-24, not taken from
the spec. Statement of fact, not a task.

**Table** `revisions`

- polymorphic: `revisionable_type` + `revisionable_id` + `field`
- `value` longText, `size_bytes`, `label` nullable, `origin` enum, `user_id`
- `project_id` real FK, cascade delete
- `created_at` only — immutable, no `updated_at`
- index `(revisionable_type, revisionable_id, field, created_at)`
- coalescing window: `RevisionRecorder` UPDATEs the still-open row

**Services**

- `RevisionRecorder` — writes
- `RevisionDiffer` — wraps `jfcherng/php-diff` v7.0.1
- `RevisionPurger` + `PurgeRevisions` command + `RevisionSetting`
- `ProjectRevisionsBrowser`

**Routes**

- `GET /revisions/{entity}/{id}/{field}` — index
- `GET /revisions/{entity}/{id}/{field}/compare` — compare
- `POST /revisions/{revision}/revert`
- `GET /projects/{project}/revisions` — browser

**Views** `resources/views/revisions/{index,compare,browser}.blade.php` + partials.
Components: `revert-revision-button`, `revision-origin-badge`, `revisions-layout`.

---

## 2. What's wrong

1. **History is field-scoped only.** Routes bake `{field}` into the path. One save
   touching 3 fields = 3 unrelated rows in 3 separate histories. Revert = 3 clicks.
   Writer thinks "what did I do to this scene", not "what happened to contents".
2. **No way to group revisions from one save.** Nothing links them. Timestamps
   collide, unreliable.
3. **Compare page has no revision pickers.** You get what you clicked from.
   Can't pick either side.
4. **Rich fields lose formatting in the diff.** `RevisionDiffer` projects Rich
   through `RichText::toPlainText()` before diffing, then returns
   `formattingChangedOnly()` when only markup moved. Decision is now the opposite:
   show formatting changes, rendered.
5. **Row summary undefined / unbounded.** Find-and-replace on a character name =
   40 hunks in one row.
6. **Compare state not in URL.**

---

## 3. Work items, in order

**Do first — data capture is time-sensitive.**

### 3.1 `save_id`

- migration: add nullable `save_id` (ULID) + index
- `RevisionRecorder`: generate one per save request, inside the transaction,
  stamp every row it writes
- existing rows stay null, permanently ungroupable — that's accepted
- careful: coalescing window UPDATEs an open row. decide whether a coalesced
  write keeps the original `save_id` or takes the new one

### 3.2 Entity-level history

- new route: `GET /revisions/{entity}/{id}` — all fields, one row per `save_id`
- keep the field-scoped route as a filtered view of the same query
- row shows which fields changed
- pagination 20, fetch N+1 for the predecessor of row 21

### 3.3 Row summary

- first hunk + small context
- bound by rendered length, not hunk count
- "and X more changes" → link to compare with `from`/`to` prefilled
- cap word-level complexity, fall back to line-level past it
  (jfcherng has options for this; see wikidiff2's `maxWordLevelDiffComplexity`)
- summary computed at write time, stored — not on render

### 3.4 Compare screen pickers

- combobox above each column (NOT native select — filter panel inside)
- left = older, defaults to opened revision
- right = newer, defaults to previous
- right disables anything not newer than left. no swapping, no backwards diff
- per-side filters: origin (manual only) + date range. deliberately not synced
- `?from=&to=` in URL
- option label: number, date, label, manual-save hint, "current" marker
- W3C APG combobox pattern — arrows/Enter/Escape/Home/End,
  `aria-expanded` / `aria-selected` / `aria-activedescendant`

### 3.5 Visual diff for Rich fields — biggest item

- Markdown (`Scene.contents`): keep diffing source. correct as-is.
- Rich (HTMLPurifier fields): stop projecting to plain text. diff rendered output.
- needs an HTML-aware differ. `jfcherng/php-diff` is line/word oriented —
  probably not enough. evaluate before committing.
- `formattingChangedOnly()` becomes dead or fallback-only
- **tag collision**: editor emits strikethrough + `<u>`. So `<del>`/`<s>` can't
  double as diff markers. Constrain TipTap strikethrough to `<s>`, keep
  `<ins>`/`<del>` for the diff layer.
- **purifier path**: order is purified content in → diff → wrap → render.
  dedicated `x-diff` component, own allow-list. never purify after wrapping —
  author allow-list strips the markers.
- appearance: tint + `+`/`−` gutter mark. never colour alone. strikethrough and
  underline are unusable (author can write both). visually-hidden
  "inserted"/"removed" for screen readers.

### 3.6 Revert

- redirect to entity edit form, restored content visible
- flash message naming the restored revision
- **watch**: if the edit form autosaves on load/first keystroke, revert
  immediately spawns a second revision on top of the one it just made
- with `save_id` shipped, offer whole-save revert alongside per-field

---

## 4. Constraints that don't move

- append-only. revert = new revision. never rewrite history.
- source is always what's stored. revert depends on it.
- portable DDL: sqlite/mysql/mariadb/pgsql/sqlsrv
- projects single-owner, `ProjectPolicy` walk from project
- every action gets a feature test incl. non-owner 403
- Markdown/Rich split is architectural — don't route `Scene.contents` through
  the sanitizer

---

## 5. Open — needs a decision

- which PHP library does the HTML-aware visual diff
- coalescing + `save_id` interaction (see 3.1)
- storage growth / pruning of old autosaves
- concurrency: reverting from a compare screen whose head moved since load
- who sets `label`, and when
- does the field-scoped history survive at all, or fold into entity history

---

## 6. Suggested route

`/mp-spec-expander` on a fresh draft rather than patching the shipped spec.
The granularity change (3.1 + 3.2) is a data-model change, not UI polish.
