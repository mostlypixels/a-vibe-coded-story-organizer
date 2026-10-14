# 06 — Exports

## Scope

- `EpubExporter`: `EntityLinks::strip()` on scene contents and on every rich field it renders.
- `StaticSiteExporter`: the same, for every rich field and scene text it renders.
- Public shared scene page (share link, no login): link text only, same as the exports.
- Update `documentation/export-import/epub.md` with one line on the rule.

## Depends on

- 02.

## Key decisions already made

- Plain text for every link type, in both exports and on the shared scene page. No links to static site pages.
- `Scene::renderedContents` already holds resolved app URLs, so `strip()` on it finds nothing. Strip `AuthorMarkdown::render($scene->contents)` instead (see `resolution-log.md`).

## Docs

- `expanded/architecture.md` → Rendering (last bullet).

## Tests

- EPUB and static site with links in scene text and a description: the text is present, and no `entity:` appears in the output.
- Shared scene page: link text present, no `<a>` for the entity link, no app URL of the target.
