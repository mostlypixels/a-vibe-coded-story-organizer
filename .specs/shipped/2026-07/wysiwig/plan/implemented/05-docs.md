# Task 05 — Documentation & changelog

## Scope

Capture the *why* of the feature so a junior developer can extend it safely — the guidelines
require documentation to stay in sync when architecture/workflows change.

**Builds:**
- `documentation/rich-text.md` — a new page covering:
  - The **field taxonomy** (which fields are rich HTML vs Markdown vs untouched) and its single
    source of truth (`App\Support\RichTextFields`).
  - The **security model**: server-side HTMLPurifier sanitization on write (per-field
    set-mutators), the allow-list, and the hard rule *"render rich HTML with `{!! !!}` only via
    `x-rich-text`, on already-sanitized data."* Use a `> [!WARNING]` for the never-trust-client
    pitfall.
  - The **`Scene.contents` Markdown-only** carve-out and why it differs.
  - The **editor** (`x-wysiwyg`, progressive enhancement, `resources/js/wysiwyg.js`, chosen
    library + fallback rationale) and that slash-menu output must stay ⊆ the allow-list.
  - A `> [!NOTE]` that **image upload is deferred to v2** (no upload endpoint / `project_media`
    table yet), and that orphaned-image GC is therefore not applicable.
- Cross-links: add a short pointer/section in `documentation/architecture.md` and update
  `documentation/ui-components.md` with the `x-wysiwyg` / `x-rich-text` entries.
- Update **`CLAUDE.md`** with a brief "Rich text / WYSIWYG" note in the architecture section
  (mirrors how Codex/attributes are summarized there).
- **`CHANGELOG.md`** `[Unreleased]`: `Added` (WYSIWYG editor, `x-wysiwyg`/`x-rich-text`,
  `HtmlSanitizer`), `Changed` (descriptions + codex description + scene notes now rich HTML;
  codex description no longer Markdown), noting `Scene.contents` unchanged.

**Does NOT:** change any code behavior.

## Depends on

- **01–04** (documents what they built; do last so it matches reality — especially the editor
  library actually chosen in task 04).

## Key decisions already made (binding)

All the binding decisions in `00-overview.md` — this task records them, it does not revisit
them.

## Docs to consult

All of `.specs/wysiwig/*.md`, plus the existing `documentation/` pages for tone/format
(GFM alert callouts, explain *why*).

## Verification

Inspection only (no test surface): the new/updated docs render as valid GFM, links resolve, and
the CHANGELOG entry is present. `vendor/bin/pint` still clean (no code touched, but run it).
