# 03 — Rendering

## Scope

- `App\Services\EntityLinkResolver`:

  ```php
  public function render(string $html, Project $project): string;
  ```

  A found target becomes its route URL. A missing target becomes `<span class="entity-link-missing" title="…">text</span>`.
- `x-rich-text`: a required `project` prop that calls the resolver. Update all callers.
- `Scene::renderedContents`: resolve after `AuthorMarkdown::render()`.
- `.entity-link-missing` in `resources/css/app.css`: struck text, theme tokens.
- Deferred: EPUB and static site → 06.

## Depends on

- 02.

## Key decisions already made

- One `queryFor($project)->whereKey($ids)` per type in the text.
- An id from another project renders as missing.
- `x-rich-text-excerpt` and `RichText::toPlainText()` stay unchanged. They keep the link text.
- The `title` text goes through `lang/`.

## Docs

- `expanded/architecture.md` → Rendering. `expanded/ui.md` → Read views.

## Tests

- Feature: found link → route URL for each type; deleted target → missing span; other project's id → missing span with no title or URL from that project.
- Query count: a text with many links of two types runs two lookups.
- A note read page and a scene read page render resolved links.
- Excerpt and plain text keep the link text.
