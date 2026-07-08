# Task 02 — Sanitize on write (mutators + Form Requests + seeder)

## Scope

Make every rich-HTML field store sanitized HTML on every write path, and update validation to
match. After this task the DB can never hold unsafe HTML in a rich field, even though the forms
still use plain textareas (the editor arrives in task 04).

**Builds:**
- A **set-mutator per rich-HTML field** delegating to `App\Services\HtmlSanitizer::clean()`, on:
  `Project`, `Act`, `Chapter`, `Plotline`, `Event` (`description`); `Scene` (`description` +
  `notes`); `CodexEntry` (`description`). Use `Attribute::make(set: ...)` so cleaning runs on
  write only. Mutators run under `WithoutModelEvents`, so this is the robust choke point.
- **Form Request updates:** attach `App\Rules\SanitizeHtml` to each rich-HTML field.
  - `StoreSceneRequest` / `UpdateSceneRequest`: `notes` moves from `ValidMarkdown` →
    `SanitizeHtml`; **`contents` KEEPS `ValidMarkdown`** (Markdown-only invariant).
  - `StoreCodexEntryRequest` / `UpdateCodexEntryRequest`: `description` moves from
    `ValidMarkdown` → `SanitizeHtml`.
  - Project/Act/Chapter/Plotline/Event description requests: add `SanitizeHtml` (they are
    currently plain `nullable|string`).
- **Reseed:** update `MelusineSeeder` (and any factory defaults) so seeded descriptions/notes
  contain clean rich HTML (a heading + list) demonstrating the new format — no backfill
  migration. Confirm the seeder path stores clean HTML (mutators run; if the seeder builds HTML
  literals, keep them within the allow-list).

**Does NOT:** change any read/display view (task 03) or any form input (task 04). Does not
touch `Scene.contents`.

## Depends on

- **01** (`HtmlSanitizer`, `SanitizeHtml`, `RichTextFields` must exist).

## Key decisions already made (binding)

- Sanitization applied via **per-field set-mutators**, not a `booted()` hook or controller code.
- `Scene.notes` → rich HTML; `Scene.contents` → stays Markdown (`ValidMarkdown`, untouched).
- No backfill; reseed instead.
- Authorization is unchanged (walk up to `Project` via `ProjectPolicy`, mirrored in the Form
  Request) — this task must **not** weaken it.

## Docs to consult

`data-model.md` (taxonomy + seeding), `architecture.md` §2, `security.md` (what must be
stripped), `.claude/guidelines.md` (authorization + testing rules).

## Tests to add

`tests/Feature/HtmlSanitizationTest.php` plus fills of the missing per-model coverage:
- **Sanitization invariant:** POST a description with
  `<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a>` to a
  representative field (e.g. `Act.description`); reload from DB and assert none of `<script`,
  `onerror`, `javascript:`, `style=` remain; allowed markup (`<strong>`, `<ul><li>`) survives.
- **Eloquent-direct write** (not HTTP) of malicious HTML also comes out clean — proves the
  mutator (guards seeder/tinker).
- **Scene split:** `notes` is sanitized as HTML; `contents` is stored verbatim as Markdown
  (not HTML-mangled) and still passes `ValidMarkdown`.
- **Authorization (fill gaps):** for each newly-touched controller lacking tests (Act, Chapter,
  Plotline, Event), owner store/update succeeds; **non-owner → 403** (Form Request mirror).
- Seeder runs clean (`php artisan migrate:fresh --seed` conceptually; at minimum the seeded HTML
  is within the allow-list).

Run `composer test` and `vendor/bin/pint`.
