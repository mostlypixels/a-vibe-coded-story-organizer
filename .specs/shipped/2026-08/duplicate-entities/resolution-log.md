# Duplicate entities — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

* "Duplicate the children" in `spec.md` means rows the entity **owns** (a codex entry's aliases,
  media, attribute values) — never the manuscript tree. The first expansion read it as a subtree
  copy (act → chapters → scenes) and was wrong. The wording is meant to stay open to a future
  entity-owned table, e.g. a hypothetical `SceneExtraData`.
* Acts and chapters were cut from the scope for that reason: with no subtree copy, duplicating one
  produces an empty shell. Only Scene and CodexEntry ship the action.
* The full set of settled questions lives in `expanded/open-questions.md`, rewritten as a
  resolution table. Treat it as binding.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

* **`<x-dialog>` renders its `footer` slot outside the `<form>` in the default slot.** A submit
  button placed there belongs to no form and does nothing when clicked — no request, no console
  error, nothing a feature test can see. Both `x-duplicate-dialog` and the pre-existing
  `x-delete-with-move-dialog` shipped with dead primary buttons. Fixed by giving the form an
  `id="{{ $name }}-form"` and pointing the button at it with `form=`, the pattern
  `x-edit-actions` already uses. **Any new dialog with a footer button needs the same.**
* **Modal panels rendered underneath their own scrim.** The scrim is `position: fixed` and the
  panel was static, so the scrim painted on top whatever the DOM order. The panel's `transform`
  class used to give it a stacking context, but Tailwind 4 resolves `transform` to `none` unless
  a translate/scale/rotate utility is also set. Fixed with `relative` on the panel in
  `x-modal` — app-wide, not specific to this feature.
* **Both were invisible to the test suite.** Every feature test posts to the route directly, so
  no test exercises a rendered control. A smoke test that opens each dialog and clicks its
  primary button would have caught both.
