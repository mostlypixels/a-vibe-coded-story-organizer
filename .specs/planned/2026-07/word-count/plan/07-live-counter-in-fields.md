# Task 7 — The live in-field counter

## Scope

**`resources/js/word-count.js`** + co-located `word-count.test.js` (vitest, the
`resources/js/autosave/*.test.js` convention):

* `countWords(text)` — `trim`, split on `/\s+/u`, count. Nothing else.
* An Alpine component that recounts on input, **debounced ~150 ms**, and exposes the number
  for display.
* Reads `editor.getText()` for the TipTap-backed `x-wysiwyg` (both modes — the editor holds
  a ProseMirror document, so `getText()` is already rendered text) and `.value` for a plain
  textarea.
* **Snaps to `word_count` from the autosave response** whenever one arrives, replacing its
  own estimate.

**`resources/views/components/autosave-field.blade.php`**: render the counter bottom-right
of the field box, muted, on **all 14** fields this component serves.

* Autosave badge moves to / stays bottom-**left**, so neither shifts as the other's text
  changes.
* `aria-live="off"` — a number changing on every keystroke must never be announced.

## Depends on

Tasks 5 (the response key to snap to) and 6 (shared formatting, if the display reuses it).

## Key decisions already made

* **Indicative, not exact.** No fence stripping, no non-word rule in the browser — that
  would mean a second Markdown parser client-side to produce a number about to be replaced.
* **The counter is not a save indicator.** `x-autosave-status-badge` says whether work is
  safe; this says how much there is. Keep them distinct in weight and colour, not only in
  position, so the counter never reads as "saved".
* All 14 fields, `rights` included. Accepted rather than special-cased: "every prose field
  has one" is a rule a reader can predict.

## Consult

`../expanded/ui.md`, `../expanded/architecture.md` (the JS section),
`../expanded/open-questions.md` Q5, Q7.

## Tests

`resources/js/word-count.test.js` (`npm run test`):

* `"one two   three"` → 3; `""` → 0; `"   "` → 0.
* Debounces — N rapid inputs produce one recount.
* **Reconciliation**: an autosave response carrying `word_count` replaces the displayed
  number, *including when it disagrees* with the typed estimate. This is the test that makes
  "indicative" safe.

PHP side — extend `tests/Feature/AutosaveFieldComponentTest.php`:

* The counter markup renders for a rendered `x-autosave-field`.
* It carries `aria-live="off"`.

> [!NOTE]
> Placement against the autosave badge at narrow widths is **not** testable here. Drive
> `scenes/edit` and `projects/edit` (the tightest fields) with `/run-imagoldfish` and look —
> `projects/edit` renders six of the fourteen fields and is where crowding will show first.
