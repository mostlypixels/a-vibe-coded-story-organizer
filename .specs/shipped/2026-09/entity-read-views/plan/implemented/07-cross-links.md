# 07 — Cross-links

## Scope

- `app/Enums/SearchDomain.php` → `viewRoute()`: a full match with a `show` route per case.
  Delete the `default => $this->editRoute()` fallback and the docblock sentence that says
  only the codex domains have a read page.
- `resources/views/codex/show.blade.php:120`: referencing scene → `scenes.show`.
- `resources/views/components/story-chapter.blade.php:48`: add `x-icon-view-link` beside
  the existing edit icon.
- Repoint the placeholder links tasks 04 and 05 left behind: chapter and scene rows on
  `acts/show.blade.php`, scene rows on `chapters/show.blade.php`.
- Sweep for any remaining `*.edit` link that should now read: `grep -rn "\.edit'" resources/views`.

## Depends on

02, 03, 04, 05, 06 — every `show` route must exist first.

## Key decisions

- `app/Services/RecentlyEdited.php` is **not** touched. It keeps `*.edit` on purpose.
- `redirectAfterSave()` targets are **not** touched.
- `resources/views/codex/partials/as-of.blade.php` already points at `codex.show`.

## Consult

`expanded/architecture.md` → Link repointing.

## Tests

- `SearchDomain::viewRoute()` returns a distinct `show` route for all eight cases and never
  equals `editRoute()`.
- A search result row links to the read page for each of the five new domains.
- The story overview renders a view link per scene.
