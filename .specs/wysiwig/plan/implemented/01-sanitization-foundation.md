# Task 01 — Sanitization foundation

## Scope

Build the server-side sanitization primitives, fully decoupled from models/controllers/views.
This is the security crux and is pure, unit-testable backend.

**Builds:**
- Add the **HTMLPurifier** dependency (`mews/purifier` Laravel wrapper, or `ezyang/htmlpurifier`
  directly — pick one, justify in the commit). Publish/point at a config if using the wrapper.
- `App\Support\RichTextFields` — the **single source of truth** for the rich-HTML field list
  (see the exact list in `00-overview.md`) and for the HTMLPurifier **allow-list** (tags,
  attributes, allowed URL protocols). Mirrors the `PlotlineColors` / `CodexMediaRules` pattern
  (constant/reference data in `app/Support`).
- `App\Services\HtmlSanitizer` — the app's next `app/Services` class (alongside
  `AttributeTimeline`, `CodexMediaService`). One method `clean(string $html): string` that runs
  HTMLPurifier with the allow-list from `RichTextFields`. Single place editor markup is cleaned.
- `App\Rules\SanitizeHtml` — a reusable validation rule (structured like the existing
  `App\Rules\ValidMarkdown`) for attaching to rich-HTML fields in Form Requests. It validates
  shape; the actual cleaning happens in `HtmlSanitizer` (task 02 wires the mutators).

**Does NOT:** wire mutators onto models, change any Form Request, touch any view, or add the
editor. Those are tasks 02 / 03 / 04. Does **not** add any image/upload handling (deferred).

## Depends on

Nothing.

## Key decisions already made (binding)

- Sanitizer = **HTMLPurifier**, not a hand-rolled regex / `strip_tags`.
- **Strict allow-list**, centralized in `RichTextFields`: only `p, h1–h4, strong, em, u, s, ul,
  ol, li, blockquote, code, pre, a[href], br, hr`. **No** `style`, no user `class`, no
  `<script>/<iframe>/<object>`, no event handlers. `a[href]` restricted to `http`/`https`/
  relative; block `javascript:` and `data:`. **No `<img>`** in v1 (image upload deferred).
- The allow-list must be a **superset of what the slash menu produces** (task 04 keeps them in
  sync); keep this list authoritative here.

## Docs to consult

`security.md` (allow-list + threat model), `architecture.md` §1–2 (`RichTextFields`,
`HtmlSanitizer`, `SanitizeHtml`), `data-model.md` (field taxonomy table).

## Tests to add

`tests/Unit/HtmlSanitizerTest.php` (or Feature — plain PHPUnit either way): a table of inputs →
expected clean output.
- `<script>alert(1)</script>` → stripped.
- `<img src=x onerror=alert(1)>` → removed (no `<img>` in allow-list, and `onerror` gone).
- `<a href="javascript:alert(1)">x</a>` → href neutralized/removed, text kept.
- `<p style="...">` → `style` stripped.
- Allowed markup survives: `<strong>`, `<em>`, `<ul><li>`, `<blockquote>`,
  `<a href="https://example.com">`.
- `RichTextFields` exposes the expected field list (a small assertion so later tasks rely on it).

Run `composer test` and `vendor/bin/pint`.
