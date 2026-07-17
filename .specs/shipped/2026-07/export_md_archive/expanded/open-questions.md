# Export to static files — Open questions

Grill these **before** `plan-tasks`. Each states a recommended answer; confirm or override.
Q1, Q5, Q7, and Q9 most change the task breakdown.

## Q1 — Which menu sections are in scope? (Story only, or Codex + Timeline too?)
The spec says *"an arborescence that matches the menu, except for the story itself"* but then
only details Story (acts/chapters/scenes) + storyline + images. The menu also has **Timeline**
(Plotlines, Events) and **Codex** (entries per type, Attributes). Images belong to codex
entries, so codex is at least implicitly involved.
- **Recommendation:** v1 = **Story tree + `storyline.html` + `images/` + `manifest.json`**,
  plus **Codex entry pages** (`codex/<type>/<NN-slug>.html`) because they are the natural
  on-disk home/anchor for the images and let the manifest link into a real page. **Defer**
  Timeline (Plotlines/Events) and Codex Attributes to a follow-up — they add scope without
  serving the "manuscript + images" core goal.
- **Alternative:** strictly Story + storyline + images/manifest (no codex pages; manifest is
  the only image connector). Simplest; smallest task set.
- Decision needed: does "matches the menu" mean *every* section now, or the manuscript core
  now with the rest deferred?

## Q2 — Image folder layout
Where do exported image files sit inside `images/`?
- **Recommendation:** `images/<NN-entry-slug>/<collection>/<original-name>` — grouped by
  entity then field, human-browsable, and the `NN-` prefix (codex entry position or id)
  avoids same-name entry collisions.
- **Alternative:** flat `images/<uuid-or-id>-<original-name>` relying solely on
  `manifest.json` for meaning. Simpler to generate, less browsable.

## Q3 — Manifest format & filename
- **Recommendation:** `images/manifest.json` — a JSON array of `{file, entity_type,
  codex_entry_id, codex_entry_type, codex_entry_name, collection, original_name, mime_type}`
  (see `architecture.md`). JSON is the project's natural machine format and trivial to assert
  in tests.
- **Alternative:** `images/manifest.csv` (spreadsheet-friendly) or embedding the mapping only
  in folder structure with no manifest. Recommend JSON; folder structure is the human view,
  manifest is the machine view — ship both.

## Q4 — Filename slug & collision rules
- **Recommendation:** `sprintf('%02d-%s', $position, Str::slug($name))`. `position` is unique
  per parent (model `booted()` invariant) so the numeric prefix guarantees uniqueness within a
  directory — **no collision handling needed**. When `Str::slug($name)` is empty (title is all
  punctuation/emoji), fall back to `NN-untitled`. Zero-pad to 2 digits (`%02d`); revisit to
  `%03d` only if >99 siblings is realistic (unlikely).
- Confirm: 2-digit padding acceptable? Fallback slug `untitled` acceptable?

## Q5 — Zip build: synchronous download vs queued job; memory
`ZipArchive` on a temp file streamed as a download is synchronous — the request blocks while
the whole project is zipped.
- **Recommendation:** **Synchronous** temp-file `ZipArchive` + `response()->download(...)
  ->deleteFileAfterSend(true)`. This is a personal, self-hosted, single-user app; projects are
  small (text + a handful of images). KISS — no queue, no job table, no polling UI.
- **Escalation trigger:** if a project could hold hundreds of MB of images, a synchronous
  request risks timeout/memory. If that is realistic, switch to a **queued job that writes the
  zip to storage and emails/links a download**. Recommend NOT doing this in v1; note it as a
  documented follow-up.
- Confirm the synchronous assumption holds for expected project sizes.

## Q6 — Standalone HTML styling
The exported `.html` files open from disk with no app CSS.
- **Recommendation:** ship **minimal inline CSS** in `exports/layout.blade.php` (readable
  body font, max-width, sane `prose`-like spacing) — no external stylesheet, no Tailwind build
  dependency in the zip. Enough to be readable, not a themed site.
- **Alternative:** bundle a small compiled `styles.css` into the zip and link it. More work;
  defer. Or ship raw unstyled HTML (ugly but faithful "as is"). Recommend minimal inline CSS.

## Q7 — Controller shape & the not-found / not-owned response
- **Recommendation:** add a focused **`ExportController@store`**; leave
  `DataTransferController@index` as the section shell (it also supplies `$projects` to the
  view). A missing/foreign `project_id` → **403** via `ExportRequest::authorize()` (returns
  false when the project is absent or not owned), matching the project's
  authorization-first convention (a foreign id is never a 422).
- Confirm: 403 (recommended) vs 404 for a non-existent project id.

## Q8 — Scene `.html` field set, order, and labels
Spec: *"the html of the scene (and of all the other fields)"*.
- **Recommendation:** render, in this order: **Name** (`<h1>`), a metadata line (**Status**
  label; **Event** title + datetime if `event` set), **Description** (rich HTML verbatim),
  **Contents** (Markdown→HTML), **Notes** (rich HTML verbatim). Include `position` implicitly
  via the filename.
- Open sub-question: should **`notes`** be exported at all? It is deliberately **private** —
  the public share view (`SharedSceneController`) never renders it. But this export is
  owner-only (behind auth + ownership), so including notes is defensible and matches "all
  fields." **Recommendation: include notes** (owner-only artifact). Flag loudly; the owner may
  prefer to exclude private notes — if so, add a second toggle later, not in v1.

## Q9 — Scene `.md` frontmatter keys
Spec: *"a second file for the MD content, with frontmatter."*
- **Recommendation (YAML frontmatter):** `name`, `position`, `status` (enum value), `act`
  (name), `chapter` (name), `event` (title, nullable), `project` (name). Body = **raw**
  `contents` Markdown (not rendered).
- Sub-question: how to represent the **HTML-only** fields (`description`, `notes`) in a
  Markdown file? Options: (a) omit them from `.md` (they live in `.html`); (b) include as raw
  HTML blocks under `## Description` / `## Notes` headings (Markdown permits inline HTML).
  **Recommendation: (a) omit** — keep `.md` clean prose + metadata; the `.html` twin carries
  the rich fields. Confirm.
- Escaping: YAML string values (names with `:` or quotes) must be quoted/escaped. Use a small
  helper or `Symfony\Component\Yaml\Yaml::dump` (already available via Laravel) rather than
  hand-concatenating frontmatter, to avoid malformed YAML.

## Q10 — No-projects empty state & copy
- **Recommendation:** if the user owns no projects, replace the form with "Create a project
  first, then come back to export it." linking to project creation, mirroring the
  `empty_states` convention. Confirm the copy.

## Q11 — `ext-zip` availability
Adding `"ext-zip": "*"` to `composer.json` and relying on `ZipArchive` assumes the extension
is present in dev, CI, and the test runner.
- **Recommendation:** add the explicit `require`, and confirm the CI/test PHP build has
  `ext-zip` enabled (bundled with most PHP distributions). If it is not guaranteed, that is a
  blocker to surface now.
- Confirm `ext-zip` is enabled where `composer test` runs.
