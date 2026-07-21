# Epub Configuration — Open Questions

These are the sharp decisions to settle in the `grilling` pass before `plan-tasks` decomposes.
Each states a recommended answer.

## Q1. Where do the four Markdown fields live — `projects` columns or `epub_settings`?
`dedication`, `acknowledgements`, `preface`, `postface` are book **content** the spec says to save
"at the project level".
**Recommendation: `projects` columns**, beside the existing `author`/`publisher`/`rights`/`isbn`
book fields — consistent, and reusable by any future export format (PDF, etc.), while
`epub_settings` holds only epub-specific *choices*. Cost: the import/export descriptor must carry
them (data-model.md). Alternative: put everything on `epub_settings` (one aggregate, nothing added
to the archive descriptor) at the price of "project content" living in an epub-named table.

## Q2. Lazy default vs. `booted()` auto-create for `EpubSetting`?
**Recommendation: lazy** — `epubSettingOrDefault()` returns an unsaved default; the row is created
only when the config form is first saved. Keeps the DB clean, makes the "defaults === v1"
regression test trivial, and avoids a data migration to backfill existing projects. Alternative:
create in `Project::booted()` (consistent with the main-plotline/bookend pattern) — but that
needs a backfill migration for existing projects and offers no real benefit here.

## Q3. Do covers include **chapter** cover pages in v1?
The spec bullet reads "Covers (and which: Project and chapter as cover pages)". Chapters have **no**
image column, upload UI, storage, or import handling today.
**Recommendation: v1 = the existing project cover only** (a single `include_project_cover` toggle);
**defer chapter cover pages to `epub-configuration-v2`** alongside the per-scene images already
parked there. Confirm this is acceptable, or scope the (substantial) chapter-image infrastructure
into v1.

## Q4. Config form ↔ export button ↔ project selector interaction.
The page needs: pick a project, edit *its* settings, save, then export. Options:
1. **Recommended:** a project `<select>` that reloads the page (`GET …/export/ebook?project={id}`)
   to load that project's saved settings; a **Save** button (PATCH settings) and a separate
   **Download EPUB** button (POST export) below. No JS beyond the appendix toggle.
2. Alpine-driven single form that swaps settings client-side (needs all projects' settings
   preloaded — heavier, more JS).
Also: should **Save** and **Download** be one button ("save & export") or two? Recommendation: two,
so re-exporting an unchanged config doesn't force a save.

## Q5. Front-matter ordering — where does *acknowledgements* sit?
Convention varies. **Recommendation:** title → **dedication** → **acknowledgements** → **preface**
→ (TOC) → body → **postface** → appendix. Confirm the exact order and whether any belong *after*
the TOC. This decision is hard-coded in `addFrontMatter()`/`addBackMatter()`, so pin it now.

## Q6. Divider types in v1 — is `image` in or out?
**Recommendation: ship `horizontal_rule` + `decorative` only**; `image` divider → V2 (it needs an
uploaded divider asset + storage, none of which exists). Either omit the enum case or include it
and reject it in `UpdateEpubSettingRequest`. Confirm which.

## Q7. Codex appendix — in v1, or its own follow-up?
It's the heaviest slice: a new render path for **rich HTML** (codex descriptions are sanitized
HTML, *not* Markdown like scenes), HTML→XHTML normalisation to survive `validatePackage()`, image
embedding, and type/ordering rules.
**Recommendation: keep it in v1 but as the final, independently-shippable task**, so the toggles +
front matter + formatting can land first. If timeline is tight, split the appendix into
`epub-configuration` phase 2. Confirm appetite.
Sub-question: does the appendix embed **all** of an entry's images or just the first? Recommend
**first image only** in v1 (matches "cover images"), all-images to V2.

## Q8. Does an export archive carry the EPUB configuration?
When a project is exported to `.zip` and re-imported, should its `EpubSetting` travel?
**Recommendation: no for v1** — the config is a local presentation preference; the archive stays a
lossless *content* copy. The four Markdown fields *do* travel (they're project content, Q1).
Document the boundary either way so the import round-trip test asserts the intended behaviour.

## Q9. Should the Markdown fields also appear on the Project edit page?
They're `projects` columns (Q1), so the project edit form *could* own them.
**Recommendation: edit them only on the Export-ebook page** (they're book-production content, not
core project identity) to avoid a confusing second home. Confirm — this decides which Form
Request/controller writes them.

## Q10. Naming: `EpubSetting` singular per project vs. a broader `PublicationSetting`.
If PDF/other formats are foreseeable, a format-neutral name/table might age better.
**Recommendation: `EpubSetting`** — name it for what it is today (YAGNI/KISS); rename if a second
format actually arrives. Confirm.
