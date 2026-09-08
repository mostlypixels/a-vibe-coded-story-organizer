# UI

## Field selector

`resources/views/revisions/index.blade.php` already renders `$fieldOptions` as a select and
hides it when empty. It gains entries for free once the controller builds `$fieldOptions`
from `TrackedFields::forModel()` instead of `AutosavableFields::fieldsForModel()`.

Labels: "Name", "Aliases", "Tags" beside the existing headlines. Order the options with the
autosaved text fields first, then the tracked-only ones — Description is the one a writer
opens the page for.

## Save point rows

One row per save point, as today, but:

- **one origin badge**, from the ranked origin (`architecture.md` → *Folding four rows*).
- **the summary reads as a sentence.** "renamed Bertrand to Bertran", "added alias Tisi".
- a save point touching several fields lists them in one row, as it does now.

The "No summary recorded" text should now be genuinely rare. It stays as the fallback for a
row whose summarizer produced nothing — do not delete it.

## Comparison screen

`revisions/compare.blade.php` diffs two values through `RevisionDiffer`. A set is not prose
and a character diff of newline-joined members reads badly.

**Render a set comparison as two lists** — added members and removed members — not a text
diff. `FieldKind::Set` is the switch. A rename (short `Plain`) keeps the existing text diff;
"Bertrand" vs "Bertran" diffs fine.

## Empty state

`index.blade.php` already picks between "No saves match these filters." and "No history
yet." on `$field !== null || $label !== '' || $manualOnly`.

**Verify before changing anything here.** The condition reads correct as written; the spec's
complaint may be that `$field` arrives non-null when the selector defaults to a field rather
than "All fields". Reproduce first — if the condition is right, this goal is already met and
the task is a test that pins it, not a fix.

## Revert

The revert control is rendered per save point. Whether it appears for a `name` or a set is
an open question — see [open-questions.md](open-questions.md). Until that is settled, build
the read path and leave revert as it is: reverting a field it cannot restore must not be
offered.

## Files touched

| File | Change |
| --- | --- |
| `resources/views/revisions/index.blade.php` | field options, one origin badge |
| `resources/views/revisions/compare.blade.php` | set comparison as two lists |
| `resources/views/revisions/partials/` | wherever the badge and summary render |
| `documentation/features/revisions.md` | tracked vs autosaved is now a real distinction |

No new component, no Alpine.
