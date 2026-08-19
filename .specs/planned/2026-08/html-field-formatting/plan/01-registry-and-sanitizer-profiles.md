# 01 — The registry and the two sanitizer profiles

The closed value sets, and the lock that keeps them out of Markdown. Nothing user-visible
changes: no editor can produce these classes yet.

**Depends on:** nothing. First task.

## Scope

- `RichTextFields`: `ALIGNMENTS`, `TEXT_COLORS`, `ALIGNABLE_TAGS`, and a
  `decorativeClasses(): list<string>` that derives every permitted class name from them.
- `class` joins `ALLOWED_ATTRIBUTES` for `ALIGNABLE_TAGS` and for `span`. `class` is an
  HTML4 attribute, so it needs no `addAttribute()` call and **no `HTML.DefinitionRev` bump**
  — unlike the `data-*` names already registered there.
- New enum `App\Enums\RichTextProfile { Rich, Structural }`.
- `HtmlSanitizer` holds two lazily built purifiers, one per case. The `AppServiceProvider`
  singleton binding is unchanged.
  ```php
  public function clean(string $html, RichTextProfile $profile = RichTextProfile::Rich): string
  ```
  `Rich` sets `Attr.AllowedClasses` to `RichTextFields::decorativeClasses()`; `Structural`
  sets it to `[]`, which strips every class.
- Three Markdown seams pass `Structural`: `AuthorMarkdown::render()`,
  `ContentSanitizer::assertMarkdownAllowed()`, `EpubExporter::renderSceneContents()`.

**Not in scope:** anything that *produces* a class — the editor is task 04, the toolbar
task 05. No CSS (tasks 02–03).

## Key decisions

- The default parameter is what keeps every existing `clean()` caller working untouched.
- `Attr.AllowedClasses` is a global set, not per-tag, so an align class would technically be
  legal on a `span`. Accepted: the editor never writes it and it is inert. Do not build a
  per-tag class map to close a hole nothing can reach.
- Rejected: one profile plus a `ValidMarkdown` regex over class names. It restates the
  allow-list in a second grammar and drifts.

## Consult

`expanded/architecture.md` → *The registry*, *Two sanitizer profiles*.

## Tests

- `HtmlSanitizerTest`: loop `decorativeClasses()` — every entry survives `Rich`, none
  survives `Structural`. Looping is what stops a later colour escaping the test.
- An unknown class (`rt-color-chartreuse`) and an app utility class (`prose`) are stripped
  under both profiles, with the element and its text kept.
- `style="color: red"` and `style="text-align: center"` are stripped under both.
- The Markdown lock, once per seam: a raw `<span class="rt-color-red">` typed into
  `Scene.contents` does not survive `AuthorMarkdown::render()`; the same inside an imported
  scene body is rejected; the same does not reach the EPUB body.
- A codex `description` posted with the class **keeps** it — the positive half of the lock.
