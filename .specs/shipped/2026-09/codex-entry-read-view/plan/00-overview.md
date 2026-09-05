# Codex entry read view — plan overview

A codex entry has no read page; `codex.edit` is its only member route. This plan adds
`codex.show` and points every entry link at it.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `extract-read-services` | Move the two private read helpers out of `CodexEntryController`; `edit` behaviour unchanged |
| 02 | `show-page-shell` | Route, action, view: header, media, description, lifespan |
| 03 | `attributes-and-scenes` | The attribute timelines and the capped referencing-scene list |
| 04 | `repoint-links` | Every entry link and the post-save redirect target |

## Binding decisions

Settled in the grill. Do not re-open them; see `expanded/open-questions.md`.

- **The codex has no "now".** An attribute renders as its whole timeline, every period,
  baseline first. There is no "current value" on this page. Only a scene gives one period
  primacy, and the as-of panel already does that.
- **No viewpoint picker** on the read page.
- **Referenced in scenes: 20 rows, then a "show all" toggle** that expands in place. No new
  route — the scene index is book-scoped and has no codex filter.
- **Media is read material**: cover in the header, reference images as thumbnails, reference
  files as a download list.
- **Plain Save lands on `codex.show`**; "Save and stay" still returns to the form. Codex only.
- **`show` is gated on `view`**, matching `index`. Every other codex action keeps `update`.
- **`codex.edit` stays reachable by URL**, unchanged.
- **`codex/partials/fields.blade.php` is not reused.** It is the form.

## Invariants every task preserves

- Authorization walks the owning `Project`, never the entry alone.
- Referencing scenes order: scenes with events first by `event_datetime` then event id, then
  unassigned scenes by act position, chapter position, scene position. Task 01 moves this rule;
  it does not change it.
- `edit` keeps showing **every** attribute, including the unset ones. Only `show` filters.
- The read page holds no `<input>`, `<textarea>` or autosave field.
