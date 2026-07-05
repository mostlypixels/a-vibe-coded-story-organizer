# WYSIWYG textareas — Data model

## Field taxonomy (the source of truth for this feature)

There is no single "field type" column today; each field is just a `text`/`string` DB column
plus validation in a Form Request. This feature classifies every long-text field into one of
three modes. **Codify this list in one place** (a constant/config, see `architecture.md`) so
views, validation, and rendering all agree.

| Model.field | Current mode | Target mode | DB column change? |
| --- | --- | --- | --- |
| `Project.description` | plain text | **rich HTML** | none (already `text`) |
| `Act.description` | plain text | **rich HTML** | none |
| `Chapter.description` | plain text | **rich HTML** | none |
| `Plotline.description` | plain text | **rich HTML** | none |
| `Event.description` | plain text | **rich HTML** | none |
| `Scene.description` | plain text | **rich HTML** | none |
| `Codex `entry` `description` | Markdown | **rich HTML** | none |
| `Scene.notes` | Markdown | **rich HTML** (see open Q) | none |
| **`Scene.contents`** | **Markdown** | **Markdown only (unchanged)** | none |

> [!IMPORTANT]
> No migration is required to *store* rich HTML — every target column is already `text`/
> nullable. The columns hold HTML strings instead of plain/Markdown strings going forward. The
> real work is validation, sanitization, and rendering, not schema.

Verify the exact column types before planning (`database/migrations/*`) — the table above
assumes the existing `description`/`notes` columns are `text` and nullable, matching the
`['nullable', 'string']` Form Request rules seen in `StoreSceneRequest`.

## Sanitization is a persistence-layer concern, not just validation

The stored value must **already be safe**. Two complementary layers (both in
`architecture.md`):

1. A reusable **`SanitizeHtml`** step (a `Rule` for shape validation + a sanitizing cast or a
   controller/Form-Request `prepareForValidation` pass) that runs HTMLPurifier over rich-HTML
   fields on the way in.
2. Rendering with `{!! !!}` **only** via a display component that receives content already
   sanitized on save — defense in depth, not the sole line of defense.

This mirrors the existing `App\Rules\ValidMarkdown` pattern (a small reusable rule that runs
the CommonMark converter). `ValidMarkdown` stays as-is for `Scene.contents` (and `notes` if it
stays Markdown).

## Existing data / backfill

Existing rows contain plain text (descriptions) or Markdown (codex description, scene notes).
After the switch these columns are interpreted as HTML:

- **Plain-text descriptions** render as HTML fine, but existing newlines collapse (HTML
  ignores them). Acceptable for short descriptions; a one-off backfill could wrap paragraphs
  / convert `\n` → `<br>`. Flag as an open question — likely **no backfill** given short
  content and dev-stage data.
- **Markdown fields becoming HTML** (codex description, maybe scene notes): existing Markdown
  source would show literally. A one-time `Str::markdown()` → sanitize backfill migration is
  the clean path if there is real seeded/authored content. For dev data seeded by
  `MelusineSeeder`, simply reseed.

No decision is forced here because the app is pre-production; see `open-questions.md`.

## Image-upload storage (only if image upload ships)

Reuse the Codex media stack rather than inventing a parallel one:

- Store uploaded editor images through **`App\Services\CodexMediaService`** (owns storage
  path, naming, validation) or a thin sibling if the association differs.
- Validate with **`App\Support\CodexMediaRules`** (`imageAccept()` / size hints) — the same
  rules the Codex cover/reference uploads use.
- **Association question:** editor images are embedded by URL in HTML, not tied to a
  `codex_entries` row. Options: (a) a new lightweight `project_media` table scoped to
  `project_id`; (b) reuse `codex_media` with a nullable owner; (c) store under a
  project-scoped disk path with no DB row. Recommend a small **`project_media`** table
  (`id`, `project_id`, `path`, `original_name`, `mime`, `size`, timestamps) mirroring
  `codex_media` columns, with `project_id` FK `cascadeOnDelete`.
- **File cleanup parity:** whatever table is chosen must honor the project/account-deletion
  cleanup invariant documented in `architecture.md` (a DB cascade bypasses model hooks, so
  disk files leak). If `project_media` is added, `Project::deleting` must purge its files too
  — extend the existing `purgeProject()` path rather than adding a second trigger.

> [!WARNING]
> Orphaned editor images: an author can upload an image then delete the paragraph referencing
> it, leaving the file unreferenced. This is the same "unreferenced upload" problem galleries
> have and is **out of scope** (galleries are deprioritized). Files are still cleaned up on
> project/account deletion via the cascade+purge path. Note it in docs; don't build GC now.

## Seeding impact

- `MelusineSeeder` runs under `WithoutModelEvents`. If a sanitizing **cast** is used (model
  layer, not a hook), casts still run under `WithoutModelEvents` — but if sanitization is done
  in the controller/Form Request, the seeder bypasses it. Prefer seeding **already-clean**
  HTML literals in the seeder, or call the sanitizer service directly (the same pattern the
  seeder already uses for `AttributeTimeline`).
- Update seeded descriptions to demonstrate rich HTML (a heading + list) so the Story overview
  and index previews show the new formatting.
