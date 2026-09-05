# 04 — Point every entry link at the read page

The change that makes the feature real. Until this task, nothing reaches `codex.show`.

## Scope

| File | Change |
|---|---|
| `resources/views/codex/index.blade.php` | cover link and name link → `codex.show`; the `x-icon-edit-link` stays on `codex.edit` |
| `resources/views/codex/partials/as-of.blade.php` | entry name link → `codex.show` |
| `resources/views/scenes/edit.blade.php` (~185) | reference sidebar link → `codex.show` |
| `app/Enums/SearchDomain.php` | add `viewRoute()`: `codex.show` for the codex domains, `editRoute()` for the rest |
| `resources/views/components/search/result-row.blade.php` | name link and `x-icon-view-link` → a new `viewRoute` prop |
| `resources/views/search/domain.blade.php`, `components/search/result-table.blade.php` | pass `viewRoute` alongside `editRoute` |
| `app/Http/Controllers/CodexEntryController.php` | `update()` done target → `['codex.show', $codexEntry]` |

## Depends on

02, 03.

## Key decisions

- `SearchDomain::editRoute()` stays. Other domains have no read page, so one method cannot
  answer both questions honestly.
- `duplicate()` keeps redirecting to `codex.edit` on the copy — a fresh duplicate is named and
  edited, not read.
- "Save and stay" is unchanged.

## Consult

`expanded/architecture.md` → Link changes, Post-save redirect.

## Tests

- The codex index links an entry name to `codex.show` and keeps the edit icon on `codex.edit`.
- A codex search result links to `codex.show`; a scene search result still links to its edit
  route, proving the `viewRoute()` fall-through.
- The scene edit reference sidebar links to `codex.show`.
- A plain save redirects to `codex.show`; a save with `stay=1` redirects to `codex.edit` and
  flashes `saved`.
