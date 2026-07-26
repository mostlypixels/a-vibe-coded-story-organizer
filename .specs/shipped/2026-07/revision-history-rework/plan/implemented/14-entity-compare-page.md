# Task 14 — The entity compare page (no-JS baseline)

## Scope

**Routes**:

* `GET /revisions/{entity}/{id}/compare` → `revisions.compare`, `?from=&to=` (save ids),
  `?field=`;
* `GET /revisions/{entity}/{id}/{field}/compare` → `revisions.field-compare`, a redirect
  that translates its old `?from=&to=` **revision** ids into the save points those
  revisions belong to (a revision row knows its `save_id`), preserving the field as
  `?field=`.

**Controller** — `RevisionController@compare`: resolve + authorize `view`, resolve the
field filter, get `RevisionHistory::savePoints()` for the pickers, resolve the pair
(defaults: the two most recent points; unknown ids 404; a reversed pair is swapped —
direction is never the user's choice), then `RevisionComparison::between()`. No diffing in
the controller.

**View** — rewrite `resources/views/revisions/compare.blade.php`:

* two picker controls at the top, rendered in this task as **plain `<select>`s inside a
  `<form method="GET">` with a Compare submit** — the working no-JS baseline that task 15
  progressively enhances. Left = older, right = newer; options older than or equal to the
  left selection are `disabled` in the right one, server-side;
* a one-line summary of the pair ("12 saves apart · 23 July 21:04 → 24 July 10:43");
* **one `x-card` section per changed field**, in registry order, titled with the field's
  headline, body rendered through `<x-diff>`; per-field restore buttons
  (`x-revert-revision-button`, existing route) on the older side, hidden when it is
  current;
* a muted "N other fields unchanged (Notes, Rights)" line;
* `?field=` renders one section plus a *Show all fields* link;
* empty states: fewer than two save points → the existing "Nothing to compare yet" panel;
  two identical snapshots → "These two saves left every field identical."

## Depends on

Tasks 11, 12.

## Key decisions already made

* **Compare is entity-level**: two save points, every field that differs. Binding
  decision 1/5. A field neither save wrote can appear — that is correct.
* **The pair lives in the URL** and the page is a pure GET; revert stays POST.
* **No swap control, no backwards diff, no error state** — the invalid pairing is made
  unreachable (disabled options) rather than validated afterwards.
* Build the accessible baseline first, enhance second: if the Alpine layer fails to load,
  the page still works with a keyboard.

## Consult

* `expanded/ui.md` — *2. Compare page*, including the layout sketch.
* `expanded/architecture.md` — *Routes*, `@compare`.
* `resources/views/revisions/compare.blade.php` — what is being replaced.

## Tests

`tests/Feature/RevisionCompareTest.php` (new; the compare half of the old
`RevisionHistoryTest` moves here):

* owner can compare; **non-owner gets 403**;
* `?from=&to=` drives the page; unknown save ids 404; a malformed ULID 404s at the router;
* a reversed pair renders the same diff as the correct order;
* missing `from`/`to` defaults to the two most recent save points;
* **snapshot semantics**: a field neither point wrote but which changed between them
  renders as a changed section; unchanged fields appear only in the summary line;
* `?field=` renders exactly one section;
* a field that did not exist at the older point renders as a whole-value insert;
* the right `<select>` marks every option not newer than the left selection `disabled`;
* the legacy `/{field}/compare?from=<revisionId>&to=<revisionId>` URL redirects to the
  equivalent save-point comparison;
* the page renders with two save points that differ only in a rich field's formatting, and
  shows a formatting-changed badge (the end-to-end proof of phase B).
