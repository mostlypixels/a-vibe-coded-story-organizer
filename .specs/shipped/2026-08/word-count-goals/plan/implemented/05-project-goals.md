# 05 — Project goals

## Scope

- Migration adding `daily_word_goal` and `total_word_goal` to `projects` —
  `unsignedInteger`, nullable, no default.
- Both into `Project::$fillable`.
- `UpdateProjectRequest` rules: `['nullable', 'integer', 'min:0']` each. Empty input
  normalises to `null` (a `prepareForValidation()` nudge, as `UpdatePublicationSettingRequest`
  does for its checkboxes).
- Two `x-text-input type="number" min="0"` on `projects/edit`, under a sub-heading, with
  `x-input-label` / `x-input-error`. Placeholder carries "leave empty for no goal".

**Not** in this task: anything that reads a goal. The chart and the readouts come later.

## Depends on

Nothing.

## Key decisions

- **Nullable, meaning "no goal".** A default of 0 would draw a goal line at zero on every
  project ever created.
- **Two goals, not three.** A monthly goal is a window with a target, which is a challenge —
  it moved to `.specs/draft/word-count-challenges/`. Do not add it back.
- **No cross-validation.** A daily goal that doesn't multiply out to the total goal is a
  reasonable intent (nobody writes every day), not an error to reject.
- On the project itself, not a `WordCountSetting` side table — two integers edited on the
  project form, and a side table would need a `…OrDefault()` accessor for nothing.

## Consult

`expanded/data-model.md` → *Changed: `projects`* · `expanded/ui.md` → *Goals form*

## Tests

- Owner sets both; they persist. Non-owner `PATCH` → 403.
- Empty input clears to `null`; negatives and non-integers → `assertSessionHasErrors`.
